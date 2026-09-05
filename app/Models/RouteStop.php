<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RouteStop extends Model
{
    protected $fillable = [
        'route_id',
        'name',
        'stop_order',
        'fare_stage_no',
        'latitude',
        'longitude',
        'distance_from_origin',
        'booking_radius_km',
        'boarding_allowed',
        'dropoff_allowed',
    ];

    protected $casts = [
        'stop_order' => 'integer',
        'fare_stage_no' => 'integer',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'distance_from_origin' => 'decimal:2',
        'booking_radius_km' => 'decimal:2',
        'boarding_allowed' => 'boolean',
        'dropoff_allowed' => 'boolean',
    ];

    public function route()
    {
        return $this->belongsTo(Route::class);
    }

    public function fixedServiceStops()
    {
        return $this->hasMany(FixedServiceStop::class);
    }
}