<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouteBookingStop extends Model
{
    protected $fillable = [
        'route_id',
        'route_stop_id',
        'direction',
        'stop_order',
        'schedule_time',
        'fare_stage_no',
        'distance_from_origin',
        'boarding_allowed',
        'dropoff_allowed',
        'is_active',
    ];

    protected $casts = [
        'route_id' => 'integer',
        'route_stop_id' => 'integer',
        'stop_order' => 'integer',
        'fare_stage_no' => 'integer',

        'distance_from_origin' => 'decimal:2',

        'boarding_allowed' => 'boolean',
        'dropoff_allowed' => 'boolean',
        'is_active' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Route
    |--------------------------------------------------------------------------
    */

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Road Way Stop
    |--------------------------------------------------------------------------
    |
    | Every booking point must belong to an existing road-way stop.
    |
    */

    public function routeStop(): BelongsTo
    {
        return $this->belongsTo(RouteStop::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isStarting(): bool
    {
        return $this->direction === 'starting';
    }

    public function isReturn(): bool
    {
        return $this->direction === 'return';
    }

    public function canBoard(): bool
    {
        return $this->is_active && $this->boarding_allowed;
    }

    public function canDropoff(): bool
    {
        return $this->is_active && $this->dropoff_allowed;
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeStarting($query)
    {
        return $query
            ->where('direction', 'starting')
            ->orderBy('stop_order');
    }

    public function scopeReturn($query)
    {
        return $query
            ->where('direction', 'return')
            ->orderBy('stop_order');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}