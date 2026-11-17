# M022 — Genuine Settlement Concurrency Testing

**Status:** In progress  
**Date:** 2026-11-17  
**Depends on:** M016 settlement state machine, M019 evidence versioning, M020 evidence-version-bound decisions

## Goal

Prove that two independent administrators cannot commit contradictory settlement decisions against the same evidence-submitted exchange when their manager transitions execute concurrently on MySQL.

The previous `SettlementConcurrencyTest` demonstrated row-lock contention on two connections, but then invoked `payment_verified` and `payment_rejected` sequentially in the PHPUnit process. That did not prove that competing `ExchangeSettlementManager::transition()` calls serialize correctly.

## Delivered in this branch

- Reworked the MySQL-only feature test to start two independent PHP worker processes. Each worker boots the application and obtains its own MySQL connection.
- Added a coordinator transaction that holds the target exchange row lock while both workers reach their own manager `SELECT ... FOR UPDATE` calls.
- The workers signal readiness and the start barrier through a temporary directory. The coordinator releases its lock only after both competing lock queries have started, forcing the manager transitions to contend concurrently.
- The workers request opposite decisions (`payment_verified` and `payment_rejected`) using the same expected source state.
- The test verifies one successful transition, one typed `stale_expected_status` failure, one final decision status and exactly one appended settlement decision event, attributed to the winner.
- Added the missing `run` command to the MySQL workflow's Sanctum Scan feature-test step so that step actually invokes the test suite.

No production settlement code, database schema, payment provider integration, wallet/ledger write or movement of funds is introduced.

## Concurrency protocol

1. The PHPUnit process inserts an isolated exchange with one unverified payment-evidence version.
2. A third MySQL connection begins a transaction and locks the exchange row.
3. Two PHP workers boot independently, establish separate MySQL sessions, and wait on a shared start signal.
4. Each worker calls `ExchangeSettlementManager::transition()` for the same exchange, with `expectedFrom=payment_evidence_submitted`.
5. A `beforeExecuting` callback writes a marker immediately before each worker executes the manager's `SELECT ... FOR UPDATE`. The PHPUnit process waits for both markers while it still owns the row lock.
6. The PHPUnit process commits its coordinator transaction. InnoDB serializes the competing manager transactions: one commits its decision and event; the other reads the new status and returns `stale_expected_status`.
7. The test inspects the final row and event table after both workers exit.

The coordinator is only a test synchronization mechanism. It does not write settlement state or settlement events.

## Acceptance criteria

- [ ] Both competing transitions run in separate PHP processes and separate MySQL connections.
- [ ] Both manager lock queries are reached before the coordinator releases the row lock.
- [ ] Exactly one transition succeeds.
- [ ] The losing transition returns `stale_expected_status`.
- [ ] Exactly one `settlement_status_changed` event exists for the test exchange.
- [ ] The event's `from`, `to`, actor guard and actor ID match the winning transition.
- [ ] The final exchange status matches the single immutable decision event.
- [ ] The test is executed by the G4 MySQL workflow; it skips outside MySQL and remains opt-in for local disposable MySQL runs.
- [ ] No production payment, wallet, ledger or funds-movement behavior changes.

## Validation

The source and workflow changes are prepared on the M022 branch. The MySQL race requires the G4 MySQL service and has not been executed in this environment. The repository's GitHub hosted-runner allocation issue remains tracked in [issue #21](https://github.com/ObiwanKenobee/qrpay-web-new-file-v5.7.0/issues/21); a failed run with no runner assigned is not evidence of a test failure or pass.
