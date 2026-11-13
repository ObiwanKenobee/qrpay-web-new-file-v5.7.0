<?php

namespace App\Services\SanctumScan;

use App\Models\SanctumScanExchange;
use App\Models\SanctumScanExchangeDispute;
use App\Models\SanctumScanExchangeDisputeMessage;
use Illuminate\Support\Facades\DB;

class ExchangeDisputeManager
{
    public function __construct(
        private readonly ExchangeEventRecorder $events
    ) {
    }

    public function show(int $exchangeId, string $actorGuard, int $actorId): ?SanctumScanExchangeDispute
    {
        $exchange = $this->participantExchange($exchangeId, $actorGuard, $actorId);

        return SanctumScanExchangeDispute::query()
            ->with('messages')
            ->where('exchange_id', $exchange->getKey())
            ->first();
    }

    public function open(
        int $exchangeId,
        string $actorGuard,
        int $actorId,
        string $category,
        string $statement
    ): SanctumScanExchangeDispute|string {
        return DB::transaction(function () use ($exchangeId, $actorGuard, $actorId, $category, $statement) {
            $exchange = $this->participantExchange($exchangeId, $actorGuard, $actorId, true);

            if (! in_array($exchange->status, ['accepted', 'dispatched', 'received'], true)) {
                return 'exchange_not_disputable';
            }

            if (SanctumScanExchangeDispute::query()->where('exchange_id', $exchange->getKey())->exists()) {
                return 'dispute_exists';
            }

            $dispute = SanctumScanExchangeDispute::create([
                'exchange_id' => $exchange->getKey(),
                'category' => $category,
                'status' => 'open',
                'opened_by_guard' => $actorGuard,
                'opened_by_id' => $actorId,
                'opened_at' => now(),
            ]);

            $this->appendMessage($dispute, 'opening_statement', $statement, $actorGuard, $actorId);
            $this->recordEvent(
                $exchange,
                'dispute_opened',
                'A participant opened a dispute about this exchange.',
                $actorGuard,
                $actorId,
                ['dispute_id' => $dispute->getKey(), 'category' => $category]
            );

            return $dispute->fresh('messages');
        });
    }

    public function addMessage(
        int $exchangeId,
        string $actorGuard,
        int $actorId,
        string $body
    ): SanctumScanExchangeDispute|string {
        return DB::transaction(function () use ($exchangeId, $actorGuard, $actorId, $body) {
            $exchange = $this->participantExchange($exchangeId, $actorGuard, $actorId, true);
            $dispute = $this->lockDispute($exchange->getKey());

            if ($dispute->status !== 'open') {
                return 'invalid_dispute_state';
            }

            $message = $this->appendMessage($dispute, 'comment', $body, $actorGuard, $actorId);
            $this->recordEvent(
                $exchange,
                'dispute_message_added',
                'A participant added a statement to the dispute record.',
                $actorGuard,
                $actorId,
                ['dispute_id' => $dispute->getKey(), 'message_id' => $message->getKey()]
            );

            return $dispute->fresh('messages');
        });
    }

    public function proposeResolution(
        int $exchangeId,
        string $actorGuard,
        int $actorId,
        string $summary
    ): SanctumScanExchangeDispute|string {
        return DB::transaction(function () use ($exchangeId, $actorGuard, $actorId, $summary) {
            $exchange = $this->participantExchange($exchangeId, $actorGuard, $actorId, true);
            $dispute = $this->lockDispute($exchange->getKey());

            if ($dispute->status !== 'open') {
                return 'invalid_dispute_state';
            }

            $dispute->forceFill([
                'status' => 'resolution_proposed',
                'resolution_proposed_by_guard' => $actorGuard,
                'resolution_proposed_by_id' => $actorId,
                'resolution_summary' => $summary,
                'resolution_proposed_at' => now(),
            ])->save();

            $message = $this->appendMessage($dispute, 'resolution_proposal', $summary, $actorGuard, $actorId);
            $this->recordEvent(
                $exchange,
                'dispute_resolution_proposed',
                'A participant proposed a resolution for the dispute.',
                $actorGuard,
                $actorId,
                ['dispute_id' => $dispute->getKey(), 'message_id' => $message->getKey()]
            );

            return $dispute->fresh('messages');
        });
    }

    public function acceptResolution(
        int $exchangeId,
        string $actorGuard,
        int $actorId
    ): SanctumScanExchangeDispute|string {
        return DB::transaction(function () use ($exchangeId, $actorGuard, $actorId) {
            $exchange = $this->participantExchange($exchangeId, $actorGuard, $actorId, true);
            $dispute = $this->lockDispute($exchange->getKey());

            if ($dispute->status !== 'resolution_proposed') {
                return 'invalid_dispute_state';
            }

            if ($this->isResolutionProposer($dispute, $actorGuard, $actorId)) {
                return 'proposer_cannot_accept';
            }

            $dispute->forceFill([
                'status' => 'resolved',
                'resolved_by_guard' => $actorGuard,
                'resolved_by_id' => $actorId,
                'resolved_at' => now(),
            ])->save();

            $message = $this->appendMessage(
                $dispute,
                'resolution_accepted',
                'The participant accepted the proposed resolution.',
                $actorGuard,
                $actorId
            );
            $this->recordEvent(
                $exchange,
                'dispute_resolution_accepted',
                'The other participant accepted the proposed dispute resolution.',
                $actorGuard,
                $actorId,
                ['dispute_id' => $dispute->getKey(), 'message_id' => $message->getKey()]
            );

            return $dispute->fresh('messages');
        });
    }

