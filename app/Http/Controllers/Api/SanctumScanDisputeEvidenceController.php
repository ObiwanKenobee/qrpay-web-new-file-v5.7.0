<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SanctumScanExchangeDisputeEvidence;
use App\Models\SanctumScanIndependentAttestation;
use App\Services\SanctumScan\DisputeEvidenceManager;
use App\Services\SanctumScan\IndependentVerificationManager;
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

    public function appealAttestation(
        Request $request,
        int $exchangeId,
        int $evidenceId,
        int $attestationId,
        IndependentVerificationManager $manager
    ): JsonResponse {
        [$guard, $actorId] = $this->actor($request);

        $validated = $request->validate([
            'reason_code' => 'required|in:scope_disagreement,evidence_integrity,undisclosed_conflict,factual_error,methodology,other',
            'statement' => 'required|string|min:20|max:2000',
        ]);

        $result = $manager->lodgeAppeal(
            $exchangeId,
            $evidenceId,
            $attestationId,
            $guard,
            $actorId,
            $validated['reason_code'],
            $validated['statement']
        );

        if (is_string($result)) {
            return $this->error($result);
        }

        return response()->json([
            'data' => [
                'appeal' => [
                    'id' => $result->getKey(),
                    'attestation_id' => $result->attestation_id,
                    'evidence_id' => $result->evidence_id,
                    'reason_code' => $result->reason_code,
                    'evidence_sha256' => $result->evidence_sha256,
                    'submitted_at' => $result->submitted_at?->toIso8601String(),
                    'status' => $result->decision ? 'decided' : 'under_review',
                ],
            ],
        ], 201);
    }

    private function actor(Request $request): array
    {
        if ($request->is('merchant-api/merchant/*')) {
            $merchant = $request->user('merchant_api');

            abort_unless($merchant, 401);

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
            'appeal_not_authorized' => [403, 'Only a buyer or merchant on the exchange can appeal its evidence attestation.'],
            'appeal_not_found' => [404, 'The attestation does not belong to this exchange evidence item.'],
            'appeal_already_exists' => [409, 'This participant has already appealed this attestation.'],
            'appeal_evidence_mismatch' => [409, 'The attestation is not bound to the current evidence digest.'],
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
            'independent_attestations' => $evidence->attestations
                ->map(fn (SanctumScanIndependentAttestation $attestation) => $this->presentAttestation($attestation))
                ->values()
                ->all(),
            ])->values()->all(),
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

    private function presentAttestation(SanctumScanIndependentAttestation $attestation): array
    {
        $latest = $attestation->revisions->last();

        return [
            'id' => $attestation->getKey(),
            'institution_name' => $attestation->institution?->name,
            'verification_scope' => $attestation->verification_scope,
            'verification_method' => $attestation->verification_method,
            'outcome' => $attestation->outcome,
            'basis_summary' => $attestation->basis_summary,
            'evidence_sha256' => $attestation->evidence_sha256,
            'attested_at' => $attestation->attested_at?->toIso8601String(),
            'current' => [
                'status' => $latest?->revision_action === 'revoked' ? 'revoked' : 'active',
                'version_number' => $latest?->version_number ?? 1,
                'verification_scope' => $latest?->verification_scope ?? $attestation->verification_scope,
                'verification_method' => $latest?->verification_method ?? $attestation->verification_method,
                'outcome' => $latest?->outcome ?? $attestation->outcome,
                'basis_summary' => $latest?->basis_summary ?? $attestation->basis_summary,
                'recorded_at' => $latest?->recorded_at?->toIso8601String()
                    ?? $attestation->attested_at?->toIso8601String(),
            ],
            'revisions' => $attestation->revisions->map(fn ($revision) => [
                'id' => $revision->getKey(),
                'version_number' => $revision->version_number,
                'previous_revision_id' => $revision->previous_revision_id,
                'revision_action' => $revision->revision_action,
                'verification_scope' => $revision->verification_scope,
                'verification_method' => $revision->verification_method,
                'outcome' => $revision->outcome,
                'basis_summary' => $revision->basis_summary,
                'reason_code' => $revision->reason_code,
                'reason_summary' => $revision->reason_summary,
                'evidence_sha256' => $revision->evidence_sha256,
                'recorded_at' => $revision->recorded_at?->toIso8601String(),
            ])->values()->all(),
            'appeals' => $attestation->appeals->map(fn ($appeal) => [
                'id' => $appeal->getKey(),
                'reason_code' => $appeal->reason_code,
                'statement' => $appeal->statement,
                'submitted_at' => $appeal->submitted_at?->toIso8601String(),
                'decision' => $appeal->decision ? [
                    'outcome' => $appeal->decision->outcome,
                    'summary' => $appeal->decision->decision_summary,
                    'decided_at' => $appeal->decision->decided_at?->toIso8601String(),
                ] : null,
            ])->values()->all(),
        ];
    }

}
