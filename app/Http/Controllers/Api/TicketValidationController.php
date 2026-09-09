<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TicketValidationController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Verify QR Ticket
    |--------------------------------------------------------------------------
    */

    public function verify(Request $request)
    {
        $staff = $this->staff($request);

        $data = $request->validate([
            'qr_token' => ['required', 'string', 'max:255'],
            'trip_id' => ['required', 'integer', 'exists:trips,id'],
        ]);

        $tripId = (int) $data['trip_id'];

        $this->assertAssignedStaff($staff, $tripId);

        $booking = $this->ticketBooking(
            $data['qr_token'],
            $tripId
        );

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket is invalid or does not belong to this trip.',
            ], 404);
        }

        if (
            strtolower((string) $booking->payment_status) !== 'paid' ||
            !in_array(
                strtolower((string) $booking->status),
                ['confirmed', 'completed'],
                true
            )
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket is not valid for check-in.',
            ], 422);
        }

        if (
            strtolower((string) ($booking->ticket_status ?? 'valid')) === 'cancelled'
        ) {
            return response()->json([
                'success' => false,
                'message' => 'This ticket has been cancelled.',
            ], 422);
        }

        $passengers = DB::table('booking_passengers')
            ->where('booking_id', $booking->id)
            ->orderByRaw("CAST(REPLACE(seat_number, 'S', '') AS UNSIGNED)")
            ->get()
            ->map(function ($passenger) use ($booking) {
                $name = trim((string) ($passenger->passenger_name ?? ''));

                if ($name === '') {
                    $name = trim(
                        (string) ($booking->primary_passenger_name ?? '')
                    );
                }

                $nic = trim((string) ($passenger->nic ?? ''));

                if ($nic === '') {
                    $nic = trim(
                        (string) ($booking->primary_passenger_nic ?? '')
                    );
                }

                return [
                    'id' => (int) $passenger->id,
                    'booking_passenger_id' => (int) $passenger->id,
                    'passenger_name' => $name !== '' ? $name : '-',
                    'name' => $name !== '' ? $name : '-',
                    'passenger_nic' => $nic !== '' ? $nic : '-',
                    'nic' => $nic !== '' ? $nic : '-',
                    'seat_number' => $passenger->seat_number ?: '-',
                    'gender' => !empty($passenger->gender)
                        ? strtoupper((string) $passenger->gender)
                        : null,
                    'checked_in_at' => $passenger->checked_in_at,
                    'checked_in' => $passenger->checked_in_at !== null,
                ];
            })
            ->values();

        $primaryPassenger = $passengers->first();

        $primaryPassengerName =
            $primaryPassenger['passenger_name'] ??
            ($booking->primary_passenger_name ?: '-');

        $primaryPassengerNic =
            $primaryPassenger['passenger_nic'] ??
            ($booking->primary_passenger_nic ?: '-');

        return response()->json([
            'success' => true,
            'ticket' => [
                'booking_id' => (int) $booking->id,
                'booking_reference' => $booking->booking_reference,
                'company_name' => $booking->company_name ?? null,
                'bus_name' => $booking->bus_name ?? null,
                'bus_number' => $booking->bus_number ?? null,
                'trip_id' => (int) $booking->trip_id,
                'trip_code' => $booking->trip_code,
                'trip_status' => $booking->trip_status,
                'ticket_status' => $booking->ticket_status ?? 'valid',
                'primary_passenger_name' => $primaryPassengerName,
                'primary_passenger_nic' => $primaryPassengerNic,
                'boarding_stop' => $booking->boarding_stop ?: '-',
                'dropoff_stop' => $booking->dropoff_stop ?: '-',
                'passengers' => $passengers,
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Individual Passenger Check-In
    |--------------------------------------------------------------------------
    */

    public function checkIn(Request $request)
    {
        $staff = $this->staff($request);

        $data = $request->validate([
            'trip_id' => ['required', 'integer', 'exists:trips,id'],
            'booking_passenger_id' => [
                'required',
                'integer',
                'exists:booking_passengers,id',
            ],
        ]);

        $tripId = (int) $data['trip_id'];
        $bookingPassengerId = (int) $data['booking_passenger_id'];

        $this->assertAssignedStaff($staff, $tripId);

        return DB::transaction(function () use (
            $staff,
            $tripId,
            $bookingPassengerId
        ) {
            $passenger = DB::table('booking_passengers as bp')
                ->join('bookings as b', 'b.id', '=', 'bp.booking_id')
                ->join('trips as t', 't.id', '=', 'b.trip_id')
                ->where('bp.id', $bookingPassengerId)
                ->where('b.trip_id', $tripId)
                ->select(
                    'bp.*',
                    'b.id as booking_id',
                    'b.booking_reference',
                    'b.primary_passenger_name',
                    'b.primary_passenger_nic',
                    'b.ticket_status',
                    'b.status as booking_status',
                    'b.payment_status',
                    't.status as trip_status'
                )
                ->lockForUpdate()
                ->first();

            if (!$passenger) {
                return response()->json([
                    'success' => false,
                    'message' => 'Passenger does not belong to this trip.',
                ], 404);
            }

            if (
                strtolower((string) $passenger->payment_status) !== 'paid' ||
                strtolower((string) $passenger->booking_status) !== 'confirmed'
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Passenger ticket is not valid for check-in.',
                ], 422);
            }

            if (
                strtolower((string) ($passenger->ticket_status ?? 'valid')) === 'cancelled'
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'This ticket has been cancelled.',
                ], 422);
            }

            if (
                strtolower((string) $passenger->trip_status) !== 'active'
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Passenger check-in is available only while the trip is active.',
                ], 422);
            }

            if ($passenger->checked_in_at !== null) {
                return response()->json([
                    'success' => true,
                    'message' => 'Passenger has already been checked in.',
                    'already_checked_in' => true,
                    'booking_id' => (int) $passenger->booking_id,
                    'booking_reference' => $passenger->booking_reference,
                    'booking_passenger_id' => (int) $passenger->id,
                    'passenger_name' => $this->passengerName($passenger),
                    'passenger_nic' => $this->passengerNic($passenger),
                    'seat_number' => $passenger->seat_number ?: '-',
                    'checked_in_at' => $passenger->checked_in_at,
                ]);
            }

            $checkInTime = now();

            $update = [
                'checked_in_at' => $checkInTime,
                'updated_at' => $checkInTime,
            ];

            if (
                Schema::hasColumn(
                    'booking_passengers',
                    'checked_in_by_staff_id'
                )
            ) {
                $update['checked_in_by_staff_id'] = $staff->id;
            }

            DB::table('booking_passengers')
                ->where('id', $passenger->id)
                ->update($update);

            $remainingPassengers = DB::table('booking_passengers')
                ->where('booking_id', $passenger->booking_id)
                ->whereNull('checked_in_at')
                ->count();

            if (
                $remainingPassengers === 0 &&
                Schema::hasColumn('bookings', 'ticket_status')
            ) {
                DB::table('bookings')
                    ->where('id', $passenger->booking_id)
                    ->update([
                        'ticket_status' => 'used',
                        'updated_at' => now(),
                    ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Passenger checked in successfully.',
                'already_checked_in' => false,
                'booking_id' => (int) $passenger->booking_id,
                'booking_reference' => $passenger->booking_reference,
                'booking_passenger_id' => (int) $passenger->id,
                'passenger_name' => $this->passengerName($passenger),
                'passenger_nic' => $this->passengerNic($passenger),
                'seat_number' => $passenger->seat_number ?: '-',
                'checked_in_at' => $checkInTime->toIso8601String(),
                'all_passengers_checked_in' => $remainingPassengers === 0,
            ]);
        }, 3);
    }

    /*
    |--------------------------------------------------------------------------
    | Find Booking from QR Token
    |--------------------------------------------------------------------------
    */

    private function ticketBooking(string $token, int $tripId)
    {
        return DB::table('bookings')
            ->join('trips', 'trips.id', '=', 'bookings.trip_id')
            ->join('buses', 'buses.id', '=', 'trips.bus_id')
            ->leftJoin(
                'operators',
                'operators.id',
                '=',
                'trips.operator_id'
            )
            ->where('bookings.ticket_token', trim($token))
            ->where('bookings.trip_id', $tripId)
            ->select(
                'bookings.*',
                'trips.trip_code',
                'trips.status as trip_status',
                'buses.bus_name',
                'buses.bus_number',
                'operators.company_name as company_name'
            )
            ->first();
    }

    /*
    |--------------------------------------------------------------------------
    | Passenger Helpers
    |--------------------------------------------------------------------------
    */

    private function passengerName($passenger): string
    {
        $name = trim(
            (string) ($passenger->passenger_name ?? '')
        );

        if ($name !== '') {
            return $name;
        }

        $primary = trim(
            (string) ($passenger->primary_passenger_name ?? '')
        );

        return $primary !== '' ? $primary : '-';
    }

    private function passengerNic($passenger): string
    {
        $nic = trim(
            (string) ($passenger->nic ?? '')
        );

        if ($nic !== '') {
            return $nic;
        }

        $primary = trim(
            (string) ($passenger->primary_passenger_nic ?? '')
        );

        return $primary !== '' ? $primary : '-';
    }

    /*
    |--------------------------------------------------------------------------
    | Logged-In Staff
    |--------------------------------------------------------------------------
    */

    private function staff(Request $request)
    {
        $staff = $request->attributes->get('staff');

        abort_unless(
            $staff,
            401,
            'Staff authentication required.'
        );

        return $staff;
    }

    /*
    |--------------------------------------------------------------------------
    | Verify Staff Assignment
    |--------------------------------------------------------------------------
    */

    private function assertAssignedStaff(
        $staff,
        int $tripId
    ): void {
        $assigned = DB::table('trips')
            ->where('id', $tripId)
            ->where('operator_id', $staff->operator_id)
            ->where(function ($query) use ($staff) {
                $query
                    ->where('driver_id', $staff->id)
                    ->orWhere('conductor_id', $staff->id);
            })
            ->exists();

        abort_unless(
            $assigned,
            403,
            'You are not assigned to this trip.'
        );
    }
}