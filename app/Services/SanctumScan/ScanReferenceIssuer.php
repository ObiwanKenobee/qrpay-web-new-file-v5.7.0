<?php

namespace App\Services\SanctumScan;

use App\Models\SanctumScanReference;
use DateTimeInterface;
use InvalidArgumentException;

class ScanReferenceIssuer
{
    public const RESOURCE_TYPES = [
        'merchant',
        'product',
        'asset',
        'agreement',
        'payment_request',
        'community_project',
        'service',
    ];

    /**
     * Issue an opaque reference for a resource.
     *
     * The plaintext reference is returned once to the caller. Only its SHA-256
     * hash is persisted, so a database read does not reveal usable scan tokens.
     *
     * @return array{reference: string, record: SanctumScanReference}
     */
    public function issue(
        string $resourceType,
        int $resourceId,
        string $displayName,
        ?string $displayDescription = null,
        ?DateTimeInterface $expiresAt = null
    ): array {
        if (! in_array($resourceType, self::RESOURCE_TYPES, true)) {
            throw new InvalidArgumentException('Unsupported Sanctum Scan resource type.');
        }

        if ($resourceId < 1) {
            throw new InvalidArgumentException('The resource ID must be a positive integer.');
        }

        $displayName = trim($displayName);

        if ($displayName === '' || strlen($displayName) > 120) {
            throw new InvalidArgumentException('The display name must contain between 1 and 120 characters.');
        }

        if ($displayDescription !== null && strlen($displayDescription) > 2000) {
            throw new InvalidArgumentException('The display description cannot exceed 2000 characters.');
        }

        if ($expiresAt !== null && $expiresAt->getTimestamp() <= now()->getTimestamp()) {
            throw new InvalidArgumentException('The expiry time must be in the future.');
        }

        // 32 cryptographically random bytes encoded as base64url (43 chars).
        $reference = 'snc_' . rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        $record = SanctumScanReference::create([
            'reference_hash' => hash('sha256', $reference),
            'schema_version' => '1.0',
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'display_name' => $displayName,
            'display_description' => $displayDescription,
            'status' => 'active',
            'expires_at' => $expiresAt,
        ]);

        return [
            'reference' => $reference,
            'record' => $record,
        ];
    }

    public function revoke(SanctumScanReference $record): SanctumScanReference
    {
        if ($record->status !== 'revoked') {
            $record->forceFill([
                'status' => 'revoked',
                'revoked_at' => now(),
            ])->save();
        }

        return $record->refresh();
    }
}
