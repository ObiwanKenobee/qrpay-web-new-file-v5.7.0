<?php

namespace Tests\Feature\SanctumScan;

use App\Services\SanctumScan\ProvenanceManifestVerifier;
use Tests\TestCase;

class ProvenanceManifestVerifierCommandTest extends TestCase
{
    public function test_artisan_command_verifies_a_saved_provenance_export(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'sanctum-provenance-');
        $this->assertNotFalse($path);

        try {
            file_put_contents($path, $this->validExport());

            $this->artisan('sanctum-scan:verify-provenance', ['file' => $path])
                ->expectsOutput('Provenance manifest verified.')
                ->expectsOutputToContain('SHA-256:')
                ->assertExitCode(0);
        } finally {
            if (is_string($path) && file_exists($path)) {
                unlink($path);
            }
        }
    }

    public function test_artisan_command_fails_for_a_modified_export(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'sanctum-provenance-');
        $this->assertNotFalse($path);

        try {
            $document = json_decode($this->validExport(), true, 512, JSON_THROW_ON_ERROR);
            $document['data']['exchange']['status'] = 'tampered';
            file_put_contents($path, json_encode($document, JSON_THROW_ON_ERROR));

            $this->artisan('sanctum-scan:verify-provenance', ['file' => $path])
                ->expectsOutput('Provenance verification failed.')
                ->expectsOutputToContain('does not match the manifest')
                ->assertExitCode(1);
        } finally {
            if (is_string($path) && file_exists($path)) {
                unlink($path);
            }
        }
    }

    public function test_artisan_command_fails_for_a_missing_file(): void
    {
        $this->artisan('sanctum-scan:verify-provenance', [
            'file' => sys_get_temp_dir() . '/sanctum-provenance-file-that-does-not-exist.json',
        ])
            ->expectsOutput('The provenance export file does not exist or is not readable.')
            ->assertExitCode(1);
    }

    private function validExport(): string
    {
        $data = [
            'exchange' => [
                'id' => 1,
                'status' => 'accepted',
            ],
            'dispute' => [
                'id' => 2,
                'category' => 'quality_concern',
                'messages' => [],
            ],
            'evidence' => [],
            'events' => [],
        ];

        $canonicalData = json_encode(
            $data,
            JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_INVALID_UTF8_SUBSTITUTE
                | JSON_PRESERVE_ZERO_FRACTION
                | JSON_THROW_ON_ERROR
        );

        return json_encode([
            'manifest' => [
                'format' => ProvenanceManifestVerifier::FORMAT,
                'schema_version' => ProvenanceManifestVerifier::SCHEMA_VERSION,
                'generated_at' => '2026-11-13T18:00:00+03:00',
                'hash_algorithm' => 'SHA-256',
                'hash_scope' => 'data',
                'content_sha256' => hash('sha256', $canonicalData),
            ],
            'data' => $data,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
