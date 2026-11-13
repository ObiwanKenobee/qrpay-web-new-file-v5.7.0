<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class SanctumScanAttestationAppeal extends Model
{
    protected $table = 'sanctum_scan_attestation_appeals';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'attestation_id' => 'integer',
        'evidence_id' => 'integer',
        'exchange_id' => 'integer',
        'appellant_id' => 'integer',
        'submitted_at' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $appeal) {
            throw new LogicException('Attestation appeals are immutable.');
        });

        static::deleting(function (self $appeal) {
            throw new LogicException('Attestation appeals cannot be deleted.');
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

    public function exchange(): BelongsTo
    {
        return $this->belongsTo(SanctumScanExchange::class, 'exchange_id');
    }

    public function decision(): HasOne
    {
        return $this->hasOne(SanctumScanAttestationAppealDecision::class, 'appeal_id');
    }
}
