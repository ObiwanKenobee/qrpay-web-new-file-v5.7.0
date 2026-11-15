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

    // Transitions an admin may drive manually.
    // Key = current status, value = allowed next statuses.
    private const ADMIN_TRANSITIONS = [
        'payment_evidence_submitted' => ['payment_verified', 'payment_rejected'],
        'payment_verified'           => ['settled'],
        'payment_rejected'           => ['payment_evidence_submitted'], // allow resubmission path
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

            $allowed = self::ADMIN_TRANSITIONS[$exchange->settlement_status] ?? [];

            if (! in_array($toStatus, $allowed, true) && $actorGuard !== 'system') {
                return 'invalid_settlement_transition';
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
