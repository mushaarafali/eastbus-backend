<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class TripStaffController extends Controller
{
    private const START_WINDOW_MINUTES = 10;
    private const TIMEZONE = 'Asia/Colombo';

    private function now(): Carbon
    {
        return Carbon::now(self::TIMEZONE);
    }

    private function staff(Request $request)
    {
        $staff = $request->attributes->get('staff');

        abort_unless($staff, 401, 'Staff authentication required.');

        return $staff;
    }

    private function assigned($staff, $tripId)
    {
        return DB::table('trips')
            ->join('routes', 'routes.id', '=', 'trips.route_id')
            ->join('buses', 'buses.id', '=', 'trips.bus_id')
            ->where('trips.id', $tripId)
            ->where('trips.operator_id', $staff->operator_id)
            ->where(function ($query) use ($staff) {
                $query->where('trips.driver_id', $staff->id)
                    ->orWhere('trips.conductor_id', $staff->id);
            })
            ->select(
                'trips.*',
                'routes.name as route_name',
                'routes.origin',
                'routes.destination',
                'buses.bus_number',
                'buses.bus_name',
                'buses.seat_count'
            )
            ->first();
    }

    private function firebaseDatabaseUrl(): string
    {
        return rtrim((string) env('FIREBASE_DATABASE_URL', ''), '/');
    }

    private function firebaseTripUrl(string $tripCode): string
    {
        return $this->firebaseDatabaseUrl()
            . '/live_trips/'
            . rawurlencode($tripCode)
            . '.json';
    }

    private function firebaseSet(string $tripCode, array $data): bool
    {
        if ($this->firebaseDatabaseUrl() === '' || trim($tripCode) === '') {
            return false;
        }

        try {
            $response = Http::timeout(8)
                ->acceptJson()
                ->put($this->firebaseTripUrl($tripCode), $data);

            if (!$response->successful()) {
                Log::warning('Firebase set failed', [
                    'trip_code' => $tripCode,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Firebase set exception', [
                'trip_code' => $tripCode,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function firebaseUpdate(string $tripCode, array $data): bool
    {
        if ($this->firebaseDatabaseUrl() === '' || trim($tripCode) === '') {
            return false;
        }

        try {
            $response = Http::timeout(8)
                ->acceptJson()
                ->patch($this->firebaseTripUrl($tripCode), $data);

            if (!$response->successful()) {
                Log::warning('Firebase update failed', [
                    'trip_code' => $tripCode,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Firebase update exception', [
                'trip_code' => $tripCode,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function dashboard(Request $request)
    {
        $staff = $this->staff($request);
        $today = $this->now()->toDateString();

        $tripIds = DB::table('trips')
            ->where('operator_id', $staff->operator_id)
            ->where(function ($query) use ($staff) {
                $query->where('driver_id', $staff->id)
                    ->orWhere('conductor_id', $staff->id);
            })
            ->whereDate('service_date', $today)
            ->pluck('id');

        $activeTrip = DB::table('trips')
            ->join('routes', 'routes.id', '=', 'trips.route_id')
            ->join('buses', 'buses.id', '=', 'trips.bus_id')
            ->whereIn('trips.id', $tripIds)
            ->where('trips.status', 'active')
            ->select(
                'trips.*',
                'routes.origin',
                'routes.destination',
                'buses.bus_number',
                'buses.bus_name'
            )
            ->first();

        $passengers = DB::table('bookings')
            ->whereIn('trip_id', $tripIds)
            ->where('status', 'confirmed')
            ->where('payment_status', 'paid')
            ->sum('passenger_count');

        $checkedIn = DB::table('booking_passengers')
            ->join('bookings', 'bookings.id', '=', 'booking_passengers.booking_id')
            ->whereIn('bookings.trip_id', $tripIds)
            ->whereIn('bookings.status', ['confirmed', 'completed'])
            ->where('bookings.payment_status', 'paid')
            ->whereNotNull('booking_passengers.checked_in_at')
            ->count();

        unset($staff->password);

        return [
            'success' => true,
            'staff' => $staff,
            'stats' => [
                'today_trips' => $tripIds->count(),
                'passengers' => (int) $passengers,
                'checked_in' => (int) $checkedIn,
            ],
            'active_trip' => $activeTrip,
        ];
    }

    public function trips(Request $request)
    {
        $staff = $this->staff($request);
        $now = $this->now();

        $rows = DB::table('trips')
            ->join('routes', 'routes.id', '=', 'trips.route_id')
            ->join('buses', 'buses.id', '=', 'trips.bus_id')
            ->where('trips.operator_id', $staff->operator_id)
            ->where(function ($query) use ($staff) {
                $query->where('trips.driver_id', $staff->id)
                    ->orWhere('trips.conductor_id', $staff->id);
            })
            ->whereDate('trips.service_date', '>=', $now->copy()->subDay()->toDateString())
            ->orderBy('trips.service_date')
            ->orderBy('trips.departure_time')
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
            $trip->booked_passengers = (int) DB::table('bookings')
                ->where('trip_id', $trip->id)
                ->where('status', 'confirmed')
                ->where('payment_status', 'paid')
                ->sum('passenger_count');

            $trip->available_seats = max(
                0,
                (int) $trip->seat_count - (int) $trip->booked_passengers
            );

            $scheduledAt = $this->scheduledDateTime($trip);
            $startFrom = $scheduledAt->copy()->subMinutes(self::START_WINDOW_MINUTES);
            $startUntil = $scheduledAt->copy()->addMinutes(self::START_WINDOW_MINUTES);

            $trip->scheduled_at = $scheduledAt->toDateTimeString();
            $trip->start_allowed_from = $startFrom->toDateTimeString();
            $trip->start_allowed_until = $startUntil->toDateTimeString();

            $trip->can_start =
                strtolower((string) $trip->status) === 'scheduled'
                && $now->betweenIncluded($startFrom, $startUntil);
        }

        return [
            'success' => true,
            'trips' => $rows,
        ];
    }

    public function start(Request $request, $id)
    {
        $staff = $this->staff($request);
        $trip = $this->assigned($staff, $id);

        if (!$trip) {
            return response()->json([
                'success' => false,
                'message' => 'Trip is not assigned to this staff member.',
            ], 403);
        }

        if (strtolower((string) $trip->status) !== 'scheduled') {
            return response()->json([
                'success' => false,
                'message' => 'Only a scheduled trip can be started.',
            ], 422);
        }

        $now = $this->now();
        $scheduledAt = $this->scheduledDateTime($trip);
        $startFrom = $scheduledAt->copy()->subMinutes(self::START_WINDOW_MINUTES);
        $startUntil = $scheduledAt->copy()->addMinutes(self::START_WINDOW_MINUTES);

        if ($now->lt($startFrom)) {
            return response()->json([
                'success' => false,
                'message' => 'Trip cannot be started yet. It can be started only within 10 minutes before the scheduled departure.',
                'scheduled_departure' => $scheduledAt->toDateTimeString(),
                'start_allowed_from' => $startFrom->toDateTimeString(),
                'start_allowed_until' => $startUntil->toDateTimeString(),
            ], 422);
        }

        if ($now->gt($startUntil)) {
            return response()->json([
                'success' => false,
                'message' => 'The allowed trip start time has passed. A trip can be started only within 10 minutes after the scheduled departure.',
                'scheduled_departure' => $scheduledAt->toDateTimeString(),
                'start_allowed_from' => $startFrom->toDateTimeString(),
                'start_allowed_until' => $startUntil->toDateTimeString(),
            ], 422);
        }

        $bookedSeats = DB::table('booking_passengers')
            ->join('bookings', 'bookings.id', '=', 'booking_passengers.booking_id')
            ->where('bookings.trip_id', $id)
            ->where('bookings.status', 'confirmed')
            ->where('bookings.payment_status', 'paid')
            ->pluck('booking_passengers.seat_number')
            ->map(fn ($seat) => trim((string) $seat))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $allSeats = DB::table('seats')
            ->where('bus_id', $trip->bus_id)
            ->orderBy('id')
            ->get();

        $snapshot = $allSeats->map(function ($seat) use ($bookedSeats) {
            $seatNumber = trim((string) $seat->seat_number);
            $disabled = (bool) ($seat->is_disabled ?? false);

            return [
                'seat_number' => $seatNumber,
                'status' => $disabled
                    ? 'unavailable'
                    : (in_array($seatNumber, $bookedSeats, true)
                        ? 'booked'
                        : 'available'),
            ];
        })->values()->all();

        $availableCount = collect($snapshot)
            ->where('status', 'available')
            ->count();

        DB::table('trips')
            ->where('id', $id)
            ->update([
                'status' => 'active',
                'started_at' => $now,
                'booking_closed_at' => $now,
                'seat_snapshot_json' => json_encode($snapshot),
                'trip_start_booked_seats' => count($bookedSeats),
                'trip_start_available_seats' => $availableCount,
                'updated_at' => $now,
            ]);

        $firebaseSynced = $this->firebaseSet(
            trim((string) $trip->trip_code),
            [
                'trip_id' => (int) $trip->id,
                'trip_code' => trim((string) $trip->trip_code),
                'operator_id' => (int) $trip->operator_id,
                'bus_number' => $trip->bus_number,
                'origin' => $trip->origin,
                'destination' => $trip->destination,
                'staff_id' => (int) $staff->id,
                'staff_login_id' => $staff->login_id ?? null,
                'staff_role' => $staff->role ?? null,
                'latitude' => null,
                'longitude' => null,
                'speed' => 0,
                'heading' => 0,
                'status' => 'ON_TRIP',
                'started_at' => $now->timestamp * 1000,
                'location_updated_at' => null,
                'updated_at' => $now->timestamp * 1000,
            ]
        );

        return [
            'success' => true,
            'message' => 'Trip started successfully.',
            'trip_id' => (int) $trip->id,
            'trip_code' => $trip->trip_code,
            'status' => 'active',
            'started_at' => $now->toDateTimeString(),
            'firebase_synced' => $firebaseSynced,
        ];
    }

    public function location(Request $request, $id)
    {
        $staff = $this->staff($request);
        $trip = $this->assigned($staff, $id);

        if (!$trip) {
            return response()->json([
                'success' => false,
                'message' => 'Trip is not assigned to this staff member.',
            ], 403);
        }

        if (strtolower((string) $trip->status) !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Live location is accepted only for an active trip.',
            ], 422);
        }

        if (!$request->has('speed_kmh') && $request->has('speed')) {
            $request->merge([
                'speed_kmh' => $request->input('speed'),
            ]);
        }

        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'speed_kmh' => ['nullable', 'numeric', 'min:0'],
            'heading' => ['nullable', 'numeric', 'min:0', 'max:360'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
        ]);

        $now = $this->now();

        DB::table('live_locations')->insert([
            'trip_id' => $id,
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'speed_kmh' => $validated['speed_kmh'] ?? null,
            'recorded_at' => $now,
        ]);

        $firebaseSynced = $this->firebaseUpdate(
            trim((string) $trip->trip_code),
            [
                'trip_id' => (int) $trip->id,
                'trip_code' => trim((string) $trip->trip_code),
                'bus_number' => $trip->bus_number,
                'latitude' => (float) $validated['latitude'],
                'longitude' => (float) $validated['longitude'],
                'speed' => (float) ($validated['speed_kmh'] ?? 0),
                'heading' => (float) ($validated['heading'] ?? 0),
                'status' => 'ON_TRIP',
                'location_updated_at' => $now->timestamp * 1000,
                'updated_at' => $now->timestamp * 1000,
            ]
        );

        return [
            'success' => true,
            'message' => 'Live location updated.',
            'firebase_synced' => $firebaseSynced,
        ];
    }

    public function end(Request $request, $id)
    {
        $staff = $this->staff($request);
        $trip = $this->assigned($staff, $id);

        if (!$trip) {
            return response()->json([
                'success' => false,
                'message' => 'Trip is not assigned to this staff member.',
            ], 403);
        }

        if (strtolower((string) $trip->status) !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Only an active trip can be ended.',
            ], 422);
        }

        $now = $this->now();

        DB::transaction(function () use ($id, $now) {
            DB::table('trips')
                ->where('id', $id)
                ->update([
                    'status' => 'completed',
                    'ended_at' => $now,
                    'updated_at' => $now,
                ]);

            DB::table('bookings')
                ->where('trip_id', $id)
                ->where('status', 'confirmed')
                ->where('payment_status', 'paid')
                ->update([
                    'status' => 'completed',
                    'updated_at' => $now,
                ]);
        });

        $firebaseSynced = $this->firebaseUpdate(
            trim((string) $trip->trip_code),
            [
                'status' => 'COMPLETED',
                'ended_at' => $now->timestamp * 1000,
                'updated_at' => $now->timestamp * 1000,
            ]
        );

        return [
            'success' => true,
            'message' => 'Trip completed successfully.',
            'status' => 'completed',
            'ended_at' => $now->toDateTimeString(),
            'firebase_synced' => $firebaseSynced,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Passenger List
    |--------------------------------------------------------------------------
    |
    | Primary passenger identity comes from bookings.
    | Traveller seat and gender come from booking_passengers.
    |
    */

    public function passengers(Request $request, $id)
    {
        $staff = $this->staff($request);

        if (!$this->assigned($staff, $id)) {
            return response()->json([
                'success' => false,
                'message' => 'Trip is not assigned to you.',
            ], 403);
        }

        $rows = DB::table('booking_passengers')
            ->join('bookings', 'bookings.id', '=', 'booking_passengers.booking_id')
            ->where('bookings.trip_id', $id)
            ->whereIn('bookings.status', ['confirmed', 'completed'])
            ->where('bookings.payment_status', 'paid')
            ->select(
                'booking_passengers.id as booking_passenger_id',
                'booking_passengers.seat_number',
                'booking_passengers.gender',
                'booking_passengers.checked_in_at',
                'bookings.id as booking_id',
                'bookings.booking_reference',
                'bookings.primary_passenger_name',
                'bookings.primary_passenger_nic',
                'bookings.boarding_stop',
                'bookings.dropoff_stop'
            )
            ->orderBy('booking_passengers.seat_number')
            ->get()
            ->map(function ($row) {
                $name = trim((string) ($row->primary_passenger_name ?? ''));
                $nic = strtoupper(trim((string) ($row->primary_passenger_nic ?? '')));

                return [
                    'booking_passenger_id' => (int) $row->booking_passenger_id,
                    'booking_id' => (int) $row->booking_id,
                    'booking_reference' => $row->booking_reference,

                    'passenger_name' => $name !== '' ? $name : '-',
                    'name' => $name !== '' ? $name : '-',

                    'nic' => $nic !== '' ? $nic : '-',
                    'passenger_nic' => $nic !== '' ? $nic : '-',

                    'primary_passenger_name' => $name !== '' ? $name : '-',
                    'primary_passenger_nic' => $nic !== '' ? $nic : '-',

                    'seat_number' => $row->seat_number ?: '-',
                    'gender' => $row->gender
                        ? strtoupper(trim((string) $row->gender))
                        : null,

                    'boarding_stop' => $row->boarding_stop ?: '-',
                    'dropoff_stop' => $row->dropoff_stop ?: '-',

                    'checked_in_at' => $row->checked_in_at,
                    'checked_in' => $row->checked_in_at !== null,
                ];
            })
            ->values();

        return [
            'success' => true,
            'passengers' => $rows,
        ];
    }

    public function notifications(Request $request)
    {
        $staff = $this->staff($request);

        if (!Schema::hasTable('notifications')) {
            return [
                'success' => true,
                'notifications' => [],
            ];
        }

        $rows = DB::table('notifications')
            ->where(function ($query) use ($staff) {
                $query->where('target_type', 'all')
                    ->orWhere('target_type', 'operators')
                    ->orWhere(function ($inner) use ($staff) {
                        $inner->where('target_type', 'operator')
                            ->where('operator_id', $staff->operator_id);
                    });
            })
            ->latest('id')
            ->limit(100)
            ->get();

        return [
            'success' => true,
            'notifications' => $rows,
        ];
    }

    public function emergency(Request $request, $id)
    {
        $staff = $this->staff($request);
        $trip = $this->assigned($staff, $id);

        if (!$trip) {
            return response()->json([
                'success' => false,
                'message' => 'Trip is not assigned to you.',
            ], 403);
        }

        $validated = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        $message = trim((string) ($validated['message'] ?? ''));

        if ($message === '') {
            $message = 'Emergency alert';
        }

        $now = $this->now();

        DB::table('emergency_alerts')->insert([
            'operator_id' => $staff->operator_id,
            'trip_id' => $id,
            'staff_id' => $staff->id,
            'message' => $message,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'status' => 'open',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            'success' => true,
            'message' => 'Emergency alert sent.',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Verify QR Ticket
    |--------------------------------------------------------------------------
    */

    public function verifyTicket(Request $request)
    {
        $staff = $this->staff($request);

        $data = $request->validate([
            'ticket_code' => ['required', 'string', 'max:255'],
            'trip_id' => ['nullable', 'integer', 'exists:trips,id'],
        ]);

        $booking = $this->ticketBooking(
            $staff,
            trim($data['ticket_code']),
            isset($data['trip_id']) ? (int) $data['trip_id'] : null
        );

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid ticket or ticket does not belong to your assigned trip.',
            ], 404);
        }

        if (
            strtolower((string) $booking->payment_status) !== 'paid'
            || !in_array(
                strtolower((string) $booking->booking_status),
                ['confirmed', 'completed'],
                true
            )
        ) {
            return response()->json([
                'success' => false,
                'message' => 'This ticket is not valid.',
            ], 422);
        }

        if (in_array(
            strtolower((string) $booking->ticket_status),
            ['cancelled', 'canceled', 'expired', 'invalid', 'refunded'],
            true
        )) {
            return response()->json([
                'success' => false,
                'message' => 'This ticket is not valid.',
            ], 422);
        }

        return [
            'success' => true,
            'ticket' => $this->ticketPayload($booking),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Passenger Check-In
    |--------------------------------------------------------------------------
    */

    public function checkIn(Request $request)
    {
        $staff = $this->staff($request);

        $data = $request->validate([
            'ticket_code' => ['required', 'string', 'max:255'],
            'trip_id' => ['nullable', 'integer', 'exists:trips,id'],
            'booking_passenger_id' => ['nullable', 'integer', 'exists:booking_passengers,id'],
        ]);

        $booking = $this->ticketBooking(
            $staff,
            trim($data['ticket_code']),
            isset($data['trip_id']) ? (int) $data['trip_id'] : null
        );

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid ticket.',
            ], 404);
        }

        if (strtolower((string) $booking->trip_status) !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Passenger check-in is available only while the trip is active.',
            ], 422);
        }

        if (
            strtolower((string) $booking->payment_status) !== 'paid'
            || strtolower((string) $booking->booking_status) !== 'confirmed'
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Booking payment is not confirmed.',
            ], 422);
        }

        $passengerQuery = DB::table('booking_passengers')
            ->where('booking_id', $booking->booking_id);

        if (!empty($data['booking_passenger_id'])) {
            $passengerQuery->where('id', $data['booking_passenger_id']);
        } else {
            $passengerQuery->whereNull('checked_in_at');
        }

        $passenger = $passengerQuery
            ->orderBy('id')
            ->first();

        if (!$passenger) {
            return response()->json([
                'success' => false,
                'message' => 'Passenger not found or all passengers are already checked in.',
            ], 404);
        }

        if ($passenger->checked_in_at !== null) {
            return response()->json([
                'success' => false,
                'message' => 'Passenger has already been checked in.',
            ], 409);
        }

        $now = $this->now();

        DB::transaction(function () use ($passenger, $booking, $staff, $now) {
            $update = [
                'checked_in_at' => $now,
                'updated_at' => $now,
            ];

            if (Schema::hasColumn('booking_passengers', 'checked_in_by_staff_id')) {
                $update['checked_in_by_staff_id'] = $staff->id;
            }

            DB::table('booking_passengers')
                ->where('id', $passenger->id)
                ->update($update);

            $remaining = DB::table('booking_passengers')
                ->where('booking_id', $booking->booking_id)
                ->whereNull('checked_in_at')
                ->count();

            if (
                $remaining === 0
                && Schema::hasColumn('bookings', 'ticket_status')
            ) {
                DB::table('bookings')
                    ->where('id', $booking->booking_id)
                    ->update([
                        'ticket_status' => 'used',
                        'updated_at' => $now,
                    ]);
            }
        });

        $name = trim((string) ($booking->primary_passenger_name ?? ''));
        $nic = strtoupper(trim((string) ($booking->primary_passenger_nic ?? ''));

        return [
            'success' => true,
            'message' => 'Passenger checked in successfully.',
            'booking_passenger_id' => (int) $passenger->id,

            'passenger_name' => $name !== '' ? $name : '-',
            'name' => $name !== '' ? $name : '-',

            'nic' => $nic !== '' ? $nic : '-',
            'passenger_nic' => $nic !== '' ? $nic : '-',

            'primary_passenger_name' => $name !== '' ? $name : '-',
            'primary_passenger_nic' => $nic !== '' ? $nic : '-',

            'seat_number' => $passenger->seat_number ?: '-',
            'gender' => $passenger->gender
                ? strtoupper((string) $passenger->gender)
                : null,

            'checked_in' => true,
            'checked_in_at' => $now->toDateTimeString(),
        ];
    }

    private function ticketBooking($staff, string $code, ?int $tripId = null)
    {
        $query = DB::table('bookings')
            ->join('trips', 'trips.id', '=', 'bookings.trip_id')
            ->join('buses', 'buses.id', '=', 'trips.bus_id')
            ->leftJoin('operators', 'operators.id', '=', 'trips.operator_id')
            ->where('trips.operator_id', $staff->operator_id)
            ->where(function ($query) use ($staff) {
                $query->where('trips.driver_id', $staff->id)
                    ->orWhere('trips.conductor_id', $staff->id);
            })
            ->where(function ($query) use ($code) {
                $query->where('bookings.ticket_token', $code)
                    ->orWhere('bookings.booking_reference', $code);

                if (str_starts_with($code, 'TKT-')) {
                    $query->orWhere(
                        'bookings.booking_reference',
                        substr($code, 4)
                    );
                }
            });

        if ($tripId !== null) {
            $query->where('trips.id', $tripId);
        }

        return $query
            ->select(
                'bookings.id as booking_id',
                'bookings.booking_reference',
                'bookings.ticket_token',
                'bookings.ticket_status',
                'bookings.status as booking_status',
                'bookings.payment_status',
                'bookings.primary_passenger_name',
                'bookings.primary_passenger_nic',
                'bookings.boarding_stop',
                'bookings.dropoff_stop',
                'trips.id as trip_id',
                'trips.trip_code',
                'trips.status as trip_status',
                'buses.bus_number',
                'buses.bus_name',
                'operators.company_name'
            )
            ->first();
    }

    /*
    |--------------------------------------------------------------------------
    | Ticket Payload
    |--------------------------------------------------------------------------
    */

    private function ticketPayload($booking): array
    {
        $name = trim((string) ($booking->primary_passenger_name ?? ''));
        $nic = strtoupper(trim((string) ($booking->primary_passenger_nic ?? ''));

        $passengers = DB::table('booking_passengers')
            ->where('booking_id', $booking->booking_id)
            ->orderBy('seat_number')
            ->get()
            ->map(function ($passenger) use ($name, $nic) {
                return [
                    'id' => (int) $passenger->id,
                    'booking_passenger_id' => (int) $passenger->id,

                    'passenger_name' => $name !== '' ? $name : '-',
                    'name' => $name !== '' ? $name : '-',

                    'passenger_nic' => $nic !== '' ? $nic : '-',
                    'nic' => $nic !== '' ? $nic : '-',

                    'seat_number' => $passenger->seat_number ?: '-',
                    'gender' => $passenger->gender
                        ? strtoupper((string) $passenger->gender)
                        : null,

                    'checked_in_at' => $passenger->checked_in_at,
                    'checked_in' => $passenger->checked_in_at !== null,
                ];
            })
            ->values();

        return [
            'booking_id' => (int) $booking->booking_id,
            'booking_reference' => $booking->booking_reference,

            'company_name' => $booking->company_name,
            'bus_name' => $booking->bus_name,
            'bus_number' => $booking->bus_number,

            'trip_id' => (int) $booking->trip_id,
            'trip_code' => $booking->trip_code,
            'trip_status' => $booking->trip_status,

            'ticket_status' => $booking->ticket_status ?? 'valid',

            'primary_passenger_name' => $name !== '' ? $name : '-',
            'primary_passenger_nic' => $nic !== '' ? $nic : '-',

            'passenger_name' => $name !== '' ? $name : '-',
            'passenger_nic' => $nic !== '' ? $nic : '-',
            'nic' => $nic !== '' ? $nic : '-',

            'boarding_stop' => $booking->boarding_stop ?: '-',
            'dropoff_stop' => $booking->dropoff_stop ?: '-',

            'passengers' => $passengers,
        ];
    }

    private function scheduledDateTime($trip): Carbon
    {
        $date = Carbon::parse(
            $trip->service_date,
            self::TIMEZONE
        )->toDateString();

        return Carbon::parse(
            $date . ' ' . $trip->departure_time,
            self::TIMEZONE
        );
    }
}