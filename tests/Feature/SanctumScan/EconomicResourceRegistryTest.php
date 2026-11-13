<?php

namespace Tests\Feature\SanctumScan;

use App\Models\Merchants\Merchant;
use App\Models\SanctumScanProvenanceEvent;
use App\Models\SanctumScanReference;
use App\Models\SanctumScanResource;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EconomicResourceRegistryTest extends TestCase
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

        $referenceMigration = require base_path('database/migrations/2026_11_13_130000_create_sanctum_scan_references_table.php');
        $referenceMigration->up();
        $auditMigration = require base_path('database/migrations/2026_11_13_140000_add_audit_fields_to_sanctum_scan_references_table.php');
        $auditMigration->up();
        $resourceMigration = require base_path('database/migrations/2026_11_13_150000_create_sanctum_scan_resources_table.php');
        $resourceMigration->up();
        $eventMigration = require base_path('database/migrations/2026_11_13_151000_create_sanctum_scan_provenance_events_table.php');
        $eventMigration->up();

        $this->withoutMiddleware([
            \App\Http\Middleware\Admin\SystemMaintenance::class,
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
            'kyc_verified' => 1,
        ]);
    }

    public function test_merchant_can_publish_an_agricultural_batch_and_render_its_scan_page(): void
    {
        $merchant = $this->makeMerchant('rongai-produce', 'Rongai Produce Collective');

        $response = $this->actingAs($merchant, 'merchant_api')
            ->postJson('/merchant-api/merchant/sanctum/resources', [
                'resource_type' => 'agricultural_batch',
                'display_name' => 'Shangi potato batch',
                'description' => 'Freshly harvested potatoes prepared for local trade.',
                'category' => 'Potatoes',
                'reference_code' => 'RNG-SHG-2026-004',
                'variety' => 'Shangi',
                'origin_label' => 'Rongai, Nakuru County',
                'occurred_on' => '2026-11-10',
                'quantity' => 250,
                'unit' => 'kg',
                'merchant_id' => 999999,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'agricultural_batch')
            ->assertJsonPath('data.display_name', 'Shangi potato batch')
            ->assertJsonPath('data.provenance_status', 'merchant_declared');

        $scanUrl = $response->json('data.scan_url');
        $this->assertIsString($scanUrl);
        $this->assertStringContainsString('/scan/snc_', $scanUrl);

        $qrDataUri = $response->json('data.qr_image_data_uri');
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $qrDataUri);

        $resource = SanctumScanResource::query()->firstOrFail();
        $this->assertSame($merchant->getKey(), $resource->merchant_id);
        $this->assertSame('published', $resource->status);
        $this->assertSame('250.0000', $resource->quantity);

        $events = SanctumScanProvenanceEvent::query()
            ->where('resource_id', $resource->getKey())
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $events);
        $this->assertSame('merchant_declared', $events[0]->verification_status);
        $this->assertSame('origin_declared', $events[1]->event_type);
        $this->assertSame('Rongai, Nakuru County', $events[1]->location_label);

        $page = $this->get($scanUrl);
        $page->assertOk()
            ->assertSee('Shangi potato batch')
            ->assertSee('Rongai, Nakuru County')
            ->assertSee('Shangi')
            ->assertSee('Merchant-declared information')
            ->assertSee('not independently verified');

        $this->assertStringNotContainsString('250.0000', $page->getContent());
        $this->assertStringNotContainsString('rongai-produce@example.test', $page->getContent());

        $reference = SanctumScanReference::query()->firstOrFail();
        $api = $this->getJson('/api/sanctum/v1/scan/' . $this->referenceFromUrl($scanUrl));

        $api->assertOk()
            ->assertJsonPath('data.resource.type', 'agricultural_batch')
            ->assertJsonPath('data.resource.details.variety', 'Shangi')
            ->assertJsonPath('data.resource.details.origin', 'Rongai, Nakuru County')
            ->assertJsonPath('data.resource.provenance.status', 'merchant_declared');

        $this->assertArrayNotHasKey('quantity', $api->json('data.resource.details'));
        $this->assertSame(hash('sha256', $this->referenceFromUrl($scanUrl)), $reference->reference_hash);
    }

    public function test_merchant_can_register_a_product_without_agricultural_batch_fields(): void
    {
        $merchant = $this->makeMerchant('mosop-supplies', 'Mosop Farm Supplies');

        $response = $this->actingAs($merchant, 'merchant_api')
            ->postJson('/merchant-api/merchant/sanctum/resources', [
                'resource_type' => 'product',
                'display_name' => 'Organic compost',
                'description' => 'Compost for smallholder farms.',
                'category' => 'Farm inputs',
                'reference_code' => 'COMPOST-01',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'product');

        $this->get($response->json('data.scan_url'))
            ->assertOk()
            ->assertSee('Organic compost')
            ->assertSee('Mosop Farm Supplies');
    }

    public function test_agricultural_batch_requires_a_variety(): void
    {
        $merchant = $this->makeMerchant('batch-validation', 'Batch Validation Farm');

        $this->actingAs($merchant, 'merchant_api')
            ->postJson('/merchant-api/merchant/sanctum/resources', [
                'resource_type' => 'agricultural_batch',
                'display_name' => 'Unspecified batch',
                'category' => 'Potatoes',
            ])
            ->assertUnprocessable();
    }

    public function test_resource_listing_is_scoped_to_the_authenticated_merchant(): void
    {
        $owner = $this->makeMerchant('resource-owner', 'Owner Farm');
        $other = $this->makeMerchant('resource-other', 'Other Farm');

        SanctumScanResource::create([
            'merchant_id' => $owner->getKey(),
            'resource_type' => 'product',
            'display_name' => 'Owner product',
            'category' => 'Produce',
            'status' => 'published',
        ]);
        SanctumScanResource::create([
            'merchant_id' => $other->getKey(),
            'resource_type' => 'product',
            'display_name' => 'Other product',
            'category' => 'Produce',
            'status' => 'published',
        ]);

        $this->actingAs($owner, 'merchant_api')
            ->getJson('/merchant-api/merchant/sanctum/resources')
            ->assertOk()
            ->assertJsonCount(1, 'data.resources')
            ->assertJsonPath('data.resources.0.display_name', 'Owner product');
    }

    public function test_rotating_a_scan_reference_revokes_the_old_qr(): void
    {
        $merchant = $this->makeMerchant('rotation-farm', 'Rotation Farm');

        $created = $this->actingAs($merchant, 'merchant_api')
            ->postJson('/merchant-api/merchant/sanctum/resources', [
                'resource_type' => 'product',
                'display_name' => 'Maize flour',
                'category' => 'Food',
            ])
            ->assertCreated();

        $resourceId = $created->json('data.id');
        $oldUrl = $created->json('data.scan_url');

        $rotated = $this->actingAs($merchant, 'merchant_api')
            ->postJson('/merchant-api/merchant/sanctum/resources/' . $resourceId . '/scan-reference')
            ->assertCreated();

        $this->assertNotSame($oldUrl, $rotated->json('data.scan_url'));
        $this->get($oldUrl)->assertNotFound();
        $this->get($rotated->json('data.scan_url'))->assertOk()->assertSee('Maize flour');
    }

    public function test_archiving_a_resource_revokes_its_public_scan_reference(): void
    {
        $merchant = $this->makeMerchant('archive-farm', 'Archive Farm');

        $created = $this->actingAs($merchant, 'merchant_api')
            ->postJson('/merchant-api/merchant/sanctum/resources', [
                'resource_type' => 'product',
                'display_name' => 'Archived beans',
                'category' => 'Produce',
            ])
            ->assertCreated();

        $resourceId = $created->json('data.id');
        $scanUrl = $created->json('data.scan_url');

        $this->actingAs($merchant, 'merchant_api')
            ->deleteJson('/merchant-api/merchant/sanctum/resources/' . $resourceId)
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');

        $this->get($scanUrl)->assertNotFound();
        $this->assertSame('archived', SanctumScanResource::findOrFail($resourceId)->status);
    }

    public function test_unauthenticated_merchant_cannot_create_an_economic_resource(): void
    {
        $this->postJson('/merchant-api/merchant/sanctum/resources', [
            'resource_type' => 'product',
            'display_name' => 'No owner',
            'category' => 'Produce',
        ])->assertUnauthorized();
    }

    private function referenceFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $marker = '/scan/';

        $this->assertIsString($path);
        $this->assertStringContainsString($marker, $path);

        return substr($path, strpos($path, $marker) + strlen($marker));
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('sanctum_scan_provenance_events');
        Schema::dropIfExists('sanctum_scan_resources');
        Schema::dropIfExists('sanctum_scan_references');
        Schema::dropIfExists('merchants');
        DB::purge('sqlite');

        parent::tearDown();
    }
}
