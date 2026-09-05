<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FixedServiceStop extends Model
{
    protected $fillable = [
        'fixed_service_id',
        'route_stop_id',
        'direction',
        'stop_name',
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

    public function fixedService()
    {
        return $this->belongsTo(FixedService::class);
    }

    public function routeStop()
    {
        return $this->belongsTo(RouteStop::class);
    }
}