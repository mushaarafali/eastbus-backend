<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EastBusMailService
{
    public function send(string $to, string $subject, string $title, string $message, array $details = [], ?string $badge = null): void
    {
        if (trim($to) === '') {
            return;
        }

        try {
            $html = view('emails.eastbus', [
                'title' => $title,
                'message' => $message,
                'details' => $details,
                'badge' => $badge,
            ])->render();

            $apiKey = env('MAILJET_API_KEY');
            $secretKey = env('MAILJET_SECRET_KEY');

            if (empty($apiKey) || empty($secretKey)) {
                throw new \RuntimeException('Mailjet API credentials are not configured.');
            }

            $response = Http::withBasicAuth($apiKey, $secretKey)
                ->acceptJson()
                ->post('https://api.mailjet.com/v3.1/send', [
                    'Messages' => [
                        [
                            'From' => [
                                'Email' => env('MAIL_FROM_ADDRESS', 'admin.mushaa@gmail.com'),
                                'Name' => env('MAIL_FROM_NAME', 'EastBus.lk'),
                            ],
                            'To' => [
                                [
                                    'Email' => $to,
                                ],
                            ],
                            'Subject' => $subject,
                            'HTMLPart' => $html,
                            'TextPart' => $message,
                        ],
                    ],
                ]);

            if (!$response->successful()) {
                throw new \RuntimeException(
                    'Mailjet send failed: ' . $response->body()
                );
            }
        } catch (\Throwable $e) {
            Log::error('EastBus email failed', [
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function otp(string $to, string $name, string $otp, string $purpose = 'Email Verification'): void
    {
        $this->send(
            $to,
            "EastBus.lk - {$purpose} Code",
            $purpose,
            "Hello {$name}, use the secure code below to continue.",
            [
                'Verification Code' => $otp,
                'Expires In' => '10 minutes',
                'Security' => 'Do not share this code with anyone.',
            ],
            'SECURITY'
        );
    }

    public function welcome(object $user): void
    {
        $this->send(
            $user->email,
            'Welcome to EastBus.lk',
            'Welcome to EastBus.lk',
            "Hello {$user->name}, your passenger account has been verified successfully.",
            [
                'Account' => $user->email,
                'Status' => 'Verified & Active',
            ],
            'WELCOME'
        );
    }

    public function securityAlert(object $user, string $ip, string $device): void
    {
        $this->send(
            $user->email,
            'EastBus.lk - Security Alert',
            'Suspicious Login Attempts',
            'We detected repeated unsuccessful login attempts on your EastBus.lk account.',
            [
                'Time' => now()->format('d M Y, h:i A'),
                'IP Address' => $ip ?: 'Unknown',
                'Device' => $device ?: 'Unknown',
                'Action' => 'Reset your password if this was not you.',
            ],
            'SECURITY ALERT'
        );
    }

    public function accountStatus(object $user, string $status): void
    {
        $this->send(
            $user->email,
            "EastBus.lk - Account {$status}",
            "Account {$status}",
            "Hello {$user->name}, your EastBus.lk account status has been changed.",
            [
                'New Status' => $status,
            ],
            'ACCOUNT'
        );
    }

    public function operatorStatus(object $operator): void
    {
        $status = ucfirst((string) $operator->status);

        $this->send(
            $operator->email,
            "EastBus.lk - Operator {$status}",
            "Operator Account {$status}",
            "Hello {$operator->owner_name}, the status of {$operator->company_name} has been updated by EastBus administration.",
            [
                'Company' => $operator->company_name,
                'Status' => $status,
            ],
            'OPERATOR'
        );
    }

    public function staffCreated(object $staff, string $plainPassword): void
    {
        if (empty($staff->email)) {
            return;
        }

        $this->send(
            $staff->email,
            'EastBus.lk - Trip Management Account',
            'Your Staff Account Is Ready',
            "Hello {$staff->full_name}, your {$staff->role} account has been created.",
            [
                'Login ID' => $staff->login_id,
                'Temporary Password' => $plainPassword,
                'Role' => ucfirst($staff->role),
                'Security' => 'Keep these credentials private.',
            ],
            'STAFF ACCOUNT'
        );
    }

    public function passwordChanged(object $user): void
    {
        $this->send(
            $user->email,
            'EastBus.lk - Password Changed',
            'Password Changed Successfully',
            "Hello {$user->name}, your EastBus.lk password was changed successfully.",
            [
                'Time' => now()->format('d M Y, h:i A'),
                'Security' => 'If this was not you, contact EastBus support immediately.',
            ],
            'SECURITY'
        );
    }

    public function bookingTicket(int $bookingId, ?string $transactionReference = null): void
    {
        $booking = DB::table('bookings as b')
            ->join('users as u', 'u.id', '=', 'b.passenger_user_id')
            ->join('trips as t', 't.id', '=', 'b.trip_id')
            ->join('buses as bus', 'bus.id', '=', 't.bus_id')
            ->join('routes as r', 'r.id', '=', 't.route_id')
            ->leftJoin('operators as o', 'o.id', '=', 't.operator_id')
            ->where('b.id', $bookingId)
            ->select(
                'b.*',
                'u.email',
                'u.name',
                't.service_date',
                't.departure_time',
                't.trip_code',
                't.operator_id',
                'bus.bus_number',
                'r.origin',
                'r.destination',
                'o.company_name'
            )
            ->first();

        if (!$booking) {
            return;
        }

        $decodedSeats = [];

        if (is_string($booking->seat_numbers)) {
            $decodedSeats = json_decode($booking->seat_numbers, true);

            if (!is_array($decodedSeats)) {
                $decodedSeats = [$booking->seat_numbers];
            }
        }

        $seats = !empty($decodedSeats)
            ? implode(', ', $decodedSeats)
            : (string) $booking->seat_numbers;

        $token = $booking->ticket_token
            ?? hash('sha256', $booking->booking_reference . '|' . $booking->id);

        $this->send(
            $booking->email,
            'EastBus.lk - Booking Confirmed & QR E-Ticket',
            'Booking Confirmed',
            "Hello {$booking->name}, payment was successful. Your booking is confirmed. Open My Tickets in the EastBus app to display and scan the QR ticket.",
            [
                'Booking Reference' => $booking->booking_reference,
                'Company' => $booking->company_name ?: 'EastBus Operator',
                'Bus' => $booking->bus_number,
                'Route' => ($booking->boarding_stop ?? $booking->origin) . ' → ' . ($booking->dropoff_stop ?? $booking->destination),
                'Travel Date' => $booking->service_date,
                'Departure' => $booking->departure_time,
                'Seats' => $seats,
                'Amount' => 'LKR ' . number_format((float) $booking->total, 2),
                'Transaction' => $transactionReference ?: 'Confirmed',
                'QR Ticket Code' => $token,
            ],
            'E-TICKET'
        );

        $operatorEmail = DB::table('operators')
            ->where('id', $booking->operator_id)
            ->value('email');

        if ($operatorEmail) {
            $this->send(
                $operatorEmail,
                'EastBus.lk - New Booking',
                'New Passenger Booking',
                'A new paid booking has been confirmed for your service.',
                [
                    'Booking' => $booking->booking_reference,
                    'Bus' => $booking->bus_number,
                    'Seats' => $seats,
                    'Amount' => 'LKR ' . number_format((float) $booking->total, 2),
                ],
                'NEW BOOKING'
            );
        }
    }

    public function bookingCancelled(object $user, object $booking): void
    {
        $this->send(
            $user->email,
            'EastBus.lk - Booking Cancelled',
            'Booking Cancelled',
            "Hello {$user->name}, your booking has been cancelled.",
            [
                'Booking Reference' => $booking->booking_reference,
                'Refund Status' => $booking->payment_status === 'paid'
                    ? 'Refund review required'
                    : 'No payment refund required',
            ],
            'CANCELLED'
        );
    }

    public function checkIn(string $email, string $name, string $reference, string $seat): void
    {
        $this->send(
            $email,
            'EastBus.lk - Check-in Confirmed',
            'Passenger Check-in Successful',
            "Hello {$name}, your QR ticket was checked in successfully.",
            [
                'Booking' => $reference,
                'Seat' => $seat,
                'Check-in Time' => now()->format('d M Y, h:i A'),
            ],
            'CHECK-IN'
        );
    }

    public function emergencyToAdmins(string $message, string $tripCode = ''): void
    {
        $admins = DB::table('users')
            ->where('role', 'admin')
            ->where('is_active', true)
            ->pluck('email');

        foreach ($admins as $email) {
            if (empty($email)) {
                continue;
            }

            try {
                $this->send(
                    $email,
                    'URGENT - EastBus Emergency Alert',
                    'Emergency Alert',
                    $message,
                    [
                        'Trip' => $tripCode ?: 'Unknown',
                        'Reported At' => now()->format('d M Y, h:i A'),
                    ],
                    'URGENT'
                );
            } catch (\Throwable $e) {
                Log::error('Emergency admin email failed', [
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}