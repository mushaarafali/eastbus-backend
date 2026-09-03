<?php
namespace Database\Seeders; use Illuminate\Database\Seeder; use App\Models\{User,Operator,Bus,Route,Staff,Trip,Booking,Payment}; use Illuminate\Support\Facades\Hash;
class DatabaseSeeder extends Seeder { public function run():void{
 $admin=User::updateOrCreate(['email'=>env('ADMIN_EMAIL','admin@eastbus.lk')],['name'=>'EastBus Administrator','phone'=>'+94771234567','password'=>Hash::make(env('ADMIN_PASSWORD','EastBus@123')),'role'=>'admin','is_active'=>true]);
 $u=User::updateOrCreate(['email'=>'operator@oceanstar.lk'],['name'=>'Mohamed Ameer','phone'=>'+94771234560','password'=>Hash::make('Operator@123'),'role'=>'operator','is_active'=>true]);
 $o=Operator::updateOrCreate(['user_id'=>$u->id],['company_name'=>'Ocean Star Travels','owner_name'=>'Mohamed Ameer','phone'=>'+94771234560','email'=>$u->email,'address'=>'Batticaloa, Sri Lanka','permit_or_registration_no'=>'OP-EBK-001','status'=>'active','is_published'=>true]);
 $bus=Bus::firstOrCreate(['bus_number'=>'ND 7434'],['operator_id'=>$o->id,'bus_name'=>'Ocean Star','route_permit_number'=>'NTC-RP-7434','seat_count'=>52,'bus_type'=>'Normal','facilities'=>'USB Charging, Wi-Fi, Reclining Seats','is_active'=>true]); if($bus->seats()->count()===0) for($i=1;$i<=52;$i++)$bus->seats()->create(['seat_number'=>'S'.$i]);
 $route=Route::firstOrCreate(['operator_id'=>$o->id,'name'=>'Batticaloa - Colombo'],['origin'=>'Batticaloa','destination'=>'Colombo','duration_minutes'=>450,'distance_km'=>320,'base_fare'=>1800,'is_active'=>true]);
 $driver=Staff::firstOrCreate(['login_id'=>'EBK-DRV-0001'],['operator_id'=>$o->id,'full_name'=>'M. Ashraf','role'=>'driver','nic'=>'901234567V','driving_licence_no'=>'B1234567','ntc_licence_no'=>'NTCD1234567','phone'=>'+94771234567','email'=>'ashraf.driver@email.com','password'=>Hash::make('Driver@123'),'is_active'=>true]);
 $trip=Trip::firstOrCreate(['trip_code'=>'TRP-DEMO-001'],['operator_id'=>$o->id,'route_id'=>$route->id,'bus_id'=>$bus->id,'driver_id'=>$driver->id,'trip_type'=>'starting','service_date'=>now()->addDay()->toDateString(),'departure_time'=>'07:00','arrival_time'=>'14:30','fare'=>1800,'status'=>'scheduled','is_published'=>true]);
 }}
