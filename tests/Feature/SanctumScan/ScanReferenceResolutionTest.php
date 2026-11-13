<?php

namespace Tests\Feature\SanctumScan;

use App\Services\SanctumScan\ScanReferenceIssuer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

class ScanReferenceResolutionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Isolate this feature slice from the inherited application's legacy migrations.
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.prefix' => '',
        ]);
        DB::purge('sqlite');

        $migration = require base_path('database/migrations/2026_11_13_130000_create_sanctum_scan_references_table.php');
        $migration->up();

        // The legacy maintenance middleware assumes a settings row exists.
        // These tests cover the resolver, not that unrelated middleware.
        $this->withoutMiddleware();
    }

    public function test_it_resolves_an_active_reference_to_public_metadata_only(): void
    {
        $issued = app(ScanReferenceIssuer::class)->issue(
            'merchant',
            845,
            'Mosop Farm Supplies',
            'Agricultural inputs and farm supplies.'
        );

        $response = $this->getJson('/api/sanctum/v1/scan/' . $issued['reference']);

        $response->assertOk()
            ->assertJsonPath('schema_version', '1.0')
            ->assertJsonPath('type', 'scan_resolution')
            ->assertJsonPath('data.resource.type', 'merchant')
            ->assertJsonPath('data.resource.display_name', 'Mosop Farm Supplies')
            ->assertJsonPath('data.resource.description', 'Agricultural inputs and farm supplies.')
            ->assertJsonPath('data.expires_at', null);

        $this->assertArrayNotHasKey('id', $response->json('data.resource'));
        $this->assertArrayNotHasKey('resource_id', $response->json('data.resource'));
        $this->assertStringNotContainsString($issued['reference'], $response->getContent());
    }

    public function test_it_stores_only_a_hash_of_the_plaintext_reference(): void
    {
        $issued = app(ScanReferenceIssuer::class)->issue('product', 123, 'Potato batch');

        $this->assertSame(
            hash('sha256', $issued['reference']),
            $issued['record']->reference_hash
        );
        $this->assertStringNotContainsString(
            $issued['reference'],
            json_encode($issued['record']->getAttributes(), JSON_THROW_ON_ERROR)
        );
    }

    public function test_it_does_not_resolve_an_expired_reference(): void
    {
        $issued = app(ScanReferenceIssuer::class)->issue(
            'asset',
            24,
            'Community water pump',
            null,
            now()->addHour()
        );

        $issued['record']->forceFill(['expires_at' => now()->subSecond()])->save();

        $this->getJson('/api/sanctum/v1/scan/' . $issued['reference'])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'scan_reference_unavailable');
    }

    public function test_it_does_not_resolve_a_revoked_reference(): void
    {
        $issuer = app(ScanReferenceIssuer::class);
        $issued = $issuer->issue('agreement', 44, 'Produce purchase agreement');

        $issuer->revoke($issued['record']);

        $this->getJson('/api/sanctum/v1/scan/' . $issued['reference'])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'scan_reference_unavailable');
    }

    public function test_unknown_and_malformed_references_have_the_same_public_error(): void
    {
        $unknown = 'snc_' . str_repeat('A', 43);

        $unknownResponse = $this->getJson('/api/sanctum/v1/scan/' . $unknown);
        $malformedResponse = $this->getJson('/api/sanctum/v1/scan/not-a-reference');

        $unknownResponse->assertNotFound()
            ->assertExactJson($malformedResponse->json());
        $malformedResponse->assertNotFound()
            ->assertJsonPath('error.code', 'scan_reference_unavailable');
    }

    public function test_issuer_rejects_unsupported_resource_types(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(ScanReferenceIssuer::class)->issue('wallet_balance', 1, 'Private balance');
    }

    public function test_issuer_rejects_an_expiry_time_in_the_past(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(ScanReferenceIssuer::class)->issue(
            'merchant',
            1,
            'Test merchant',
            null,
            now()->subMinute()
        );
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('sanctum_scan_references');
        DB::purge('sqlite');

        parent::tearDown();
    }
}
