<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SanctumScanVerifierMembership extends Model
{
    protected $table = 'sanctum_scan_verifier_memberships';

    protected $guarded = ['id'];

    protected $casts = [
        'institution_id' => 'integer',
        'user_id' => 'integer',
        'invited_by_admin_id' => 'integer',
        'invited_at' => 'immutable_datetime',
        'accepted_at' => 'immutable_datetime',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(SanctumScanVerifierInstitution::class, 'institution_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function attestations(): HasMany
    {
        return $this->hasMany(SanctumScanIndependentAttestation::class, 'membership_id');
    }

    public function credentialReviews(): HasMany
    {
        return $this->hasMany(SanctumScanVerifierCredentialReview::class, 'membership_id');
    }

    public function conflictDeclarations(): HasMany
    {
        return $this->hasMany(SanctumScanVerifierConflictDeclaration::class, 'membership_id');
    }
}