<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SanctumScanVerifierInstitution;
use App\Models\SanctumScanVerifierMembership;
use App\Services\SanctumScan\IndependentVerificationManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SanctumScanVerifierRegistryController extends Controller
{
    public function index(): JsonResponse
    {
        $items = SanctumScanVerifierInstitution::query()
            ->withCount(['memberships', 'mandates', 'attestations'])
            ->orderBy('name')
            ->get()
            ->map(fn (SanctumScanVerifierInstitution $institution) => $this->presentInstitution($institution));

        return response()->json(['data' => ['institutions' => $items]]);
    }

    public function store(Request $request, IndependentVerificationManager $manager): JsonResponse
    {
        $adminId = $this->adminId($request);
        $validated = $request->validate([
            'name' => 'required|string|max:180',
            'organization_type' => 'required|in:public_agency,research_institute,inspection_body,cooperative,nonprofit,standards_body,other',
            'country_code' => ['required', 'string', 'size:2', 'regex:/^[A-Za-z]{2}$/'],
            'registration_reference' => 'nullable|string|max:120',
            'website' => 'nullable|url|max:2048',
        ]);

        $institution = $manager->registerInstitution($adminId, $validated);

        return response()->json([
            'data' => ['institution' => $this->presentInstitution($institution)],
        ], 201);
    }

    public function inviteMember(
        Request $request,
        int $institutionId,
        IndependentVerificationManager $manager
    ): JsonResponse {
        $adminId = $this->adminId($request);
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $result = $manager->inviteMember(
            $institutionId,
            (int) $validated['user_id'],
            $adminId
        );

        if (is_string($result)) {
            return $this->error($result);
        }

        return response()->json([
            'data' => ['membership' => $this->presentMembership($result)],
        ], 201);
    }

    public function setInstitutionStatus(
        Request $request,
        int $institutionId,
        IndependentVerificationManager $manager
    ): JsonResponse {
        $adminId = $this->adminId($request);
        $validated = $request->validate([
            'status' => 'required|in:active,suspended,revoked',
        ]);

        $result = $manager->setInstitutionStatus(
            $institutionId,
            $adminId,
            $validated['status']
        );

        if (is_string($result)) {
            return $this->error($result);
        }

        return response()->json([
            'data' => ['institution' => $this->presentInstitution($result)],
        ]);
    }

    public function setMembershipStatus(
        Request $request,
        int $membershipId,
        IndependentVerificationManager $manager
    ): JsonResponse {
        $adminId = $this->adminId($request);
        $validated = $request->validate([
            'status' => 'required|in:active,suspended,revoked',
        ]);

        $result = $manager->setMembershipStatus(
            $membershipId,
            $adminId,
            $validated['status']
        );

        if (is_string($result)) {
            return $this->error($result);
        }

        return response()->json([
            'data' => ['membership' => $this->presentMembership($result)],
        ]);
    }

    public function grantMandate(
        Request $request,
        int $evidenceId,
        IndependentVerificationManager $manager
    ): JsonResponse {
        $adminId = $this->adminId($request);
        $validated = $request->validate([
            'institution_id' => 'required|integer|exists:sanctum_scan_verifier_institutions,id',
            'verification_scope' => 'required|in:document_authenticity,source_provenance,delivery_observation,quality_observation,payment_document,other',
            'mandate_reason' => 'required|string|min:20|max:2000',
            'expires_at' => 'required|date|after:now',
        ]);

        $result = $manager->grantMandate(
            $evidenceId,
            (int) $validated['institution_id'],
            $adminId,
            $validated['verification_scope'],
            $validated['mandate_reason'],
            $validated['expires_at']
        );

        if (is_string($result)) {
            return $this->error($result);
        }

        return response()->json([
            'data' => [
                'mandate' => [
                    'id' => $result->getKey(),
                    'evidence_id' => $result->evidence_id,
                    'institution_id' => $result->institution_id,
                    'institution_name' => $result->institution?->name,
                    'verification_scope' => $result->verification_scope,
                    'status' => $result->status,
                    'expires_at' => $result->expires_at?->toIso8601String(),
                ],
            ],
        ], 201);
    }

    public function setMandateStatus(
        Request $request,
        int $mandateId,
        IndependentVerificationManager $manager
    ): JsonResponse {
        $adminId = $this->adminId($request);
        $validated = $request->validate([
            'status' => 'required|in:revoked,completed',
        ]);

        $result = $manager->setMandateStatus(
            $mandateId,
            $adminId,
            $validated['status']
        );

        if (is_string($result)) {
            return $this->error($result);
        }

        return response()->json([
            'data' => [
                'mandate' => [
                    'id' => $result->getKey(),
                    'evidence_id' => $result->evidence_id,
                    'institution_id' => $result->institution_id,
                    'status' => $result->status,
                    'expires_at' => $result->expires_at?->toIso8601String(),
                ],
            ],
        ]);
    }

    private function adminId(Request $request): int
    {
        $admin = $request->user('admin');
        abort_unless($admin && $admin->status, 403);

        return (int) $admin->getKey();
    }

    private function presentInstitution(SanctumScanVerifierInstitution $institution): array
    {
        return [
            'id' => $institution->getKey(),
            'name' => $institution->name,
            'organization_type' => $institution->organization_type,
            'country_code' => $institution->country_code,
            'registration_reference' => $institution->registration_reference,
            'website' => $institution->website,
            'status' => $institution->status,
            'memberships_count' => $institution->memberships_count ?? null,
            'mandates_count' => $institution->mandates_count ?? null,
            'attestations_count' => $institution->attestations_count ?? null,
        ];
    }

    private function presentMembership(SanctumScanVerifierMembership $membership): array
    {
        return [
            'id' => $membership->getKey(),
            'institution_id' => $membership->institution_id,
            'institution_name' => $membership->institution?->name,
            'user_id' => $membership->user_id,
            'status' => $membership->status,
            'invited_at' => $membership->invited_at?->toIso8601String(),
            'accepted_at' => $membership->accepted_at?->toIso8601String(),
        ];
    }

    private function error(string $code): JsonResponse
    {
        [$status, $message] = match ($code) {
            'institution_unavailable' => [409, 'The verifier institution is not active.'],
            'institution_revoked' => [409, 'A revoked institution cannot be reactivated.'],
            'verifier_account_unavailable' => [422, 'The verifier must be an active account with verified email and phone.'],
            'membership_already_exists' => [409, 'A membership or invitation already exists for this institution and user.'],
            'membership_revoked' => [409, 'A revoked membership cannot be reactivated.'],
            'invitation_requires_user_acceptance' => [409, 'A verifier must accept their own invitation before receiving active membership.'],
            'membership_status_change_invalid' => [409, 'Only an active verifier membership can be suspended.'],
            'mandate_already_active' => [409, 'This institution already has an active mandate for the evidence.'],
            'mandate_unavailable' => [409, 'The mandate is no longer active.'],
            'mandate_expiry_invalid' => [422, 'The mandate expiry must be in the future.'],
            'invalid_verification_scope' => [422, 'The requested verification scope is not supported.'],
            default => [409, 'The verifier registry operation could not be completed.'],
        };

        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $status);
    }
}