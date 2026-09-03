<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class RouteStop extends Model {
 protected $fillable=['route_id','name','stop_order','latitude','longitude','booking_radius_km','boarding_allowed','dropoff_allowed'];
 protected $casts=['latitude'=>'decimal:7','longitude'=>'decimal:7','booking_radius_km'=>'decimal:2','boarding_allowed'=>'boolean','dropoff_allowed'=>'boolean'];
 public function route(){return $this->belongsTo(Route::class);}
}
