<?php

namespace App\Services\SanctumScan;

use InvalidArgumentException;

final class SettlementStateMachine
{
    public const AWAITING_PAYMENT = 'awaiting_payment';

    public const EVIDENCE_SUBMITTED = 'payment_evidence_submitted';

    public const VERIFIED = 'payment_verified';

    public const REJECTED = 'payment_rejected';

    public const SETTLED = 'settled';

    public const CANCELLED = 'cancelled';

    public const STATUSES = [
        self::AWAITING_PAYMENT,
        self::EVIDENCE_SUBMITTED,
        self::VERIFIED,
        self::REJECTED,
        self::SETTLED,
        self::CANCELLED,
    ];

    /**
     * Rejection is a resting state, not a terminal one: the buyer may append a
     * new evidence version, which reopens verification. Settled and cancelled
     * cannot be left.
     */
    public const TERMINAL = [
        self::SETTLED,
        self::CANCELLED,
    ];

    /**
     * The only authoritative settlement transition table.
     *
     * Actor guards restrict who may request a legal transition. "system" is
     * not a superuser: it appears only where an automated path legitimately
     * records the transition.
     *
     * @var array<string, array<string, array<int, string>>>
     */
    private const TRANSITIONS = [
        self::AWAITING_PAYMENT => [
            self::EVIDENCE_SUBMITTED => ['api', 'system'],
            self::CANCELLED => ['api', 'merchant_api', 'system'],
        ],
        self::EVIDENCE_SUBMITTED => [
            self::VERIFIED => ['admin'],
            self::REJECTED => ['admin'],
            self::CANCELLED => ['api', 'merchant_api', 'system'],
        ],
        self::VERIFIED => [
            self::SETTLED => ['admin'],
        ],
        self::REJECTED => [
            self::EVIDENCE_SUBMITTED => ['api'],
        ],
    ];

    public static function canTransition(string $from, string $to, string $guard): bool
    {
        if (! self::isStatus($from) || ! self::isStatus($to) || $from === $to) {
            return false;
        }

        $allowedGuards = self::TRANSITIONS[$from][$to] ?? [];

        return in_array($guard, $allowedGuards, true);
    }

    public static function isStatus(string $status): bool
    {
        return in_array($status, self::STATUSES, true);
    }

    public static function isTerminal(string $status): bool
    {
        return in_array($status, self::TERMINAL, true);
    }

    public static function assertKnownStatus(string $status): void
    {
        if (! self::isStatus($status)) {
            throw new InvalidArgumentException("Unknown settlement status [{$status}].");
        }
    }

    /**
     * @return array<int, string>
     */
    public static function targetsFrom(string $status, string $guard): array
    {
        if (! self::isStatus($status)) {
            return [];
        }

        $targets = [];

        foreach (self::TRANSITIONS[$status] ?? [] as $target => $guards) {
            if (in_array($guard, $guards, true)) {
                $targets[] = $target;
            }
        }

        return $targets;
    }
}
