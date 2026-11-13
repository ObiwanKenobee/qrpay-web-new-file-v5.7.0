<?php

namespace App\Services\SanctumScan;

use App\Models\SanctumScanExchange;
use App\Models\SanctumScanExchangeEvent;

class ExchangeEventRecorder
{
    /**
     * Append an immutable exchange event.
     *
     * There are intentionally no update/delete methods for event records.
     */
    public function record(
        SanctumScanExchange $exchange,
        string $eventType,
        string $description,
        string $actorGuard,
        int $actorId,
        string $verificationStatus,
        array $eventData = [],
        $occurredAt = null
    ): SanctumScanExchangeEvent {
        return SanctumScanExchangeEvent::create([
            'exchange_id' => $exchange->getKey(),
            'event_type' => $eventType,
            'description' => $description,
            'event_data' => $eventData ?: null,
            'verification_status' => $verificationStatus,
            'recorded_by_guard' => $actorGuard,
            'recorded_by_id' => $actorId,
            'occurred_at' => $occurredAt,
            'created_at' => now(),
        ]);
    }
}
