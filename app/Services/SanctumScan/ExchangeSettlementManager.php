<?php

namespace App\Services\SanctumScan;

use App\Models\SanctumScanExchange;
use Illuminate\Support\Facades\DB;

class ExchangeSettlementManager
{
    public function __construct(private readonly ExchangeEventRecorder $events) {}

    /**
     * Administrator-driven transition. Returns a typed result so callers can
     * distinguish an illegal transition from an authorisation failure.
     */
    public function transition(
        SanctumScanExchange $exchange,
        string $toStatus,
        string $actorGuard,
        int $actorId,
        string $reason,
        ?string $expectedFrom = null
    ): SettlementTransitionResult {
        if (! SettlementStateMachine::isStatus($toStatus)) {
            return SettlementTransitionResult::failure('unknown_settlement_status');
        }

        return DB::transaction(function () use (
            $exchange,
            $toStatus,
            $actorGuard,
            $actorId,
            $reason,
            $expectedFrom
        ) {
            $locked = SanctumScanExchange::query()
                ->whereKey($exchange->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($expectedFrom !== null && $locked->settlement_status !== $expectedFrom) {
                return SettlementTransitionResult::failure(
                    'stale_expected_status',
                    $locked->settlement_status,
                    $toStatus
                );
            }

            return $this->applyTransition($locked, $toStatus, $actorGuard, $actorId, $reason);
        });
    }

    /**
     * Called inside the buyer's existing locked transaction after payment
     * evidence has been persisted. Performs no additional locking.
     */
    public function recordEvidenceSubmitted(
        SanctumScanExchange $lockedExchange,
        int $buyerId
    ): SettlementTransitionResult {
        return $this->applyTransition(
            $lockedExchange,
            SettlementStateMachine::EVIDENCE_SUBMITTED,
            'api',
            $buyerId,
            'Payment evidence was submitted by the buyer.'
        );
    }

    /**
     * Keeps settlement state consistent when an exchange is closed before any
     * payment verification. A verified or settled payment is never cancelled
     * by this method.
     */
    public function cancelForClosedExchange(
        SanctumScanExchange $lockedExchange,
        string $actorGuard,
        int $actorId,
        string $exchangeStatus
    ): SettlementTransitionResult {
        $current = $lockedExchange->settlement_status;

        if ($current === SettlementStateMachine::CANCELLED) {
            return SettlementTransitionResult::success($lockedExchange, $current, $current);
        }

        if (in_array($current, [SettlementStateMachine::VERIFIED, SettlementStateMachine::SETTLED], true)) {
            return SettlementTransitionResult::failure('settlement_already_verified', $current);
        }

        return $this->applyTransition(
            $lockedExchange,
            SettlementStateMachine::CANCELLED,
            $actorGuard,
            $actorId,
            "Exchange closed with status [{$exchangeStatus}]."
        );
    }

    /**
     * Assumes the caller holds the row lock and transaction.
     */
    private function applyTransition(
        SanctumScanExchange $exchange,
        string $toStatus,
        string $actorGuard,
        int $actorId,
        string $reason
    ): SettlementTransitionResult {
        $fromStatus = $exchange->settlement_status;

        if (! SettlementStateMachine::isStatus($fromStatus)) {
            return SettlementTransitionResult::failure('unknown_settlement_status', $fromStatus, $toStatus);
        }

        if ($fromStatus === $toStatus) {
            return SettlementTransitionResult::failure('already_in_state', $fromStatus, $toStatus);
        }

        if (! SettlementStateMachine::canTransition($fromStatus, $toStatus, $actorGuard)) {
            return SettlementTransitionResult::failure('invalid_settlement_transition', $fromStatus, $toStatus);
        }

        $exchange->forceFill([
            'settlement_status' => $toStatus,
            'settlement_status_updated_at' => now(),
        ])->save();

        $this->events->record(
            $exchange,
            'settlement_status_changed',
            "Settlement status changed from {$fromStatus} to {$toStatus}. {$reason}",
            $actorGuard,
            $actorId,
            $this->verificationStatusFor($actorGuard),
            [
                'from' => $fromStatus,
                'to' => $toStatus,
                'reason' => $reason,
            ]
        );

        return SettlementTransitionResult::success($exchange, $fromStatus, $toStatus);
    }

    private function verificationStatusFor(string $actorGuard): string
    {
        return match ($actorGuard) {
            'admin' => 'platform_verified',
            'api' => 'buyer_declared',
            'merchant_api' => 'merchant_declared',
            default => 'system_recorded',
        };
    }
}
