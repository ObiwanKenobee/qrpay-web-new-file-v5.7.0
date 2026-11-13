<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class SanctumScanIndependentAttestation extends Model
{
    protected $table = 'sanctum_scan_independent_attestations';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'mandate_id' => 'integer',
        'evidence_id' => 'integer',
        'institution_id' => 'integer',
        'membership_id' => 'integer',
        'verifier_user_id' => 'integer',
        'attested_at' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $attestation) {
            throw new LogicException('Independent verifier attestations are immutable.');
        });

        static::deleting(function (self $attestation) {
            throw new LogicException('Independent verifier attestations cannot be deleted.');
        });
    }

    public function evidence(): BelongsTo
    {
        return $this->belongsTo(SanctumScanExchangeDisputeEvidence::class, 'evidence_id');
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(SanctumScanVerifierInstitution::class, 'institution_id');
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(SanctumScanVerifierMembership::class, 'membership_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verifier_user_id');
    }

    public function mandate(): BelongsTo
    {
        return $this->belongsTo(SanctumScanEvidenceVerificationMandate::class, 'mandate_id');
    }

    public function appeals(): HasMany
    {
        return $this->hasMany(SanctumScanAttestationAppeal::class, 'attestation_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(SanctumScanAttestationRevision::class, 'attestation_id')
            ->orderBy('version_number');
    }

    public function reassessments(): HasMany
    {
        return $this->hasMany(SanctumScanAttestationReassessment::class, 'prior_attestation_id');
    }

    public function reassessmentOf(): HasOne
    {
        return $this->hasOne(SanctumScanAttestationReassessment::class, 'reassessment_attestation_id');
    }
}