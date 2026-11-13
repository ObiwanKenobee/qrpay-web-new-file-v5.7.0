<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SanctumScanExchangeDisputeEvidence;
use App\Services\SanctumScan\DisputeEvidenceManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SanctumScanDisputeEvidenceController extends Controller
{
    public function index(
        Request $request,
        int $exchangeId,
        DisputeEvidenceManager $evidence
    ): JsonResponse {
        [$guard, $actorId] = $this->actor($request);

        $items = $evidence->listForParticipant($exchangeId, $guard, $actorId)
            ->map(fn (SanctumScanExchangeDisputeEvidence $item) => $this->present($item));

        return response()->json([
            'data' => [
                'evidence' => $items->values()->all(),
            ],
        ]);
    }

    public function store(
        Request $request,
        int $exchangeId,
        DisputeEvidenceManager $evidence
    ): JsonResponse {
        $validated = $request->validate([
            'evidence_type' => 'required|in:proof_of_delivery,payment_receipt,quantity_record,quality_record,correspondence,other',
            'caption' => 'nullable|string|max:500',
            'file' => [
                'required',
                'file',
                'max:10240',
                'mimes:pdf,jpg,jpeg,png',
                'mimetypes:application/pdf,image/jpeg,image/png',
            ],
        ]);

        [$guard, $actorId] = $this->actor($request);
        $result = $evidence->attach(
            $exchangeId,
            $guard,
            $actorId,
            $validated['file'],
            $validated['evidence_type'],
            $validated['caption'] ?? null
        );

        if (is_string($result)) {
            return $this->error($result);
        }

        return response()->json([
            'data' => [
                'evidence' => $this->present($result),
            ],
        ], 201);
    }

    public function download(
        Request $request,
        int $exchangeId,
        int $evidenceId,
        DisputeEvidenceManager $evidence
    ): StreamedResponse|JsonResponse {
        [$guard, $actorId] = $this->actor($request);
        $result = $evidence->downloadForParticipant($exchangeId, $evidenceId, $guard, $actorId);

        return is_string($result) ? $this->error($result) : $result;
    }

    private function actor(Request $request): array
    {
        $merchant = $request->user('merchant_api');

        if ($merchant) {
            return ['merchant_api', (int) $merchant->getKey()];
        }

        $buyer = $request->user('api');

        abort_unless($buyer, 401);

        return ['api', (int) $buyer->getKey()];
    }

    private function error(string $code): JsonResponse
    {
        [$status, $message] = match ($code) {
            'dispute_not_open' => [409, 'Evidence can only be added while the dispute is open.'],
            'duplicate_evidence' => [409, 'The same file has already been submitted to this dispute.'],
            'evidence_integrity_failure' => [409, 'The stored evidence file is unavailable or its integrity check failed.'],
            'unsupported_file_type' => [422, 'Only PDF, JPEG and PNG evidence files are accepted.'],
            'file_unreadable' => [422, 'The uploaded file could not be read.'],
            'storage_failed' => [503, 'The evidence file could not be stored.'],
            default => [409, 'The evidence operation could not be completed.'],
        };

        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $status);
    }

    private function present(SanctumScanExchangeDisputeEvidence $evidence): array
    {
        $review = $evidence->review;

        return [
            'id' => $evidence->getKey(),
            'evidence_type' => $evidence->evidence_type,
            'original_name' => $evidence->original_name,
            'mime_type' => $evidence->mime_type,
            'size_bytes' => $evidence->size_bytes,
            'sha256' => $evidence->sha256,
            'caption' => $evidence->caption,
            'submitted_by' => $evidence->submitted_by_guard === 'merchant_api' ? 'merchant' : 'buyer',
            'submitted_at' => $evidence->submitted_at?->toIso8601String(),
            'verification' => $review ? [
                'status' => $review->review_status,
                'method' => $review->review_method,
                'summary' => $review->review_summary,
                'reviewed_at' => $review->reviewed_at?->toIso8601String(),
                'evidence_sha256' => $review->evidence_sha256,
            ] : [
                'status' => 'pending',
            ],
        ];
    }
}
