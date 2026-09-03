<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EastBusMailService
{
    public function send(string $to, string $subject, string $title, string $message, array $details = [], ?string $badge = null): void
    {
        if (trim($to) === '') return;

        try {
            Mail::send('emails.eastbus', compact('title', 'message', 'details', 'badge'), function ($mail) use ($to, $subject) {
                $mail->to($to)->subject($subject);
            });
        } catch (\Throwable $e) {
            Log::warning('EastBus email failed', ['to' => $to, 'subject' => $subject, 'error' => $e->getMessage()]);
        }
    }

    public function otp(string $to, string $name, string $otp, string $purpose = 'Email Verification'): void
    {
        $this->send($to, "EastBus.lk - {$purpose} Code", $purpose, "Hello {$name}, use the secure code below to continue.", [
            'Verification Code' => $otp,
            'Expires In' => '10 minutes',
            'Security' => 'Do not share this code with anyone.',
        ], 'SECURITY');
    }

    public function welcome(object $user): void
    {
        $this->send($user->email, 'Welcome to EastBus.lk', 'Welcome to EastBus.lk', "Hello {$user->name}, your passenger account has been verified successfully.", [
            'Account' => $user->email,
            'Status' => 'Verified & Active',
        ], 'WELCOME');
    }

    public function securityAlert(object $user, string $ip, string $device): void
    {
        $this->send($user->email, 'EastBus.lk - Security Alert', 'Suspicious Login Attempts', "We detected repeated unsuccessful login attempts on your EastBus.lk account.", [
            'Time' => now()->format('d M Y, h:i A'), 'IP Address' => $ip ?: 'Unknown', 'Device' => $device ?: 'Unknown',
            'Action' => 'Reset your password if this was not you.',
        ], 'SECURITY ALERT');
    }

    public function accountStatus(object $user, string $status): void
    {
        $this->send($user->email, "EastBus.lk - Account {$status}", "Account {$status}", "Hello {$user->name}, your EastBus.lk account status has been changed.", ['New Status' => $status], 'ACCOUNT');
    }

    public function operatorStatus(object $operator): void
    {
        $status = ucfirst((string) $operator->status);
        $this->send($operator->email, "EastBus.lk - Operator {$status}", "Operator Account {$status}", "Hello {$operator->owner_name}, the status of {$operator->company_name} has been updated by EastBus administration.", ['Company' => $operator->company_name, 'Status' => $status], 'OPERATOR');
    }

    public function staffCreated(object $staff, string $plainPassword): void
    {
        if (!$staff->email) return;
        $this->send($staff->email, 'EastBus.lk - Trip Management Account', 'Your Staff Account Is Ready', "Hello {$staff->full_name}, your {$staff->role} account has been created.", [
            'Login ID' => $staff->login_id, 'Temporary Password' => $plainPassword, 'Role' => ucfirst($staff->role),
            'Security' => 'Keep these credentials private.',
        ], 'STAFF ACCOUNT');
    }

    public function passwordChanged(object $user): void
    {
        $this->send($user->email, 'EastBus.lk - Password Changed', 'Password Changed Successfully', "Hello {$user->name}, your EastBus.lk password was changed successfully.", ['Time' => now()->format('d M Y, h:i A'), 'Security' => 'If this was not you, contact EastBus support immediately.'], 'SECURITY');
    }

    public function bookingTicket(int $bookingId, ?string $transactionReference = null): void
    {
        $b = DB::table('bookings as b')->join('users as u','u.id','=','b.passenger_user_id')->join('trips as t','t.id','=','b.trip_id')->join('buses as bus','bus.id','=','t.bus_id')->join('routes as r','r.id','=','t.route_id')->leftJoin('operators as o','o.id','=','t.operator_id')->where('b.id',$bookingId)->select('b.*','u.email','u.name','t.service_date','t.departure_time','t.trip_code','bus.bus_number','r.origin','r.destination','o.company_name')->first();
        if (!$b) return;
        $seats = is_string($b->seat_numbers) ? implode(', ', json_decode($b->seat_numbers, true) ?: [$b->seat_numbers]) : (string) $b->seat_numbers;
        $token = $b->ticket_token ?? hash('sha256', $b->booking_reference.'|'.$b->id);
        $this->send($b->email, 'EastBus.lk - Booking Confirmed & QR E-Ticket', 'Booking Confirmed', "Hello {$b->name}, payment was successful. Your booking is confirmed. Open My Tickets in the EastBus app to display and scan the QR ticket.", [
            'Booking Reference' => $b->booking_reference, 'Company' => $b->company_name ?: 'EastBus Operator', 'Bus' => $b->bus_number,
            'Route' => ($b->boarding_stop ?? $b->origin).' → '.($b->dropoff_stop ?? $b->destination), 'Travel Date' => $b->service_date,
            'Departure' => $b->departure_time, 'Seats' => $seats, 'Amount' => 'LKR '.number_format((float)$b->total,2),
            'Transaction' => $transactionReference ?: 'Confirmed', 'QR Ticket Code' => $token,
        ], 'E-TICKET');

        if ($b->company_name) {
            $operatorEmail = DB::table('operators')->where('id', DB::table('trips')->where('id',$b->trip_id)->value('operator_id'))->value('email');
            if ($operatorEmail) $this->send($operatorEmail, 'EastBus.lk - New Booking', 'New Passenger Booking', 'A new paid booking has been confirmed for your service.', ['Booking' => $b->booking_reference, 'Bus' => $b->bus_number, 'Seats' => $seats, 'Amount' => 'LKR '.number_format((float)$b->total,2)], 'NEW BOOKING');
        }
    }

    public function bookingCancelled(object $user, object $booking): void
    {
        $this->send($user->email, 'EastBus.lk - Booking Cancelled', 'Booking Cancelled', "Hello {$user->name}, your booking has been cancelled.", ['Booking Reference' => $booking->booking_reference, 'Refund Status' => $booking->payment_status === 'paid' ? 'Refund review required' : 'No payment refund required'], 'CANCELLED');
    }

    public function checkIn(string $email, string $name, string $reference, string $seat): void
    {
        $this->send($email, 'EastBus.lk - Check-in Confirmed', 'Passenger Check-in Successful', "Hello {$name}, your QR ticket was checked in successfully.", ['Booking' => $reference, 'Seat' => $seat, 'Check-in Time' => now()->format('d M Y, h:i A')], 'CHECK-IN');
    }

    public function emergencyToAdmins(string $message, string $tripCode = ''): void
    {
        $admins = DB::table('users')->where('role','admin')->where('is_active',true)->pluck('email');
        foreach ($admins as $email) $this->send($email, 'URGENT - EastBus Emergency Alert', 'Emergency Alert', $message, ['Trip' => $tripCode ?: 'Unknown', 'Reported At' => now()->format('d M Y, h:i A')], 'URGENT');
    }
}
