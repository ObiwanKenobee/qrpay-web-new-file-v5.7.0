<?php

namespace App\Services\SanctumScan;

use JsonException;

final class ProvenanceManifestVerifier
{
    public const FORMAT = 'sanctum-scan-participant-provenance';

    public const SCHEMA_VERSION = '1.0.0';

    public const MAX_BYTES = 26214400; // 25 MiB

    private const JSON_FLAGS = JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_INVALID_UTF8_SUBSTITUTE
        | JSON_PRESERVE_ZERO_FRACTION
        | JSON_THROW_ON_ERROR;

    /**
     * Verify a previously exported provenance JSON document without network access.
     *
     * This verifies the manifest digest and the internal evidence/revision/
     * reassessment relationships. It does not authenticate the exporter or
     * establish that the underlying claims are true.
     *
     * @return array{valid: bool, errors: array<int, string>, checks: array<int, string>, content_sha256: ?string}
     */
    public function verifyJson(string $json): array
    {
        if (strlen($json) > self::MAX_BYTES) {
            return $this->failure('The export exceeds the 25 MiB verification limit.');
        }

        try {
            $document = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            return $this->failure('The export is not valid JSON: ' . $exception->getMessage());
        }

        if (! is_array($document) || ! is_array($document['manifest'] ?? null) || ! is_array($document['data'] ?? null)) {
            return $this->failure('The export must contain manifest and data objects.');
        }

        $manifest = $document['manifest'];
        $data = $document['data'];
        $errors = [];

        if (($manifest['format'] ?? null) !== self::FORMAT) {
            $errors[] = 'Unsupported provenance manifest format.';
        }

        if (($manifest['schema_version'] ?? null) !== self::SCHEMA_VERSION) {
            $errors[] = 'Unsupported provenance schema version.';
        }

        if (($manifest['hash_algorithm'] ?? null) !== 'SHA-256' || ($manifest['hash_scope'] ?? null) !== 'data') {
            $errors[] = 'The manifest must declare SHA-256 over the data object.';
        }

        if (! is_string($manifest['generated_at'] ?? null) || trim($manifest['generated_at']) === '') {
            $errors[] = 'The manifest must contain a generated_at timestamp.';
        }

        $expectedDigest = $manifest['content_sha256'] ?? null;

        if (! is_string($expectedDigest) || ! preg_match('/\A[a-f0-9]{64}\z/', $expectedDigest)) {
            $errors[] = 'The manifest content_sha256 must be a lowercase 64-character SHA-256 digest.';
        }

        if ($errors !== []) {
            return $this->failure(...$errors);
        }

        try {
            $canonicalData = json_encode($data, self::JSON_FLAGS);
        } catch (JsonException $exception) {
            return $this->failure('The data object cannot be serialized for digest verification: ' . $exception->getMessage());
        }

        $actualDigest = hash('sha256', $canonicalData);

        if (! hash_equals($expectedDigest, $actualDigest)) {
            return [
                'valid' => false,
                'errors' => ['The provenance data digest does not match the manifest.'],
                'checks' => [],
                'content_sha256' => $actualDigest,
            ];
        }

        $errors = array_merge($errors, $this->validateData($data));

        if ($errors !== []) {
            return [
                'valid' => false,
                'errors' => $errors,
                'checks' => ['manifest_digest'],
                'content_sha256' => $actualDigest,
            ];
        }

        return [
            'valid' => true,
            'errors' => [],
            'checks' => [
                'manifest_format',
                'schema_version',
                'manifest_digest',
                'evidence_hash_binding',
                'platform_review_structure',
                'attestation_revision_chains',
                'attestation_current_projection',
                'reassessment_link_types',
                'reassessment_links',
                'event_and_message_structure',
            ],
            'content_sha256' => $actualDigest,
        ];
    }

