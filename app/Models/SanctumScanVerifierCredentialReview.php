<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class SanctumScanVerifierCredentialReview extends Model
{
    protected $table = 'sanctum_scan_verifier_credential_reviews';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'institution_id' => 'integer',
        'membership_id' => 'integer',
        'reviewed_by_admin_id' => 'integer',
        'reviewed_at' => 'immutable_datetime',
        'expires_at' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $review) {
            throw new LogicException('Verifier credential reviews are immutable.');
        });

        static::deleting(function (self $review) {
            throw new LogicException('Verifier credential reviews cannot be deleted.');
        });
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(SanctumScanVerifierInstitution::class, 'institution_id');
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(SanctumScanVerifierMembership::class, 'membership_id');
    }
}
