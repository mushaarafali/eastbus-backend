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
            'qr_token' => [
                'required',
                'string',
                'max:255',
            ],

            'trip_id' => [
                'required',
                'integer',
                'exists:trips,id',
            ],
        ]);

        $tripId = (int) $data['trip_id'];

        $this->assertAssignedStaff(
            $staff,
            $tripId
        );

        $booking = $this->ticketBooking(
            $data['qr_token'],
            $tripId
        );

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Ticket is invalid or does not belong to this trip.',
            ], 404);
        }

        if (
            strtolower((string) $booking->payment_status) !== 'paid'
            ||
            strtolower((string) $booking->status) !== 'confirmed'
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Ticket is not valid for check-in.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Booking Passengers
        |--------------------------------------------------------------------------
        */

        $passengers = DB::table('booking_passengers')
            ->where(
                'booking_id',
                $booking->id
            )
            ->orderBy('seat_number')
            ->get()
            ->map(function ($passenger) {
                $name = $this->firstExistingValue(
                    $passenger,
                    [
                        'passenger_name',
                        'name',
                        'full_name',
                    ]
                );

                $nic = $this->firstExistingValue(
                    $passenger,
                    [
                        'passenger_nic',
                        'nic',
                        'nic_number',
                        'national_id',
                        'national_id_number',
                    ]
                );

                $seat = $this->firstExistingValue(
                    $passenger,
                    [
                        'seat_number',
                        'seat',
                    ]
                );

                $gender = $this->firstExistingValue(
                    $passenger,
                    [
                        'gender',
                    ]
                );

                $checkedInAt = $this->firstExistingValue(
                    $passenger,
                    [
                        'checked_in_at',
                    ],
                    null
                );

                return [
                    'id' =>
                        $passenger->id,

                    'booking_passenger_id' =>
                        $passenger->id,

                    'passenger_name' =>
                        $name ?: '-',

                    'name' =>
                        $name ?: '-',

                    'passenger_nic' =>
                        $nic ?: '-',

                    'nic' =>
                        $nic ?: '-',

                    'seat_number' =>
                        $seat ?: '-',

                    'gender' =>
                        $gender ?: null,

                    'checked_in_at' =>
                        $checkedInAt,

                    'checked_in' =>
                        !empty($checkedInAt),
                ];
            })
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Primary Passenger
        |--------------------------------------------------------------------------
        */

        $primaryPassenger =
            $passengers->first();

        $primaryPassengerName =
            $primaryPassenger['passenger_name']
            ?? '-';

        $primaryPassengerNic =
            $primaryPassenger['passenger_nic']
            ?? '-';

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,

            'ticket' => [
                'booking_id' =>
                    $booking->id,

                'booking_reference' =>
                    $booking->booking_reference,

                'company_name' =>
                    $booking->company_name
                    ?? null,

                'bus_name' =>
                    $booking->bus_name
                    ?? null,

                'bus_number' =>
                    $booking->bus_number
                    ?? null,

                'trip_id' =>
                    $booking->trip_id,

                'trip_code' =>
                    $booking->trip_code,

                'trip_status' =>
                    $booking->trip_status,

                'ticket_status' =>
                    $booking->ticket_status
                    ?? 'valid',

                'primary_passenger_name' =>
                    $primaryPassengerName,

                'primary_passenger_nic' =>
                    $primaryPassengerNic,

                'boarding_stop' =>
                    $booking->boarding_stop
                    ?? '-',

                'dropoff_stop' =>
                    $booking->dropoff_stop
                    ?? '-',

                'passengers' =>
                    $passengers,
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
            'trip_id' => [
                'required',
                'integer',
                'exists:trips,id',
            ],

            'booking_passenger_id' => [
                'required',
                'integer',
                'exists:booking_passengers,id',
            ],
        ]);

        $tripId =
            (int) $data['trip_id'];

        $bookingPassengerId =
            (int) $data['booking_passenger_id'];

        $this->assertAssignedStaff(
            $staff,
            $tripId
        );

        return DB::transaction(
            function () use (
                $staff,
                $tripId,
                $bookingPassengerId
            ) {
                /*
                |--------------------------------------------------------------------------
                | Find Passenger
                |--------------------------------------------------------------------------
                */

                $passenger = DB::table(
                    'booking_passengers as bp'
                )
                    ->join(
                        'bookings as b',
                        'b.id',
                        '=',
                        'bp.booking_id'
                    )
                    ->join(
                        'trips as t',
                        't.id',
                        '=',
                        'b.trip_id'
                    )
                    ->where(
                        'bp.id',
                        $bookingPassengerId
                    )
                    ->where(
                        'b.trip_id',
                        $tripId
                    )
                    ->select([
                        'bp.*',

                        'b.booking_reference',

                        'b.status as booking_status',

                        'b.payment_status',

                        't.status as trip_status',
                    ])
                    ->lockForUpdate()
                    ->first();

                if (!$passenger) {
                    return response()->json([
                        'success' => false,
                        'message' =>
                            'Passenger does not belong to this trip.',
                    ], 404);
                }

                /*
                |--------------------------------------------------------------------------
                | Booking Validation
                |--------------------------------------------------------------------------
                */

                if (
                    strtolower(
                        (string)
                        $passenger->payment_status
                    ) !== 'paid'
                    ||
                    strtolower(
                        (string)
                        $passenger->booking_status
                    ) !== 'confirmed'
                ) {
                    return response()->json([
                        'success' => false,
                        'message' =>
                            'Passenger ticket is not valid for check-in.',
                    ], 422);
                }

                /*
                |--------------------------------------------------------------------------
                | Trip Must Be Active
                |--------------------------------------------------------------------------
                */

                if (
                    strtolower(
                        (string)
                        $passenger->trip_status
                    ) !== 'active'
                ) {
                    return response()->json([
                        'success' => false,
                        'message' =>
                            'Passenger check-in is available only while the trip is active.',
                    ], 422);
                }

                /*
                |--------------------------------------------------------------------------
                | Already Checked In
                |--------------------------------------------------------------------------
                */

                if (
                    $passenger->checked_in_at
                    !== null
                ) {
                    return response()->json([
                        'success' => true,

                        'message' =>
                            'Passenger has already been checked in.',

                        'already_checked_in' =>
                            true,

                        'booking_passenger_id' =>
                            $passenger->id,

                        'checked_in_at' =>
                            $passenger->checked_in_at,
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Save Check-In
                |--------------------------------------------------------------------------
                */

                $update = [
                    'checked_in_at' =>
                        now(),
                ];

                if (
                    Schema::hasColumn(
                        'booking_passengers',
                        'checked_in_by_staff_id'
                    )
                ) {
                    $update[
                        'checked_in_by_staff_id'
                    ] = $staff->id;
                }

                if (
                    Schema::hasColumn(
                        'booking_passengers',
                        'updated_at'
                    )
                ) {
                    $update['updated_at'] =
                        now();
                }

                DB::table('booking_passengers')
                    ->where(
                        'id',
                        $passenger->id
                    )
                    ->update(
                        $update
                    );

                /*
                |--------------------------------------------------------------------------
                | Remaining Passengers
                |--------------------------------------------------------------------------
                */

                $remainingPassengers =
                    DB::table(
                        'booking_passengers'
                    )
                        ->where(
                            'booking_id',
                            $passenger->booking_id
                        )
                        ->whereNull(
                            'checked_in_at'
                        )
                        ->count();

                /*
                |--------------------------------------------------------------------------
                | Mark Ticket Used Only When Everyone Is Checked In
                |--------------------------------------------------------------------------
                */

                if (
                    $remainingPassengers === 0
                    &&
                    Schema::hasColumn(
                        'bookings',
                        'ticket_status'
                    )
                ) {
                    $bookingUpdate = [
                        'ticket_status' =>
                            'used',
                    ];

                    if (
                        Schema::hasColumn(
                            'bookings',
                            'updated_at'
                        )
                    ) {
                        $bookingUpdate[
                            'updated_at'
                        ] = now();
                    }

                    DB::table('bookings')
                        ->where(
                            'id',
                            $passenger->booking_id
                        )
                        ->update(
                            $bookingUpdate
                        );
                }

                /*
                |--------------------------------------------------------------------------
                | Passenger Details
                |--------------------------------------------------------------------------
                */

                $passengerName =
                    $this->firstExistingValue(
                        $passenger,
                        [
                            'passenger_name',
                            'name',
                            'full_name',
                        ]
                    );

                $passengerNic =
                    $this->firstExistingValue(
                        $passenger,
                        [
                            'passenger_nic',
                            'nic',
                            'nic_number',
                            'national_id',
                            'national_id_number',
                        ]
                    );

                $seatNumber =
                    $this->firstExistingValue(
                        $passenger,
                        [
                            'seat_number',
                            'seat',
                        ]
                    );

                return response()->json([
                    'success' => true,

                    'message' =>
                        'Passenger checked in successfully.',

                    'booking_id' =>
                        $passenger->booking_id,

                    'booking_reference' =>
                        $passenger->booking_reference,

                    'booking_passenger_id' =>
                        $passenger->id,

                    'passenger_name' =>
                        $passengerName
                        ?: '-',

                    'passenger_nic' =>
                        $passengerNic
                        ?: '-',

                    'seat_number' =>
                        $seatNumber
                        ?: '-',

                    'checked_in_at' =>
                        now()->toIso8601String(),

                    'all_passengers_checked_in' =>
                        $remainingPassengers === 0,
                ]);
            },
            3
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Find Booking from QR Token
    |--------------------------------------------------------------------------
    */

    private function ticketBooking(
        string $token,
        int $tripId
    ) {
        if (
            !Schema::hasColumn(
                'bookings',
                'ticket_token'
            )
        ) {
            return null;
        }

        return DB::table('bookings')
            ->join(
                'trips',
                'trips.id',
                '=',
                'bookings.trip_id'
            )
            ->join(
                'buses',
                'buses.id',
                '=',
                'trips.bus_id'
            )
            ->leftJoin(
                'operators',
                'operators.id',
                '=',
                'trips.operator_id'
            )
            ->where(
                'bookings.ticket_token',
                $token
            )
            ->where(
                'bookings.trip_id',
                $tripId
            )
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
    | Read First Existing Value
    |--------------------------------------------------------------------------
    |
    | This lets the API work even if the passenger table uses names such as
    | passenger_nic, nic, nic_number, etc.
    |
    */

    private function firstExistingValue(
        object $record,
        array $keys,
        $fallback = ''
    ) {
        foreach ($keys as $key) {
            if (
                property_exists(
                    $record,
                    $key
                )
                &&
                $record->{$key} !== null
                &&
                trim(
                    (string)
                    $record->{$key}
                ) !== ''
            ) {
                return $record->{$key};
            }
        }

        return $fallback;
    }

    /*
    |--------------------------------------------------------------------------
    | Logged-In Staff
    |--------------------------------------------------------------------------
    */

    private function staff(
        Request $request
    ) {
        $staff =
            $request->attributes->get(
                'staff'
            );

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
        $assigned =
            DB::table('trips')
                ->where(
                    'id',
                    $tripId
                )
                ->where(
                    function ($query) use (
                        $staff
                    ) {
                        $query
                            ->where(
                                'driver_id',
                                $staff->id
                            )
                            ->orWhere(
                                'conductor_id',
                                $staff->id
                            );
                    }
                )
                ->exists();

        abort_unless(
            $assigned,
            403,
            'You are not assigned to this trip.'
        );
    }
}