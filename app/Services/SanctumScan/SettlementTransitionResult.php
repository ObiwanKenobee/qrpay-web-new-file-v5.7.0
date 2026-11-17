<?php

namespace App\Services\SanctumScan;

use App\Models\SanctumScanExchange;

final class SettlementTransitionResult
{
    private function __construct(
        public readonly bool $ok,
        public readonly ?SanctumScanExchange $exchange,
        public readonly ?string $error,
        public readonly ?string $from,
        public readonly ?string $to
    ) {}

    public static function success(SanctumScanExchange $exchange, string $from, string $to): self
    {
        return new self(true, $exchange, null, $from, $to);
    }

    public static function failure(string $error, ?string $from = null, ?string $to = null): self
    {
        return new self(false, null, $error, $from, $to);
    }
}
