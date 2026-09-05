<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Route extends Model
{
    protected $fillable = [
        'operator_id',
        'name',
        'origin',
        'destination',
        'duration_minutes',
        'distance_km',
        'base_fare',
        'is_active',
    ];

    protected $casts = [
        'duration_minutes' => 'integer',
        'distance_km' => 'decimal:2',
        'base_fare' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function operator(): BelongsTo
    {
        return $this->belongsTo(
            Operator::class,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Full Road Way Stops
    |--------------------------------------------------------------------------
    |
    | All towns / stops in the complete route order.
    |
    */

    public function stops(): HasMany
    {
        return $this->hasMany(
            RouteStop::class,
        )->orderBy(
            'stop_order',
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Booking Stops
    |--------------------------------------------------------------------------
    |
    | Only selected road-way stops that are available for passenger booking.
    | Starting and Return directions are stored separately.
    |
    */

    public function bookingStops(): HasMany
    {
        return $this->hasMany(
            RouteBookingStop::class,
        )->orderBy(
            'stop_order',
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
            ->where(
                'is_active',
                true,
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
            ->where(
                'is_active',
                true,
            )
            ->orderBy(
                'stop_order',
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Trips
    |--------------------------------------------------------------------------
    */

    public function trips(): HasMany
    {
        return $this->hasMany(
            Trip::class,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Fixed Services
    |--------------------------------------------------------------------------
    */

    public function fixedServices(): HasMany
    {
        return $this->hasMany(
            FixedService::class,
        );
    }
}