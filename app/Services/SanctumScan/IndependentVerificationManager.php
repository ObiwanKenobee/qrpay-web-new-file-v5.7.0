<?php

namespace App\Services\SanctumScan;

use App\Models\SanctumScanEvidenceVerificationMandate;
use App\Models\SanctumScanExchangeDisputeEvidence;
use App\Models\SanctumScanIndependentAttestation;
use App\Models\SanctumScanVerifierInstitution;
use App\Models\SanctumScanVerifierMembership;
use App\Models\SanctumScanVerifierRegistryEvent;
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

            $existing = SanctumScanEvidenceVerificationMandate::query()
                ->where('evidence_id', $evidenceId)
                ->where('institution_id', $institutionId)
                ->lockForUpdate()
                ->first();

            if ($existing && $existing->status === 'active' && $existing->expires_at->isFuture()) {
                return 'mandate_already_active';
            }

            $attributes = [
                'verification_scope' => $scope,
                'mandate_reason' => trim($reason),
                'status' => 'active',
                'mandated_by_admin_id' => $adminId,
                'expires_at' => $expiresAt,
                'revoked_by_admin_id' => null,
                'revoked_at' => null,
            ];

            if (! $existing) {
                $mandate = SanctumScanEvidenceVerificationMandate::create($attributes + [
                    'evidence_id' => $evidenceId,
                    'institution_id' => $institutionId,
                ]);
            } else {
                $mandate = $existing->forceFill($attributes);
                $mandate->save();
            }

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
        if (! $this->authorizedMandate($membershipId, $evidenceId, $userId)) {
            return 'verification_not_authorized';
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

            if (SanctumScanIndependentAttestation::query()
                ->where('mandate_id', $mandate->getKey())
                ->where('membership_id', $membershipId)
                ->exists()) {
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