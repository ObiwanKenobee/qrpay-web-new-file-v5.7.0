<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class SanctumScanAttestationRevision extends Model
{
    protected $table = 'sanctum_scan_attestation_revisions';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'attestation_id' => 'integer',
        'evidence_id' => 'integer',
        'previous_revision_id' => 'integer',
        'version_number' => 'integer',
        'recorded_by_id' => 'integer',
        'recorded_at' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $revision) {
            throw new LogicException('Independent attestation revisions are immutable.');
        });

        static::deleting(function (self $revision) {
            throw new LogicException('Independent attestation revisions cannot be deleted.');
        });
    }

    public function attestation(): BelongsTo
    {
        return $this->belongsTo(SanctumScanIndependentAttestation::class, 'attestation_id');
    }

    public function evidence(): BelongsTo
    {
        return $this->belongsTo(SanctumScanExchangeDisputeEvidence::class, 'evidence_id');
    }

    public function previousRevision(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_revision_id');
    }
}
