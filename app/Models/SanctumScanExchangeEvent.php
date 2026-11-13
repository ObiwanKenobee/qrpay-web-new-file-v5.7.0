<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SanctumScanExchangeEvent extends Model
{
    public $timestamps = false;

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
