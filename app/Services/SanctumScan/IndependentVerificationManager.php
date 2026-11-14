<?php

namespace App\Services\SanctumScan;

use App\Models\SanctumScanEvidenceVerificationMandate;
use App\Models\SanctumScanExchangeDisputeEvidence;
use App\Models\SanctumScanExchange;
use App\Models\SanctumScanIndependentAttestation;
use App\Models\SanctumScanVerifierInstitution;
use App\Models\SanctumScanVerifierMembership;
use App\Models\SanctumScanVerifierRegistryEvent;
use App\Models\SanctumScanVerifierCredentialReview;
use App\Models\SanctumScanVerifierConflictDeclaration;
use App\Models\SanctumScanAttestationAppeal;
use App\Models\SanctumScanAttestationAppealDecision;
use App\Models\SanctumScanAttestationRevision;
use App\Models\SanctumScanAttestationReassessment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IndependentVerificationManager
{
    public const SCOPES = [
        'document_authenticity',
        'source_provenance',
        'delivery_observation',
        'quality_observation',
        'payment_document',
        'other',
    ];

    public const METHODS = [
        'document_review',
        'source_cross_check',
        'site_inspection',
        'direct_confirmation',
        'other',
    ];

    public const OUTCOMES = [
        'corroborated',
        'not_corroborated',
        'unable_to_assess',
    ];

    public function __construct(
        private readonly DisputeEvidenceManager $evidence,
        private readonly ExchangeEventRecorder $events
    ) {
    }

    public function registerInstitution(int $adminId, array $attributes): SanctumScanVerifierInstitution
    {
        return DB::transaction(function () use ($adminId, $attributes) {
            $institution = SanctumScanVerifierInstitution::create([
                'name' => trim($attributes['name']),
                'organization_type' => $attributes['organization_type'],
                'country_code' => strtoupper($attributes['country_code']),
                'registration_reference' => $attributes['registration_reference'] ?? null,
                'website' => $attributes['website'] ?? null,
                'status' => 'active',
                'created_by_admin_id' => $adminId,
            ]);

            $this->record('institution', (int) $institution->getKey(), 'institution_registered', 'admin', $adminId, [
                'organization_type' => $institution->organization_type,
                'country_code' => $institution->country_code,
            ]);

            return $institution;
        });
    }

    public function inviteMember(int $institutionId, int $userId, int $adminId): SanctumScanVerifierMembership|string
    {
        return DB::transaction(function () use ($institutionId, $userId, $adminId) {
            $institution = SanctumScanVerifierInstitution::query()
                ->whereKey($institutionId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($institution->status !== 'active') {
                return 'institution_unavailable';
            }

            $eligible = User::query()
                ->whereKey($userId)
                ->where('status', true)
                ->where('email_verified', true)
                ->where('sms_verified', true)
                ->exists();

            if (! $eligible) {
                return 'verifier_account_unavailable';
            }

            $membership = SanctumScanVerifierMembership::query()
                ->where('institution_id', $institutionId)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if ($membership && $membership->status !== 'declined') {
                return $membership->status === 'revoked'
                    ? 'membership_revoked'
                    : 'membership_already_exists';
            }

            if (! $membership) {
                $membership = SanctumScanVerifierMembership::create([
                    'institution_id' => $institutionId,
                    'user_id' => $userId,
                    'status' => 'invited',
                    'invited_by_admin_id' => $adminId,
                    'invited_at' => now(),
                ]);
            } else {
                $membership->forceFill([
                    'status' => 'invited',
                    'invited_by_admin_id' => $adminId,
                    'invited_at' => now(),
                    'accepted_at' => null,
                ])->save();
            }

            $this->record('membership', (int) $membership->getKey(), 'member_invited', 'admin', $adminId, [
                'institution_id' => $institutionId,
                'user_id' => $userId,
            ]);

            return $membership->fresh(['institution']);
        });
    }

    public function membershipsForUser(int $userId)
    {
        return SanctumScanVerifierMembership::query()
            ->with('institution')
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->get();
    }

    public function respondToInvitation(
        int $membershipId,
        int $userId,
        bool $accept
    ): SanctumScanVerifierMembership|string {
        return DB::transaction(function () use ($membershipId, $userId, $accept) {
            $membership = SanctumScanVerifierMembership::query()
                ->with('institution')
                ->whereKey($membershipId)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($membership->status !== 'invited') {
                return 'invitation_unavailable';
            }

            if ($membership->institution->status !== 'active') {
                return 'institution_unavailable';
            }

            if ($accept && ! User::query()
                ->whereKey($userId)
                ->where('status', true)
                ->where('email_verified', true)
                ->where('sms_verified', true)
                ->exists()) {
                return 'verifier_account_unavailable';
            }

            $membership->forceFill([
                'status' => $accept ? 'active' : 'declined',
                'accepted_at' => $accept ? now() : null,
            ])->save();

            $this->record(
                'membership',
                (int) $membership->getKey(),
                $accept ? 'invitation_accepted' : 'invitation_declined',
                'api',
                $userId,
                ['institution_id' => $membership->institution_id]
            );

            return $membership->fresh(['institution']);
        });
    }

    public function setInstitutionStatus(
        int $institutionId,
        int $adminId,
        string $status
    ): SanctumScanVerifierInstitution|string {
        return DB::transaction(function () use ($institutionId, $adminId, $status) {
            $institution = SanctumScanVerifierInstitution::query()
                ->whereKey($institutionId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($institution->status === 'revoked' && $status !== 'revoked') {
                return 'institution_revoked';
            }

            if ($institution->status === $status) {
                return $institution;
            }

            $previousStatus = $institution->status;
            $institution->forceFill(['status' => $status])->save();

            $this->record('institution', $institutionId, 'institution_status_changed', 'admin', $adminId, [
                'from' => $previousStatus,
                'to' => $status,
            ]);

            if ($status !== 'active') {
                $mandates = SanctumScanEvidenceVerificationMandate::query()
                    ->where('institution_id', $institutionId)
                    ->where('status', 'active')
                    ->lockForUpdate()
                    ->get();

                foreach ($mandates as $mandate) {
                    $mandate->forceFill([
                        'status' => 'revoked',
                        'revoked_by_admin_id' => $adminId,
                        'revoked_at' => now(),
                    ])->save();

                    $this->record(
                        'mandate',
                        (int) $mandate->getKey(),
                        'mandate_revoked_with_institution',
                        'admin',
                        $adminId,
                        ['institution_id' => $institutionId]
                    );
                }
            }

            return $institution->fresh();
        });
    }

    public function setMembershipStatus(
        int $membershipId,
        int $adminId,
        string $status
    ): SanctumScanVerifierMembership|string {
        return DB::transaction(function () use ($membershipId, $adminId, $status) {
            $membership = SanctumScanVerifierMembership::query()
                ->with('institution')
                ->whereKey($membershipId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($membership->status === 'revoked') {
                return 'membership_revoked';
            }

            if ($membership->status === $status) {
                return $membership;
            }

            if ($status === 'active' && ($membership->status !== 'suspended' || ! $membership->accepted_at)) {
                return 'invitation_requires_user_acceptance';
            }

            if ($status === 'suspended' && $membership->status !== 'active') {
                return 'membership_status_change_invalid';
            }

            if ($status === 'active' && $membership->institution->status !== 'active') {
                return 'institution_unavailable';
            }

            $previousStatus = $membership->status;
            $membership->forceFill([
                'status' => $status,
            ])->save();

            $this->record('membership', $membershipId, 'membership_status_changed', 'admin', $adminId, [
                'from' => $previousStatus,
                'to' => $status,
            ]);

            return $membership->fresh(['institution']);
        });
    }

    public function grantMandate(
        int $evidenceId,
        int $institutionId,
        int $adminId,
        string $scope,
        string $reason,
        string $expiresAt
    ): SanctumScanEvidenceVerificationMandate|string {
        if (! in_array($scope, self::SCOPES, true)) {
            return 'invalid_verification_scope';
        }

        if (\Illuminate\Support\Carbon::parse($expiresAt)->isPast()) {
            return 'mandate_expiry_invalid';
        }

        return DB::transaction(function () use (
            $evidenceId,
            $institutionId,
            $adminId,
            $scope,
            $reason,
            $expiresAt
        ) {
            SanctumScanExchangeDisputeEvidence::query()
                ->whereKey($evidenceId)
                ->firstOrFail();

            $institution = SanctumScanVerifierInstitution::query()
                ->whereKey($institutionId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($institution->status !== 'active') {
                return 'institution_unavailable';
            }

            $activeMandate = SanctumScanEvidenceVerificationMandate::query()
                ->where('evidence_id', $evidenceId)
                ->where('institution_id', $institutionId)
                ->where('status', 'active')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            if ($activeMandate) {
                return 'mandate_already_active';
            }

            // Each grant is a new record so prior attestations retain the exact mandate
            // scope and expiry under which they were issued.
            $mandate = SanctumScanEvidenceVerificationMandate::create([
                'evidence_id' => $evidenceId,
                'institution_id' => $institutionId,
                'verification_scope' => $scope,
                'mandate_reason' => trim($reason),
                'status' => 'active',
                'mandated_by_admin_id' => $adminId,
                'expires_at' => $expiresAt,
            ]);

            $this->record('mandate', (int) $mandate->getKey(), 'mandate_granted', 'admin', $adminId, [
                'evidence_id' => $evidenceId,
                'institution_id' => $institutionId,
                'verification_scope' => $scope,
                'expires_at' => $expiresAt,
            ]);

            return $mandate->fresh(['institution']);
        });
    }

    public function setMandateStatus(
        int $mandateId,
        int $adminId,
        string $status
    ): SanctumScanEvidenceVerificationMandate|string {
        return DB::transaction(function () use ($mandateId, $adminId, $status) {
            $mandate = SanctumScanEvidenceVerificationMandate::query()
                ->whereKey($mandateId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($mandate->status !== 'active') {
                return 'mandate_unavailable';
            }

            $mandate->forceFill([
                'status' => $status,
                'revoked_by_admin_id' => $status === 'revoked' ? $adminId : null,
                'revoked_at' => $status === 'revoked' ? now() : null,
            ])->save();

            $this->record(
                'mandate',
                $mandateId,
                $status === 'revoked' ? 'mandate_revoked' : 'mandate_completed',
                'admin',
                $adminId,
                [
                    'evidence_id' => $mandate->evidence_id,
                    'institution_id' => $mandate->institution_id,
                ]
            );

            return $mandate->fresh();
        });
    }

    public function downloadForVerifier(
        int $membershipId,
        int $evidenceId,
        int $userId
    ): StreamedResponse|string {
        $mandate = $this->authorizedMandate($membershipId, $evidenceId, $userId);

        if (! $mandate) {
            return 'verification_not_authorized';
        }

        $readinessIssue = $this->verificationReadinessIssue($mandate, $membershipId, true);

        if ($readinessIssue !== null) {
            return $readinessIssue;
        }

        $evidence = SanctumScanExchangeDisputeEvidence::query()
            ->whereKey($evidenceId)
            ->firstOrFail();

        if (! $this->evidence->fileIntegrityIsValid($evidence)) {
            return 'evidence_integrity_failure';
        }

        return Storage::disk('local')->download(
            $evidence->storage_path,
            $evidence->original_name,
            [
                'Content-Type' => $evidence->mime_type,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ]
        );
    }

    public function attest(
        int $membershipId,
        int $evidenceId,
        int $userId,
        string $scope,
        string $method,
        string $outcome,
        string $summary
    ): SanctumScanIndependentAttestation|string {
        if (
            ! in_array($scope, self::SCOPES, true) ||
            ! in_array($method, self::METHODS, true) ||
            ! in_array($outcome, self::OUTCOMES, true)
        ) {
            return 'invalid_attestation';
        }

        return DB::transaction(function () use (
            $membershipId,
            $evidenceId,
            $userId,
            $scope,
            $method,
            $outcome,
            $summary
        ) {
            $mandate = $this->authorizedMandate($membershipId, $evidenceId, $userId, $scope, true);

            if (! $mandate) {
                return 'verification_not_authorized';
            }

            $evidence = SanctumScanExchangeDisputeEvidence::query()
                ->with('dispute.exchange')
                ->whereKey($evidenceId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $this->evidence->fileIntegrityIsValid($evidence)) {
                return 'evidence_integrity_failure';
            }

            $exchange = $evidence->dispute?->exchange;

            if (
                ($exchange && (int) $exchange->buyer_user_id === $userId) ||
                ($evidence->submitted_by_guard === 'api' && (int) $evidence->submitted_by_id === $userId)
            ) {
                return 'verifier_conflict_of_interest';
            }

            $readinessIssue = $this->verificationReadinessIssue($mandate, $membershipId, true);

            if ($readinessIssue !== null) {
                return $readinessIssue;
            }

            if (SanctumScanIndependentAttestation::query()
                ->where('mandate_id', $mandate->getKey())
                ->where('membership_id', $membershipId)
                ->lockForUpdate()
                ->first()) {
                return 'attestation_already_recorded';
            }

            $attestation = SanctumScanIndependentAttestation::create([
                'mandate_id' => $mandate->getKey(),
                'evidence_id' => $evidenceId,
                'institution_id' => $mandate->institution_id,
                'membership_id' => $membershipId,
                'verifier_user_id' => $userId,
                'evidence_sha256' => $evidence->sha256,
                'verification_scope' => $scope,
                'verification_method' => $method,
                'outcome' => $outcome,
                'basis_summary' => trim($summary),
                'attested_at' => now(),
                'created_at' => now(),
            ]);

            $this->record('attestation', (int) $attestation->getKey(), 'attestation_recorded', 'api', $userId, [
                'mandate_id' => $mandate->getKey(),
                'evidence_id' => $evidenceId,
                'institution_id' => $mandate->institution_id,
                'evidence_sha256' => $evidence->sha256,
                'outcome' => $outcome,
            ]);

            if ($exchange) {
                $eventStatus = match ($outcome) {
                    'corroborated' => 'external_corroborated',
                    'not_corroborated' => 'external_not_corroborated',
                    default => 'external_unable_to_assess',
                };

                $this->events->record(
                    $exchange,
                    'external_verifier_attestation_recorded',
                    'An authorized verifier recorded an evidence-specific institutional attestation.',
                    'api',
                    $userId,
                    $eventStatus,
                    [
                        'attestation_id' => $attestation->getKey(),
                        'mandate_id' => $mandate->getKey(),
                        'institution_id' => $mandate->institution_id,
                        'membership_id' => $membershipId,
                        'evidence_id' => $evidenceId,
                        'evidence_sha256' => $evidence->sha256,
                        'verification_scope' => $scope,
                        'verification_method' => $method,
                        'outcome' => $outcome,
                    ]
                );
            }

            return $attestation->fresh(['institution']);
        });
    }



    /**
     * Record a fresh institutional assessment that is explicitly linked to an
     * earlier attestation. This never edits or inherits the earlier issuer's record.
     */
    public function reassessAttestation(
        int $membershipId,
        int $priorAttestationId,
        int $userId,
        string $method,
        string $outcome,
        string $summary,
        string $reasonSummary
    ): SanctumScanAttestationReassessment|string {
        if (
            ! in_array($method, self::METHODS, true) ||
            ! in_array($outcome, self::OUTCOMES, true) ||
            trim($summary) === '' ||
            trim($reasonSummary) === ''
        ) {
            return 'invalid_attestation_reassessment';
        }

        return DB::transaction(function () use (
            $membershipId,
            $priorAttestationId,
            $userId,
            $method,
            $outcome,
            $summary,
            $reasonSummary
        ) {
            // Use the same lock order as attestation corrections and governance writers:
            // institution -> membership -> source attestation -> mandate.
            // Read only the institution reference first; the source row itself is
            // locked only after the governance rows, avoiding a correction/reassessment
            // lock inversion.
            $priorReference = SanctumScanIndependentAttestation::query()
                ->select(['id', 'institution_id'])
                ->whereKey($priorAttestationId)
                ->firstOrFail();

            $institution = SanctumScanVerifierInstitution::query()
                ->whereKey($priorReference->institution_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($institution->status !== 'active') {
                return 'verification_not_authorized';
            }

            $membership = SanctumScanVerifierMembership::query()
                ->whereKey($membershipId)
                ->where('user_id', $userId)
                ->where('institution_id', $institution->getKey())
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if (! $membership) {
                return 'reassessment_not_authorized';
            }

            $prior = SanctumScanIndependentAttestation::query()
                ->with('evidence.dispute.exchange')
                ->whereKey($priorAttestationId)
                ->where('institution_id', $institution->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (
                (int) $membership->getKey() === (int) $prior->membership_id ||
                (int) $userId === (int) $prior->verifier_user_id
            ) {
                return 'reassessment_not_authorized';
            }

            $mandate = $this->authorizedMandate(
                $membershipId,
                (int) $prior->evidence_id,
                $userId,
                $prior->verification_scope,
                true
            );

            if (! $mandate) {
                return 'verification_not_authorized';
            }

            if ((int) $mandate->getKey() === (int) $prior->mandate_id) {
                return 'reassessment_requires_new_mandate';
            }

            if (
                ! $prior->evidence ||
                (int) $prior->evidence->getKey() !== (int) $prior->evidence_id ||
                $prior->evidence->sha256 !== $prior->evidence_sha256
            ) {
                return 'attestation_evidence_mismatch';
            }

            $priorRevision = SanctumScanAttestationRevision::query()
                ->where('attestation_id', $priorAttestationId)
                ->orderByDesc('version_number')
                ->first();

            // The prior issuer's current revision is captured as the exact subject
            // of this reassessment. A withdrawal remains visible and is not undone.
            $priorVersion = $priorRevision?->version_number ?? 1;
            $priorStatus = $priorRevision?->revision_action === 'revoked' ? 'revoked' : 'active';

            $newAttestation = $this->attest(
                $membershipId,
                (int) $prior->evidence_id,
                $userId,
                $prior->verification_scope,
                $method,
                $outcome,
                $summary
            );

            if (is_string($newAttestation)) {
                return $newAttestation;
            }

            $link = SanctumScanAttestationReassessment::create([
                'prior_attestation_id' => $priorAttestationId,
                'prior_revision_id' => $priorRevision?->getKey(),
                'reassessment_attestation_id' => $newAttestation->getKey(),
                'evidence_id' => $prior->evidence_id,
                'institution_id' => $prior->institution_id,
                'reassessed_by_membership_id' => $membershipId,
                'reassessed_by_user_id' => $userId,
                'prior_version_number' => $priorVersion,
                'prior_status' => $priorStatus,
                'evidence_sha256' => $prior->evidence_sha256,
                'reason_summary' => trim($reasonSummary),
                'recorded_at' => now(),
                'created_at' => now(),
            ]);

            $this->record(
                'attestation_reassessment',
                (int) $link->getKey(),
                'attestation_reassessed',
                'api',
                $userId,
                [
                    'prior_attestation_id' => $priorAttestationId,
                    'prior_revision_id' => $priorRevision?->getKey(),
                    'prior_version_number' => $priorVersion,
                    'prior_status' => $priorStatus,
                    'reassessment_attestation_id' => $newAttestation->getKey(),
                    'evidence_id' => $prior->evidence_id,
                    'institution_id' => $prior->institution_id,
                    'membership_id' => $membershipId,
                    'evidence_sha256' => $prior->evidence_sha256,
                ]
            );

            $exchange = $prior->evidence?->dispute?->exchange;

            if ($exchange) {
                $this->events->record(
                    $exchange,
                    'external_verifier_attestation_reassessed',
                    'A different authorized member of the same institution recorded a fresh, separately attributable assessment of an earlier attestation.',
                    'api',
                    $userId,
                    'external_reassessment_recorded',
                    [
                        'reassessment_id' => $link->getKey(),
                        'prior_attestation_id' => $priorAttestationId,
                        'prior_revision_id' => $priorRevision?->getKey(),
                        'prior_version_number' => $priorVersion,
                        'prior_status' => $priorStatus,
                        'reassessment_attestation_id' => $newAttestation->getKey(),
                        'evidence_id' => $prior->evidence_id,
                        'evidence_sha256' => $prior->evidence_sha256,
                    ]
                );
            }

            return $link->fresh(['reassessmentAttestation.institution']);
        });
    }

    public const REVISION_REASONS = [
        'factual_error',
        'methodology_error',
        'new_evidence',
        'conflict_discovered',
        'credential_issue',
        'evidence_integrity',
        'other',
    ];

    public function reviseAttestation(
        int $membershipId,
        int $attestationId,
        int $userId,
        string $action,
        ?string $method,
        ?string $outcome,
        ?string $basisSummary,
        string $reasonCode,
        string $reasonSummary
    ): SanctumScanAttestationRevision|string {
        if (
            ! in_array($action, ['corrected', 'revoked'], true) ||
            ! in_array($reasonCode, self::REVISION_REASONS, true)
        ) {
            return 'invalid_attestation_revision';
        }

        if (
            $action === 'corrected' &&
            (
                ! in_array($method, self::METHODS, true) ||
                ! in_array($outcome, self::OUTCOMES, true) ||
                trim((string) $basisSummary) === ''
            )
        ) {
            return 'invalid_attestation_revision';
        }

        return DB::transaction(function () use (
            $membershipId,
            $attestationId,
            $userId,
            $action,
            $method,
            $outcome,
            $basisSummary,
            $reasonCode,
            $reasonSummary
        ) {
            $attestation = SanctumScanIndependentAttestation::query()
                ->with(['evidence.dispute.exchange', 'mandate'])
                ->whereKey($attestationId)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                (int) $attestation->membership_id !== $membershipId ||
                (int) $attestation->verifier_user_id !== $userId
            ) {
                return 'revision_not_authorized';
            }

            $userIsActive = User::query()
                ->whereKey($userId)
                ->where('status', true)
                ->where('email_verified', true)
                ->where('sms_verified', true)
                ->exists();

            if (! $userIsActive) {
                return 'revision_not_authorized';
            }

            $membership = SanctumScanVerifierMembership::query()
                ->whereKey($membershipId)
                ->where('user_id', $userId)
                ->first();

            if (
                ! $membership ||
                (int) $membership->institution_id !== (int) $attestation->institution_id
            ) {
                return 'revision_not_authorized';
            }

            $latest = SanctumScanAttestationRevision::query()
                ->where('attestation_id', $attestationId)
                ->orderByDesc('version_number')
                ->lockForUpdate()
                ->first();

            if ($latest && $latest->revision_action === 'revoked') {
                return 'attestation_already_revoked';
            }

            if ($action === 'corrected') {
                if ($membership->status !== 'active') {
                    return 'revision_not_authorized';
                }

                $mandate = SanctumScanEvidenceVerificationMandate::query()
                    ->whereKey($attestation->mandate_id)
                    ->where('evidence_id', $attestation->evidence_id)
                    ->where('institution_id', $attestation->institution_id)
                    ->where('verification_scope', $attestation->verification_scope)
                    ->whereIn('status', ['active', 'completed'])
                    ->lockForUpdate()
                    ->first();

                if (
                    ! $mandate ||
                    ($mandate->status === 'active' && $mandate->expires_at?->isPast())
                ) {
                    return 'revision_not_authorized';
                }

                $institutionIsActive = SanctumScanVerifierInstitution::query()
                    ->whereKey($attestation->institution_id)
                    ->where('status', 'active')
                    ->exists();

                if (! $institutionIsActive) {
                    return 'revision_not_authorized';
                }

                $readinessIssue = $this->verificationReadinessIssue($mandate, $membershipId, true);

                if ($readinessIssue !== null) {
                    return $readinessIssue;
                }

                if (! $this->evidence->fileIntegrityIsValid($attestation->evidence)) {
                    return 'evidence_integrity_failure';
                }
            }

            if (! $attestation->evidence || $attestation->evidence->sha256 !== $attestation->evidence_sha256) {
                return 'attestation_evidence_mismatch';
            }

            $effective = [
                'verification_scope' => $latest?->verification_scope ?? $attestation->verification_scope,
                'verification_method' => $latest?->verification_method ?? $attestation->verification_method,
                'outcome' => $latest?->outcome ?? $attestation->outcome,
                'basis_summary' => $latest?->basis_summary ?? $attestation->basis_summary,
            ];

            if ($action === 'corrected') {
                $effective = [
                    'verification_scope' => $attestation->verification_scope,
                    'verification_method' => $method,
                    'outcome' => $outcome,
                    'basis_summary' => trim((string) $basisSummary),
                ];
            }

            $revision = SanctumScanAttestationRevision::create([
                'attestation_id' => $attestationId,
                'evidence_id' => $attestation->evidence_id,
                'previous_revision_id' => $latest?->getKey(),
                'version_number' => ($latest?->version_number ?? 1) + 1,
                'evidence_sha256' => $attestation->evidence_sha256,
                'revision_action' => $action,
                'verification_scope' => $effective['verification_scope'],
                'verification_method' => $effective['verification_method'],
                'outcome' => $effective['outcome'],
                'basis_summary' => $effective['basis_summary'],
                'reason_code' => $reasonCode,
                'reason_summary' => trim($reasonSummary),
                'recorded_by_guard' => 'api',
                'recorded_by_id' => $userId,
                'recorded_at' => now(),
                'created_at' => now(),
            ]);

            $eventName = $action === 'corrected' ? 'attestation_corrected' : 'attestation_revoked';
            $this->record(
                'attestation_revision',
                (int) $revision->getKey(),
                $eventName,
                'api',
                $userId,
                [
                    'attestation_id' => $attestationId,
                    'evidence_id' => $attestation->evidence_id,
                    'evidence_sha256' => $attestation->evidence_sha256,
                    'version_number' => $revision->version_number,
                    'previous_revision_id' => $revision->previous_revision_id,
                    'revision_action' => $action,
                    'reason_code' => $reasonCode,
                ]
            );

            $exchange = $attestation->evidence?->dispute?->exchange;

            if ($exchange) {
                $this->events->record(
                    $exchange,
                    $action === 'corrected'
                        ? 'external_verifier_attestation_corrected'
                        : 'external_verifier_attestation_revoked',
                    $action === 'corrected'
                        ? 'The issuing verifier appended a correction to an independent attestation.'
                        : 'The issuing verifier withdrew an independent attestation.',
                    'api',
                    $userId,
                    $action === 'corrected' ? 'external_corrected' : 'external_revoked',
                    [
                        'attestation_id' => $attestationId,
                        'revision_id' => $revision->getKey(),
                        'evidence_id' => $attestation->evidence_id,
                        'evidence_sha256' => $attestation->evidence_sha256,
                        'version_number' => $revision->version_number,
                        'revision_action' => $action,
                        'reason_code' => $reasonCode,
                    ]
                );
            }

            return $revision;
        });
    }

    public function recordCredentialReview(
        int $institutionId,
        ?int $membershipId,
        int $adminId,
        string $scope,
        string $credentialType,
        string $issuerName,
        string $credentialReference,
        ?string $jurisdiction,
        ?string $sourceUrl,
        string $reviewMethod,
        string $reviewStatus,
        string $summary,
        ?string $expiresAt
    ): SanctumScanVerifierCredentialReview|string {
        if (
            ! in_array($reviewStatus, ['verified', 'rejected', 'unable_to_verify'], true) ||
            ! in_array($reviewMethod, ['registry_lookup', 'issuer_confirmation', 'document_review', 'other'], true) ||
            trim($credentialType) === '' ||
            trim($issuerName) === '' ||
            trim($credentialReference) === ''
        ) {
            return 'invalid_credential_review';
        }

        if ($membershipId === null && $scope !== 'institution_registration') {
            return 'invalid_credential_scope';
        }

        if ($membershipId !== null && ! in_array($scope, self::SCOPES, true)) {
            return 'invalid_credential_scope';
        }

        if (
            $reviewStatus === 'verified' &&
            $expiresAt !== null &&
            \Illuminate\Support\Carbon::parse($expiresAt)->isPast()
        ) {
            return 'credential_expiry_invalid';
        }

        return DB::transaction(function () use (
            $institutionId,
            $membershipId,
            $adminId,
            $scope,
            $credentialType,
            $issuerName,
            $credentialReference,
            $jurisdiction,
            $sourceUrl,
            $reviewMethod,
            $reviewStatus,
            $summary,
            $expiresAt
        ) {
            SanctumScanVerifierInstitution::query()
                ->whereKey($institutionId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($membershipId !== null) {
                $membership = SanctumScanVerifierMembership::query()
                    ->whereKey($membershipId)
                    ->where('institution_id', $institutionId)
                    ->lockForUpdate()
                    ->first();

                if (! $membership) {
                    return 'credential_subject_mismatch';
                }
            }

            $review = SanctumScanVerifierCredentialReview::create([
                'institution_id' => $institutionId,
                'membership_id' => $membershipId,
                'credential_scope' => $scope,
                'credential_type' => trim($credentialType),
                'issuer_name' => trim($issuerName),
                'credential_reference' => trim($credentialReference),
                'jurisdiction' => $jurisdiction ? trim($jurisdiction) : null,
                'source_url' => $sourceUrl,
                'review_method' => $reviewMethod,
                'review_status' => $reviewStatus,
                'review_summary' => trim($summary),
                'reviewed_by_admin_id' => $adminId,
                'reviewed_at' => now(),
                'expires_at' => $expiresAt,
                'created_at' => now(),
            ]);

            $this->record(
                'credential_review',
                (int) $review->getKey(),
                'credential_review_recorded',
                'admin',
                $adminId,
                [
                    'institution_id' => $institutionId,
                    'membership_id' => $membershipId,
                    'credential_scope' => $scope,
                    'review_status' => $reviewStatus,
                    'expires_at' => $expiresAt,
                ]
            );

            return $review;
        });
    }

    public function declareConflict(
        int $membershipId,
        int $evidenceId,
        int $userId,
        bool $hasConflict,
        string $statement
    ): SanctumScanVerifierConflictDeclaration|string {
        return DB::transaction(function () use (
            $membershipId,
            $evidenceId,
            $userId,
            $hasConflict,
            $statement
        ) {
            $mandate = $this->authorizedMandate($membershipId, $evidenceId, $userId, null, true);

            if (! $mandate) {
                return 'verification_not_authorized';
            }

            $credentialIssue = $this->verificationCredentialIssue($mandate, $membershipId);

            if ($credentialIssue !== null) {
                return $credentialIssue;
            }

            $evidence = SanctumScanExchangeDisputeEvidence::query()
                ->with('dispute.exchange')
                ->whereKey($evidenceId)
                ->firstOrFail();
            $exchange = $evidence->dispute?->exchange;

            if (
                ($exchange && (int) $exchange->buyer_user_id === $userId) ||
                ($evidence->submitted_by_guard === 'api' && (int) $evidence->submitted_by_id === $userId)
            ) {
                return 'verifier_conflict_of_interest';
            }

            $previous = SanctumScanVerifierConflictDeclaration::query()
                ->where('membership_id', $membershipId)
                ->where('mandate_id', $mandate->getKey())
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if ($previous && $previous->has_conflict) {
                return 'verifier_conflict_of_interest';
            }

            if ($previous && ! $hasConflict) {
                return 'conflict_declaration_already_recorded';
            }

            $declaration = SanctumScanVerifierConflictDeclaration::create([
                'membership_id' => $membershipId,
                'mandate_id' => $mandate->getKey(),
                'verifier_user_id' => $userId,
                'has_conflict' => $hasConflict,
                'disclosure_statement' => trim($statement),
                'declared_at' => now(),
                'created_at' => now(),
            ]);

            $this->record(
                'conflict_declaration',
                (int) $declaration->getKey(),
                'conflict_declaration_recorded',
                'api',
                $userId,
                [
                    'membership_id' => $membershipId,
                    'mandate_id' => $mandate->getKey(),
                    'evidence_id' => $evidenceId,
                    'has_conflict' => $hasConflict,
                ]
            );

            return $declaration;
        });
    }

    public function lodgeAppeal(
        int $exchangeId,
        int $evidenceId,
        int $attestationId,
        string $actorGuard,
        int $actorId,
        string $reasonCode,
        string $statement
    ): SanctumScanAttestationAppeal|string {
        if (! in_array($actorGuard, ['api', 'merchant_api'], true)) {
            return 'appeal_not_authorized';
        }

        return DB::transaction(function () use (
            $exchangeId,
            $evidenceId,
            $attestationId,
            $actorGuard,
            $actorId,
            $reasonCode,
            $statement
        ) {
            $attestation = SanctumScanIndependentAttestation::query()
                ->with('evidence.dispute.exchange')
                ->whereKey($attestationId)
                ->where('evidence_id', $evidenceId)
                ->lockForUpdate()
                ->firstOrFail();

            $evidence = $attestation->evidence;
            $exchange = $evidence?->dispute?->exchange;

            if ($exchange) {
                $exchange = SanctumScanExchange::query()
                    ->whereKey($exchange->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            if (! $exchange || (int) $exchange->getKey() !== $exchangeId) {
                return 'appeal_not_found';
            }

            $isParticipant = (
                $actorGuard === 'api' &&
                (int) $exchange->buyer_user_id === $actorId
            ) || (
                $actorGuard === 'merchant_api' &&
                (int) $exchange->merchant_id === $actorId
            );

            if (! $isParticipant) {
                return 'appeal_not_authorized';
            }

            if ($attestation->evidence_sha256 !== $evidence->sha256) {
                return 'appeal_evidence_mismatch';
            }

            $existing = SanctumScanAttestationAppeal::query()
                ->where('attestation_id', $attestationId)
                ->where('appellant_guard', $actorGuard)
                ->where('appellant_id', $actorId)
                ->first();

            if ($existing) {
                return 'appeal_already_exists';
            }

            $appeal = SanctumScanAttestationAppeal::create([
                'attestation_id' => $attestationId,
                'evidence_id' => $evidenceId,
                'exchange_id' => $exchangeId,
                'appellant_guard' => $actorGuard,
                'appellant_id' => $actorId,
                'evidence_sha256' => $attestation->evidence_sha256,
                'reason_code' => $reasonCode,
                'statement' => trim($statement),
                'submitted_at' => now(),
                'created_at' => now(),
            ]);

            $this->record(
                'appeal',
                (int) $appeal->getKey(),
                'attestation_appeal_submitted',
                $actorGuard,
                $actorId,
                [
                    'attestation_id' => $attestationId,
                    'evidence_id' => $evidenceId,
                    'exchange_id' => $exchangeId,
                    'evidence_sha256' => $attestation->evidence_sha256,
                    'reason_code' => $reasonCode,
                ]
            );

            $this->events->record(
                $exchange,
                'external_verifier_attestation_appealed',
                'An exchange participant submitted an appeal concerning an independent attestation.',
                $actorGuard,
                $actorId,
                'appeal_submitted',
                [
                    'appeal_id' => $appeal->getKey(),
                    'attestation_id' => $attestationId,
                    'evidence_id' => $evidenceId,
                    'evidence_sha256' => $attestation->evidence_sha256,
                    'reason_code' => $reasonCode,
                ]
            );

            return $appeal->fresh(['attestation.institution', 'decision']);
        });
    }

    public function adjudicateAppeal(
        int $appealId,
        int $adminId,
        string $outcome,
        string $summary
    ): SanctumScanAttestationAppealDecision|string {
        if (! in_array($outcome, ['upheld', 'overturned', 'correction_recommended', 'dismissed'], true)) {
            return 'invalid_appeal_decision';
        }

        return DB::transaction(function () use ($appealId, $adminId, $outcome, $summary) {
            $appeal = SanctumScanAttestationAppeal::query()
                ->with(['exchange', 'attestation'])
                ->whereKey($appealId)
                ->lockForUpdate()
                ->firstOrFail();

            if (SanctumScanAttestationAppealDecision::query()->where('appeal_id', $appealId)->exists()) {
                return 'appeal_already_decided';
            }

            $decision = SanctumScanAttestationAppealDecision::create([
                'appeal_id' => $appealId,
                'outcome' => $outcome,
                'decision_summary' => trim($summary),
                'reviewed_by_admin_id' => $adminId,
                'decided_at' => now(),
                'created_at' => now(),
            ]);

            $this->record(
                'appeal',
                $appealId,
                'attestation_appeal_decided',
                'admin',
                $adminId,
                [
                    'decision_id' => $decision->getKey(),
                    'outcome' => $outcome,
                    'attestation_id' => $appeal->attestation_id,
                    'evidence_id' => $appeal->evidence_id,
                    'evidence_sha256' => $appeal->evidence_sha256,
                ]
            );

            if ($appeal->exchange) {
                $this->events->record(
                    $appeal->exchange,
                    'external_verifier_appeal_reviewed',
                    'A platform administrator recorded a process finding for an independent-attestation appeal.',
                    'admin',
                    $adminId,
                    'appeal_' . $outcome,
                    [
                        'appeal_id' => $appealId,
                        'decision_id' => $decision->getKey(),
                        'attestation_id' => $appeal->attestation_id,
                        'evidence_id' => $appeal->evidence_id,
                        'evidence_sha256' => $appeal->evidence_sha256,
                        'outcome' => $outcome,
                    ]
                );
            }

            return $decision->fresh(['appeal']);
        });
    }

    private function verificationReadinessIssue(
        SanctumScanEvidenceVerificationMandate $mandate,
        int $membershipId,
        bool $requireConflictDeclaration
    ): ?string {
        $credentialIssue = $this->verificationCredentialIssue($mandate, $membershipId);

        if ($credentialIssue !== null) {
            return $credentialIssue;
        }

        if (! $requireConflictDeclaration) {
            return null;
        }

        $declaration = SanctumScanVerifierConflictDeclaration::query()
            ->where('membership_id', $membershipId)
            ->where('mandate_id', $mandate->getKey())
            ->orderByDesc('id')
            ->first();

        if (! $declaration) {
            return 'conflict_declaration_required';
        }

        return $declaration->has_conflict ? 'verifier_conflict_of_interest' : null;
    }

    private function verificationCredentialIssue(
        SanctumScanEvidenceVerificationMandate $mandate,
        int $membershipId
    ): ?string {
        $institutionReview = SanctumScanVerifierCredentialReview::query()
            ->where('institution_id', $mandate->institution_id)
            ->whereNull('membership_id')
            ->where('credential_scope', 'institution_registration')
            ->orderByDesc('id')
            ->first();

        if (
            ! $institutionReview ||
            $institutionReview->review_status !== 'verified' ||
            ($institutionReview->expires_at && $institutionReview->expires_at->isPast())
        ) {
            return 'institution_credential_unverified';
        }

        $membershipReview = SanctumScanVerifierCredentialReview::query()
            ->where('institution_id', $mandate->institution_id)
            ->where('membership_id', $membershipId)
            ->where('credential_scope', $mandate->verification_scope)
            ->orderByDesc('id')
            ->first();

        if (
            ! $membershipReview ||
            $membershipReview->review_status !== 'verified' ||
            ($membershipReview->expires_at && $membershipReview->expires_at->isPast())
        ) {
            return 'verifier_credential_unverified';
        }

        return null;
    }

    private function authorizedMandate(
        int $membershipId,
        int $evidenceId,
        int $userId,
        ?string $scope = null,
        bool $lock = false
    ): ?SanctumScanEvidenceVerificationMandate {
        $membershipQuery = SanctumScanVerifierMembership::query()
            ->whereKey($membershipId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->whereHas('verifier', function ($query) {
                $query->where('status', true)
                    ->where('email_verified', true)
                    ->where('sms_verified', true);
            })
            ->whereHas('institution', fn ($query) => $query->where('status', 'active'));

        if ($lock) {
            $membershipQuery->lockForUpdate();
        }

        $membership = $membershipQuery->first();

        if (! $membership) {
            return null;
        }

        $mandateQuery = SanctumScanEvidenceVerificationMandate::query()
            ->where('evidence_id', $evidenceId)
            ->where('institution_id', $membership->institution_id)
            ->where('status', 'active')
            ->where('expires_at', '>', now());

        if ($scope !== null) {
            $mandateQuery->where('verification_scope', $scope);
        }

        if ($lock) {
            $mandateQuery->lockForUpdate();
        }

        return $mandateQuery->first();
    }

    private function record(
        string $subjectType,
        int $subjectId,
        string $eventType,
        string $actorGuard,
        int $actorId,
        array $data = []
    ): void {
        SanctumScanVerifierRegistryEvent::create([
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'event_type' => $eventType,
            'actor_guard' => $actorGuard,
            'actor_id' => $actorId,
            'event_data' => $data ?: null,
            'recorded_at' => now(),
        ]);
    }
}