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
        'distance_from_origin',
        'latitude',
        'longitude',
        'booking_radius_km',
    ];

    protected $casts = [
        'stop_order' => 'integer',
        'fare_stage_no' => 'integer',
        'distance_from_origin' => 'decimal:2',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'booking_radius_km' => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | Route
    |--------------------------------------------------------------------------
    */

    public function route(): BelongsTo
    {
        return $this->belongsTo(
            Route::class,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Route Booking Stops
    |--------------------------------------------------------------------------
    |
    | A road-way stop can be selected as a booking point for
    | Starting direction, Return direction, or both.
    |
    */

    public function bookingStops(): HasMany
    {
        return $this->hasMany(
            RouteBookingStop::class,
        );
    }

    public function startingBookingStops(): HasMany
    {
        return $this->hasMany(
            RouteBookingStop::class,
        )
            ->where(
                'direction',
                'starting',
            )
            ->orderBy(
                'stop_order',
            );
    }

    public function returnBookingStops(): HasMany
    {
        return $this->hasMany(
            RouteBookingStop::class,
        )
            ->where(
                'direction',
                'return',
            )
            ->orderBy(
                'stop_order',
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Fixed Service Stops
    |--------------------------------------------------------------------------
    |
    | Kept for the existing fixed timetable-only service feature.
    |
    */

    public function fixedServiceStops(): HasMany
    {
        return $this->hasMany(
            FixedServiceStop::class,
        );
    }
}