<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bus extends Model
{
    use HasFactory;

    protected $fillable = [
        'operator_id',
        'bus_number',
        'bus_name',
        'route_permit_number',
        'seat_count',
        'bus_type',
        'facilities',
        'is_active',
    ];

    protected $casts = [
        'seat_count' => 'integer',
        'is_active' => 'boolean',
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
    | Seats
    |--------------------------------------------------------------------------
    */

    public function seats(): HasMany
    {
        return $this->hasMany(
            Seat::class,
            'bus_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Fixed Bus Route Services
    |--------------------------------------------------------------------------
    |
    | A bus does not own a Master Route directly.
    |
    | The System Administrator creates Master Routes.
    | The operator links this bus to a Master Route through
    | the fixed_services table.
    |
    | Example:
    |
    | Bus NB-1234
    |      ↓
    | Fixed Service
    |      ↓
    | Master Route 76
    |
    */

    public function fixedServices(): HasMany
    {
        return $this->hasMany(
            FixedService::class,
            'bus_id'
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
            'bus_id'
        );
    }
}