<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Trip extends Model
{
    protected $fillable = [
        'operator_id',
        'route_id',
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

    public function driver()
    {
        return $this->belongsTo(Staff::class, 'driver_id');
    }

    public function conductor()
    {
        return $this->belongsTo(Staff::class, 'conductor_id');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function liveLocation()
    {
        return $this->hasOne(LiveLocation::class)->latestOfMany();
    }
}