<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SanctumScanVerifierMembership;
use App\Services\SanctumScan\IndependentVerificationManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SanctumScanIndependentVerificationController extends Controller
{
    public function memberships(
        Request $request,
        IndependentVerificationManager $manager
    ): JsonResponse {
        $user = $request->user('api');
        abort_unless($user, 401);

        $items = $manager->membershipsForUser((int) $user->getKey())->map(
            fn (SanctumScanVerifierMembership $membership) => [
                'id' => $membership->getKey(),
                'institution' => [
                    'id' => $membership->institution?->getKey(),
                    'name' => $membership->institution?->name,
                    'organization_type' => $membership->institution?->organization_type,
                    'country_code' => $membership->institution?->country_code,
                    'status' => $membership->institution?->status,
                ],
                'status' => $membership->status,
                'invited_at' => $membership->invited_at?->toIso8601String(),
                'accepted_at' => $membership->accepted_at?->toIso8601String(),
            ]
        );

        return response()->json([
            'data' => ['memberships' => $items->values()],
        ]);
    }

    public function accept(
        Request $request,
        int $membershipId,
        IndependentVerificationManager $manager
    ): JsonResponse {
        return $this->respond($request, $membershipId, true, $manager);
    }

    public function decline(
        Request $request,
        int $membershipId,
        IndependentVerificationManager $manager
    ): JsonResponse {
        return $this->respond($request, $membershipId, false, $manager);
    }

    public function download(
        Request $request,
        int $membershipId,
        int $evidenceId,
        IndependentVerificationManager $manager
    ): StreamedResponse|JsonResponse {
        $user = $request->user('api');
        abort_unless($user, 401);

        $result = $manager->downloadForVerifier(
            $membershipId,
            $evidenceId,
            (int) $user->getKey()
        );

        return is_string($result) ? $this->error($result) : $result;
    }

    public function attest(
        Request $request,
        int $membershipId,
        int $evidenceId,
        IndependentVerificationManager $manager
    ): JsonResponse {
        $user = $request->user('api');
        abort_unless($user, 401);

        $validated = $request->validate([
            'verification_scope' => 'required|in:document_authenticity,source_provenance,delivery_observation,quality_observation,payment_document,other',
            'verification_method' => 'required|in:document_review,source_cross_check,site_inspection,direct_confirmation,other',
            'outcome' => 'required|in:corroborated,not_corroborated,unable_to_assess',
            'basis_summary' => 'required|string|min:20|max:2000',
        ]);

        $result = $manager->attest(
            $membershipId,
            $evidenceId,
            (int) $user->getKey(),
            $validated['verification_scope'],
            $validated['verification_method'],
            $validated['outcome'],
            $validated['basis_summary']
        );

        if (is_string($result)) {
            return $this->error($result);
        }

        return response()->json([
            'data' => [
                'attestation' => [
                    'id' => $result->getKey(),
                    'evidence_id' => $result->evidence_id,
                    'institution_id' => $result->institution_id,
                    'institution_name' => $result->institution?->name,
                    'verification_scope' => $result->verification_scope,
                    'verification_method' => $result->verification_method,
                    'outcome' => $result->outcome,
                    'basis_summary' => $result->basis_summary,
                    'evidence_sha256' => $result->evidence_sha256,
                    'attested_at' => $result->attested_at?->toIso8601String(),
                ],
            ],
        ], 201);
    }

    public function correctAttestation(
        Request $request,
        int $membershipId,
        int $attestationId,
        IndependentVerificationManager $manager
    ): JsonResponse {
        $user = $request->user('api');
        abort_unless($user, 401);

        $validated = $request->validate([
            'verification_method' => 'required|in:document_review,source_cross_check,site_inspection,direct_confirmation,other',
            'outcome' => 'required|in:corroborated,not_corroborated,unable_to_assess',
            'basis_summary' => 'required|string|min:20|max:2000',
            'reason_code' => 'required|in:factual_error,methodology_error,new_evidence,conflict_discovered,credential_issue,evidence_integrity,other',
            'reason_summary' => 'required|string|min:20|max:2000',
        ]);

        $result = $manager->reviseAttestation(
            $membershipId,
            $attestationId,
            (int) $user->getKey(),
            'corrected',
            $validated['verification_method'],
            $validated['outcome'],
            $validated['basis_summary'],
            $validated['reason_code'],
            $validated['reason_summary']
        );

        if (is_string($result)) {
            return $this->error($result);
        }

        return $this->revisionResponse($result);
    }

    public function revokeAttestation(
        Request $request,
        int $membershipId,
        int $attestationId,
        IndependentVerificationManager $manager
    ): JsonResponse {
        $user = $request->user('api');
        abort_unless($user, 401);

        $validated = $request->validate([
            'reason_code' => 'required|in:factual_error,methodology_error,new_evidence,conflict_discovered,credential_issue,evidence_integrity,other',
            'reason_summary' => 'required|string|min:20|max:2000',
        ]);

        $result = $manager->reviseAttestation(
            $membershipId,
            $attestationId,
            (int) $user->getKey(),
            'revoked',
            null,
            null,
            null,
            $validated['reason_code'],
            $validated['reason_summary']
        );

        if (is_string($result)) {
            return $this->error($result);
        }

        return $this->revisionResponse($result);
    }

    private function revisionResponse(\\App\\Models\\SanctumScanAttestationRevision $revision): JsonResponse
    {
        return response()->json([
            'data' => [
                'revision' => [
                    'id' => $revision->getKey(),
                    'attestation_id' => $revision->attestation_id,
                    'evidence_id' => $revision->evidence_id,
                    'version_number' => $revision->version_number,
                    'revision_action' => $revision->revision_action,
                    'verification_scope' => $revision->verification_scope,
                    'verification_method' => $revision->verification_method,
                    'outcome' => $revision->outcome,
                    'basis_summary' => $revision->basis_summary,
                    'reason_code' => $revision->reason_code,
                    'reason_summary' => $revision->reason_summary,
                    'evidence_sha256' => $revision->evidence_sha256,
                    'recorded_at' => $revision->recorded_at?->toIso8601String(),
                ],
            ],
        ], 201);
    }

    public function declareConflict(
        Request $request,
        int $membershipId,
        int $evidenceId,
        IndependentVerificationManager $manager
    ): JsonResponse {
        $user = $request->user('api');
        abort_unless($user, 401);

        $validated = $request->validate([
            'has_conflict' => 'required|boolean',
            'disclosure_statement' => 'required|string|min:20|max:2000',
        ]);

        $result = $manager->declareConflict(
            $membershipId,
            $evidenceId,
            (int) $user->getKey(),
            (bool) $validated['has_conflict'],
            $validated['disclosure_statement']
        );

        if (is_string($result)) {
            return $this->error($result);
        }

        return response()->json([
            'data' => [
                'conflict_declaration' => [
                    'id' => $result->getKey(),
                    'membership_id' => $result->membership_id,
                    'mandate_id' => $result->mandate_id,
                    'has_conflict' => $result->has_conflict,
                    'declared_at' => $result->declared_at?->toIso8601String(),
                ],
            ],
        ], 201);
    }

    private function respond(
        Request $request,
        int $membershipId,
        bool $accept,
        IndependentVerificationManager $manager
    ): JsonResponse {
        $user = $request->user('api');
        abort_unless($user, 401);

        $result = $manager->respondToInvitation(
            $membershipId,
            (int) $user->getKey(),
            $accept
        );

        if (is_string($result)) {
            return $this->error($result);
        }

        return response()->json([
            'data' => [
                'membership' => [
                    'id' => $result->getKey(),
                    'institution_id' => $result->institution_id,
                    'institution_name' => $result->institution?->name,
                    'status' => $result->status,
                    'accepted_at' => $result->accepted_at?->toIso8601String(),
                ],
            ],
        ]);
    }

    private function error(string $code): JsonResponse
    {
        [$status, $message] = match ($code) {
            'invitation_unavailable' => [409, 'This invitation is no longer pending.'],
            'institution_unavailable' => [409, 'The verifier institution is not active.'],
            'verifier_account_unavailable' => [403, 'The verifier account must remain active with verified email and phone.'],
            'verification_not_authorized' => [403, 'No active mandate authorizes this account to access this evidence.'],
            'evidence_integrity_failure' => [409, 'The stored evidence file is unavailable or its integrity check failed.'],
            'verifier_conflict_of_interest' => [409, 'A dispute participant or evidence submitter cannot independently attest to this evidence.'],
            'attestation_already_recorded' => [409, 'This verifier membership has already attested to this evidence under the mandate.'],
            'invalid_attestation' => [422, 'The attestation scope, method or outcome is not supported.'],
            'institution_credential_unverified' => [403, 'The institution registration credential has not been verified or is no longer current.'],
            'verifier_credential_unverified' => [403, 'The verifier does not have a current credential review for this scope.'],
            'conflict_declaration_required' => [403, 'A current conflict-of-interest declaration is required before evidence access or attestation.'],
            'conflict_declaration_already_recorded' => [409, 'A no-conflict declaration has already been recorded for this mandate.'],
            'credential_expiry_invalid' => [422, 'A verified credential review cannot expire in the past.'],
            'invalid_credential_review' => [422, 'The credential review status or method is not supported.'],
            'invalid_credential_scope' => [422, 'The credential scope is not valid for the selected subject.'],
            'credential_subject_mismatch' => [422, 'The verifier membership does not belong to this institution.'],
            'invalid_attestation_revision' => [422, 'The revision action or supplied verification fields are not supported.'],
            'revision_not_authorized' => [403, 'Only the original verifier with the recorded membership can revise this attestation.'],
            'attestation_evidence_mismatch' => [409, 'The original evidence digest no longer matches the attestation record.'],
            'attestation_already_revoked' => [409, 'This attestation has already been withdrawn and cannot be revised further.'],
            default => [409, 'The independent verification operation could not be completed.'],
        };

        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $status);
    }
}