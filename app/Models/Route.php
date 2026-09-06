<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Route extends Model
{
    protected $fillable = [
        'route_number',
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

    /*
    |--------------------------------------------------------------------------
    | Fixed Road Way Stops
    |--------------------------------------------------------------------------
    |
    | Full System Admin managed roadway for this Master Route.
    |
    | Example:
    |
    | Route 76
    | Akkaraipattu
    | Addalaichenai
    | Kalmunai
    | Batticaloa
    | Trincomalee
    |
    */

    public function stops(): HasMany
    {
        return $this->hasMany(
            RouteStop::class,
            'route_id'
        )->orderBy('stop_order');
    }

    /*
    |--------------------------------------------------------------------------
    | Fixed Bus Route Services
    |--------------------------------------------------------------------------
    |
    | Multiple operators and buses can use the same Master Route.
    |
    | Example:
    |
    | Route 76
    |   ├── Bus A Service
    |   ├── Bus B Service
    |   └── Bus C Service
    |
    | Each Fixed Service can have different:
    |
    | - Bus
    | - Operator
    | - Starting booking points
    | - Return booking points
    | - Starting timetable
    | - Return timetable
    |
    */

    public function fixedServices(): HasMany
    {
        return $this->hasMany(
            FixedService::class,
            'route_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Trips
    |--------------------------------------------------------------------------
    |
    | Trips running on this Master Route.
    |
    | Each trip is also linked to an exact fixed_service_id.
    |
    */

    public function trips(): HasMany
    {
        return $this->hasMany(
            Trip::class,
            'route_id'
        );
    }
}