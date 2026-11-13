<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class SanctumScanAttestationAppealDecision extends Model
{
    protected $table = 'sanctum_scan_attestation_appeal_decisions';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'appeal_id' => 'integer',
        'reviewed_by_admin_id' => 'integer',
        'decided_at' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $decision) {
            throw new LogicException('Attestation appeal decisions are immutable.');
        });

        static::deleting(function (self $decision) {
            throw new LogicException('Attestation appeal decisions cannot be deleted.');
        });
    }

    public function appeal(): BelongsTo
    {
        return $this->belongsTo(SanctumScanAttestationAppeal::class, 'appeal_id');
    }
}
