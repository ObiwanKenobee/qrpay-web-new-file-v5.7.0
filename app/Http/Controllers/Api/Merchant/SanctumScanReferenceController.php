<?php

namespace App\Http\Controllers\Api\Merchant;

use App\Http\Controllers\Controller;
use App\Models\SanctumScanReference;
use App\Services\SanctumScan\ScanReferenceIssuer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class SanctumScanReferenceController extends Controller
{
    /**
     * List references belonging to the authenticated merchant.
     *
     * Plaintext scan tokens and their hashes are never returned by this method.
     */
    public function index(Request $request): JsonResponse
    {
        $merchant = $request->user('merchant_api');

        $references = SanctumScanReference::query()
            ->where('resource_type', 'merchant')
            ->where('resource_id', $merchant->getKey())
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(function (SanctumScanReference $reference) {
                $status = $reference->status;

                if (
                    $status === 'active'
                    && $reference->expires_at !== null
                    && $reference->expires_at->isPast()
                ) {
                    $status = 'expired';
                }

                return [
                    'id' => $reference->getKey(),
                    'display_name' => $reference->display_name,
                    'status' => $status,
                    'created_at' => $reference->created_at?->toIso8601String(),
                    'expires_at' => $reference->expires_at?->toIso8601String(),
                    'revoked_at' => $reference->revoked_at?->toIso8601String(),
                ];
            });

        return response()->json([
            'data' => [
                'references' => $references,
            ],
        ]);
    }

    /**
     * Issue a merchant profile reference and return a QR SVG data URI.
     *
     * The merchant ID and public display name are always derived from the
     * authenticated merchant, never accepted from request input.
     */
    public function store(Request $request, ScanReferenceIssuer $issuer): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'expires_in_days' => 'nullable|integer|min:1|max:365',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'code' => 'validation_failed',
                    'messages' => $validator->errors()->all(),
                ],
            ], 422);
        }

        $merchant = $request->user('merchant_api');
        $displayName = trim((string) ($merchant->business_name ?: $merchant->username));

        if ($displayName === '') {
            return response()->json([
                'error' => [
                    'code' => 'merchant_profile_incomplete',
                    'message' => 'Add a business name or username before issuing a Sanctum Scan QR.',
                ],
            ], 422);
        }

        $expiresAt = $request->filled('expires_in_days')
            ? now()->addDays((int) $request->input('expires_in_days'))
            : null;

        $issued = $issuer->issue(
            'merchant',
            (int) $merchant->getKey(),
            $displayName,
            null,
            $expiresAt,
            'merchant_api',
            (int) $merchant->getKey()
        );

        $scanUrl = route('sanctum.scan.resolve', [
            'reference' => $issued['reference'],
        ]);

        $svg = QrCode::format('svg')
            ->size(260)
            ->margin(1)
            ->errorCorrection('M')
            ->generate($scanUrl);

        return response()->json([
            'data' => [
                'id' => $issued['record']->getKey(),
                'type' => 'merchant',
                'display_name' => $displayName,
                'scan_url' => $scanUrl,
                'qr_image_data_uri' => 'data:image/svg+xml;base64,' . base64_encode($svg),
                'status' => $issued['record']->status,
                'created_at' => $issued['record']->created_at?->toIso8601String(),
                'expires_at' => $issued['record']->expires_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Revoke a reference only when it belongs to the authenticated merchant.
     */
    public function destroy(
        Request $request,
        int $referenceId,
        ScanReferenceIssuer $issuer
    ): JsonResponse {
        $merchant = $request->user('merchant_api');

        $reference = SanctumScanReference::query()
            ->whereKey($referenceId)
            ->where('resource_type', 'merchant')
            ->where('resource_id', $merchant->getKey())
            ->firstOrFail();

        $issuer->revoke(
            $reference,
            'merchant_api',
            (int) $merchant->getKey()
        );

        return response()->json([
            'data' => [
                'id' => $reference->getKey(),
                'status' => $reference->status,
                'revoked_at' => $reference->revoked_at?->toIso8601String(),
            ],
        ]);
    }
}
