<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class SanctumScanVerifierConflictDeclaration extends Model
{
    protected $table = 'sanctum_scan_verifier_conflict_declarations';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'membership_id' => 'integer',
        'mandate_id' => 'integer',
        'verifier_user_id' => 'integer',
        'has_conflict' => 'boolean',
        'declared_at' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $declaration) {
            throw new LogicException('Verifier conflict declarations are immutable.');
        });

        static::deleting(function (self $declaration) {
            throw new LogicException('Verifier conflict declarations cannot be deleted.');
        });
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(SanctumScanVerifierMembership::class, 'membership_id');
    }

    public function mandate(): BelongsTo
    {
        return $this->belongsTo(SanctumScanEvidenceVerificationMandate::class, 'mandate_id');
    }
}
