<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SanctumScanEvidenceVerificationMandate extends Model
{
    protected $table = 'sanctum_scan_evidence_verification_mandates';

    protected $guarded = ['id'];

    protected $casts = [
        'evidence_id' => 'integer',
        'institution_id' => 'integer',
        'mandated_by_admin_id' => 'integer',
        'revoked_by_admin_id' => 'integer',
        'expires_at' => 'immutable_datetime',
        'revoked_at' => 'immutable_datetime',
    ];

    public function evidence(): BelongsTo
    {
        return $this->belongsTo(SanctumScanExchangeDisputeEvidence::class, 'evidence_id');
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(SanctumScanVerifierInstitution::class, 'institution_id');
    }

    public function attestations(): HasMany
    {
        return $this->hasMany(SanctumScanIndependentAttestation::class, 'mandate_id');
    }

    public function conflictDeclarations(): HasMany
    {
        return $this->hasMany(SanctumScanVerifierConflictDeclaration::class, 'mandate_id');
    }
}