<?php

namespace App\Services\SanctumScan;

use App\Models\SanctumScanExchange;
use App\Models\SanctumScanExchangePaymentEvidence;
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

        if (! $this->actorIdentityIsValid($actorGuard, $actorId)) {
            return SettlementTransitionResult::failure('invalid_actor', $fromStatus, $toStatus);
        }

        if (! SettlementStateMachine::canTransition($fromStatus, $toStatus, $actorGuard)) {
            return SettlementTransitionResult::failure('invalid_settlement_transition', $fromStatus, $toStatus);
        }

        $decisionEvidence = null;

        if (in_array($toStatus, [
            SettlementStateMachine::VERIFIED,
            SettlementStateMachine::REJECTED,
            SettlementStateMachine::SETTLED,
        ], true)) {
            $decisionEvidence = $exchange->paymentEvidence;

            if (! $decisionEvidence) {
                return SettlementTransitionResult::failure('payment_evidence_missing', $fromStatus, $toStatus);
            }
        }

        if ($toStatus === SettlementStateMachine::VERIFIED) {
            if (
                $decisionEvidence->verification_status !== 'unverified'
                || $this->normalizeDecimal((string) $decisionEvidence->amount) !==
                    $this->normalizeDecimal((string) $exchange->amount)
                || strtoupper((string) $decisionEvidence->currency) !== strtoupper((string) $exchange->currency)
            ) {
                return SettlementTransitionResult::failure('payment_evidence_mismatch', $fromStatus, $toStatus);
            }
        }

        if ($toStatus === SettlementStateMachine::SETTLED
            && ($exchange->status !== 'received' || $exchange->received_at === null)) {
            return SettlementTransitionResult::failure('buyer_receipt_required', $fromStatus, $toStatus);
        }

        $eventData = [
            'from' => $fromStatus,
            'to' => $toStatus,
            'reason' => $reason,
        ];

        if ($decisionEvidence !== null) {
            $eventData['payment_evidence_id'] = (int) $decisionEvidence->getKey();
            $eventData['payment_evidence_version'] = (int) $decisionEvidence->version;
            $eventData['payment_evidence_sha256'] = $this->paymentEvidenceFingerprint($decisionEvidence);
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
            $this->verificationStatusFor($actorGuard, $toStatus),
            $eventData
        );

        return SettlementTransitionResult::success($exchange, $fromStatus, $toStatus);
    }

    /**
     * Fingerprints the complete immutable evidence version without copying its
     * payment reference or other submitted fields into the decision event.
     *
     * The ordered payload keys and JSON flags are part of the digest contract.
     */
    private function paymentEvidenceFingerprint(SanctumScanExchangePaymentEvidence $evidence): string
    {
        $payload = [
            'exchange_id' => (int) $evidence->exchange_id,
            'evidence_id' => (int) $evidence->getKey(),
            'version' => (int) $evidence->version,
            'supersedes_id' => $evidence->supersedes_id === null ? null : (int) $evidence->supersedes_id,
            'rail' => (string) $evidence->rail,
            'reference' => (string) $evidence->reference,
            'amount' => (string) $evidence->amount,
            'currency' => (string) $evidence->currency,
            'submitted_by_guard' => (string) $evidence->submitted_by_guard,
            'submitted_by_id' => (int) $evidence->submitted_by_id,
            'submitted_at' => $evidence->submitted_at?->utc()->format('Y-m-d\\TH:i:s.u\\Z'),
        ];

        $canonicalJson = json_encode(
            $payload,
            JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_INVALID_UTF8_SUBSTITUTE
                | JSON_PRESERVE_ZERO_FRACTION
        );

        return hash('sha256', $canonicalJson);
    }

    private function actorIdentityIsValid(string $actorGuard, int $actorId): bool
    {
        return match ($actorGuard) {
            'admin', 'api', 'merchant_api' => $actorId > 0,
            'system' => $actorId === 0,
            default => false,
        };
    }

    private function normalizeDecimal(string $value): ?string
    {
        if (! preg_match('/\\A\\d+(?:\\.\\d{1,8})?\\z/', $value)) {
            return null;
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;
        $fraction = rtrim($fraction, '0');

        return $fraction === '' ? $whole : $whole.'.'.$fraction;
    }

    private function verificationStatusFor(string $actorGuard, string $toStatus): string
    {
        if ($actorGuard === 'admin') {
            return $toStatus === SettlementStateMachine::REJECTED
                ? 'platform_rejected'
                : 'platform_verified';
        }

        return match ($actorGuard) {
            'api' => 'buyer_declared',
            'merchant_api' => 'merchant_declared',
            default => 'system_recorded',
        };
    }
}
