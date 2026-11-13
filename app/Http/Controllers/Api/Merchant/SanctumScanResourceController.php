<?php

namespace App\Http\Controllers\Api\Merchant;

use App\Http\Controllers\Controller;
use App\Models\SanctumScanProvenanceEvent;
use App\Models\SanctumScanReference;
use App\Models\SanctumScanResource;
use App\Services\SanctumScan\ScanReferenceIssuer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class SanctumScanResourceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $merchant = $request->user('merchant_api');

        $resources = SanctumScanResource::query()
            ->where('merchant_id', $merchant->getKey())
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(function (SanctumScanResource $resource) {
                return [
                    'id' => $resource->getKey(),
                    'type' => $resource->resource_type,
                    'display_name' => $resource->display_name,
                    'category' => $resource->category,
                    'status' => $resource->status,
                    'created_at' => $resource->created_at?->toIso8601String(),
                    'archived_at' => $resource->archived_at?->toIso8601String(),
                ];
            });

        return response()->json(['data' => ['resources' => $resources]]);
    }

    public function store(Request $request, ScanReferenceIssuer $issuer): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'resource_type' => 'required|in:product,agricultural_batch',
            'display_name' => 'required|string|max:140',
            'description' => 'nullable|string|max:2000',
            'category' => 'required|string|max:100',
            'reference_code' => 'nullable|string|max:80',
            'variety' => 'nullable|string|max:100|required_if:resource_type,agricultural_batch',
            'origin_label' => 'nullable|string|max:160',
            'occurred_on' => 'nullable|date|before_or_equal:today',
            'quantity' => 'nullable|numeric|min:0|max:999999999999.9999',
            'unit' => 'nullable|string|max:24',
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
        $validated = $validator->validated();
        $expiresAt = ! empty($validated['expires_in_days'])
            ? now()->addDays((int) $validated['expires_in_days'])
            : null;

        return $this->issueResource($merchant, $validated, $expiresAt, $issuer);
    }

    public function rotateScanReference(
        Request $request,
        int $resourceId,
        ScanReferenceIssuer $issuer
    ): JsonResponse {
        $merchant = $request->user('merchant_api');

        $resource = SanctumScanResource::query()
            ->whereKey($resourceId)
            ->where('merchant_id', $merchant->getKey())
            ->where('status', 'published')
            ->firstOrFail();

        return DB::transaction(function () use ($merchant, $resource, $issuer) {
            SanctumScanReference::query()
                ->where('resource_type', $resource->resource_type)
                ->where('resource_id', $resource->getKey())
                ->where('status', 'active')
                ->get()
                ->each(fn (SanctumScanReference $reference) => $issuer->revoke(
                    $reference,
                    'merchant_api',
                    (int) $merchant->getKey()
                ));

            $issued = $issuer->issue(
                $resource->resource_type,
                (int) $resource->getKey(),
                $resource->display_name,
                $resource->description,
                null,
                'merchant_api',
                (int) $merchant->getKey()
            );

            return $this->qrResponse($resource, $issued);
        });
    }

    public function archive(
        Request $request,
        int $resourceId,
        ScanReferenceIssuer $issuer
    ): JsonResponse {
        $merchant = $request->user('merchant_api');

        $resource = SanctumScanResource::query()
            ->whereKey($resourceId)
            ->where('merchant_id', $merchant->getKey())
            ->firstOrFail();

        return DB::transaction(function () use ($merchant, $resource, $issuer) {
            $resource->forceFill([
                'status' => 'archived',
                'archived_at' => now(),
            ])->save();

            SanctumScanReference::query()
                ->where('resource_type', $resource->resource_type)
                ->where('resource_id', $resource->getKey())
                ->where('status', 'active')
                ->get()
                ->each(fn (SanctumScanReference $reference) => $issuer->revoke(
                    $reference,
                    'merchant_api',
                    (int) $merchant->getKey()
                ));

            SanctumScanProvenanceEvent::create([
                'resource_id' => $resource->getKey(),
                'event_type' => 'resource_archived',
                'statement' => 'The merchant archived this public resource record.',
                'verification_status' => 'merchant_declared',
                'recorded_by_guard' => 'merchant_api',
                'recorded_by_id' => $merchant->getKey(),
                'created_at' => now(),
            ]);

            return response()->json([
                'data' => [
                    'id' => $resource->getKey(),
                    'status' => $resource->status,
                    'archived_at' => $resource->archived_at?->toIso8601String(),
                ],
            ]);
        });
    }

    private function issueResource(
        $merchant,
        array $validated,
        $expiresAt,
        ScanReferenceIssuer $issuer
    ): JsonResponse {
        return DB::transaction(function () use ($merchant, $validated, $expiresAt, $issuer) {
            $resource = SanctumScanResource::create([
                'merchant_id' => $merchant->getKey(),
                'resource_type' => $validated['resource_type'],
                'display_name' => trim($validated['display_name']),
                'description' => $validated['description'] ?? null,
                'category' => trim($validated['category']),
                'reference_code' => $validated['reference_code'] ?? null,
                'variety' => $validated['variety'] ?? null,
                'origin_label' => $validated['origin_label'] ?? null,
                'occurred_on' => $validated['occurred_on'] ?? null,
                'quantity' => $validated['quantity'] ?? null,
                'unit' => $validated['unit'] ?? null,
                'status' => 'published',
            ]);

            SanctumScanProvenanceEvent::create([
                'resource_id' => $resource->getKey(),
                'event_type' => 'resource_registered',
                'statement' => 'Resource details were submitted by the merchant and recorded by Sanctum Scan.',
                'verification_status' => 'merchant_declared',
                'recorded_by_guard' => 'merchant_api',
                'recorded_by_id' => $merchant->getKey(),
                'created_at' => now(),
            ]);

            if (! empty($validated['origin_label'])) {
                SanctumScanProvenanceEvent::create([
                    'resource_id' => $resource->getKey(),
                    'event_type' => 'origin_declared',
                    'statement' => 'The merchant declared this as the resource origin. This claim has not been independently verified.',
                    'location_label' => trim($validated['origin_label']),
                    'occurred_at' => ! empty($validated['occurred_on'])
                        ? Carbon::parse($validated['occurred_on'])->startOfDay()
                        : null,
                    'verification_status' => 'merchant_declared',
                    'recorded_by_guard' => 'merchant_api',
                    'recorded_by_id' => $merchant->getKey(),
                    'created_at' => now(),
                ]);
            }

            $issued = $issuer->issue(
                $resource->resource_type,
                (int) $resource->getKey(),
                $resource->display_name,
                $resource->description,
                $expiresAt,
                'merchant_api',
                (int) $merchant->getKey()
            );

            return $this->qrResponse($resource, $issued);
        });
    }

    private function qrResponse(SanctumScanResource $resource, array $issued): JsonResponse
    {
        $scanUrl = route('sanctum.scan.public', [
            'reference' => $issued['reference'],
        ]);

        $svg = QrCode::format('svg')
            ->size(280)
            ->margin(1)
            ->errorCorrection('M')
            ->generate($scanUrl);

        return response()->json([
            'data' => [
                'id' => $resource->getKey(),
                'type' => $resource->resource_type,
                'display_name' => $resource->display_name,
                'scan_url' => $scanUrl,
                'qr_image_data_uri' => 'data:image/svg+xml;base64,' . base64_encode($svg),
                'status' => $resource->status,
                'created_at' => $resource->created_at?->toIso8601String(),
                'expires_at' => $issued['record']->expires_at?->toIso8601String(),
                'provenance_status' => 'merchant_declared',
            ],
        ], 201);
    }
}
