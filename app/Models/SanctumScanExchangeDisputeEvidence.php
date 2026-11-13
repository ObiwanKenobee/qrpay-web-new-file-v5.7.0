<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class SanctumScanExchangeDisputeEvidence extends Model
{
    protected $table = 'sanctum_scan_exchange_dispute_evidence';

    protected $guarded = ['id'];

    protected $casts = [
        'dispute_id' => 'integer',
        'size_bytes' => 'integer',
        'submitted_by_id' => 'integer',
        'submitted_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $evidence) {
            throw new LogicException('Submitted dispute evidence is immutable.');
        });

        static::deleting(function (self $evidence) {
            throw new LogicException('Submitted dispute evidence cannot be deleted.');
        });
    }

    public function dispute(): BelongsTo
    {
        return $this->belongsTo(SanctumScanExchangeDispute::class, 'dispute_id');
    }

    public function review(): HasOne
    {
        return $this->hasOne(SanctumScanExchangeEvidenceReview::class, 'evidence_id');
    }
}