    private function validateData(array $data): array
    {
        $errors = [];

        foreach (['exchange', 'dispute'] as $objectKey) {
            if (! is_array($data[$objectKey] ?? null) || ! $this->isPositiveInteger($data[$objectKey]['id'] ?? null)) {
                $errors[] = "The data.{$objectKey} object must contain a positive integer id.";
            }
        }

        foreach (['evidence', 'events'] as $arrayKey) {
            if (! is_array($data[$arrayKey] ?? null) || ! array_is_list($data[$arrayKey])) {
                $errors[] = "The data.{$arrayKey} field must be a JSON array.";
            }
        }

        if ($errors !== []) {
            return $errors;
        }

        $evidenceIds = [];
        foreach ($data['evidence'] as $evidenceIndex => $evidence) {
            if (! is_array($evidence) || ! $this->isPositiveInteger($evidence['id'] ?? null)) {
                $errors[] = "The evidence item at index {$evidenceIndex} has no valid id.";
                continue;
            }

            $evidenceId = (string) $evidence['id'];
            if (isset($evidenceIds[$evidenceId])) {
                $errors[] = "Evidence id {$evidenceId} appears more than once.";
            }
            $evidenceIds[$evidenceId] = true;

            $evidenceDigest = $evidence['sha256'] ?? null;
            if (! $this->isDigest($evidenceDigest)) {
                $errors[] = "Evidence {$evidenceId} has an invalid SHA-256 digest.";
                continue;
            }

            $verification = $evidence['verification'] ?? null;
            if (! is_array($verification)) {
                $errors[] = "Evidence {$evidenceId} must contain a platform verification object.";
            } else {
                $reviewStatus = $verification['status'] ?? null;
                if (! in_array($reviewStatus, ['pending', 'verified', 'rejected', 'unable_to_verify'], true)) {
                    $errors[] = "Evidence {$evidenceId} has an unsupported platform review status.";
                } elseif ($reviewStatus === 'pending') {
                    if (array_key_exists('evidence_sha256', $verification) && $verification['evidence_sha256'] !== null) {
                        $errors[] = "Pending evidence {$evidenceId} must not claim a completed review digest.";
                    }
                } else {
                    $reviewDigest = $verification['evidence_sha256'] ?? null;
                    if (! $this->isDigest($reviewDigest) || ! hash_equals($evidenceDigest, $reviewDigest)) {
                        $errors[] = "Evidence {$evidenceId} has a platform review digest mismatch.";
                    }

                    foreach (['method', 'summary', 'reviewed_at'] as $field) {
                        if (! is_string($verification[$field] ?? null) || trim($verification[$field]) === '') {
                            $errors[] = "Evidence {$evidenceId} has an incomplete platform review record.";
                            break;
                        }
                    }
                }
            }

            $attestations = $evidence['independent_attestations'] ?? null;
            if (! is_array($attestations) || ! array_is_list($attestations)) {
                $errors[] = "Evidence {$evidenceId} must contain an independent_attestations array.";
                continue;
            }

            $attestationMap = [];
            $revisionIds = [];

            foreach ($attestations as $attestationIndex => $attestation) {
                if (! is_array($attestation) || ! $this->isPositiveInteger($attestation['id'] ?? null)) {
                    $errors[] = "Evidence {$evidenceId} has an invalid attestation at index {$attestationIndex}.";
                    continue;
                }

                $attestationId = (string) $attestation['id'];
                if (isset($attestationMap[$attestationId])) {
                    $errors[] = "Attestation id {$attestationId} appears more than once for evidence {$evidenceId}.";
                }
                $attestationMap[$attestationId] = $attestation;

                foreach (['verification_scope', 'verification_method', 'outcome', 'basis_summary', 'attested_at'] as $field) {
                    if (! is_string($attestation[$field] ?? null) || trim($attestation[$field]) === '') {
                        $errors[] = "Attestation {$attestationId} is missing its original {$field} value.";
                    }
                }

                if (! $this->isDigest($attestation['evidence_sha256'] ?? null)
                    || ! hash_equals($evidenceDigest, (string) $attestation['evidence_sha256'])) {
                    $errors[] = "Attestation {$attestationId} is not bound to evidence {$evidenceId}'s digest.";
                }

                $revisions = $attestation['revisions'] ?? null;
                if (! is_array($revisions) || ! array_is_list($revisions)) {
                    $errors[] = "Attestation {$attestationId} must contain a revisions array.";
                    continue;
                }

                $expectedVersion = 2;
                $previousRevisionId = null;
                $revisionByVersion = [];

                foreach ($revisions as $revisionIndex => $revision) {
                    if (! is_array($revision)
                        || ! $this->isPositiveInteger($revision['id'] ?? null)
                        || ! $this->isPositiveInteger($revision['version_number'] ?? null)) {
                        $errors[] = "Attestation {$attestationId} has an invalid revision at index {$revisionIndex}.";
                        continue;
                    }

                    $revisionId = (string) $revision['id'];
                    if (isset($revisionIds[$revisionId])) {
                        $errors[] = "Revision id {$revisionId} appears more than once for evidence {$evidenceId}.";
                    }
                    $revisionIds[$revisionId] = true;

                    if ((int) $revision['version_number'] !== $expectedVersion) {
                        $errors[] = "Attestation {$attestationId} revision versions are not sequential.";
                    }

                    $actualPreviousId = $revision['previous_revision_id'] ?? null;
                    if ($actualPreviousId !== $previousRevisionId) {
                        $errors[] = "Attestation {$attestationId} revision {$revisionId} does not link to its predecessor.";
                    }

                    if (! in_array($revision['revision_action'] ?? null, ['corrected', 'revoked'], true)) {
                        $errors[] = "Attestation {$attestationId} revision {$revisionId} has an unsupported action.";
                    }

                    foreach (['verification_scope', 'verification_method', 'outcome', 'basis_summary', 'recorded_at'] as $field) {
                        if (! is_string($revision[$field] ?? null) || trim($revision[$field]) === '') {
                            $errors[] = "Attestation {$attestationId} revision {$revisionId} is missing its {$field} value.";
                            break;
                        }
                    }

                    if (! $this->isDigest($revision['evidence_sha256'] ?? null)
                        || ! hash_equals($evidenceDigest, (string) $revision['evidence_sha256'])) {
                        $errors[] = "Attestation {$attestationId} revision {$revisionId} is bound to a different evidence digest.";
                    }

                    if (($revision['revision_action'] ?? null) === 'revoked' && $revisionIndex !== array_key_last($revisions)) {
                        $errors[] = "Attestation {$attestationId} has a revision after terminal withdrawal.";
                    }

                    $revisionByVersion[(int) $revision['version_number']] = $revision;
                    $previousRevisionId = $revision['id'];
                    $expectedVersion++;
                }

                $current = $attestation['current'] ?? null;
                $expectedCurrentVersion = $expectedVersion - 1;
                $lastRevision = $revisions !== [] && is_array($revisions[array_key_last($revisions)])
                    ? $revisions[array_key_last($revisions)]
                    : [];
                $expectedCurrentStatus = ($lastRevision['revision_action'] ?? null) === 'revoked'
                    ? 'revoked'
                    : 'active';
                $expectedCurrentProjection = [
                    'status' => $expectedCurrentStatus,
                    'verification_scope' => $lastRevision['verification_scope'] ?? ($attestation['verification_scope'] ?? null),
                    'verification_method' => $lastRevision['verification_method'] ?? ($attestation['verification_method'] ?? null),
                    'outcome' => $lastRevision['outcome'] ?? ($attestation['outcome'] ?? null),
                    'basis_summary' => $lastRevision['basis_summary'] ?? ($attestation['basis_summary'] ?? null),
                    'recorded_at' => $lastRevision['recorded_at'] ?? ($attestation['attested_at'] ?? null),
                ];

                if (! is_array($current)
                    || ! $this->isPositiveInteger($current['version_number'] ?? null)
                    || (int) $current['version_number'] !== $expectedCurrentVersion) {
                    $errors[] = "Attestation {$attestationId} current state does not match its revision history.";
                } else {
                    foreach ($expectedCurrentProjection as $field => $expectedValue) {
                        if (($current[$field] ?? null) !== $expectedValue) {
                            $errors[] = "Attestation {$attestationId} current {$field} projection does not match its source statement.";
                        }
                    }
                }

                $attestation['_verified_revision_by_version'] = $revisionByVersion;
                $attestations[$attestationIndex] = $attestation;
            }

            // Rebuild the map after adding the private verification index above.
            $attestationMap = [];
            foreach ($attestations as $attestation) {
                if (is_array($attestation) && $this->isPositiveInteger($attestation['id'] ?? null)) {
                    $attestationMap[(string) $attestation['id']] = $attestation;
                }
            }

            foreach ($attestationMap as $attestationId => $attestation) {
                $reassessmentLinks = $attestation['reassessments'] ?? null;
                if (! is_array($reassessmentLinks) || ! array_is_list($reassessmentLinks)) {
                    $errors[] = "Attestation {$attestationId} must contain a reassessments array.";
                    continue;
                }

                foreach ($reassessmentLinks as $link) {
                    if (! is_array($link)
                        || ! $this->isPositiveInteger($link['id'] ?? null)
                        || ! $this->isPositiveInteger($link['reassessment_attestation_id'] ?? null)
                        || ! $this->isPositiveInteger($link['prior_version_number'] ?? null)
                        || ! in_array($link['prior_status'] ?? null, ['active', 'revoked'], true)) {
                        $errors[] = "Attestation {$attestationId} contains an invalid reassessment link.";
                        continue;
                    }

                    $priorRevisionId = $link['prior_revision_id'] ?? null;
                    if (((int) $link['prior_version_number'] === 1 && $priorRevisionId !== null)
                        || ((int) $link['prior_version_number'] > 1 && ! $this->isPositiveInteger($priorRevisionId))) {
                        $errors[] = "Reassessment link {$link['id']} contains an invalid prior revision reference.";
                        continue;
                    }

                    $targetId = (string) $link['reassessment_attestation_id'];
                    if ($targetId === (string) $attestationId) {
                        $errors[] = "Attestation {$attestationId} cannot reassess itself.";
                        continue;
                    }
                    $target = $attestationMap[$targetId] ?? null;
                    $reverse = is_array($target) ? ($target['reassessment_of'] ?? null) : null;

                    if (! is_array($reverse)
                        || (string) ($reverse['id'] ?? '') !== (string) $link['id']
                        || (string) ($reverse['prior_attestation_id'] ?? '') !== (string) $attestationId
                        || ($reverse['prior_revision_id'] ?? null) !== ($link['prior_revision_id'] ?? null)
                        || (int) ($reverse['prior_version_number'] ?? 0) !== (int) ($link['prior_version_number'] ?? 0)
                        || ($reverse['prior_status'] ?? null) !== ($link['prior_status'] ?? null)) {
                        $errors[] = "Reassessment link {$link['id']} does not match the target attestation's reverse link.";
                        continue;
                    }

                    if (($link['evidence_sha256'] ?? null) !== $evidenceDigest
                        || ($target['evidence_sha256'] ?? null) !== $evidenceDigest) {
                        $errors[] = "Reassessment link {$link['id']} is not bound to evidence {$evidenceId}'s digest.";
                    }

                    $this->validatePriorVersion($attestationId, $attestation, $link, $errors);
                }

                $reverse = $attestation['reassessment_of'] ?? null;
                if (is_array($reverse)) {
                    if (! $this->isPositiveInteger($reverse['id'] ?? null)
                        || ! $this->isPositiveInteger($reverse['prior_attestation_id'] ?? null)
                        || ! $this->isPositiveInteger($reverse['prior_version_number'] ?? null)
                        || ! in_array($reverse['prior_status'] ?? null, ['active', 'revoked'], true)) {
                        $errors[] = "Attestation {$attestationId} contains an invalid reverse reassessment link.";
                        continue;
                    }

                    $priorRevisionId = $reverse['prior_revision_id'] ?? null;
                    if (((int) $reverse['prior_version_number'] === 1 && $priorRevisionId !== null)
                        || ((int) $reverse['prior_version_number'] > 1 && ! $this->isPositiveInteger($priorRevisionId))) {
                        $errors[] = "Attestation {$attestationId} contains an invalid reverse prior revision reference.";
                        continue;
                    }

                    $sourceId = (string) $reverse['prior_attestation_id'];
                    if ($sourceId === (string) $attestationId) {
                        $errors[] = "Attestation {$attestationId} cannot reassess itself.";
                        continue;
                    }
                    $source = $attestationMap[$sourceId] ?? null;
                    if (! is_array($source)) {
                        $errors[] = "Attestation {$attestationId} references a prior attestation that is absent from this evidence export.";
                        continue;
                    }

                    $linked = false;
                    foreach (($source['reassessments'] ?? []) as $link) {
                        if (is_array($link) && (string) ($link['reassessment_attestation_id'] ?? '') === (string) $attestationId) {
                            $linked = true;
                            break;
                        }
                    }

                    if (! $linked) {
                        $errors[] = "Attestation {$attestationId} has no matching source-side reassessment link.";
                    }
                }
            }
        }

        $eventIds = [];
        foreach ($data['events'] as $eventIndex => $event) {
            if (! is_array($event)
                || ! $this->isPositiveInteger($event['id'] ?? null)
                || ! is_string($event['type'] ?? null)
                || trim($event['type']) === ''
                || ! is_string($event['actor_type'] ?? null)
                || trim($event['actor_type']) === ''
                || ! is_string($event['recorded_at'] ?? null)
                || trim($event['recorded_at']) === '') {
                $errors[] = "The event at index {$eventIndex} is missing required provenance fields.";
                continue;
            }

            $eventId = (string) $event['id'];
            if (isset($eventIds[$eventId])) {
                $errors[] = "Event id {$eventId} appears more than once.";
            }
            $eventIds[$eventId] = true;
        }

        $messages = $data['dispute']['messages'] ?? null;
        if (! is_array($messages) || ! array_is_list($messages)) {
            $errors[] = 'The dispute must contain a messages array.';
        } else {
            $messageIds = [];
            foreach ($messages as $messageIndex => $message) {
                if (! is_array($message)
                    || ! $this->isPositiveInteger($message['id'] ?? null)
                    || ! is_string($message['type'] ?? null)
                    || trim($message['type']) === ''
                    || ! is_string($message['body'] ?? null)
                    || ! is_string($message['actor_type'] ?? null)
                    || trim($message['actor_type']) === ''
                    || ! is_string($message['recorded_at'] ?? null)
                    || trim($message['recorded_at']) === '') {
                    $errors[] = "The dispute message at index {$messageIndex} is missing required provenance fields.";
                    continue;
                }

                $messageId = (string) $message['id'];
                if (isset($messageIds[$messageId])) {
                    $errors[] = "Dispute message id {$messageId} appears more than once.";
                }
                $messageIds[$messageId] = true;
            }
        }

        return $errors;
    }

