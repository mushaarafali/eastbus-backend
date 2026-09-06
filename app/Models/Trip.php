<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Trip extends Model
{
    protected $fillable = [
        'operator_id',
        'route_id',
        'fixed_service_id',
        'bus_id',
        'driver_id',
        'conductor_id',
        'trip_code',
        'trip_type',
        'service_date',
        'departure_time',
        'arrival_time',
        'fare',
        'status',
        'is_published',
        'started_at',
        'booking_closed_at',
        'seat_snapshot_json',
        'trip_start_booked_seats',
        'trip_start_available_seats',
        'ended_at',
    ];

    protected $casts = [
        'service_date' => 'date',
        'fare' => 'decimal:2',
        'is_published' => 'boolean',
        'started_at' => 'datetime',
        'booking_closed_at' => 'datetime',
        'ended_at' => 'datetime',
        'trip_start_booked_seats' => 'integer',
        'trip_start_available_seats' => 'integer',
        'seat_snapshot_json' => 'array',
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
    | The route is managed by the System Administrator.
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
    | Fixed Bus Route Service
    |--------------------------------------------------------------------------
    |
    | This identifies the exact operator + bus + master route service
    | configuration used by this trip.
    |
    | It also determines the Starting / Return booking-point timetable.
    |
    */

    public function fixedService(): BelongsTo
    {
        return $this->belongsTo(
            FixedService::class,
            'fixed_service_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Bus
    |--------------------------------------------------------------------------
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
    | Driver
    |--------------------------------------------------------------------------
    */

    public function driver(): BelongsTo
    {
        return $this->belongsTo(
            Staff::class,
            'driver_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Conductor
    |--------------------------------------------------------------------------
    */

    public function conductor(): BelongsTo
    {
        return $this->belongsTo(
            Staff::class,
            'conductor_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Bookings
    |--------------------------------------------------------------------------
    */

    public function bookings(): HasMany
    {
        return $this->hasMany(
            Booking::class,
            'trip_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Latest Live Location
    |--------------------------------------------------------------------------
    */

    public function liveLocation(): HasOne
    {
        return $this->hasOne(
            LiveLocation::class,
            'trip_id'
        )->latestOfMany();
    }
}