<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class SanctumScanVerifierRegistryEvent extends Model
{
    protected $table = 'sanctum_scan_verifier_registry_events';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'subject_id' => 'integer',
        'actor_id' => 'integer',
        'event_data' => 'array',
        'recorded_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $event) {
            throw new LogicException('Verifier registry events are immutable.');
        });

        static::deleting(function (self $event) {
            throw new LogicException('Verifier registry events cannot be deleted.');
        });
    }
}