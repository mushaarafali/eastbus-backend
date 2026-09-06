<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RouteStop extends Model
{
    protected $fillable = [
        'route_id',
        'name',
        'stop_order',
        'fare_stage_no',
        'distance_from_origin_km',
        'latitude',
        'longitude',
        'booking_radius_km',
    ];

    protected $casts = [
        'stop_order' => 'integer',
        'fare_stage_no' => 'integer',
        'distance_from_origin_km' => 'decimal:2',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'booking_radius_km' => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | Master Route
    |--------------------------------------------------------------------------
    |
    | Each roadway stop belongs to one System Admin managed Master Route.
    |
    */

    public function route(): BelongsTo
    {
        return $this->belongsTo(
            Route::class,
            'route_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Fixed Service Stops
    |--------------------------------------------------------------------------
    |
    | This roadway stop can be selected by one or more operator bus services
    | as a Starting or Return booking point.
    |
    | Example:
    |
    | Route Stop: Kalmunai
    |
    | Fixed Service A
    |   -> Starting booking point
    |   -> 05:45 AM
    |
    | Fixed Service B
    |   -> Starting booking point
    |   -> 08:15 AM
    |
    | The stop name and KM remain stored only in route_stops.
    |
    */

    public function fixedServiceStops(): HasMany
    {
        return $this->hasMany(
            FixedServiceStop::class,
            'route_stop_id'
        );
    }
}