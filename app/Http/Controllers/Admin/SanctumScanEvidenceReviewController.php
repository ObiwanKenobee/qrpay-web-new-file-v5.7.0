<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SanctumScanExchangeDisputeEvidence;
use App\Models\SanctumScanExchangeEvidenceReview;
use App\Services\SanctumScan\DisputeEvidenceManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SanctumScanEvidenceReviewController extends Controller
{
    public function index(DisputeEvidenceManager $evidence): JsonResponse
    {
        $page = $evidence->pendingForAdmin();
        $items = $page->getCollection()->map(function (SanctumScanExchangeDisputeEvidence $item) {
            $exchange = $item->dispute?->exchange;

            return [
                'id' => $item->getKey(),
                'dispute_id' => $item->dispute_id,
                'exchange_id' => $exchange?->getKey(),
                'dispute_category' => $item->dispute?->category,
                'merchant' => $exchange?->merchant?->username,
                'buyer' => $exchange?->buyer?->username,
                'evidence_type' => $item->evidence_type,
                'original_name' => $item->original_name,
                'mime_type' => $item->mime_type,
                'size_bytes' => $item->size_bytes,
                'sha256' => $item->sha256,
                'submitted_by' => $item->submitted_by_guard,
                'submitted_at' => $item->submitted_at?->toIso8601String(),
            ];
        })->values();

        return response()->json([
            'data' => [
                'evidence' => $items,
                'pagination' => [
                    'current_page' => $page->currentPage(),
                    'last_page' => $page->lastPage(),
                    'per_page' => $page->perPage(),
                    'total' => $page->total(),
                ],
            ],
        ]);
    }

    public function download(
        int $evidenceId,
        DisputeEvidenceManager $evidence
    ): StreamedResponse|JsonResponse {
        $result = $evidence->downloadForAdmin($evidenceId);

        return is_string($result) ? $this->error($result) : $result;
    }

    public function review(
        Request $request,
        int $evidenceId,
        DisputeEvidenceManager $evidence
    ): JsonResponse {
        $validated = $request->validate([
            'review_status' => 'required|in:verified,rejected,unable_to_verify',
            'review_method' => 'required|in:document_review,source_cross_check,field_inspection,other',
            'review_summary' => 'required|string|min:20|max:2000',
        ]);

        $admin = $request->user('admin');

        abort_unless($admin, 401);

        $result = $evidence->review(
            $evidenceId,
            (int) $admin->getKey(),
            $validated['review_status'],
            $validated['review_method'],
            $validated['review_summary']
        );

        if (is_string($result)) {
            return $this->error($result);
        }

        return response()->json([
            'data' => [
                'review' => $this->present($result),
            ],
        ], 201);
    }

    private function present(SanctumScanExchangeEvidenceReview $review): array
    {
        return [
            'id' => $review->getKey(),
            'evidence_id' => $review->evidence_id,
            'status' => $review->review_status,
            'method' => $review->review_method,
            'evidence_sha256' => $review->evidence_sha256,
            'summary' => $review->review_summary,
            'reviewed_at' => $review->reviewed_at?->toIso8601String(),
        ];
    }

    private function error(string $code): JsonResponse
    {
        [$status, $message] = match ($code) {
            'evidence_already_reviewed' => [409, 'A review decision has already been recorded for this evidence. Submit a new evidence item for a separate review.'],
            'evidence_integrity_failure' => [409, 'The stored evidence file is unavailable or its integrity check failed. No review decision was recorded.'],
            default => [409, 'The evidence review could not be completed.'],
        };

        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $status);
    }
}
