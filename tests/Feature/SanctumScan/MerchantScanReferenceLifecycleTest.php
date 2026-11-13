<?php

namespace Tests\Feature\SanctumScan;

use App\Models\Merchants\Merchant;
use App\Models\SanctumScanReference;
use App\Services\SanctumScan\ScanReferenceIssuer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MerchantScanReferenceLifecycleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.prefix' => '',
        ]);
        DB::purge('sqlite');

        $referenceMigration = require base_path('database/migrations/2026_11_13_130000_create_sanctum_scan_references_table.php');
        $referenceMigration->up();

        $auditMigration = require base_path('database/migrations/2026_11_13_140000_add_audit_fields_to_sanctum_scan_references_table.php');
        $auditMigration->up();

        Schema::create('merchants', function (Blueprint $table) {
            $table->id();
            $table->string('firstname')->default('Merchant');
            $table->string('lastname')->nullable();
            $table->string('username')->unique();
            $table->string('email')->nullable()->unique();
            $table->string('password');
            $table->string('business_name')->nullable();
            $table->boolean('status')->default(true);
            $table->boolean('email_verified')->default(true);
            $table->boolean('sms_verified')->default(true);
            $table->boolean('two_factor_status')->default(false);
            $table->boolean('two_factor_verified')->default(true);
            $table->unsignedTinyInteger('kyc_verified')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });

        // Exclude only the legacy maintenance and conditional KYC middleware.
        // Keep merchant authentication, account status and 2FA in the request path.
        $this->withoutMiddleware([
            \App\Http\Middleware\Admin\SystemMaintenanceApi::class,
            \App\Http\Middleware\KycApi::class,
        ]);
    }

    private function makeMerchant(string $username, string $businessName): Merchant
    {
        return Merchant::query()->create([
            'firstname' => 'Test',
            'lastname' => 'Merchant',
            'username' => $username,
            'email' => $username . '@example.test',
            'password' => bcrypt('not-a-real-password'),
            'business_name' => $businessName,
            'status' => true,
            'email_verified' => true,
            'sms_verified' => true,
            'two_factor_status' => false,
            'two_factor_verified' => true,
        ]);
    }

    public function test_authenticated_merchant_can_issue_a_qr_without_sending_identity_fields(): void
    {
        $merchant = $this->makeMerchant('mosop-farm-inputs', 'Mosop Farm Inputs');

        $response = $this->actingAs($merchant, 'merchant_api')
            ->postJson('/merchant-api/merchant/sanctum/scan-references', [
                'expires_in_days' => 30,
                'merchant_id' => 999999,
                'resource_type' => 'wallet_balance',
                'display_name' => 'Attacker Controlled Name',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'merchant')
            ->assertJsonPath('data.display_name', 'Mosop Farm Inputs')
            ->assertJsonPath('data.status', 'active');

        $scanUrl = $response->json('data.scan_url');
        $this->assertIsString($scanUrl);
        $this->assertStringContainsString('/api/sanctum/v1/scan/snc_', $scanUrl);

        $qrDataUri = $response->json('data.qr_image_data_uri');
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $qrDataUri);

        $svg = base64_decode(substr($qrDataUri, strlen('data:image/svg+xml;base64,')), true);
        $this->assertIsString($svg);
        $this->assertStringContainsString('<svg', $svg);

        $record = SanctumScanReference::query()->firstOrFail();
        $this->assertSame('merchant', $record->resource_type);
        $this->assertSame($merchant->getKey(), $record->resource_id);
        $this->assertSame('merchant_api', $record->created_by_guard);
        $this->assertSame($merchant->getKey(), $record->created_by_id);
        $this->assertSame(hash('sha256', $this->referenceFromUrl($scanUrl)), $record->reference_hash);
        $this->assertStringNotContainsString($merchant->email, $scanUrl);

        $this->getJson($scanUrl)
            ->assertOk()
            ->assertJsonPath('data.resource.display_name', 'Mosop Farm Inputs')
            ->assertJsonPath('data.resource.profile.handle', 'mosop-farm-inputs');
    }

    public function test_merchant_listing_is_scoped_to_the_authenticated_owner(): void
    {
        $owner = $this->makeMerchant('owner-merchant', 'Owner Business');
        $other = $this->makeMerchant('other-merchant', 'Other Business');

        app(ScanReferenceIssuer::class)->issue(
            'merchant',
            (int) $owner->getKey(),
            'Owner Business',
            null,
            null,
            'merchant_api',
            (int) $owner->getKey()
        );
        app(ScanReferenceIssuer::class)->issue(
            'merchant',
            (int) $other->getKey(),
            'Other Business',
            null,
            null,
            'merchant_api',
            (int) $other->getKey()
        );

        $response = $this->actingAs($owner, 'merchant_api')
            ->getJson('/merchant-api/merchant/sanctum/scan-references');

        $response->assertOk()
            ->assertJsonCount(1, 'data.references')
            ->assertJsonPath('data.references.0.display_name', 'Owner Business');

        $this->assertStringNotContainsString('reference_hash', $response->getContent());
        $this->assertStringNotContainsString('resource_id', $response->getContent());
    }

    public function test_merchant_can_revoke_only_its_own_reference_with_actor_attribution(): void
    {
        $owner = $this->makeMerchant('revoke-owner', 'Owner Business');
        $other = $this->makeMerchant('revoke-other', 'Other Business');

        $own = app(ScanReferenceIssuer::class)->issue(
            'merchant',
            (int) $owner->getKey(),
            'Owner Business',
            null,
            null,
            'merchant_api',
            (int) $owner->getKey()
        )['record'];

        $otherReference = app(ScanReferenceIssuer::class)->issue(
            'merchant',
            (int) $other->getKey(),
            'Other Business',
            null,
            null,
            'merchant_api',
            (int) $other->getKey()
        )['record'];

        $this->actingAs($owner, 'merchant_api')
            ->deleteJson('/merchant-api/merchant/sanctum/scan-references/' . $otherReference->getKey())
            ->assertNotFound();

        $this->assertSame('active', $otherReference->fresh()->status);

        $response = $this->actingAs($owner, 'merchant_api')
            ->deleteJson('/merchant-api/merchant/sanctum/scan-references/' . $own->getKey());

        $response->assertOk()->assertJsonPath('data.status', 'revoked');

        $revoked = $own->fresh();
        $this->assertSame('merchant_api', $revoked->revoked_by_guard);
        $this->assertSame($owner->getKey(), $revoked->revoked_by_id);
        $this->assertNotNull($revoked->revoked_at);
    }

    public function test_unauthenticated_caller_cannot_issue_a_merchant_qr(): void
    {
        $this->postJson('/merchant-api/merchant/sanctum/scan-references')
            ->assertUnauthorized();
    }

    public function test_public_resolution_uses_current_merchant_name_and_only_exposes_a_handle(): void
    {
        $merchant = $this->makeMerchant('public-farm-shop', 'Current Business Name');
        $issued = app(ScanReferenceIssuer::class)->issue(
            'merchant',
            (int) $merchant->getKey(),
            'Old Business Name'
        );

        $response = $this->getJson('/api/sanctum/v1/scan/' . $issued['reference']);

        $response->assertOk()
            ->assertJsonPath('data.resource.type', 'merchant')
            ->assertJsonPath('data.resource.display_name', 'Current Business Name')
            ->assertJsonPath('data.resource.profile.handle', 'public-farm-shop');

        $this->assertStringNotContainsString($merchant->email, $response->getContent());
        $this->assertStringNotContainsString('password', $response->getContent());
        $this->assertStringNotContainsString('kyc', $response->getContent());
        $this->assertStringNotContainsString('wallet', $response->getContent());
    }

    public function test_inactive_merchant_profile_reference_is_not_resolvable(): void
    {
        $merchant = $this->makeMerchant('inactive-farm-shop', 'Inactive Business');
        $issued = app(ScanReferenceIssuer::class)->issue('merchant', (int) $merchant->getKey(), 'Inactive Business');

        $merchant->forceFill(['status' => false])->save();

        $this->getJson('/api/sanctum/v1/scan/' . $issued['reference'])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'scan_reference_unavailable');
    }

    private function referenceFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $marker = '/api/sanctum/v1/scan/';

        $this->assertIsString($path);
        $this->assertStringContainsString($marker, $path);

        return substr($path, strpos($path, $marker) + strlen($marker));
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('sanctum_scan_references');
        Schema::dropIfExists('merchants');
        DB::purge('sqlite');

        parent::tearDown();
    }
}
