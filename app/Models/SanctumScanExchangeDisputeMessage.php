<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class SanctumScanExchangeDisputeMessage extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'dispute_id' => 'integer',
        'recorded_by_id' => 'integer',
        'created_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $message) {
            throw new LogicException('Sanctum Scan dispute messages are immutable.');
        });

        static::deleting(function (self $message) {
            throw new LogicException('Sanctum Scan dispute messages cannot be deleted.');
        });
    }

    public function dispute(): BelongsTo
    {
        return $this->belongsTo(SanctumScanExchangeDispute::class, 'dispute_id');
    }
}
