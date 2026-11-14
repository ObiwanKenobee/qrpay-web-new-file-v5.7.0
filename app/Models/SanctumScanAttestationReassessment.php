<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class SanctumScanAttestationReassessment extends Model
{
    protected $table = 'sanctum_scan_attestation_reassessments';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'prior_attestation_id' => 'integer',
        'prior_revision_id' => 'integer',
        'reassessment_attestation_id' => 'integer',
        'evidence_id' => 'integer',
        'institution_id' => 'integer',
        'reassessed_by_membership_id' => 'integer',
        'reassessed_by_user_id' => 'integer',
        'prior_version_number' => 'integer',
        'recorded_at' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $reassessment) {
            throw new LogicException('Attestation reassessment links are immutable.');
        });

        static::deleting(function (self $reassessment) {
            throw new LogicException('Attestation reassessment links cannot be deleted.');
        });
    }

    public function priorAttestation(): BelongsTo
    {
        return $this->belongsTo(SanctumScanIndependentAttestation::class, 'prior_attestation_id');
    }

    public function priorRevision(): BelongsTo
    {
        return $this->belongsTo(SanctumScanAttestationRevision::class, 'prior_revision_id');
    }

    public function reassessmentAttestation(): BelongsTo
    {
        return $this->belongsTo(SanctumScanIndependentAttestation::class, 'reassessment_attestation_id');
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(SanctumScanVerifierInstitution::class, 'institution_id');
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(SanctumScanVerifierMembership::class, 'reassessed_by_membership_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reassessed_by_user_id');
    }
}
