<?php
// Copy this file to database/seeders/TripAppDemoSeeder.php in the Laravel portal project.
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
class TripAppDemoSeeder extends Seeder {
 public function run(): void {
  $trip=DB::table('trips')->where('trip_code','TRP-DEMO-001')->first(); if(!$trip)return;
  $uid=DB::table('users')->where('email','passenger.demo@eastbus.lk')->value('id');
  if(!$uid)$uid=DB::table('users')->insertGetId(['name'=>'Demo Passenger','email'=>'passenger.demo@eastbus.lk','phone'=>'+94770000001','password'=>Hash::make('Passenger@123'),'role'=>'passenger','is_active'=>1,'created_at'=>now(),'updated_at'=>now()]);
  $bid=DB::table('bookings')->where('booking_reference','EBK-DEMO-0001')->value('id');
  if(!$bid)$bid=DB::table('bookings')->insertGetId(['trip_id'=>$trip->id,'passenger_user_id'=>$uid,'booking_reference'=>'EBK-DEMO-0001','seat_numbers'=>json_encode(['S1']),'passenger_count'=>1,'subtotal'=>$trip->fare,'discount'=>0,'total'=>$trip->fare,'payment_status'=>'paid','status'=>'confirmed','created_at'=>now(),'updated_at'=>now()]);
  if(!DB::table('payments')->where('booking_id',$bid)->exists())DB::table('payments')->insert(['booking_id'=>$bid,'transaction_id'=>'TXN-DEMO-0001','amount'=>$trip->fare,'method'=>'card','status'=>'success','paid_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
  $pid=DB::table('booking_passengers')->where('booking_id',$bid)->where('seat_number','S1')->value('id');
  if(!$pid)$pid=DB::table('booking_passengers')->insertGetId(['booking_id'=>$bid,'passenger_name'=>'Demo Passenger','nic'=>'200012345678','seat_number'=>'S1','created_at'=>now(),'updated_at'=>now()]);
  DB::table('tickets')->updateOrInsert(['ticket_code'=>'EBK-TICKET-DEMO-001'],['booking_id'=>$bid,'booking_passenger_id'=>$pid,'status'=>'valid','created_at'=>now(),'updated_at'=>now()]);
 }
}
