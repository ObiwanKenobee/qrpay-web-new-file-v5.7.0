<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class SanctumScanExchangeEvent extends Model
{
    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(function (self $event) {
            throw new LogicException('Sanctum Scan exchange events are immutable.');
        });

        static::deleting(function (self $event) {
            throw new LogicException('Sanctum Scan exchange events cannot be deleted.');
        });
    }

    protected $guarded = ['id'];

    protected $casts = [
        'exchange_id' => 'integer',
        'event_data' => 'array',
        'recorded_by_id' => 'integer',
        'occurred_at' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
    ];

    public function exchange(): BelongsTo
    {
        return $this->belongsTo(SanctumScanExchange::class, 'exchange_id');
    }
}
