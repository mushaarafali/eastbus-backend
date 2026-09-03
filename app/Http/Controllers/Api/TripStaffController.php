<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TripStaffController extends Controller
{
    private function staff(Request $request)
    {
        return $request->attributes->get('staff');
    }

    private function assigned($staff, $tripId)
    {
        return DB::table('trips')
            ->join(
                'routes',
                'routes.id',
                '=',
                'trips.route_id'
            )
            ->join(
                'buses',
                'buses.id',
                '=',
                'trips.bus_id'
            )
            ->where(
                'trips.id',
                $tripId
            )
            ->where(
                'trips.operator_id',
                $staff->operator_id
            )
            ->where(function ($query) use ($staff) {
                $query
                    ->where(
                        'trips.driver_id',
                        $staff->id
                    )
                    ->orWhere(
                        'trips.conductor_id',
                        $staff->id
                    );
            })
            ->select(
                'trips.*',
                'routes.origin',
                'routes.destination',
                'buses.bus_number',
                'buses.bus_name'
            )
            ->first();
    }

    /*
    |--------------------------------------------------------------------------
    | FIREBASE
    |--------------------------------------------------------------------------
    */

    private function firebaseDatabaseUrl(): string
    {
        return rtrim(
            env(
                'FIREBASE_DATABASE_URL',
                ''
            ),
            '/'
        );
    }

    private function firebaseTripUrl(
        string $tripCode
    ): string {
        return $this->firebaseDatabaseUrl()
            . '/live_trips/'
            . $tripCode
            . '.json';
    }

    private function firebaseSet(
        string $tripCode,
        array $data
    ): bool {
        if ($this->firebaseDatabaseUrl() === '') {
            return false;
        }

        try {
            $response = Http::timeout(8)
                ->put(
                    $this->firebaseTripUrl(
                        $tripCode
                    ),
                    $data
                );

            if (!$response->successful()) {
                Log::warning(
                    'Firebase set failed',
                    [
                        'trip_code' =>
                            $tripCode,
                        'status' =>
                            $response
                                ->status(),
                        'body' =>
                            $response
                                ->body(),
                    ]
                );

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error(
                'Firebase set exception',
                [
                    'trip_code' =>
                        $tripCode,
                    'message' =>
                        $e->getMessage(),
                ]
            );

            return false;
        }
    }

    private function firebaseUpdate(
        string $tripCode,
        array $data
    ): bool {
        if ($this->firebaseDatabaseUrl() === '') {
            return false;
        }

        try {
            $response = Http::timeout(8)
                ->patch(
                    $this->firebaseTripUrl(
                        $tripCode
                    ),
                    $data
                );

            if (!$response->successful()) {
                Log::warning(
                    'Firebase update failed',
                    [
                        'trip_code' =>
                            $tripCode,
                        'status' =>
                            $response
                                ->status(),
                        'body' =>
                            $response
                                ->body(),
                    ]
                );

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error(
                'Firebase update exception',
                [
                    'trip_code' =>
                        $tripCode,
                    'message' =>
                        $e->getMessage(),
                ]
            );

            return false;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DASHBOARD
    |--------------------------------------------------------------------------
    */

    public function dashboard(
        Request $request
    ) {
        $staff =
            $this->staff($request);

        $today =
            now()->toDateString();

        $tripIds =
            DB::table('trips')
                ->where(
                    'operator_id',
                    $staff->operator_id
                )
                ->where(
                    function ($query) use ($staff) {
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
                ->whereDate(
                    'service_date',
                    $today
                )
                ->pluck('id');

        $activeTrip =
            DB::table('trips')
                ->join(
                    'routes',
                    'routes.id',
                    '=',
                    'trips.route_id'
                )
                ->join(
                    'buses',
                    'buses.id',
                    '=',
                    'trips.bus_id'
                )
                ->whereIn(
                    'trips.id',
                    $tripIds
                )
                ->where(
                    'trips.status',
                    'active'
                )
                ->select(
                    'trips.*',
                    'routes.origin',
                    'routes.destination',
                    'buses.bus_number'
                )
                ->first();

        $passengers =
            DB::table('bookings')
                ->whereIn(
                    'trip_id',
                    $tripIds
                )
                ->where(
                    'status',
                    'confirmed'
                )
                ->sum(
                    'passenger_count'
                );

        $checked =
            DB::table(
                'booking_passengers'
            )
                ->join(
                    'bookings',
                    'bookings.id',
                    '=',
                    'booking_passengers.booking_id'
                )
                ->whereIn(
                    'bookings.trip_id',
                    $tripIds
                )
                ->whereNotNull(
                    'booking_passengers.checked_in_at'
                )
                ->count();

        unset($staff->password);

        return [
            'success' => true,

            'staff' => $staff,

            'stats' => [
                'today_trips' =>
                    $tripIds->count(),

                'passengers' =>
                    (int) $passengers,

                'checked_in' =>
                    $checked,
            ],

            'active_trip' =>
                $activeTrip,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | TRIPS
    |--------------------------------------------------------------------------
    */

    public function trips(
        Request $request
    ) {
        $staff =
            $this->staff($request);

        $rows =
            DB::table('trips')
                ->join(
                    'routes',
                    'routes.id',
                    '=',
                    'trips.route_id'
                )
                ->join(
                    'buses',
                    'buses.id',
                    '=',
                    'trips.bus_id'
                )
                ->where(
                    'trips.operator_id',
                    $staff->operator_id
                )
                ->where(
                    function ($query) use ($staff) {
                        $query
                            ->where(
                                'trips.driver_id',
                                $staff->id
                            )
                            ->orWhere(
                                'trips.conductor_id',
                                $staff->id
                            );
                    }
                )
                ->whereDate(
                    'trips.service_date',
                    '>=',
                    now()
                        ->subDay()
                        ->toDateString()
                )
                ->orderBy(
                    'trips.service_date'
                )
                ->orderBy(
                    'trips.departure_time'
                )
                ->select(
                    'trips.*',
                    'routes.name as route_name',
                    'routes.origin',
                    'routes.destination',
                    'buses.bus_number',
                    'buses.bus_name',
                    'buses.seat_count'
                )
                ->get();

        foreach ($rows as $trip) {
            $trip->booked_passengers =
                (int) DB::table(
                    'bookings'
                )
                    ->where(
                        'trip_id',
                        $trip->id
                    )
                    ->where(
                        'status',
                        'confirmed'
                    )
                    ->sum(
                        'passenger_count'
                    );
        }

        return [
            'success' => true,
            'trips' => $rows,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | START TRIP
    |--------------------------------------------------------------------------
    */

    public function start(
        Request $request,
        $id
    ) {
        $staff =
            $this->staff($request);

        $trip =
            $this->assigned(
                $staff,
                $id
            );

        if (!$trip) {
            return response()->json(
                [
                    'success' => false,
                    'message' =>
                        'Trip is not assigned to this staff member.',
                ],
                403
            );
        }

        if (
            $trip->status !==
            'scheduled'
        ) {
            return response()->json(
                [
                    'success' => false,
                    'message' =>
                        'Only a scheduled trip can be started.',
                ],
                422
            );
        }

        $bookedSeats = DB::table('booking_passengers')
            ->join('bookings','bookings.id','=','booking_passengers.booking_id')
            ->where('bookings.trip_id',$id)->where('bookings.status','confirmed')
            ->pluck('booking_passengers.seat_number')->map(fn($x)=>trim((string)$x))->all();
        $allSeats = DB::table('seats')->where('bus_id',$trip->bus_id)->orderBy('id')->get();
        $snapshot = $allSeats->map(function($seat) use ($bookedSeats) {
            $number=trim((string)$seat->seat_number); $disabled=(bool)($seat->is_disabled??false);
            return ['seat_number'=>$number,'status'=>$disabled?'unavailable':(in_array($number,$bookedSeats,true)?'booked':'available')];
        })->values()->all();
        $availableCount = collect($snapshot)->where('status','available')->count();

        DB::table('trips')->where('id',$id)->update([
            'status'=>'active','started_at'=>now(),'booking_closed_at'=>now(),
            'seat_snapshot_json'=>json_encode($snapshot),'trip_start_booked_seats'=>count($bookedSeats),
            'trip_start_available_seats'=>$availableCount,'updated_at'=>now(),
        ]);

        $firebaseSynced =
            $this->firebaseSet(
                $trip->trip_code,
                [
                    'trip_id' =>
                        (int) $trip->id,

                    'trip_code' =>
                        $trip->trip_code,

                    'operator_id' =>
                        (int) $trip->operator_id,

                    'bus_number' =>
                        $trip->bus_number,

                    'staff_id' =>
                        (int) $staff->id,

                    'staff_login_id' =>
                        $staff->login_id
                        ?? null,

                    'staff_role' =>
                        $staff->role
                        ?? null,

                    'origin' =>
                        $trip->origin,

                    'destination' =>
                        $trip->destination,

                    'latitude' => null,
                    'longitude' => null,

                    'speed' => 0,
                    'heading' => 0,

                    'status' =>
                        'ON_TRIP',

                    'started_at' =>
                        now()
                            ->timestamp
                            * 1000,

                    'updated_at' =>
                        now()
                            ->timestamp
                            * 1000,
                ]
            );

        return [
            'success' => true,

            'message' =>
                'Trip started.',

            'firebase_synced' =>
                $firebaseSynced,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | LIVE LOCATION
    |--------------------------------------------------------------------------
    */

    public function location(
        Request $request,
        $id
    ) {
        $staff =
            $this->staff($request);

        $trip =
            $this->assigned(
                $staff,
                $id
            );

        if (
            !$trip ||
            $trip->status !==
                'active'
        ) {
            return response()->json(
                [
                    'success' => false,
                    'message' =>
                        'Live location is accepted only for an assigned active trip.',
                ],
                422
            );
        }

        $validated =
            $request->validate([
                'latitude' =>
                    'required|numeric|between:-90,90',

                'longitude' =>
                    'required|numeric|between:-180,180',

                'speed_kmh' =>
                    'nullable|numeric|min:0',

                'heading' =>
                    'nullable|numeric|min:0|max:360',
            ]);

        DB::table(
            'live_locations'
        )->insert([
            'trip_id' =>
                $id,

            'latitude' =>
                $validated[
                    'latitude'
                ],

            'longitude' =>
                $validated[
                    'longitude'
                ],

            'speed_kmh' =>
                $validated[
                    'speed_kmh'
                ] ?? null,

            'recorded_at' =>
                now(),
        ]);

        $firebaseSynced =
            $this->firebaseUpdate(
                $trip->trip_code,
                [
                    'trip_id' =>
                        (int) $trip->id,

                    'trip_code' =>
                        $trip->trip_code,

                    'bus_number' =>
                        $trip->bus_number,

                    'latitude' =>
                        (float) $validated[
                            'latitude'
                        ],

                    'longitude' =>
                        (float) $validated[
                            'longitude'
                        ],

                    'speed' =>
                        (float) (
                            $validated[
                                'speed_kmh'
                            ]
                            ?? 0
                        ),

                    'heading' =>
                        (float) (
                            $validated[
                                'heading'
                            ]
                            ?? 0
                        ),

                    'status' =>
                        'ON_TRIP',

                    'updated_at' =>
                        now()
                            ->timestamp
                            * 1000,
                ]
            );

        return [
            'success' => true,

            'firebase_synced' =>
                $firebaseSynced,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | END TRIP
    |--------------------------------------------------------------------------
    */

    public function end(
        Request $request,
        $id
    ) {
        $staff =
            $this->staff($request);

        $trip =
            $this->assigned(
                $staff,
                $id
            );

        if (!$trip) {
            return response()->json(
                [
                    'success' => false,
                    'message' =>
                        'Trip is not assigned to this staff member.',
                ],
                403
            );
        }

        if (
            $trip->status !==
            'active'
        ) {
            return response()->json(
                [
                    'success' => false,
                    'message' =>
                        'Only an active trip can be ended.',
                ],
                422
            );
        }

        DB::transaction(
            function () use ($id) {
                DB::table('trips')
                    ->where(
                        'id',
                        $id
                    )
                    ->update([
                        'status' =>
                            'completed',

                        'ended_at' =>
                            now(),

                        'updated_at' =>
                            now(),
                    ]);

                DB::table('bookings')
                    ->where(
                        'trip_id',
                        $id
                    )
                    ->where(
                        'status',
                        'confirmed'
                    )
                    ->update([
                        'status' =>
                            'completed',

                        'updated_at' =>
                            now(),
                    ]);
            }
        );

        $firebaseSynced =
            $this->firebaseUpdate(
                $trip->trip_code,
                [
                    'status' =>
                        'COMPLETED',

                    'ended_at' =>
                        now()
                            ->timestamp
                            * 1000,

                    'updated_at' =>
                        now()
                            ->timestamp
                            * 1000,
                ]
            );

        return [
            'success' => true,

            'message' =>
                'Trip completed.',

            'firebase_synced' =>
                $firebaseSynced,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | PASSENGERS
    |--------------------------------------------------------------------------
    */

    public function passengers(
        Request $request,
        $id
    ) {
        $staff =
            $this->staff($request);

        if (
            !$this->assigned(
                $staff,
                $id
            )
        ) {
            return response()->json(
                [
                    'success' => false,
                    'message' =>
                        'Trip is not assigned to you.',
                ],
                403
            );
        }

        $rows =
            DB::table(
                'booking_passengers'
            )
                ->join(
                    'bookings',
                    'bookings.id',
                    '=',
                    'booking_passengers.booking_id'
                )
                ->where(
                    'bookings.trip_id',
                    $id
                )
                ->whereIn(
                    'bookings.status',
                    [
                        'confirmed',
                        'completed',
                    ]
                )
                ->select(
                    'booking_passengers.passenger_name',
                    'booking_passengers.nic',
                    'booking_passengers.seat_number',
                    'booking_passengers.checked_in_at',
                    'bookings.booking_reference'
                )
                ->orderBy(
                    'booking_passengers.seat_number'
                )
                ->get()
                ->map(
                    function ($row) {
                        $row->checked_in =
                            $row
                                ->checked_in_at
                                !== null;

                        return $row;
                    }
                );

        return [
            'success' => true,
            'passengers' => $rows,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | NOTIFICATIONS
    |--------------------------------------------------------------------------
    */

    public function notifications(
        Request $request
    ) {
        $staff =
            $this->staff($request);

        $rows =
            DB::table(
                'notifications'
            )
                ->where(
                    function ($query) use ($staff) {
                        $query
                            ->where(
                                'target_type',
                                'all'
                            )
                            ->orWhere(
                                'target_type',
                                'operators'
                            )
                            ->orWhere(
                                function ($inner) use ($staff) {
                                    $inner
                                        ->where(
                                            'target_type',
                                            'operator'
                                        )
                                        ->where(
                                            'operator_id',
                                            $staff
                                                ->operator_id
                                        );
                                }
                            );
                    }
                )
                ->latest('id')
                ->limit(100)
                ->get();

        return [
            'success' => true,
            'notifications' => $rows,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | EMERGENCY ALERT
    |--------------------------------------------------------------------------
    */

    public function emergency(
        Request $request,
        $id
    ) {
        $staff =
            $this->staff($request);

        $trip =
            $this->assigned(
                $staff,
                $id
            );

        if (!$trip) {
            return response()->json(
                [
                    'success' => false,
                    'message' =>
                        'Trip is not assigned to you.',
                ],
                403
            );
        }

        $validated =
            $request->validate([
                'latitude' =>
                    'nullable|numeric',

                'longitude' =>
                    'nullable|numeric',

                'message' =>
                    'nullable|string|max:1000',
            ]);

        DB::table(
            'emergency_alerts'
        )->insert([
            'operator_id' =>
                $staff->operator_id,

            'trip_id' =>
                $id,

            'staff_id' =>
                $staff->id,

            'message' =>
                $validated[
                    'message'
                ]
                ?? 'Emergency alert',

            'latitude' =>
                $validated[
                    'latitude'
                ] ?? null,

            'longitude' =>
                $validated[
                    'longitude'
                ] ?? null,

            'status' =>
                'open',

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);

        return [
            'success' => true,
            'message' =>
                'Emergency alert sent.',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | VERIFY TICKET
    |--------------------------------------------------------------------------
    */

    public function verifyTicket(
        Request $request
    ) {
        $staff =
            $this->staff($request);

        $code =
            $request->validate([
                'ticket_code' =>
                    'required|string|max:255',
            ])['ticket_code'];

        $row =
            $this->ticketRow(
                $staff,
                $code
            );

        if (!$row) {
            return response()->json(
                [
                    'success' => false,
                    'message' =>
                        'Invalid ticket or ticket does not belong to your assigned trip.',
                ],
                404
            );
        }

        if (
            $row->ticket_status ===
                'cancelled'
            ||
            $row->booking_status ===
                'cancelled'
        ) {
            return response()->json(
                [
                    'success' => false,
                    'message' =>
                        'This ticket has been cancelled.',
                ],
                422
            );
        }

        return [
            'success' => true,
            'ticket' =>
                $this->ticketPayload(
                    $row
                ),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | CHECK IN
    |--------------------------------------------------------------------------
    */

    public function checkIn(
        Request $request
    ) {
        $staff =
            $this->staff($request);

        $code =
            $request->validate([
                'ticket_code' =>
                    'required|string|max:255',
            ])['ticket_code'];

        $row =
            $this->ticketRow(
                $staff,
                $code
            );

        if (!$row) {
            return response()->json(
                [
                    'success' => false,
                    'message' =>
                        'Invalid ticket.',
                ],
                404
            );
        }

        if (
            $row->ticket_status ===
                'used'
            ||
            $row->checked_in_at
        ) {
            return response()->json(
                [
                    'success' => false,
                    'message' =>
                        'Ticket has already been used.',
                ],
                409
            );
        }

        if (
            $row->payment_status !==
            'paid'
        ) {
            return response()->json(
                [
                    'success' => false,
                    'message' =>
                        'Booking payment is not confirmed.',
                ],
                422
            );
        }

        DB::transaction(
            function () use (
                $row,
                $staff
            ) {
                DB::table(
                    'booking_passengers'
                )
                    ->where(
                        'id',
                        $row
                            ->booking_passenger_id
                    )
                    ->update([
                        'checked_in_at' =>
                            now(),

                        'checked_in_by_staff_id' =>
                            $staff->id,

                        'updated_at' =>
                            now(),
                    ]);

                DB::table('tickets')
                    ->where(
                        'id',
                        $row
                            ->ticket_id
                    )
                    ->update([
                        'status' =>
                            'used',

                        'used_at' =>
                            now(),

                        'updated_at' =>
                            now(),
                    ]);
            }
        );

        return [
            'success' => true,
            'message' =>
                'Passenger checked in.',
        ];
    }

    private function ticketRow(
        $staff,
        $code
    ) {
        return DB::table('tickets')
            ->join(
                'booking_passengers',
                'booking_passengers.id',
                '=',
                'tickets.booking_passenger_id'
            )
            ->join(
                'bookings',
                'bookings.id',
                '=',
                'tickets.booking_id'
            )
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
            ->where(
                'tickets.ticket_code',
                $code
            )
            ->where(
                'trips.operator_id',
                $staff->operator_id
            )
            ->where(
                function ($query) use ($staff) {
                    $query
                        ->where(
                            'trips.driver_id',
                            $staff->id
                        )
                        ->orWhere(
                            'trips.conductor_id',
                            $staff->id
                        );
                }
            )
            ->select(
                'tickets.id as ticket_id',
                'tickets.status as ticket_status',
                'booking_passengers.id as booking_passenger_id',
                'booking_passengers.passenger_name',
                'booking_passengers.nic',
                'booking_passengers.seat_number',
                'booking_passengers.checked_in_at',
                'bookings.booking_reference',
                'bookings.payment_status',
                'bookings.status as booking_status',
                'trips.trip_code',
                'trips.status as trip_status',
                'buses.bus_number'
            )
            ->first();
    }

    private function ticketPayload(
        $row
    ) {
        return [
            'passenger_name' =>
                $row->passenger_name,

            'nic' =>
                $row->nic,

            'seat_number' =>
                $row->seat_number,

            'booking_reference' =>
                $row->booking_reference,

            'payment_status' =>
                $row->payment_status,

            'trip_code' =>
                $row->trip_code,

            'bus_number' =>
                $row->bus_number,

            'checked_in' =>
                $row->checked_in_at
                    !== null
                ||
                $row->ticket_status
                    === 'used',
        ];
    }
}