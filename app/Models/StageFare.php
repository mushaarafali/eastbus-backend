<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StageFare extends Model
{
    protected $fillable = [
        'service_class',
        'stage_no',
        'fare',
        'effective_from',
    ];

    protected $casts = [
        'stage_no' => 'integer',
        'fare' => 'decimal:2',
        'effective_from' => 'date',
    ];
}