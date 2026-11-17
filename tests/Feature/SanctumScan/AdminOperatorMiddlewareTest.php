<?php

namespace Tests\Feature\SanctumScan;

use App\Models\Admin\Admin;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Exercises the production admin middleware stack (auth:admin, app.mode,
 * admin.role.guard, admin.google.two.factor) instead of disabling it.
 */
class AdminOperatorMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.prefix' => '',
            'database.connections.sqlite.foreign_key_constraints' => true,
            'app.mode' => 'live',
        ]);
        DB::purge('sqlite');

        $this->createIdentityTables();
        $this->createRoleTables();
        $this->createScanTables();
    }

    public function test_super_admin_with_2fa_satisfied_can_read_and_transition_settlement(): void
    {
        $admin = $this->makeAdmin('super-happy', superAdmin: true);
        $exchangeId = $this->makeExchange();

        $this->actingAs($admin, 'admin')
            ->getJson($this->showUrl($exchangeId))
            ->assertOk()
            ->assertJsonPath('data.exchange.settlement_status', 'payment_evidence_submitted');

        $this->actingAs($admin, 'admin')
            ->postJson($this->transitionUrl($exchangeId), [
                'to_status' => 'payment_verified',
                'expected_status' => 'payment_evidence_submitted',
                'reason' => 'Bank statement reconciled by the operator.',
            ])
            ->assertOk()
            ->assertJsonPath('data.exchange.settlement_status', 'payment_verified');

        $this->assertSame(
            'payment_verified',
            DB::table('sanctum_scan_exchanges')->where('id', $exchangeId)->value('settlement_status')
        );
    }

    public function test_admin_without_a_role_is_logged_out_and_redirected(): void
    {
        $admin = $this->makeAdmin('no-role', superAdmin: false, assignRole: false);
        $exchangeId = $this->makeExchange();

        $this->actingAs($admin, 'admin')
            ->getJson($this->showUrl($exchangeId))
            ->assertRedirect();

        $this->assertGuest('admin');
    }

    public function test_non_super_admin_without_permission_receives_not_found(): void
    {
        $admin = $this->makeAdmin('reviewer', superAdmin: false);
        $exchangeId = $this->makeExchange();

        $this->actingAs($admin, 'admin')
            ->getJson($this->showUrl($exchangeId))
            ->assertNotFound();
    }

    public function test_demo_mode_blocks_settlement_writes_but_allows_reads(): void
    {
        $admin = $this->makeAdmin('demo-mode', superAdmin: true);
        $exchangeId = $this->makeExchange();

        config(['app.mode' => 'demo']);

        $this->actingAs($admin, 'admin')
            ->getJson($this->showUrl($exchangeId))
            ->assertOk();

        $this->actingAs($admin, 'admin')
            ->postJson($this->transitionUrl($exchangeId), [
                'to_status' => 'payment_verified',
                'reason' => 'Demo mode must reject this write.',
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.unknown.0', "Can't change anything for demo application.");

        $this->assertSame(
            'payment_evidence_submitted',
            DB::table('sanctum_scan_exchanges')->where('id', $exchangeId)->value('settlement_status')
        );
    }

    public function test_admin_with_pending_two_factor_is_redirected_to_verification(): void
    {
        $admin = $this->makeAdmin('2fa-pending', superAdmin: true);
        $admin->forceFill(['two_factor_status' => true, 'two_factor_verified' => false])->save();
        $exchangeId = $this->makeExchange();

        $this->actingAs($admin, 'admin')
            ->getJson($this->showUrl($exchangeId))
            ->assertRedirect(route('admin.authorize.google.2fa'));
    }


    public function test_reconciliation_queue_filters_active_cases_and_bounds_pagination(): void
    {
        $admin = $this->makeAdmin('reconciliation-queue', superAdmin: false);
        $this->grantReconciliationPermissions($admin);

        $disputedExchangeId = $this->makeExchange();
        $this->makeDispute($disputedExchangeId, 'open');

        $paymentExchangeId = $this->makeExchange();
        DB::table('sanctum_scan_exchanges')->where('id', $paymentExchangeId)
            ->update(['settlement_status' => 'awaiting_payment']);

        $completedExchangeId = $this->makeExchange();
        DB::table('sanctum_scan_exchanges')->where('id', $completedExchangeId)
            ->update(['settlement_status' => 'settled']);
        $this->makeDispute($completedExchangeId, 'resolved');

        $url = route('admin.sanctum.reconciliation.index');

        $this->actingAs($admin, 'admin')->getJson($url.'?per_page=1')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 2)
            ->assertJsonPath('data.pagination.per_page', 1)
            ->assertJsonPath('data.pagination.current_page', 1)
            ->assertJsonPath('data.pagination.last_page', 2);

        $this->actingAs($admin, 'admin')->getJson($url.'?page=2&per_page=1')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 2)
            ->assertJsonPath('data.pagination.current_page', 2);

        $this->actingAs($admin, 'admin')->getJson($url.'?settlement_status=awaiting_payment')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.cases.0.exchange_id', $paymentExchangeId);

        $this->actingAs($admin, 'admin')->getJson($url.'?dispute_status=open')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.cases.0.exchange_id', $disputedExchangeId);
    }

    public function test_reconciliation_detail_projects_history_and_never_exposes_storage_location_or_file_bytes(): void
    {
        $admin = $this->makeAdmin('reconciliation-detail', superAdmin: false);
        $this->grantReconciliationPermissions($admin);

        $exchangeId = $this->makeExchange();
        $disputeId = $this->makeDispute($exchangeId, 'open');

        DB::table('sanctum_scan_exchange_events')->insert([
            'exchange_id' => $exchangeId,
            'event_type' => 'settlement_status_changed',
            'description' => 'Payment evidence submitted',
            'event_data' => json_encode(['payment_evidence_id' => 1, 'payment_evidence_version' => 1]),
            'verification_status' => 'unverified',
            'recorded_by_guard' => 'admin',
            'recorded_by_id' => $admin->getKey(),
            'occurred_at' => now(),
            'created_at' => now(),
        ]);

        DB::table('sanctum_scan_exchange_dispute_messages')->insert([
            'dispute_id' => $disputeId,
            'message_type' => 'participant_statement',
            'body' => 'The delivery quantity did not match the agreed amount.',
            'recorded_by_guard' => 'user',
            'recorded_by_id' => 1,
            'created_at' => now(),
        ]);

        $storagePath = 'private/sanctum-scan/reconciliation-secret-object.pdf';
        $evidenceId = DB::table('sanctum_scan_exchange_dispute_evidence')->insertGetId([
            'dispute_id' => $disputeId,
            'evidence_type' => 'document',
            'original_name' => 'delivery-note.pdf',
            'storage_disk' => 'private',
            'storage_path' => $storagePath,
            'mime_type' => 'application/pdf',
            'size_bytes' => 4096,
            'sha256' => str_repeat('a', 64),
            'caption' => 'Signed delivery note',
            'submitted_by_guard' => 'user',
            'submitted_by_id' => 1,
            'submitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('sanctum_scan_exchange_evidence_reviews')->insert([
            'evidence_id' => $evidenceId,
            'review_status' => 'accepted',
            'review_method' => 'manual',
            'evidence_sha256' => str_repeat('a', 64),
            'review_summary' => 'Document is legible and relevant.',
            'reviewed_by_admin_id' => $admin->getKey(),
            'reviewed_at' => now(),
            'created_at' => now(),
        ]);

        $response = $this->actingAs($admin, 'admin')->getJson(
            route('admin.sanctum.reconciliation.show', ['exchangeId' => $exchangeId])
        );

        $response->assertOk()
            ->assertJsonPath('data.case.exchange.id', $exchangeId)
            ->assertJsonPath('data.case.payment_evidence_history.0.version', 1)
            ->assertJsonPath('data.case.settlement_timeline.0.event_data.payment_evidence_id', 1)
            ->assertJsonPath('data.case.dispute.messages.0.body', 'The delivery quantity did not match the agreed amount.')
            ->assertJsonPath('data.case.dispute.evidence.0.review.status', 'accepted');

        $payload = $response->getContent();
        $this->assertStringNotContainsString($storagePath, $payload);
        $this->assertStringNotContainsString('storage_disk', $payload);
        $this->assertStringNotContainsString('file_bytes', $payload);
    }

    public function test_reconciliation_routes_deny_admin_without_explicit_route_permissions(): void
    {
        $admin = $this->makeAdmin('reconciliation-no-permission', superAdmin: false);
        $exchangeId = $this->makeExchange();

        $this->actingAs($admin, 'admin')
            ->getJson(route('admin.sanctum.reconciliation.index'))
            ->assertNotFound();

        $this->actingAs($admin, 'admin')
            ->getJson(route('admin.sanctum.reconciliation.show', ['exchangeId' => $exchangeId]))
            ->assertNotFound();
    }

    private function grantReconciliationPermissions(Admin $admin): void
    {
        $roleId = DB::table('admin_has_roles')
            ->where('admin_id', $admin->getKey())
            ->value('admin_role_id');

        foreach (['admin.sanctum.reconciliation.index', 'admin.sanctum.reconciliation.show'] as $routeName) {
            $permissionId = DB::table('admin_role_permissions')->insertGetId([
                'admin_role_id' => $roleId,
                'admin_id' => $admin->getKey(),
                'name' => 'Reconciliation '.str_replace('.', ' ', $routeName),
                'slug' => str_replace('.', '-', $routeName),
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('admin_role_has_permissions')->insert([
                'admin_role_permission_id' => $permissionId,
                'route' => $routeName,
                'title' => $routeName,
                'last_edit_by' => $admin->getKey(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $admin->refresh();
    }

    private function makeDispute(int $exchangeId, string $status): int
    {
        return (int) DB::table('sanctum_scan_exchange_disputes')->insertGetId([
            'exchange_id' => $exchangeId,
            'category' => 'delivery',
            'status' => $status,
            'opened_by_guard' => 'user',
            'opened_by_id' => 1,
            'opened_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createIdentityTables(): void
    {
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
    }

    private function createRoleTables(): void
    {
        Schema::create('admin_roles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id');
            $table->string('name', 60)->unique();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('admin_role_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_role_id');
            $table->unsignedBigInteger('admin_id');
            $table->string('name', 60)->unique();
            $table->string('slug', 80)->unique();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('admin_role_has_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_role_permission_id');
            $table->string('route', 100);
            $table->string('title', 100)->nullable();
            $table->unsignedBigInteger('last_edit_by');
            $table->timestamps();
        });

        Schema::create('admin_has_roles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id');
            $table->unsignedBigInteger('admin_role_id');
            $table->unsignedBigInteger('last_edit_by')->nullable();
            $table->timestamps();
        });
    }

    private function createScanTables(): void
    {
        foreach ([
            '2026_11_13_150000_create_sanctum_scan_resources_table.php',
            '2026_11_13_160000_create_sanctum_scan_exchanges_table.php',
            '2026_11_13_161000_create_sanctum_scan_exchange_events_table.php',
            '2026_11_13_162000_create_sanctum_scan_exchange_payment_evidence_table.php',
            '2026_11_16_130000_add_versioning_to_sanctum_scan_exchange_payment_evidence_table.php',
            '2026_11_14_110000_add_settlement_status_to_sanctum_scan_exchanges_table.php',
            '2026_11_13_163000_create_sanctum_scan_exchange_disputes_table.php',
            '2026_11_13_164000_create_sanctum_scan_exchange_dispute_messages_table.php',
            '2026_11_13_165000_create_sanctum_scan_exchange_dispute_evidence_table.php',
            '2026_11_13_166000_create_sanctum_scan_exchange_evidence_reviews_table.php',
        ] as $migrationFile) {
            (require base_path('database/migrations/'.$migrationFile))->up();
        }
    }

    private function makeAdmin(string $suffix, bool $superAdmin, bool $assignRole = true): Admin
    {
        $admin = Admin::query()->create([
            'firstname' => 'Operator',
            'lastname' => 'Middleware',
            'username' => 'operator-'.$suffix,
            'email' => 'operator-'.$suffix.'@example.test',
            'password' => bcrypt('not-a-real-password'),
            'status' => true,
            'two_factor_status' => false,
            'two_factor_verified' => true,
        ]);

        if (! $assignRole) {
            return $admin->fresh();
        }

        $roleId = DB::table('admin_roles')->insertGetId([
            'admin_id' => $admin->getKey(),
            'name' => $superAdmin ? 'Super Admin' : 'Reviewer',
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('admin_has_roles')->insert([
            'admin_id' => $admin->getKey(),
            'admin_role_id' => $roleId,
            'last_edit_by' => $admin->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $admin->fresh();
    }

    private int $fixtureSequence = 0;

    private function makeExchange(): int
    {
        $suffix = ++$this->fixtureSequence;
        $merchantId = DB::table('merchants')->insertGetId([
            'firstname' => 'Middleware',
            'lastname' => 'Merchant',
            'username' => 'middleware-merchant-'.$suffix,
            'email' => 'middleware-merchant-'.$suffix.'@example.test',
            'password' => bcrypt('not-a-real-password'),
            'business_name' => 'Middleware Merchant Ltd',
            'status' => true,
            'email_verified' => true,
            'sms_verified' => true,
            'kyc_verified' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $buyerId = DB::table('users')->insertGetId([
            'firstname' => 'Middleware',
            'lastname' => 'Buyer',
            'username' => 'middleware-buyer-'.$suffix,
            'email' => 'middleware-buyer-'.$suffix.'@example.test',
            'password' => bcrypt('not-a-real-password'),
            'status' => true,
            'email_verified' => true,
            'sms_verified' => true,
            'kyc_verified' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resourceId = DB::table('sanctum_scan_resources')->insertGetId([
            'merchant_id' => $merchantId,
            'resource_type' => 'agricultural_batch',
            'display_name' => 'Middleware fixture batch',
            'category' => 'Potatoes',
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $exchangeId = (int) DB::table('sanctum_scan_exchanges')->insertGetId([
            'merchant_id' => $merchantId,
            'buyer_user_id' => $buyerId,
            'resource_id' => $resourceId,
            'resource_type' => 'agricultural_batch',
            'resource_name_snapshot' => 'Middleware fixture batch',
            'quantity' => '100.0000',
            'unit' => 'kg',
            'amount' => '25000.00000000',
            'currency' => 'KES',
            'status' => 'received',
            'settlement_status' => 'payment_evidence_submitted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('sanctum_scan_exchange_payment_evidence')->insert([
            'exchange_id' => $exchangeId,
            'version' => 1,
            'rail' => 'bank_transfer',
            'reference' => 'MIDDLEWARE-REF-'.$exchangeId,
            'amount' => '25000.00000000',
            'currency' => 'KES',
            'verification_status' => 'unverified',
            'submitted_by_guard' => 'api',
            'submitted_by_id' => $buyerId,
            'submitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $exchangeId;
    }

    private function showUrl(int $exchangeId): string
    {
        return route('admin.sanctum.exchanges.settlement.show', ['exchangeId' => $exchangeId]);
    }

    private function transitionUrl(int $exchangeId): string
    {
        return route('admin.sanctum.exchanges.settlement.transition', ['exchangeId' => $exchangeId]);
    }
}
