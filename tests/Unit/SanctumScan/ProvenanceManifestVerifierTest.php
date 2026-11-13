<?php

namespace Tests\Unit\SanctumScan;

use App\Services\SanctumScan\ProvenanceManifestVerifier;
use PHPUnit\Framework\TestCase;

class ProvenanceManifestVerifierTest extends TestCase
{
    private ProvenanceManifestVerifier $verifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->verifier = new ProvenanceManifestVerifier();
    }

    public function test_it_accepts_a_valid_manifest_and_reports_the_digest(): void
    {
        $document = $this->document();

        $result = $this->verifier->verifyJson($this->encodeDocument($document));

        $this->assertTrue($result['valid']);
        $this->assertSame([], $result['errors']);
        $this->assertContains('manifest_digest', $result['checks']);
        $this->assertSame($document['manifest']['content_sha256'], $result['content_sha256']);
    }

    public function test_it_rejects_data_that_was_changed_after_export(): void
    {
        $document = $this->document();
        $document['data']['exchange']['status'] = 'cancelled';

        $result = $this->verifier->verifyJson($this->encodeDocument($document));

        $this->assertFalse($result['valid']);
        $this->assertContains(
            'The provenance data digest does not match the manifest.',
            $result['errors']
        );
    }

    public function test_it_rejects_invalid_json_and_oversized_documents(): void
    {
        $invalidJson = $this->verifier->verifyJson('{not-json');
        $oversized = $this->verifier->verifyJson(str_repeat('x', ProvenanceManifestVerifier::MAX_BYTES + 1));

        $this->assertFalse($invalidJson['valid']);
        $this->assertStringContainsString('not valid JSON', $invalidJson['errors'][0]);
        $this->assertFalse($oversized['valid']);
        $this->assertStringContainsString('25 MiB', $oversized['errors'][0]);
    }

    public function test_it_rejects_an_unsupported_schema_version(): void
    {
        $document = $this->document();
        $document['manifest']['schema_version'] = '2.0.0';
        $document = $this->refreshDigest($document);

        $result = $this->verifier->verifyJson($this->encodeDocument($document));

        $this->assertFalse($result['valid']);
        $this->assertContains('Unsupported provenance schema version.', $result['errors']);
    }

    public function test_it_rejects_evidence_and_attestation_digest_mismatches_even_when_manifest_digest_matches(): void
    {
        $document = $this->document();
        $document['data']['evidence'][0]['independent_attestations'][] = [
            'id' => 11,
            'evidence_sha256' => str_repeat('b', 64),
            'current' => [
                'status' => 'active',
                'version_number' => 1,
            ],
            'revisions' => [],
            'reassessments' => [],
            'reassessment_of' => null,
        ];
        $document = $this->refreshDigest($document);

        $result = $this->verifier->verifyJson($this->encodeDocument($document));

        $this->assertFalse($result['valid']);
        $this->assertTrue(collect($result['errors'])->contains(
            fn (string $error) => str_contains($error, 'not bound to evidence')
        ));
    }

    public function test_it_verifies_sequential_revision_history_and_reassessment_links(): void
    {
        $document = $this->document();
        $digest = $document['data']['evidence'][0]['sha256'];

        $source = [
            'id' => 11,
            'evidence_sha256' => $digest,
            'current' => [
                'status' => 'active',
                'version_number' => 2,
            ],
            'revisions' => [[
                'id' => 21,
                'version_number' => 2,
                'previous_revision_id' => null,
                'revision_action' => 'corrected',
                'evidence_sha256' => $digest,
            ]],
            'reassessments' => [[
                'id' => 31,
                'prior_revision_id' => 21,
                'prior_version_number' => 2,
                'prior_status' => 'active',
                'reassessment_attestation_id' => 12,
                'evidence_sha256' => $digest,
            ]],
            'reassessment_of' => null,
        ];

        $target = [
            'id' => 12,
            'evidence_sha256' => $digest,
            'current' => [
                'status' => 'active',
                'version_number' => 1,
            ],
            'revisions' => [],
            'reassessments' => [],
            'reassessment_of' => [
                'id' => 31,
                'prior_attestation_id' => 11,
                'prior_revision_id' => 21,
                'prior_version_number' => 2,
                'prior_status' => 'active',
            ],
        ];

        $document['data']['evidence'][0]['independent_attestations'] = [$source, $target];
        $document = $this->refreshDigest($document);

        $result = $this->verifier->verifyJson($this->encodeDocument($document));

        $this->assertTrue($result['valid'], implode(' | ', $result['errors']));
        $this->assertContains('attestation_revision_chains', $result['checks']);
        $this->assertContains('reassessment_links', $result['checks']);
    }

    public function test_it_rejects_a_broken_reassessment_reverse_link(): void
    {
        $document = $this->document();
        $digest = $document['data']['evidence'][0]['sha256'];

        $document['data']['evidence'][0]['independent_attestations'] = [[
            'id' => 11,
            'evidence_sha256' => $digest,
            'current' => [
                'status' => 'active',
                'version_number' => 1,
            ],
            'revisions' => [],
            'reassessments' => [[
                'id' => 31,
                'prior_revision_id' => null,
                'prior_version_number' => 1,
                'prior_status' => 'active',
                'reassessment_attestation_id' => 12,
                'evidence_sha256' => $digest,
            ]],
            'reassessment_of' => null,
        ], [
            'id' => 12,
            'evidence_sha256' => $digest,
            'current' => [
                'status' => 'active',
                'version_number' => 1,
            ],
            'revisions' => [],
            'reassessments' => [],
            'reassessment_of' => [
                'id' => 31,
                'prior_attestation_id' => 99,
                'prior_revision_id' => null,
                'prior_version_number' => 1,
                'prior_status' => 'active',
            ],
        ]];

        $document = $this->refreshDigest($document);
        $result = $this->verifier->verifyJson($this->encodeDocument($document));

        $this->assertFalse($result['valid']);
        $this->assertTrue(collect($result['errors'])->contains(
            fn (string $error) => str_contains($error, 'reverse link')
        ));
    }

    private function document(): array
    {
        $digest = hash('sha256', 'private-evidence-fixture');

        return $this->refreshDigest([
            'manifest' => [
                'format' => ProvenanceManifestVerifier::FORMAT,
                'schema_version' => ProvenanceManifestVerifier::SCHEMA_VERSION,
                'generated_at' => '2026-11-13T18:00:00+03:00',
                'hash_algorithm' => 'SHA-256',
                'hash_scope' => 'data',
                'content_sha256' => str_repeat('0', 64),
            ],
            'data' => [
                'exchange' => [
                    'id' => 1,
                    'status' => 'accepted',
                ],
                'dispute' => [
                    'id' => 2,
                    'category' => 'quality_concern',
                    'messages' => [],
                ],
                'evidence' => [[
                    'id' => 3,
                    'sha256' => $digest,
                    'verification' => [
                        'status' => 'pending',
                    ],
                    'independent_attestations' => [],
                ]],
                'events' => [],
            ],
        ]);
    }

    private function refreshDigest(array $document): array
    {
        $canonicalData = json_encode(
            $document['data'],
            JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_INVALID_UTF8_SUBSTITUTE
                | JSON_PRESERVE_ZERO_FRACTION
                | JSON_THROW_ON_ERROR
        );

        $document['manifest']['content_sha256'] = hash('sha256', $canonicalData);

        return $document;
    }

    private function encodeDocument(array $document): string
    {
        return json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