    public function rejectResolution(
        int $exchangeId,
        string $actorGuard,
        int $actorId,
        string $reason
    ): SanctumScanExchangeDispute|string {
        return DB::transaction(function () use ($exchangeId, $actorGuard, $actorId, $reason) {
            $exchange = $this->participantExchange($exchangeId, $actorGuard, $actorId, true);
            $dispute = $this->lockDispute($exchange->getKey());

            if ($dispute->status !== 'resolution_proposed') {
                return 'invalid_dispute_state';
            }

            if ($this->isResolutionProposer($dispute, $actorGuard, $actorId)) {
                return 'proposer_cannot_reject_own_resolution';
            }

            $dispute->forceFill([
                'status' => 'open',
                'resolution_proposed_by_guard' => null,
                'resolution_proposed_by_id' => null,
                'resolution_summary' => null,
                'resolution_proposed_at' => null,
            ])->save();

            $message = $this->appendMessage($dispute, 'resolution_rejected', $reason, $actorGuard, $actorId);
            $this->recordEvent(
                $exchange,
                'dispute_resolution_rejected',
                'The other participant rejected the proposed resolution; the dispute remains open.',
                $actorGuard,
                $actorId,
                ['dispute_id' => $dispute->getKey(), 'message_id' => $message->getKey()]
            );

            return $dispute->fresh('messages');
        });
    }

    public function withdraw(
        int $exchangeId,
        string $actorGuard,
        int $actorId
    ): SanctumScanExchangeDispute|string {
        return DB::transaction(function () use ($exchangeId, $actorGuard, $actorId) {
            $exchange = $this->participantExchange($exchangeId, $actorGuard, $actorId, true);
            $dispute = $this->lockDispute($exchange->getKey());

            if (
                $dispute->status !== 'open'
                || $dispute->opened_by_guard !== $actorGuard
                || (int) $dispute->opened_by_id !== $actorId
            ) {
                return 'withdrawal_not_allowed';
            }

            $counterpartyHasResponded = $dispute->messages()
                ->where(function ($query) use ($dispute) {
                    $query->where('recorded_by_guard', '!=', $dispute->opened_by_guard)
                        ->orWhere('recorded_by_id', '!=', $dispute->opened_by_id);
                })
                ->exists();

            if ($counterpartyHasResponded) {
                return 'withdrawal_not_allowed';
            }

            $dispute->forceFill([
                'status' => 'withdrawn',
                'withdrawn_at' => now(),
            ])->save();

            $message = $this->appendMessage(
                $dispute,
                'withdrawal',
                'The initiating participant withdrew the dispute before the other participant responded.',
                $actorGuard,
                $actorId
            );
            $this->recordEvent(
                $exchange,
                'dispute_withdrawn',
                'The initiating participant withdrew the dispute before a counterparty response.',
                $actorGuard,
                $actorId,
                ['dispute_id' => $dispute->getKey(), 'message_id' => $message->getKey()]
            );

            return $dispute->fresh('messages');
        });
    }

    private function participantExchange(
        int $exchangeId,
        string $actorGuard,
        int $actorId,
        bool $lock = false
    ): SanctumScanExchange {
        $query = SanctumScanExchange::query()->whereKey($exchangeId);

        if ($actorGuard === 'merchant_api') {
            $query->where('merchant_id', $actorId);
        } elseif ($actorGuard === 'api') {
            $query->where('buyer_user_id', $actorId);
        } else {
            abort(403);
        }

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->firstOrFail();
    }

    private function lockDispute(int $exchangeId): SanctumScanExchangeDispute
    {
        return SanctumScanExchangeDispute::query()
            ->where('exchange_id', $exchangeId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function appendMessage(
        SanctumScanExchangeDispute $dispute,
        string $type,
        string $body,
        string $actorGuard,
        int $actorId
    ): SanctumScanExchangeDisputeMessage {
        return SanctumScanExchangeDisputeMessage::create([
            'dispute_id' => $dispute->getKey(),
            'message_type' => $type,
            'body' => trim($body),
            'recorded_by_guard' => $actorGuard,
            'recorded_by_id' => $actorId,
            'created_at' => now(),
        ]);
    }

    private function recordEvent(
        SanctumScanExchange $exchange,
        string $type,
        string $description,
        string $actorGuard,
        int $actorId,
        array $data
    ): void {
        $this->events->record(
            $exchange,
            $type,
            $description,
            $actorGuard,
            $actorId,
            $actorGuard === 'merchant_api' ? 'merchant_declared' : 'buyer_declared',
            $data
        );
    }

    private function isResolutionProposer(
        SanctumScanExchangeDispute $dispute,
        string $actorGuard,
        int $actorId
    ): bool {
        return $dispute->resolution_proposed_by_guard === $actorGuard
            && (int) $dispute->resolution_proposed_by_id === $actorId;
    }
}
