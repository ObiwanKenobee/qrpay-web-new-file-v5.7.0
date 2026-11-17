<?php

namespace Tests\Unit\SanctumScan;

use App\Services\SanctumScan\SettlementStateMachine;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SettlementStateMachineTest extends TestCase
{
    private const STATUSES = [
        'awaiting_payment',
        'payment_evidence_submitted',
        'payment_verified',
        'payment_rejected',
        'settled',
        'cancelled',
    ];

    private const GUARDS = ['admin', 'api', 'merchant_api', 'system'];

    /**
     * Independently written expectation. It must not be derived from the
     * production constant, otherwise a regression in the table would also
     * change the expectation and the test would always pass.
     *
     * @var array<string, array<string, array<int, string>>>
     */
    private const EXPECTED = [
        'awaiting_payment' => [
            'payment_evidence_submitted' => ['api', 'system'],
            'cancelled' => ['api', 'merchant_api', 'system'],
        ],
        'payment_evidence_submitted' => [
            'payment_verified' => ['admin'],
            'payment_rejected' => ['admin'],
            'cancelled' => ['api', 'merchant_api', 'system'],
        ],
        'payment_verified' => [
            'settled' => ['admin'],
        ],
        'payment_rejected' => [
            'payment_evidence_submitted' => ['api'],
        ],
        'settled' => [],
        'cancelled' => [],
    ];

    public function test_the_complete_transition_matrix_matches_the_authoritative_expectation(): void
    {
        $checked = 0;

        foreach (self::STATUSES as $from) {
            foreach (self::STATUSES as $to) {
                foreach (self::GUARDS as $guard) {
                    $expected = in_array(
                        $guard,
                        self::EXPECTED[$from][$to] ?? [],
                        true
                    );

                    $this->assertSame(
                        $expected,
                        SettlementStateMachine::canTransition($from, $to, $guard),
                        "Unexpected decision for [{$from}] -> [{$to}] as [{$guard}]."
                    );

                    $checked++;
                }
            }
        }

        $this->assertSame(
            count(self::STATUSES) * count(self::STATUSES) * count(self::GUARDS),
            $checked
        );
    }

    public function test_same_status_pairs_are_never_transitions(): void
    {
        foreach (self::STATUSES as $status) {
            foreach (self::GUARDS as $guard) {
                $this->assertFalse(
                    SettlementStateMachine::canTransition($status, $status, $guard),
                    "Expected [{$status}] -> [{$status}] as [{$guard}] to be rejected."
                );
            }
        }
    }

    public function test_terminal_statuses_have_no_outgoing_transitions(): void
    {
        foreach (['settled', 'cancelled'] as $terminal) {
            $this->assertTrue(SettlementStateMachine::isTerminal($terminal));

            foreach (self::STATUSES as $to) {
                foreach (self::GUARDS as $guard) {
                    $this->assertFalse(
                        SettlementStateMachine::canTransition($terminal, $to, $guard),
                        "Terminal [{$terminal}] must not transition to [{$to}]."
                    );
                }
            }

            foreach (self::GUARDS as $guard) {
                $this->assertSame([], SettlementStateMachine::targetsFrom($terminal, $guard));
            }
        }
    }

    public function test_non_terminal_statuses_are_reported_as_non_terminal(): void
    {
        foreach (['awaiting_payment', 'payment_evidence_submitted', 'payment_verified', 'payment_rejected'] as $status) {
            $this->assertFalse(SettlementStateMachine::isTerminal($status));
        }
    }

    public function test_rejection_can_only_be_reopened_by_the_buyer(): void
    {
        $this->assertTrue(
            SettlementStateMachine::canTransition('payment_rejected', 'payment_evidence_submitted', 'api')
        );
        $this->assertSame(
            ['payment_evidence_submitted'],
            SettlementStateMachine::targetsFrom('payment_rejected', 'api')
        );

        foreach (['admin', 'merchant_api', 'system'] as $guard) {
            $this->assertFalse(
                SettlementStateMachine::canTransition('payment_rejected', 'payment_evidence_submitted', $guard)
            );
            $this->assertSame([], SettlementStateMachine::targetsFrom('payment_rejected', $guard));
        }
    }

    public function test_admin_cannot_cancel_and_system_cannot_verify_reject_or_settle(): void
    {
        $this->assertFalse(
            SettlementStateMachine::canTransition('awaiting_payment', 'cancelled', 'admin')
        );
        $this->assertFalse(
            SettlementStateMachine::canTransition('payment_evidence_submitted', 'cancelled', 'admin')
        );

        $this->assertFalse(
            SettlementStateMachine::canTransition('payment_evidence_submitted', 'payment_verified', 'system')
        );
        $this->assertFalse(
            SettlementStateMachine::canTransition('payment_evidence_submitted', 'payment_rejected', 'system')
        );
        $this->assertFalse(
            SettlementStateMachine::canTransition('payment_verified', 'settled', 'system')
        );
    }

    public function test_unknown_statuses_are_rejected_in_both_positions(): void
    {
        foreach (self::GUARDS as $guard) {
            $this->assertFalse(SettlementStateMachine::canTransition('unknown', 'cancelled', $guard));
            $this->assertFalse(SettlementStateMachine::canTransition('awaiting_payment', 'unknown', $guard));
            $this->assertSame([], SettlementStateMachine::targetsFrom('unknown', $guard));
        }

        $this->assertFalse(SettlementStateMachine::isStatus('unknown'));
        $this->assertFalse(SettlementStateMachine::isTerminal('unknown'));
    }

    public function test_targets_from_reflect_the_transition_table(): void
    {
        $this->assertSame(
            ['payment_evidence_submitted', 'cancelled'],
            SettlementStateMachine::targetsFrom('awaiting_payment', 'api')
        );
        $this->assertSame(
            ['cancelled'],
            SettlementStateMachine::targetsFrom('awaiting_payment', 'merchant_api')
        );
        $this->assertSame(
            [],
            SettlementStateMachine::targetsFrom('awaiting_payment', 'admin')
        );
        $this->assertSame(
            ['payment_evidence_submitted', 'cancelled'],
            SettlementStateMachine::targetsFrom('awaiting_payment', 'system')
        );
        $this->assertSame(
            ['payment_verified', 'payment_rejected'],
            SettlementStateMachine::targetsFrom('payment_evidence_submitted', 'admin')
        );
        $this->assertSame(
            ['cancelled'],
            SettlementStateMachine::targetsFrom('payment_evidence_submitted', 'merchant_api')
        );
        $this->assertSame(
            ['settled'],
            SettlementStateMachine::targetsFrom('payment_verified', 'admin')
        );
        $this->assertSame(
            [],
            SettlementStateMachine::targetsFrom('payment_verified', 'api')
        );
    }

    public function test_assert_known_status_throws_for_unknown_values(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown settlement status [not_a_settlement_status].');

        SettlementStateMachine::assertKnownStatus('not_a_settlement_status');
    }

    public function test_assert_known_status_accepts_every_known_status(): void
    {
        foreach (self::STATUSES as $status) {
            SettlementStateMachine::assertKnownStatus($status);
            $this->addToAssertionCount(1);
        }
    }
}
