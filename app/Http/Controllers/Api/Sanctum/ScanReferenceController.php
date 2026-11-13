<?php

namespace App\Http\Controllers\Api\Sanctum;

use App\Http\Controllers\Controller;
use App\Models\Merchants\Merchant;
use App\Models\SanctumScanReference;
use Illuminate\Http\JsonResponse;

class ScanReferenceController extends Controller
{
    /**
     * Resolve a scan reference to its intentionally public display information.
     *
     * Resolution does not authenticate a person, authorize a payment, reveal
     * internal resource IDs, or confirm that a transaction has settled.
     */
    public function show(string $reference): JsonResponse
    {
        if (! preg_match('/\Asnc_[A-Za-z0-9_-]{43}\z/', $reference)) {
            return $this->unavailable();
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
            return $this->unavailable();
        }

        $resource = [
            'type' => $record->resource_type,
            'display_name' => $record->display_name,
            'description' => $record->display_description,
        ];

        if ($record->resource_type === 'merchant') {
            $merchant = Merchant::query()
                ->whereKey($record->resource_id)
                ->where('status', true)
                ->whereNull('deleted_at')
                ->first();

            if (! $merchant) {
                return $this->unavailable();
            }

            $displayName = trim((string) ($merchant->business_name ?: $merchant->username));

            if ($displayName !== '') {
                $resource['display_name'] = $displayName;
            }

            // The public profile is intentionally limited to a business handle.
            // Contact details, address, KYC files and wallet data are never returned.
            $resource['profile'] = [
                'handle' => $merchant->username,
            ];
        }

        return response()->json([
            'schema_version' => $record->schema_version,
            'type' => 'scan_resolution',
            'data' => [
                'resource' => $resource,
                'expires_at' => $record->expires_at?->toIso8601String(),
            ],
        ]);
    }

    private function unavailable(): JsonResponse
    {
        // Keep invalid, unknown, expired, revoked and inactive resources indistinguishable.
        return response()->json([
            'error' => [
                'code' => 'scan_reference_unavailable',
                'message' => 'This scan reference is invalid, expired, or unavailable.',
            ],
        ], 404);
    }
}
