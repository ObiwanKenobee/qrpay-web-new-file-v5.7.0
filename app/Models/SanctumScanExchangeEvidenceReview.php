<?php

namespace App\Models;

use App\Models\Admin\Admin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class SanctumScanExchangeEvidenceReview extends Model
{
    protected $table = 'sanctum_scan_exchange_evidence_reviews';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'evidence_id' => 'integer',
        'reviewed_by_admin_id' => 'integer',
        'reviewed_at' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $review) {
            throw new LogicException('Sanctum Scan evidence reviews are immutable.');
        });

        static::deleting(function (self $review) {
            throw new LogicException('Sanctum Scan evidence reviews cannot be deleted.');
        });
    }

    public function evidence(): BelongsTo
    {
        return $this->belongsTo(SanctumScanExchangeDisputeEvidence::class, 'evidence_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by_admin_id');
    }
}
