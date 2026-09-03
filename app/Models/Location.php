<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Location extends Model
{
    protected $fillable = ['name','district','province','latitude','longitude','is_active'];
    protected $casts = ['latitude'=>'decimal:7','longitude'=>'decimal:7','is_active'=>'boolean'];
}
