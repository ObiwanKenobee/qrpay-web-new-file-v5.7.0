<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SanctumScanVerifierInstitution extends Model
{
    protected $table = 'sanctum_scan_verifier_institutions';

    protected $guarded = ['id'];

    public function memberships(): HasMany
    {
        return $this->hasMany(SanctumScanVerifierMembership::class, 'institution_id');
    }

    public function mandates(): HasMany
    {
        return $this->hasMany(SanctumScanEvidenceVerificationMandate::class, 'institution_id');
    }

    public function attestations(): HasMany
    {
        return $this->hasMany(SanctumScanIndependentAttestation::class, 'institution_id');
    }
}