<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
        'distance_km' => 'decimal:2',
        'base_fare' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }

    public function stops()
    {
        return $this->hasMany(RouteStop::class)
            ->orderBy('stop_order');
    }

    public function trips()
    {
        return $this->hasMany(Trip::class);
    }

    public function fixedServices()
    {
        return $this->hasMany(FixedService::class);
    }
}