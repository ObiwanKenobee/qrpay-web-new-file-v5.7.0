<?php

namespace App\Services\SanctumScan;

use App\Models\SanctumScanExchange;
use Illuminate\Support\Facades\DB;

class ExchangeSettlementManager
{
    // Valid settlement statuses in lifecycle order.
    public const STATUSES = [
        'awaiting_payment',
        'payment_evidence_submitted',
        'payment_verified',
        'payment_rejected',
        'settled',
        'cancelled',
    ];

    // System transitions are deliberately narrow: recording submitted evidence
    // is not payment verification and never settles an exchange.
    private const SYSTEM_TRANSITIONS = [
        'awaiting_payment' => ['payment_evidence_submitted'],
    ];

    // Only an authenticated platform administrator may record review decisions.
    private const ADMIN_TRANSITIONS = [
        'payment_evidence_submitted' => ['payment_verified', 'payment_rejected'],
        'payment_verified'           => ['settled'],
    ];

    public function __construct(private readonly ExchangeEventRecorder $events) {}

    /**
     * Called automatically when a buyer submits payment evidence (M005).
     * Transitions awaiting_payment → payment_evidence_submitted.
     */
    public function onPaymentEvidenceSubmitted(SanctumScanExchange $exchange): void
    {
        if ($exchange->settlement_status !== 'awaiting_payment') {
            return;
        }

        $this->transition($exchange, 'payment_evidence_submitted', 'system', 0, 'system_recorded');
    }

    /**
     * Admin-driven transition. Returns the updated exchange or an error string.
     */
    public function transition(
        SanctumScanExchange $exchange,
        string $toStatus,
        string $actorGuard,
        int $actorId,
        string $verificationStatus = 'platform_verified'
    ): SanctumScanExchange|string {
        return DB::transaction(function () use ($exchange, $toStatus, $actorGuard, $actorId, $verificationStatus) {
            $exchange = SanctumScanExchange::query()
                ->whereKey($exchange->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($actorGuard, ['admin', 'system'], true)) {
                return 'invalid_settlement_actor';
            }

            if (($actorGuard === 'system' && $actorId !== 0)
                || ($actorGuard === 'admin' && $actorId < 1)) {
                return 'invalid_settlement_actor';
            }

            $isCancellation = $toStatus === 'cancelled'
                && $exchange->status === 'cancelled';

            $transitionMap = $actorGuard === 'system'
                ? self::SYSTEM_TRANSITIONS
                : self::ADMIN_TRANSITIONS;
            $allowed = $transitionMap[$exchange->settlement_status] ?? [];

            if (! $isCancellation && ! in_array($toStatus, $allowed, true)) {
                return 'invalid_settlement_transition';
            }

            if ($toStatus === 'payment_rejected' && $verificationStatus !== 'platform_rejected') {
                return 'payment_rejection_decision_required';
            }

            if ($toStatus === 'payment_verified') {
                if ($verificationStatus !== 'platform_verified') {
                    return 'payment_verification_decision_required';
                }

                $paymentEvidence = $exchange->paymentEvidence;
                if (! $paymentEvidence
                    || $paymentEvidence->verification_status !== 'unverified'
                    || (string) $paymentEvidence->amount !== (string) $exchange->amount
                    || strtoupper($paymentEvidence->currency) !== strtoupper($exchange->currency)) {
                    return 'payment_evidence_mismatch';
                }
            }

            if ($toStatus === 'settled'
                && ($exchange->status !== 'received' || $exchange->received_at === null)) {
                return 'buyer_receipt_required';
            }

            $from = $exchange->settlement_status;

            $exchange->forceFill([
                'settlement_status'            => $toStatus,
                'settlement_status_updated_at' => now(),
            ])->save();

            $this->events->record(
                $exchange,
                'settlement_status_changed',
                "Settlement status changed from {$from} to {$toStatus}.",
                $actorGuard,
                $actorId,
                $verificationStatus,
                ['from' => $from, 'to' => $toStatus]
            );

            return $exchange->fresh();
        });
    }

    /**
     * Transition to cancelled — available when exchange is cancelled.
     */
    public function cancel(SanctumScanExchange $exchange, string $actorGuard, int $actorId): void
    {
        if ($exchange->settlement_status === 'cancelled') {
            return;
        }

        $this->transition($exchange, 'cancelled', $actorGuard, $actorId, 'system_recorded');
    }
}
