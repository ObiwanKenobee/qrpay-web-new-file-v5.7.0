<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class SanctumScanExchangeDispute extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'exchange_id' => 'integer',
        'opened_by_id' => 'integer',
        'resolution_proposed_by_id' => 'integer',
        'resolved_by_id' => 'integer',
        'opened_at' => 'immutable_datetime',
        'resolution_proposed_at' => 'immutable_datetime',
        'resolved_at' => 'immutable_datetime',
        'withdrawn_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function (self $dispute) {
            throw new LogicException('Sanctum Scan dispute records cannot be deleted.');
        });
    }

    public function exchange(): BelongsTo
    {
        return $this->belongsTo(SanctumScanExchange::class, 'exchange_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SanctumScanExchangeDisputeMessage::class, 'dispute_id')
            ->orderBy('id');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(SanctumScanExchangeDisputeEvidence::class, 'dispute_id')
            ->orderBy('id');
    }
}