    private function validatePriorVersion(string $attestationId, array $attestation, array $link, array &$errors): void
    {
        $versionValue = $link['prior_version_number'] ?? null;
        $revisionId = $link['prior_revision_id'] ?? null;

        if (! $this->isPositiveInteger($versionValue)) {
            $errors[] = "Reassessment link {$link['id']} has an invalid prior version number.";
            return;
        }

        $version = (int) $versionValue;

        if ($version === 1) {
            if ($revisionId !== null) {
                $errors[] = "Reassessment link {$link['id']} has a revision reference for original version 1.";
                return;
            }
            $expectedStatus = 'active';
        } else {
            if (! $this->isPositiveInteger($revisionId)) {
                $errors[] = "Reassessment link {$link['id']} has an invalid prior revision id.";
                return;
            }

            $revisionByVersion = $attestation['_verified_revision_by_version'] ?? [];
            $revision = $revisionByVersion[$version] ?? null;

            if (! is_array($revision) || (string) ($revision['id'] ?? '') !== (string) $revisionId) {
                $errors[] = "Reassessment link {$link['id']} references a revision that does not match attestation {$attestationId}'s history.";
                return;
            }

            $expectedStatus = ($revision['revision_action'] ?? null) === 'revoked' ? 'revoked' : 'active';
        }

        if (($link['prior_status'] ?? null) !== $expectedStatus) {
            $errors[] = "Reassessment link {$link['id']} records an incorrect prior status.";
        }
    }

    private function isPositiveInteger(mixed $value): bool
    {
        return (is_int($value) || (is_string($value) && ctype_digit($value)))
            && (int) $value > 0;
    }

    private function isDigest(mixed $value): bool
    {
        return is_string($value) && (bool) preg_match('/\A[a-f0-9]{64}\z/', $value);
    }

    private function failure(string ...$errors): array
    {
        return [
            'valid' => false,
            'errors' => $errors,
            'checks' => [],
            'content_sha256' => null,
        ];
    }
}
