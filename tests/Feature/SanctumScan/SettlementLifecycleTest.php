<?php

namespace Tests\Feature\SanctumScan;

use App\Http\Middleware\Admin\AppModeGuard;
use App\Http\Middleware\Admin\RoleGuard;
use App\Http\Middleware\Admin\SystemMaintenanceApi;
use App\Http\Middleware\KycApi;
use App\Http\Middleware\User\PinSetupGuard;
use App\Models\Admin\Admin;
use App\Models\Merchants\Merchant;
use App\Models\SanctumScanExchange;
use App\Models\SanctumScanExchangeEvent;
use App\Models\SanctumScanExchangePaymentEvidence;
use App\Models\SanctumScanResource;
use App\Models\User;
use App\Services\SanctumScan\ExchangeSettlementManager;
use App\Services\SanctumScan\ScanReferenceIssuer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SettlementLifecycleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.prefix' => '',
            'database.connections.sqlite.foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('firstname');
            $table->string('lastname')->nullable();
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('status')->default(true);
            $table->boolean('email_verified')->default(true);
            $table->boolean('sms_verified')->default(true);
            $table->boolean('kyc_verified')->default(1);
            $table->boolean('two_factor_status')->default(false);
            $table->boolean('two_factor_verified')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('firstname')->nullable();
            $table->string('lastname')->nullable();
            $table->string('username')->nullable()->unique();
            $table->string('email')->nullable()->unique();
            $table->string('password')->nullable();
            $table->boolean('status')->default(true);
            $table->boolean('two_factor_status')->default(false);
            $table->boolean('two_factor_verified')->default(true);
            $table->timestamps();
        });

        // The Admin model eager-loads its roles; the table must exist even when
        // the test administrator holds no role (role checks are middleware).
        Schema::create('admin_has_roles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id');
            $table->unsignedBigInteger('admin_role_id');
            $table->unsignedBigInteger('last_edit_by')->nullable();
            $table->timestamps();
        });

        Schema::create('basic_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('merchant_pin_verification')->default(false);
            $table->timestamps();
        });

        Schema::create('merchants', function (Blueprint $table) {
            $table->id();
            $table->string('firstname');
            $table->string('lastname')->nullable();
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('business_name')->nullable();
            $table->boolean('status')->default(true);
            $table->boolean('email_verified')->default(true);
            $table->boolean('sms_verified')->default(true);
            $table->boolean('kyc_verified')->default(1);
            $table->boolean('two_factor_status')->default(false);
            $table->boolean('two_factor_verified')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        foreach ([
            '2026_11_13_130000_create_sanctum_scan_references_table.php',
            '2026_11_13_140000_add_audit_fields_to_sanctum_scan_references_table.php',
            '2026_11_13_150000_create_sanctum_scan_resources_table.php',
            '2026_11_13_160000_create_sanctum_scan_exchanges_table.php',
            '2026_11_13_161000_create_sanctum_scan_exchange_events_table.php',
            '2026_11_13_162000_create_sanctum_scan_exchange_payment_evidence_table.php',
            '2026_11_16_130000_add_versioning_to_sanctum_scan_exchange_payment_evidence_table.php',
            '2026_11_14_100000_add_identity_verification_to_users_table.php',
            '2026_11_14_100100_add_sanctum_exchange_identity_gate_to_basic_settings.php',
            '2026_11_14_110000_add_settlement_status_to_sanctum_scan_exchanges_table.php',
            '2026_11_16_120000_backfill_settlement_status_for_closed_exchanges.php',
        ] as $migrationFile) {
            (require base_path('database/migrations/'.$migrationFile))->up();
        }

        // Keep passport authentication and participant status checks active.
        // Disable only environment-dependent gates, plus the admin operator
        // middleware verified separately: AppModeGuard rejects writes outside
        // "live" mode and RoleGuard requires a seeded role/permission graph.
        $this->withoutMiddleware([
            SystemMaintenanceApi::class,
            KycApi::class,
            PinSetupGuard::class,
            \App\Http\Middleware\Merchant\PinSetupGuard::class,
            AppModeGuard::class,
            RoleGuard::class,
        ]);
    }

    private function makeMerchant(string $username, string $businessName): Merchant
    {
        return Merchant::query()->create([
            'firstname' => 'Test',
            'lastname' => 'Merchant',
            'username' => $username,
            'email' => $username.'@example.test',
            'password' => bcrypt('not-a-real-password'),
            'business_name' => $businessName,
            'status' => true,
            'email_verified' => true,
            'sms_verified' => true,
            'kyc_verified' => 1,
            'two_factor_status' => false,
            'two_factor_verified' => true,
        ]);
    }

    private function makeBuyer(string $username): User
    {
        return User::query()->create([
            'firstname' => 'Test',
            'lastname' => 'Buyer',
            'username' => $username,
            'email' => $username.'@example.test',
            'password' => bcrypt('not-a-real-password'),
            'status' => true,
            'email_verified' => true,
            'sms_verified' => true,
            'kyc_verified' => 1,
            'two_factor_status' => false,
            'two_factor_verified' => true,
        ]);
    }

    private function makeAdmin(string $suffix): Admin
    {
        return Admin::query()->create([
            'firstname' => 'Settlement',
            'lastname' => 'Operator',
            'username' => 'settlement-admin-'.$suffix,
            'email' => 'settlement-admin-'.$suffix.'@example.test',
            'password' => bcrypt('not-a-real-password'),
            'status' => true,
            'two_factor_status' => false,
            'two_factor_verified' => true,
        ]);
    }

    private function makeResource(Merchant $merchant): SanctumScanResource
    {
        return SanctumScanResource::query()->create([
            'merchant_id' => $merchant->getKey(),
            'resource_type' => 'agricultural_batch',
            'display_name' => 'Settlement fixture batch',
            'description' => 'Fresh produce batch for settlement tests.',
            'category' => 'Potatoes',
            'reference_code' => 'SETTLE-001',
            'variety' => 'Shangi',
            'origin_label' => 'Rongai, Nakuru County',
            'occurred_on' => '2026-11-10',
            'quantity' => '500.0000',
            'unit' => 'kg',
            'status' => 'published',
        ]);
    }

    private function issueOffer(Merchant $merchant, User $buyer, SanctumScanResource $resource): int
    {
        $response = $this->actingAs($merchant, 'merchant_api')
            ->postJson('/merchant-api/merchant/sanctum/exchanges', [
                'resource_id' => $resource->getKey(),
                'buyer_username' => $buyer->username,
                'quantity' => '100.0000',
                'unit' => 'kg',
                'amount' => '25000.00',
                'currency' => 'KES',
                'terms' => 'Collect from the agreed local market point.',
                'expires_in_hours' => 48,
            ]);

        $response->assertCreated();

        return (int) $response->json('data.exchange.id');
    }

    private function acceptOffer(User $buyer, int $exchangeId): void
    {
        $this->actingAs($buyer, 'api')
            ->postJson('/api/user/sanctum/exchanges/'.$exchangeId.'/accept')
            ->assertOk()
            ->assertJsonPath('data.exchange.settlement_status', 'awaiting_payment');
    }

    private function submitEvidence(User $buyer, int $exchangeId, array $overrides = []): void
    {
        $this->actingAs($buyer, 'api')
            ->postJson('/api/user/sanctum/exchanges/'.$exchangeId.'/payment-reference', array_merge([
                'rail' => 'mpesa',
                'reference' => 'QJK8XY5ABCDE',
                'amount' => '25000.00000000',
                'currency' => 'KES',
            ], $overrides))
            ->assertCreated()
            ->assertJsonPath('data.exchange.settlement_status', 'payment_evidence_submitted');
    }

    private function adminSettlementUrl(int $exchangeId): string
    {
        return route('admin.sanctum.exchanges.settlement.show', ['exchangeId' => $exchangeId]);
    }

    private function adminTransitionUrl(int $exchangeId): string
    {
        return route('admin.sanctum.exchanges.settlement.transition', ['exchangeId' => $exchangeId]);
    }

    private function transition(Admin $admin, int $exchangeId, array $payload)
    {
        return $this->actingAs($admin, 'admin')
            ->postJson($this->adminTransitionUrl($exchangeId), $payload);
    }

    private function settlementEvents(int $exchangeId)
    {
        return SanctumScanExchangeEvent::query()
            ->where('exchange_id', $exchangeId)
            ->where('event_type', 'settlement_status_changed')
            ->orderBy('id')
            ->get();
    }

    public function test_full_settlement_lifecycle_moves_one_step_per_admin_action_and_is_audited(): void
    {
        $merchant = $this->makeMerchant('settlement-seller', 'Settlement Seller');
        $buyer = $this->makeBuyer('settlement-buyer');
        $exporter = $this->makeBuyer('settlement-exporter');
        $admin = $this->makeAdmin('lifecycle');

        $exchangeId = $this->issueOffer($merchant, $buyer, $this->makeResource($merchant));

        $this->assertSame(
            'awaiting_payment',
            SanctumScanExchange::findOrFail($exchangeId)->settlement_status
        );

        $this->acceptOffer($buyer, $exchangeId);

        $this->actingAs($merchant, 'merchant_api')
            ->postJson('/merchant-api/merchant/sanctum/exchanges/'.$exchangeId.'/dispatch', [
                'dispatch_reference' => 'LOCAL-PICKUP-SETTLEMENT',
                'dispatch_note' => 'Goods made available for buyer collection.',
            ])
            ->assertOk()
            ->assertJsonPath('data.exchange.status', 'dispatched');

        $this->actingAs($buyer, 'api')
            ->postJson('/api/user/sanctum/exchanges/'.$exchangeId.'/receipt', [
                'receipt_note' => 'Buyer confirmed receipt of the goods.',
            ])
            ->assertOk()
            ->assertJsonPath('data.exchange.status', 'received');

        $this->submitEvidence($buyer, $exchangeId);

        $this->assertSame(1, $this->settlementEvents($exchangeId)->count());
        $this->assertSame(1, SanctumScanExchangePaymentEvidence::query()
            ->where('exchange_id', $exchangeId)->count());

        $show = $this->actingAs($admin, 'admin')->getJson($this->adminSettlementUrl($exchangeId));

        $show->assertOk()
            ->assertJsonPath('data.exchange.settlement_status', 'payment_evidence_submitted')
            ->assertJsonPath('data.exchange.payment_evidence.reference', 'QJK8XY5ABCDE')
            ->assertJsonPath('data.exchange.payment_evidence.verification_status', 'unverified')
            ->assertJsonCount(1, 'data.exchange.settlement_events')
            ->assertJsonPath('data.exchange.settlement_events.0.from', 'awaiting_payment')
            ->assertJsonPath('data.exchange.settlement_events.0.to', 'payment_evidence_submitted');

        $this->transition($admin, $exchangeId, [
            'to_status' => 'payment_verified',
            'expected_status' => 'payment_evidence_submitted',
            'reason' => 'Bank statement reconciled against the declared reference.',
        ])->assertOk()
            ->assertJsonPath('data.exchange.settlement_status', 'payment_verified')
            ->assertJsonCount(2, 'data.exchange.settlement_events')
            ->assertJsonPath('data.exchange.settlement_events.1.payment_evidence_version', 1);

        $firstEvidence = SanctumScanExchangePaymentEvidence::query()
            ->where('exchange_id', $exchangeId)
            ->firstOrFail();
        $verificationEvent = $this->settlementEvents($exchangeId)->last();

        $this->assertSame((int) $firstEvidence->getKey(), $verificationEvent->event_data['payment_evidence_id']);
        $this->assertSame(1, $verificationEvent->event_data['payment_evidence_version']);
        $this->assertMatchesRegularExpression('/\\A[a-f0-9]{64}\\z/', $verificationEvent->event_data['payment_evidence_sha256']);

        $this->transition($admin, $exchangeId, [
            'to_status' => 'settled',
            'expected_status' => 'payment_verified',
            'reason' => 'Administrative record that the settlement completed off platform.',
        ])->assertOk()
            ->assertJsonPath('data.exchange.settlement_status', 'settled')
            ->assertJsonCount(3, 'data.exchange.settlement_events');

        $events = $this->settlementEvents($exchangeId);

        $this->assertSame(3, $events->count());
        $this->assertSame(
            ['awaiting_payment', 'payment_evidence_submitted', 'payment_verified'],
            $events->pluck('event_data')->map(fn ($data) => $data['from'])->all()
        );
        $this->assertSame(
            ['payment_evidence_submitted', 'payment_verified', 'settled'],
            $events->pluck('event_data')->map(fn ($data) => $data['to'])->all()
        );
        $this->assertSame(['api', 'admin', 'admin'], $events->pluck('recorded_by_guard')->all());
        $this->assertSame(
            ['buyer_declared', 'platform_verified', 'platform_verified'],
            $events->pluck('verification_status')->all()
        );
        $this->assertSame($admin->getKey(), $events->last()->recorded_by_id);
        $this->assertSame(
            (int) $firstEvidence->getKey(),
            $events->last()->event_data['payment_evidence_id']
        );
        $this->assertSame(1, $events->last()->event_data['payment_evidence_version']);

        $this->transition($admin, $exchangeId, [
            'to_status' => 'payment_verified',
            'reason' => 'A settled exchange must never change settlement status again.',
        ])->assertStatus(409)
            ->assertJsonPath('error.code', 'invalid_settlement_transition');

        $this->assertSame(3, $this->settlementEvents($exchangeId)->count());

        $this->actingAs($buyer, 'api')
            ->getJson('/api/user/sanctum/exchanges')
            ->assertOk()
            ->assertJsonPath('data.exchanges.0.settlement_status', 'settled')
            ->assertJsonPath('data.exchanges.0.settlement_status_updated_at', fn ($value) => is_string($value));

        $this->actingAs($merchant, 'merchant_api')
            ->getJson('/merchant-api/merchant/sanctum/exchanges')
            ->assertOk()
            ->assertJsonPath('data.exchanges.0.settlement_status', 'settled');
    }

    public function test_admin_rejection_allows_a_versioned_resubmission_and_reopened_verification(): void
    {
        $merchant = $this->makeMerchant('reject-seller', 'Reject Seller');
        $buyer = $this->makeBuyer('reject-buyer');
        $admin = $this->makeAdmin('reject');

        $exchangeId = $this->issueOffer($merchant, $buyer, $this->makeResource($merchant));
        $this->acceptOffer($buyer, $exchangeId);
        $this->submitEvidence($buyer, $exchangeId);

        $this->transition($admin, $exchangeId, [
            'to_status' => 'payment_rejected',
            'expected_status' => 'payment_evidence_submitted',
            'reason' => 'The declared reference could not be found on any bank statement.',
        ])->assertOk()
            ->assertJsonPath('data.exchange.settlement_status', 'payment_rejected');

        $this->assertSame('platform_rejected', $this->settlementEvents($exchangeId)->last()->verification_status);
        $rejectionEvent = $this->settlementEvents($exchangeId)->last();
        $firstVersionId = (int) SanctumScanExchangePaymentEvidence::query()
            ->where('exchange_id', $exchangeId)
            ->where('version', 1)
            ->value('id');

        $this->assertSame($firstVersionId, $rejectionEvent->event_data['payment_evidence_id']);
        $this->assertSame(1, $rejectionEvent->event_data['payment_evidence_version']);
        $firstVersionFingerprint = $rejectionEvent->event_data['payment_evidence_sha256'];
        $this->assertMatchesRegularExpression('/\\A[a-f0-9]{64}\\z/', $firstVersionFingerprint);

        $this->transition($admin, $exchangeId, [
            'to_status' => 'payment_verified',
            'reason' => 'A rejected settlement cannot be verified until new evidence exists.',
        ])->assertStatus(409)
            ->assertJsonPath('error.code', 'invalid_settlement_transition');

        $this->actingAs($buyer, 'api')
            ->postJson('/api/user/sanctum/exchanges/'.$exchangeId.'/payment-reference', [
                'rail' => 'bank_transfer',
                'reference' => 'RETRY-REFERENCE-2',
                'amount' => '25000',
                'currency' => 'KES',
            ])
            ->assertCreated()
            ->assertJsonPath('data.exchange.settlement_status', 'payment_evidence_submitted')
            ->assertJsonPath('data.exchange.payment_evidence.version', 2)
            ->assertJsonPath('data.exchange.payment_evidence.reference', 'RETRY-REFERENCE-2')
            ->assertJsonPath('data.payment_evidence.version', 2);

        $evidence = SanctumScanExchangePaymentEvidence::query()
            ->where('exchange_id', $exchangeId)
            ->orderBy('version')
            ->get();

        $this->assertCount(2, $evidence);
        $this->assertSame([1, 2], $evidence->pluck('version')->all());
        $this->assertSame('QJK8XY5ABCDE', $evidence[0]->reference);
        $this->assertSame('RETRY-REFERENCE-2', $evidence[1]->reference);
        $this->assertNull($evidence[0]->supersedes_id);
        $this->assertSame($evidence[0]->getKey(), $evidence[1]->supersedes_id);

        $this->actingAs($admin, 'admin')
            ->getJson($this->adminSettlementUrl($exchangeId))
            ->assertOk()
            ->assertJsonPath('data.exchange.payment_evidence.version', 2)
            ->assertJsonCount(2, 'data.exchange.payment_evidence_history')
            ->assertJsonPath('data.exchange.payment_evidence_history.0.version', 1)
            ->assertJsonPath('data.exchange.payment_evidence_history.1.version', 2)
            ->assertJsonPath('data.exchange.payment_evidence_history.1.reference', 'RETRY-REFERENCE-2');

        $this->assertSame(3, $this->settlementEvents($exchangeId)->count());
        $this->assertSame(
            1,
            SanctumScanExchangeEvent::query()
                ->where('exchange_id', $exchangeId)
                ->where('event_type', 'payment_reference_resubmitted')
                ->count()
        );

        $this->transition($admin, $exchangeId, [
            'to_status' => 'payment_verified',
            'expected_status' => 'payment_evidence_submitted',
            'reason' => 'The replacement reference reconciled against the merchant statement.',
        ])->assertOk()
            ->assertJsonPath('data.exchange.settlement_status', 'payment_verified');

        $versionTwoEvent = $this->settlementEvents($exchangeId)->last();

        $this->assertSame((int) $evidence[1]->getKey(), $versionTwoEvent->event_data['payment_evidence_id']);
        $this->assertSame(2, $versionTwoEvent->event_data['payment_evidence_version']);
        $this->assertMatchesRegularExpression('/\\A[a-f0-9]{64}\\z/', $versionTwoEvent->event_data['payment_evidence_sha256']);
        $this->assertNotSame($firstVersionFingerprint, $versionTwoEvent->event_data['payment_evidence_sha256']);

        $this->actingAs($admin, 'admin')
            ->getJson($this->adminSettlementUrl($exchangeId))
            ->assertOk()
            ->assertJsonPath('data.exchange.settlement_events.1.payment_evidence_id', $firstVersionId)
            ->assertJsonPath('data.exchange.settlement_events.1.payment_evidence_version', 1)
            ->assertJsonPath('data.exchange.settlement_events.3.payment_evidence_id', $evidence[1]->getKey())
            ->assertJsonPath('data.exchange.settlement_events.3.payment_evidence_version', 2);
    }

    public function test_illegal_repeated_stale_and_unknown_transitions_write_no_events(): void
    {
        $merchant = $this->makeMerchant('illegal-seller', 'Illegal Seller');
        $buyer = $this->makeBuyer('illegal-buyer');
        $admin = $this->makeAdmin('illegal');

        $exchangeId = $this->issueOffer($merchant, $buyer, $this->makeResource($merchant));
        $this->acceptOffer($buyer, $exchangeId);

        $this->transition($admin, $exchangeId, [
            'to_status' => 'settled',
            'expected_status' => 'awaiting_payment',
            'reason' => 'Skipping verification must never be accepted.',
        ])->assertStatus(409)
            ->assertJsonPath('error.code', 'invalid_settlement_transition');

        $this->assertSame(0, $this->settlementEvents($exchangeId)->count());

        $this->submitEvidence($buyer, $exchangeId);

        $this->transition($admin, $exchangeId, [
            'to_status' => 'payment_verified',
            'expected_status' => 'payment_evidence_submitted',
            'reason' => 'Reconciled against the merchant settlement statement.',
        ])->assertOk();

        $this->transition($admin, $exchangeId, [
            'to_status' => 'payment_verified',
            'reason' => 'Repeating the same transition must be rejected.',
        ])->assertStatus(409)
            ->assertJsonPath('error.code', 'already_in_state');

        $this->transition($admin, $exchangeId, [
            'to_status' => 'settled',
            'expected_status' => 'payment_evidence_submitted',
            'reason' => 'A stale expected status must be rejected.',
        ])->assertStatus(409)
            ->assertJsonPath('error.code', 'stale_expected_status');

        $this->transition($admin, $exchangeId, [
            'to_status' => 'cancelled',
            'reason' => 'An administrator cannot cancel a verified settlement.',
        ])->assertStatus(409)
            ->assertJsonPath('error.code', 'invalid_settlement_transition');

        $this->transition($admin, $exchangeId, [
            'to_status' => 'refunded',
            'reason' => 'Unknown target statuses must fail validation.',
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $this->transition($admin, $exchangeId, [
            'to_status' => 'settled',
            'reason' => 'short',
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $this->assertSame('payment_verified', SanctumScanExchange::findOrFail($exchangeId)->settlement_status);
        $this->assertSame(2, $this->settlementEvents($exchangeId)->count());

        $this->actingAs($admin, 'admin')
            ->getJson(route('admin.sanctum.exchanges.settlement.show', ['exchangeId' => 999999]))
            ->assertNotFound();
    }

    public function test_admin_endpoint_denies_unauthenticated_and_non_admin_callers(): void
    {
        $merchant = $this->makeMerchant('guard-seller', 'Guard Seller');
        $buyer = $this->makeBuyer('guard-buyer');

        $exchangeId = $this->issueOffer($merchant, $buyer, $this->makeResource($merchant));

        $this->postJson($this->adminTransitionUrl($exchangeId), [
            'to_status' => 'payment_verified',
            'reason' => 'No authenticated administrator is present.',
        ])->assertStatus(401);

        $this->actingAs($buyer, 'api')
            ->postJson($this->adminTransitionUrl($exchangeId), [
                'to_status' => 'payment_verified',
                'reason' => 'A buyer must never control settlement state.',
            ])
            ->assertStatus(401);

        $this->actingAs($merchant, 'merchant_api')
            ->postJson($this->adminTransitionUrl($exchangeId), [
                'to_status' => 'payment_verified',
                'reason' => 'A merchant must never control settlement state.',
            ])
            ->assertStatus(401);

        $this->assertSame('awaiting_payment', SanctumScanExchange::findOrFail($exchangeId)->settlement_status);
        $this->assertSame(0, $this->settlementEvents($exchangeId)->count());
    }

    public function test_amount_mismatch_fails_before_any_settlement_change(): void
    {
        $merchant = $this->makeMerchant('mismatch-seller', 'Mismatch Seller');
        $buyer = $this->makeBuyer('mismatch-buyer');

        $exchangeId = $this->issueOffer($merchant, $buyer, $this->makeResource($merchant));
        $this->acceptOffer($buyer, $exchangeId);

        $this->actingAs($buyer, 'api')
            ->postJson('/api/user/sanctum/exchanges/'.$exchangeId.'/payment-reference', [
                'rail' => 'bank_transfer',
                'reference' => 'WRONG-AMOUNT-REF',
                'amount' => '24999',
                'currency' => 'KES',
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'payment_amount_mismatch');

        $this->assertSame('awaiting_payment', SanctumScanExchange::findOrFail($exchangeId)->settlement_status);
        $this->assertSame(0, SanctumScanExchangePaymentEvidence::query()
            ->where('exchange_id', $exchangeId)->count());
        $this->assertSame(0, $this->settlementEvents($exchangeId)->count());
    }

    public function test_manager_bounds_actor_ids_and_rejects_non_admin_verification(): void
    {
        $merchant = $this->makeMerchant('actor-boundary-seller', 'Actor Boundary Seller');
        $buyer = $this->makeBuyer('actor-boundary-buyer');
        $exchangeId = $this->issueOffer($merchant, $buyer, $this->makeResource($merchant));
        $exchange = SanctumScanExchange::findOrFail($exchangeId);
        $manager = app(ExchangeSettlementManager::class);

        $systemWithUserId = $manager->transition(
            $exchange,
            'payment_evidence_submitted',
            'system',
            99,
            'A system actor must use the reserved zero identifier.'
        );
        $this->assertFalse($systemWithUserId->ok);
        $this->assertSame('invalid_actor', $systemWithUserId->error);

        $apiWithoutUserId = $manager->transition(
            $exchange,
            'payment_evidence_submitted',
            'api',
            0,
            'An API actor must be bound to an authenticated buyer.'
        );
        $this->assertFalse($apiWithoutUserId->ok);
        $this->assertSame('invalid_actor', $apiWithoutUserId->error);

        $this->acceptOffer($buyer, $exchangeId);
        $this->submitEvidence($buyer, $exchangeId);

        $adminWithoutId = $manager->transition(
            SanctumScanExchange::findOrFail($exchangeId),
            'payment_verified',
            'admin',
            0,
            'An administrative decision must be bound to an authenticated administrator.'
        );
        $this->assertFalse($adminWithoutId->ok);
        $this->assertSame('invalid_actor', $adminWithoutId->error);

        $merchantAttempt = $manager->transition(
            SanctumScanExchange::findOrFail($exchangeId),
            'payment_verified',
            'merchant_api',
            (int) $merchant->getKey(),
            'A merchant cannot verify their own payment evidence.'
        );
        $this->assertFalse($merchantAttempt->ok);
        $this->assertSame('invalid_settlement_transition', $merchantAttempt->error);

        $this->assertSame(
            'payment_evidence_submitted',
            SanctumScanExchange::findOrFail($exchangeId)->settlement_status
        );
        $this->assertSame(1, $this->settlementEvents($exchangeId)->count());
    }

    public function test_manager_checks_evidence_amount_and_requires_buyer_receipt_before_settlement(): void
    {
        $merchant = $this->makeMerchant('evidence-guard-seller', 'Evidence Guard Seller');
        $buyer = $this->makeBuyer('evidence-guard-buyer');
        $admin = $this->makeAdmin('evidence-guard');
        $exchangeId = $this->issueOffer($merchant, $buyer, $this->makeResource($merchant));
        $this->acceptOffer($buyer, $exchangeId);
        $this->submitEvidence($buyer, $exchangeId);

        $manager = app(ExchangeSettlementManager::class);

        // Bypass the immutable model hook only to simulate corrupt legacy data;
        // application writes must never update a submitted evidence version.
        DB::table('sanctum_scan_exchange_payment_evidence')
            ->where('exchange_id', $exchangeId)
            ->update(['amount' => '1.00000000']);

        $mismatched = $manager->transition(
            SanctumScanExchange::findOrFail($exchangeId),
            'payment_verified',
            'admin',
            (int) $admin->getKey(),
            'The submitted amount does not reconcile with the accepted offer.'
        );
        $this->assertFalse($mismatched->ok);
        $this->assertSame('payment_evidence_mismatch', $mismatched->error);

        DB::table('sanctum_scan_exchange_payment_evidence')
            ->where('exchange_id', $exchangeId)
            ->update(['amount' => '25000.00000000']);

        $verified = $manager->transition(
            SanctumScanExchange::findOrFail($exchangeId),
            'payment_verified',
            'admin',
            (int) $admin->getKey(),
            'The current evidence amount and currency reconcile with the accepted offer.'
        );
        $this->assertTrue($verified->ok);
        $this->assertSame('payment_verified', $verified->exchange->settlement_status);

        $settledWithoutReceipt = $manager->transition(
            $verified->exchange,
            'settled',
            'admin',
            (int) $admin->getKey(),
            'Settlement cannot be recorded until buyer receipt is confirmed.'
        );
        $this->assertFalse($settledWithoutReceipt->ok);
        $this->assertSame('buyer_receipt_required', $settledWithoutReceipt->error);
        $this->assertSame(
            'payment_verified',
            SanctumScanExchange::findOrFail($exchangeId)->settlement_status
        );
    }

    public function test_buyer_decline_cancels_settlement(): void
    {
        $merchant = $this->makeMerchant('decline-seller', 'Decline Seller');
        $buyer = $this->makeBuyer('decline-buyer');

        $exchangeId = $this->issueOffer($merchant, $buyer, $this->makeResource($merchant));

        $this->actingAs($buyer, 'api')
            ->postJson('/api/user/sanctum/exchanges/'.$exchangeId.'/decline')
            ->assertOk()
            ->assertJsonPath('data.exchange.status', 'declined')
            ->assertJsonPath('data.exchange.settlement_status', 'cancelled');

        $events = $this->settlementEvents($exchangeId);

        $this->assertSame(1, $events->count());
        $this->assertSame('api', $events->first()->recorded_by_guard);
        $this->assertSame('buyer_declared', $events->first()->verification_status);
        $this->assertSame('awaiting_payment', $events->first()->event_data['from']);
        $this->assertSame('cancelled', $events->first()->event_data['to']);
    }

    public function test_merchant_cancel_cancels_settlement(): void
    {
        $merchant = $this->makeMerchant('cancel-seller', 'Cancel Seller');
        $buyer = $this->makeBuyer('cancel-buyer');

        $exchangeId = $this->issueOffer($merchant, $buyer, $this->makeResource($merchant));

        $this->actingAs($merchant, 'merchant_api')
            ->postJson('/merchant-api/merchant/sanctum/exchanges/'.$exchangeId.'/cancel')
            ->assertOk()
            ->assertJsonPath('data.exchange.status', 'cancelled')
            ->assertJsonPath('data.exchange.settlement_status', 'cancelled');

        $events = $this->settlementEvents($exchangeId);

        $this->assertSame(1, $events->count());
        $this->assertSame('merchant_api', $events->first()->recorded_by_guard);
        $this->assertSame('merchant_declared', $events->first()->verification_status);
    }

    public function test_expired_offer_cancels_settlement_when_the_buyer_responds(): void
    {
        $merchant = $this->makeMerchant('expiry-seller', 'Expiry Seller');
        $buyer = $this->makeBuyer('expiry-buyer');

        $exchangeId = $this->issueOffer($merchant, $buyer, $this->makeResource($merchant));

        SanctumScanExchange::query()
            ->whereKey($exchangeId)
            ->firstOrFail()
            ->forceFill(['expires_at' => now()->subMinute()])
            ->save();

        $this->actingAs($buyer, 'api')
            ->postJson('/api/user/sanctum/exchanges/'.$exchangeId.'/accept')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'offer_expired');

        $exchange = SanctumScanExchange::findOrFail($exchangeId);

        $this->assertSame('expired', $exchange->status);
        $this->assertSame('cancelled', $exchange->settlement_status);

        $events = $this->settlementEvents($exchangeId);

        $this->assertSame(1, $events->count());
        $this->assertSame('system', $events->first()->recorded_by_guard);
        $this->assertSame('system_recorded', $events->first()->verification_status);
    }

    public function test_public_scan_payload_never_exposes_settlement_state(): void
    {
        $issued = app(ScanReferenceIssuer::class)->issue(
            'agreement',
            77,
            'Produce purchase agreement'
        );

        $response = $this->getJson('/api/sanctum/v1/scan/'.$issued['reference']);

        $response->assertOk();

        $this->assertStringNotContainsString('settlement_status', $response->getContent());
        $this->assertStringNotContainsString('settlement', $response->getContent());
    }

    protected function tearDown(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('sanctum_scan_references');
        Schema::dropIfExists('sanctum_scan_exchange_payment_evidence');
        Schema::dropIfExists('sanctum_scan_exchange_events');
        Schema::dropIfExists('sanctum_scan_exchanges');
        Schema::dropIfExists('sanctum_scan_resources');
        Schema::dropIfExists('basic_settings');
        Schema::dropIfExists('admin_has_roles');
        Schema::dropIfExists('merchants');
        Schema::dropIfExists('admins');
        Schema::dropIfExists('users');
        Schema::enableForeignKeyConstraints();
        DB::purge('sqlite');

        parent::tearDown();
    }
}
