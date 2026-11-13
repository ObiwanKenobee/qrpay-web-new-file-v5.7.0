<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SanctumScanVerifierInstitution;
use App\Models\SanctumScanVerifierMembership;
use App\Models\SanctumScanAttestationAppeal;
use App\Models\SanctumScanVerifierCredentialReview;
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

    public function credentialReviews(int $institutionId): JsonResponse
    {
        $page = SanctumScanVerifierCredentialReview::query()
            ->with('membership')
            ->where('institution_id', $institutionId)
            ->orderByDesc('id')
            ->paginate(50);

        return response()->json([
            'data' => [
                'credential_reviews' => $page->getCollection()->map(
                    fn (SanctumScanVerifierCredentialReview $review) => [
                        'id' => $review->getKey(),
                        'institution_id' => $review->institution_id,
                        'membership_id' => $review->membership_id,
                        'verifier_user_id' => $review->membership?->user_id,
                        'credential_scope' => $review->credential_scope,
                        'credential_type' => $review->credential_type,
                        'issuer_name' => $review->issuer_name,
                        'credential_reference' => $review->credential_reference,
                        'jurisdiction' => $review->jurisdiction,
                        'source_url' => $review->source_url,
                        'review_method' => $review->review_method,
                        'review_status' => $review->review_status,
                        'review_summary' => $review->review_summary,
                        'reviewed_by_admin_id' => $review->reviewed_by_admin_id,
                        'reviewed_at' => $review->reviewed_at?->toIso8601String(),
                        'expires_at' => $review->expires_at?->toIso8601String(),
                    ]
                )->values(),
                'pagination' => [
                    'current_page' => $page->currentPage(),
                    'last_page' => $page->lastPage(),
                    'per_page' => $page->perPage(),
                    'total' => $page->total(),
                ],
            ],
        ]);
    }

    public function recordCredentialReview(
        Request $request,
        int $institutionId,
        IndependentVerificationManager $manager
    ): JsonResponse {
        $adminId = $this->adminId($request);
        $validated = $request->validate([
            'membership_id' => 'nullable|integer|exists:sanctum_scan_verifier_memberships,id',
            'credential_scope' => 'required|string|max:40',
            'credential_type' => 'required|string|max:48',
            'issuer_name' => 'required|string|max:180',
            'credential_reference' => 'required|string|max:160',
            'jurisdiction' => 'nullable|string|max:120',
            'source_url' => 'nullable|url|max:2048',
            'review_method' => 'required|in:registry_lookup,issuer_confirmation,document_review,other',
            'review_status' => 'required|in:verified,rejected,unable_to_verify',
            'review_summary' => 'required|string|min:20|max:2000',
            'expires_at' => 'nullable|date',
        ]);

        $result = $manager->recordCredentialReview(
            $institutionId,
            isset($validated['membership_id']) ? (int) $validated['membership_id'] : null,
            $adminId,
            $validated['credential_scope'],
            $validated['credential_type'],
            $validated['issuer_name'],
            $validated['credential_reference'],
            $validated['jurisdiction'] ?? null,
            $validated['source_url'] ?? null,
            $validated['review_method'],
            $validated['review_status'],
            $validated['review_summary'],
            $validated['expires_at'] ?? null
        );

        if (is_string($result)) {
            return $this->error($result);
        }

        return response()->json([
            'data' => [
                'credential_review' => [
                    'id' => $result->getKey(),
                    'institution_id' => $result->institution_id,
                    'membership_id' => $result->membership_id,
                    'credential_scope' => $result->credential_scope,
                    'credential_type' => $result->credential_type,
                    'issuer_name' => $result->issuer_name,
                    'credential_reference' => $result->credential_reference,
                    'jurisdiction' => $result->jurisdiction,
                    'review_method' => $result->review_method,
                    'review_status' => $result->review_status,
                    'reviewed_at' => $result->reviewed_at?->toIso8601String(),
                    'expires_at' => $result->expires_at?->toIso8601String(),
                ],
            ],
        ], 201);
    }

    public function appealsIndex(): JsonResponse
    {
        $page = SanctumScanAttestationAppeal::query()
            ->with(['attestation.institution', 'exchange', 'decision'])
            ->whereDoesntHave('decision')
            ->orderBy('id')
            ->paginate(50);

        return response()->json([
            'data' => [
                'appeals' => $page->getCollection()->map(fn (SanctumScanAttestationAppeal $appeal) => [
                    'id' => $appeal->getKey(),
                    'exchange_id' => $appeal->exchange_id,
                    'evidence_id' => $appeal->evidence_id,
                    'attestation_id' => $appeal->attestation_id,
                    'institution_name' => $appeal->attestation?->institution?->name,
                    'appellant_guard' => $appeal->appellant_guard,
                    'appellant_id' => $appeal->appellant_id,
                    'reason_code' => $appeal->reason_code,
                    'statement' => $appeal->statement,
                    'evidence_sha256' => $appeal->evidence_sha256,
                    'submitted_at' => $appeal->submitted_at?->toIso8601String(),
                ])->values(),
                'pagination' => [
                    'current_page' => $page->currentPage(),
                    'last_page' => $page->lastPage(),
                    'per_page' => $page->perPage(),
                    'total' => $page->total(),
                ],
            ],
        ]);
    }

    public function decideAppeal(
        Request $request,
        int $appealId,
        IndependentVerificationManager $manager
    ): JsonResponse {
        $adminId = $this->adminId($request);
        $validated = $request->validate([
            'outcome' => 'required|in:upheld,overturned,correction_recommended,dismissed',
            'decision_summary' => 'required|string|min:20|max:2000',
        ]);

        $result = $manager->adjudicateAppeal(
            $appealId,
            $adminId,
            $validated['outcome'],
            $validated['decision_summary']
        );

        if (is_string($result)) {
            return $this->error($result);
        }

        return response()->json([
            'data' => [
                'decision' => [
                    'id' => $result->getKey(),
                    'appeal_id' => $result->appeal_id,
                    'outcome' => $result->outcome,
                    'decision_summary' => $result->decision_summary,
                    'decided_at' => $result->decided_at?->toIso8601String(),
                ],
            ],
        ], 201);
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
            'invalid_credential_review' => [422, 'The credential review status or method is not supported.'],
            'invalid_credential_scope' => [422, 'The credential scope is not valid for the selected subject.'],
            'credential_subject_mismatch' => [422, 'The verifier membership does not belong to this institution.'],
            'credential_expiry_invalid' => [422, 'A verified credential review cannot expire in the past.'],
            'invalid_appeal_decision' => [422, 'The appeal decision outcome is not supported.'],
            'appeal_already_decided' => [409, 'This appeal already has an immutable platform process decision.'],
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