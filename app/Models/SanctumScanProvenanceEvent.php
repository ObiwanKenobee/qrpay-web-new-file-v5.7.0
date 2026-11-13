<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SanctumScanProvenanceEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'resource_id',
        'event_type',
        'statement',
        'location_label',
        'occurred_at',
        'verification_status',
        'recorded_by_guard',
        'recorded_by_id',
        'created_at',
    ];

    protected $casts = [
        'resource_id' => 'integer',
        'recorded_by_id' => 'integer',
        'occurred_at' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
    ];

    public function resource(): BelongsTo
    {
        return $this->belongsTo(SanctumScanResource::class, 'resource_id');
    }
}
