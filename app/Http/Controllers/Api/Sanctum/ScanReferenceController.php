<?php

namespace App\Http\Controllers\Api\Sanctum;

use App\Http\Controllers\Controller;
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

        return response()->json([
            'schema_version' => $record->schema_version,
            'type' => 'scan_resolution',
            'data' => [
                'resource' => [
                    'type' => $record->resource_type,
                    'display_name' => $record->display_name,
                    'description' => $record->display_description,
                ],
                'expires_at' => $record->expires_at?->toIso8601String(),
            ],
        ]);
    }

    private function unavailable(): JsonResponse
    {
        // Keep invalid, unknown, expired and revoked references indistinguishable.
        return response()->json([
            'error' => [
                'code' => 'scan_reference_unavailable',
                'message' => 'This scan reference is invalid, expired, or unavailable.',
            ],
        ], 404);
    }
}
