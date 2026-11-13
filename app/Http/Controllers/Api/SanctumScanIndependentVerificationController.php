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
            'verification_not_authorized' => [403, 'No active mandate authorizes this account to access this evidence.'],
            'evidence_integrity_failure' => [409, 'The stored evidence file is unavailable or its integrity check failed.'],
            'verifier_conflict_of_interest' => [409, 'A dispute participant or evidence submitter cannot independently attest to this evidence.'],
            'attestation_already_recorded' => [409, 'This verifier membership has already attested to this evidence under the mandate.'],
            'invalid_attestation' => [422, 'The attestation scope, method or outcome is not supported.'],
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