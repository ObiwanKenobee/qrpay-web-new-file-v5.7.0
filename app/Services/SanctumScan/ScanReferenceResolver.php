<?php

namespace App\Services\SanctumScan;

use App\Models\Merchants\Merchant;
use App\Models\SanctumScanReference;
use App\Models\SanctumScanResource;

class ScanReferenceResolver
{
    /**
     * Return the public scan document, or null when the reference is unavailable.
     *
     * This resolver deliberately returns presentation-safe fields only.
     */
    public function resolve(string $reference): ?array
    {
        if (! preg_match('/\Asnc_[A-Za-z0-9_-]{43}\z/', $reference)) {
            return null;
        }

        $record = SanctumScanReference::query()
            ->where('reference_hash', hash('sha256', $reference))
            ->where('status', 'active')
            ->whereNull('revoked_at')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();

        if (! $record) {
            return null;
        }

        $resource = [
            'type' => $record->resource_type,
            'display_name' => $record->display_name,
            'description' => $record->display_description,
        ];

        if ($record->resource_type === 'merchant') {
            $merchant = $this->activeMerchant($record->resource_id);

            if (! $merchant) {
                return null;
            }

            $resource['display_name'] = $this->merchantDisplayName($merchant);
            $resource['profile'] = [
                'handle' => $merchant->username,
            ];
        } elseif (in_array($record->resource_type, ['product', 'agricultural_batch'], true)) {
            $scanResource = SanctumScanResource::query()
                ->with(['merchant', 'provenanceEvents'])
                ->whereKey($record->resource_id)
                ->where('resource_type', $record->resource_type)
                ->where('status', 'published')
                ->first();

            if (! $scanResource || ! $this->merchantIsActive($scanResource->merchant)) {
                return null;
            }

            $resource = $this->economicResourceDocument($scanResource);
        }

        return [
            'schema_version' => $record->schema_version,
            'type' => 'scan_resolution',
            'data' => [
                'resource' => $resource,
                'expires_at' => $record->expires_at?->toIso8601String(),
            ],
        ];
    }

    private function activeMerchant(int $merchantId): ?Merchant
    {
        return Merchant::query()
            ->whereKey($merchantId)
            ->where('status', true)
            ->whereNull('deleted_at')
            ->first();
    }

    private function merchantIsActive(?Merchant $merchant): bool
    {
        return $merchant !== null
            && (bool) $merchant->status
            && $merchant->deleted_at === null;
    }

    private function merchantDisplayName(Merchant $merchant): string
    {
        $displayName = trim((string) ($merchant->business_name ?: $merchant->username));

        return $displayName !== '' ? $displayName : 'Merchant';
    }

    private function economicResourceDocument(SanctumScanResource $record): array
    {
        $merchant = $record->merchant;

        $document = [
            'type' => $record->resource_type,
            'display_name' => $record->display_name,
            'description' => $record->description,
            'profile' => [
                'seller_name' => $this->merchantDisplayName($merchant),
                'seller_handle' => $merchant->username,
            ],
            'details' => [
                'category' => $record->category,
                'reference_code' => $record->reference_code,
            ],
            'provenance' => [
                'status' => 'merchant_declared',
                'label' => 'Merchant-declared information',
                'events' => $record->provenanceEvents->map(function ($event) {
                    return [
                        'type' => $event->event_type,
                        'statement' => $event->statement,
                        'location' => $event->location_label,
                        'occurred_at' => $event->occurred_at?->toIso8601String(),
                        'recorded_at' => $event->created_at?->toIso8601String(),
                        'verification_status' => $event->verification_status,
                    ];
                })->values()->all(),
            ],
        ];

        if ($record->resource_type === 'agricultural_batch') {
            $document['details'] = array_merge($document['details'], [
                'variety' => $record->variety,
                'origin' => $record->origin_label,
                'harvest_date' => $record->occurred_on?->toDateString(),
            ]);
        }

        // Quantity and unit remain merchant-private in this first public registry.
        return $document;
    }
}
