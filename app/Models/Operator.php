<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Operator extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'company_name',
        'owner_name',
        'phone',
        'email',
        'address',
        'permit_or_registration_no',
        'status',
        'is_published',
    ];

    protected $casts = [
        'is_published' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | User
    |--------------------------------------------------------------------------
    */

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'user_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Buses
    |--------------------------------------------------------------------------
    */

    public function buses(): HasMany
    {
        return $this->hasMany(
            Bus::class,
            'operator_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Staff
    |--------------------------------------------------------------------------
    */

    public function staff(): HasMany
    {
        return $this->hasMany(
            Staff::class,
            'operator_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Fixed Bus Route Services
    |--------------------------------------------------------------------------
    |
    | Master Routes are managed by the System Administrator.
    |
    | The operator does not own Route records directly.
    | Instead, an operator connects their bus to a Master Route
    | through fixed_services.
    |
    */

    public function fixedServices(): HasMany
    {
        return $this->hasMany(
            FixedService::class,
            'operator_id'
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
            'operator_id'
        );
    }
}