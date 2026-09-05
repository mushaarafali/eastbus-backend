<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }

    public function route()
    {
        return $this->belongsTo(Route::class);
    }

    public function bus()
    {
        return $this->belongsTo(Bus::class);
    }

    public function stops()
    {
        return $this->hasMany(FixedServiceStop::class)
            ->orderBy('direction')
            ->orderBy('stop_order');
    }

    public function startingStops()
    {
        return $this->hasMany(FixedServiceStop::class)
            ->where('direction', 'starting')
            ->orderBy('stop_order');
    }

    public function returnStops()
    {
        return $this->hasMany(FixedServiceStop::class)
            ->where('direction', 'return')
            ->orderBy('stop_order');
    }

    public function startingBookingStops()
    {
        return $this->hasMany(FixedServiceStop::class)
            ->where('direction', 'starting')
            ->where(function ($query) {
                $query->where('boarding_allowed', true)
                    ->orWhere('dropoff_allowed', true);
            })
            ->orderBy('stop_order');
    }

    public function returnBookingStops()
    {
        return $this->hasMany(FixedServiceStop::class)
            ->where('direction', 'return')
            ->where(function ($query) {
                $query->where('boarding_allowed', true)
                    ->orWhere('dropoff_allowed', true);
            })
            ->orderBy('stop_order');
    }
}