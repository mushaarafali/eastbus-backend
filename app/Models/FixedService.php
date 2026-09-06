<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FixedService extends Model
{
    protected $fillable = [
        'operator_id',
        'route_id',
        'bus_id',
        'service_name',
        'starting_time',
        'return_time',
        'is_active',
        'is_published',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_published' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Operator
    |--------------------------------------------------------------------------
    */

    public function operator(): BelongsTo
    {
        return $this->belongsTo(
            Operator::class,
            'operator_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Master Route
    |--------------------------------------------------------------------------
    |
    | The System Administrator manages the Master Route and its full roadway.
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
    | Bus
    |--------------------------------------------------------------------------
    |
    | The operator bus assigned to this service.
    |
    */

    public function bus(): BelongsTo
    {
        return $this->belongsTo(
            Bus::class,
            'bus_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Trips
    |--------------------------------------------------------------------------
    |
    | Trips created from this exact Bus Route Service.
    |
    */

    public function trips(): HasMany
    {
        return $this->hasMany(
            Trip::class,
            'fixed_service_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | All Service Stops
    |--------------------------------------------------------------------------
    |
    | These are bus-specific booking/timetable stops selected from the
    | Master Route roadway.
    |
    */

    public function stops(): HasMany
    {
        return $this->hasMany(
            FixedServiceStop::class,
            'fixed_service_id'
        )
            ->orderBy('direction')
            ->orderBy('stop_order');
    }

    /*
    |--------------------------------------------------------------------------
    | Starting Direction Stops
    |--------------------------------------------------------------------------
    */

    public function startingStops(): HasMany
    {
        return $this->hasMany(
            FixedServiceStop::class,
            'fixed_service_id'
        )
            ->where(
                'direction',
                'starting'
            )
            ->orderBy(
                'stop_order'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Return Direction Stops
    |--------------------------------------------------------------------------
    */

    public function returnStops(): HasMany
    {
        return $this->hasMany(
            FixedServiceStop::class,
            'fixed_service_id'
        )
            ->where(
                'direction',
                'return'
            )
            ->orderBy(
                'stop_order'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Starting Booking Points
    |--------------------------------------------------------------------------
    */

    public function startingBookingStops(): HasMany
    {
        return $this->hasMany(
            FixedServiceStop::class,
            'fixed_service_id'
        )
            ->where(
                'direction',
                'starting'
            )
            ->where(function ($query) {
                $query
                    ->where(
                        'boarding_allowed',
                        true
                    )
                    ->orWhere(
                        'dropoff_allowed',
                        true
                    );
            })
            ->orderBy(
                'stop_order'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Return Booking Points
    |--------------------------------------------------------------------------
    */

    public function returnBookingStops(): HasMany
    {
        return $this->hasMany(
            FixedServiceStop::class,
            'fixed_service_id'
        )
            ->where(
                'direction',
                'return'
            )
            ->where(function ($query) {
                $query
                    ->where(
                        'boarding_allowed',
                        true
                    )
                    ->orWhere(
                        'dropoff_allowed',
                        true
                    );
            })
            ->orderBy(
                'stop_order'
            );
    }
}