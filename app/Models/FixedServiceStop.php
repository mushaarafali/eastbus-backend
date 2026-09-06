<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FixedServiceStop extends Model
{
    protected $fillable = [
        'fixed_service_id',
        'route_stop_id',
        'direction',
        'stop_order',
        'arrival_time',
        'departure_time',
        'boarding_allowed',
        'dropoff_allowed',
    ];

    protected $casts = [
        'stop_order' => 'integer',
        'boarding_allowed' => 'boolean',
        'dropoff_allowed' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Fixed Service
    |--------------------------------------------------------------------------
    |
    | This row belongs to one exact operator bus service.
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
    | Master Route Stop
    |--------------------------------------------------------------------------
    |
    | The actual stop name, KM, fare stage and location details
    | come from the System Admin managed route_stops table.
    |
    */

    public function routeStop(): BelongsTo
    {
        return $this->belongsTo(
            RouteStop::class,
            'route_stop_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Stop Name
    |--------------------------------------------------------------------------
    |
    | Example:
    | $serviceStop->stop_name
    |
    */

    public function getStopNameAttribute(): ?string
    {
        return $this->routeStop?->name;
    }

    /*
    |--------------------------------------------------------------------------
    | Distance From Origin
    |--------------------------------------------------------------------------
    |
    | Example:
    | $serviceStop->distance_from_origin_km
    |
    */

    public function getDistanceFromOriginKmAttribute(): ?string
    {
        return $this->routeStop?->distance_from_origin_km;
    }

    /*
    |--------------------------------------------------------------------------
    | Fare Stage Number
    |--------------------------------------------------------------------------
    */

    public function getFareStageNoAttribute(): ?int
    {
        return $this->routeStop?->fare_stage_no;
    }

    /*
    |--------------------------------------------------------------------------
    | Latitude
    |--------------------------------------------------------------------------
    */

    public function getLatitudeAttribute(): ?string
    {
        return $this->routeStop?->latitude;
    }

    /*
    |--------------------------------------------------------------------------
    | Longitude
    |--------------------------------------------------------------------------
    */

    public function getLongitudeAttribute(): ?string
    {
        return $this->routeStop?->longitude;
    }

    /*
    |--------------------------------------------------------------------------
    | Booking Radius
    |--------------------------------------------------------------------------
    */

    public function getBookingRadiusKmAttribute(): ?string
    {
        return $this->routeStop?->booking_radius_km;
    }

    /*
    |--------------------------------------------------------------------------
    | Scheduled Time
    |--------------------------------------------------------------------------
    |
    | Convenience accessor.
    | Departure time is preferred; otherwise arrival time is used.
    |
    */

    public function getScheduleTimeAttribute(): ?string
    {
        return $this->departure_time
            ?? $this->arrival_time
            ?? null;
    }
}