<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EastBusMailService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PassengerBookingController extends Controller
{
    private const MAX_SEATS = 6;
    private const HOLD_MINUTES = 10;
    private const MIN_JOURNEY_KM = 50;

    public function locations(Request $request)
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $term = trim((string) ($data['search'] ?? ''));

        $query = DB::table('route_stops')
            ->select('name')
            ->distinct();

        if ($term !== '') {
            $query->where('name', 'like', $term . '%');
        }

        return response()->json([
            'success' => true,
            'locations' => $query->orderBy('name')->limit(30)->get(),
        ]);
    }

    public function searchTrips(Request $request)
    {
        $data = $request->validate([
            'origin' => ['required', 'string', 'max:120'],
            'destination' => ['required', 'string', 'max:120', 'different:origin'],
            'date' => ['required', 'date', 'after_or_equal:today'],
        ]);

        $origin = trim($data['origin']);
        $destination = trim($data['destination']);
        $date = Carbon::parse($data['date'])->toDateString();

        $trips = $this->tripQuery()
            ->where('trips.is_published', true)
            ->where('trips.status', 'scheduled')
            ->whereDate('trips.service_date', $date)
            ->when(
                $date === today()->toDateString(),
                fn ($query) => $query->whereTime('trips.departure_time', '>', now()->format('H:i:s'))
            )
            ->orderBy('trips.departure_time')
            ->get();

        $matches = $trips->map(function ($trip) use ($origin, $destination) {
            $stops = $this->orderedStopsForTrip($trip);

            if ($stops->isEmpty()) {
                return null;
            }

            $boarding = $stops->first(
                fn ($stop) => $this->sameStopName($stop->name, $origin) && (bool) $stop->boarding_allowed
            );

            $dropoff = $stops->first(
                fn ($stop) => $this->sameStopName($stop->name, $destination) && (bool) $stop->dropoff_allowed
            );

            if (!$boarding || !$dropoff) {
                return null;
            }

            if ((int) $boarding->_journey_order >= (int) $dropoff->_journey_order) {
                return null;
            }

            $journeyDistance = $this->journeyDistance($boarding, $dropoff);

            if ($journeyDistance < self::MIN_JOURNEY_KM) {
                return null;
            }

            $trip->requested_origin = $boarding->name;
            $trip->requested_destination = $dropoff->name;
            $trip->boarding_stop = $boarding->name;
            $trip->dropoff_stop = $dropoff->name;
            $trip->journey_distance_km = round($journeyDistance, 2);
            $trip->minimum_journey_km = self::MIN_JOURNEY_KM;
            $trip->segment_fare = $this->calculateSegmentFare($trip, $journeyDistance);
            $trip->booking_available = $this->isTripBookable($trip);

            if ($this->isReturnTrip($trip)) {
                $originalOrigin = $trip->origin;
                $trip->origin = $trip->destination;
                $trip->destination = $originalOrigin;
            }

            return $trip;
        })->filter()->values();

        return response()->json([
            'success' => true,
            'minimum_journey_km' => self::MIN_JOURNEY_KM,
            'trips' => $matches,
        ]);
    }

    public function tripDetails($id)
    {
        $trip = $this->tripQuery()->where('trips.id', $id)->first();

        if (!$trip) {
            return response()->json([
                'success' => false,
                'message' => 'Trip not found.',
            ], 404);
        }

        $stops = $this->orderedStopsForTrip($trip);

        if ($this->isReturnTrip($trip)) {
            $originalOrigin = $trip->origin;
            $trip->origin = $trip->destination;
            $trip->destination = $originalOrigin;
        }

        return response()->json([
            'success' => true,
            'trip' => $trip,
            'stops' => $stops,
            'booking_available' => $this->isTripBookable($trip),
            'minimum_journey_km' => self::MIN_JOURNEY_KM,
        ]);
    }

    public function bookings(Request $request)
    {
        $passenger = $this->passenger($request);

        $bookings = DB::table('bookings')
            ->join('trips', 'trips.id', '=', 'bookings.trip_id')
            ->join('routes', 'routes.id', '=', 'trips.route_id')
            ->join('buses', 'buses.id', '=', 'trips.bus_id')
            ->leftJoin('operators', 'operators.id', '=', 'trips.operator_id')
            ->where('bookings.passenger_user_id', $passenger->id)
            ->select(
                'bookings.*',
                'trips.trip_code',
                'trips.service_date',
                'trips.departure_time',
                'trips.arrival_time',
                'trips.status as trip_status',
                'trips.trip_type',
                'routes.origin',
                'routes.destination',
                'buses.bus_number',
                'buses.bus_name',
                'buses.bus_type',
                'operators.company_name as company_name',
                'operators.company_name as operator_name'
            )
            ->orderByDesc('bookings.id')
            ->get();

        foreach ($bookings as $booking) {
            if ($this->isReturnTrip($booking)) {
                $originalOrigin = $booking->origin;
                $booking->origin = $booking->destination;
                $booking->destination = $originalOrigin;
            }

            $booking->passengers = DB::table('booking_passengers')
                ->where('booking_id', $booking->id)
                ->orderByRaw("CAST(REPLACE(seat_number, 'S', '') AS UNSIGNED)")
                ->get();

            $booking->seats = $this->decodeSeatNumbers($booking->seat_numbers ?? '');

            $booking->feedback_submitted = Schema::hasTable('trip_feedback')
                ? DB::table('trip_feedback')
                    ->where('booking_id', $booking->id)
                    ->where('passenger_id', $passenger->id)
                    ->exists()
                : false;

            $booking->feedback_available =
                strtolower((string) ($booking->trip_status ?? '')) === 'completed'
                && strtolower((string) ($booking->status ?? '')) === 'confirmed'
                && strtolower((string) ($booking->payment_status ?? '')) === 'paid'
                && !$booking->feedback_submitted;
        }

        return response()->json([
            'success' => true,
            'bookings' => $bookings,
        ]);
    }

    public function createBooking(Request $request)
    {
        $passenger = $this->passenger($request);

        $data = $request->validate([
            'trip_id' => ['required', 'integer', 'exists:trips,id'],
            'origin' => ['required', 'string', 'max:120'],
            'destination' => ['required', 'string', 'max:120', 'different:origin'],
            'seat_numbers' => ['required', 'array', 'min:1', 'max:' . self::MAX_SEATS],
            'seat_numbers.*' => ['required', 'string', 'max:20'],
            'primary_passenger_name' => ['required', 'string', 'max:150'],
            'primary_passenger_nic' => ['required', 'string', 'regex:/^([0-9]{9}[VvXx]|[0-9]{12})$/'],
            'phone' => ['nullable', 'string', 'regex:/^\+94[0-9]{9}$/'],
            'travellers' => ['required', 'array', 'min:1', 'max:' . self::MAX_SEATS],
            'travellers.*.seat_number' => ['required', 'string', 'max:20'],
            'travellers.*.name' => ['required', 'string', 'max:150'],
            'travellers.*.gender' => ['required', 'in:male,female'],
        ], [
            'primary_passenger_nic.regex' => 'Enter a valid Sri Lankan NIC number.',
            'phone.regex' => 'Phone number must use Sri Lankan international format +94XXXXXXXXX.',
        ]);

        $seatNumbers = collect($data['seat_numbers'])
            ->map(fn ($seat) => $this->normalizeSeatNumber($seat))
            ->filter()
            ->unique()
            ->values();

        if ($seatNumbers->count() !== count($data['seat_numbers'])) {
            throw ValidationException::withMessages([
                'seat_numbers' => 'Duplicate or invalid seats were selected.',
            ]);
        }

        $travellers = collect($data['travellers'])->map(fn ($row) => [
            'seat_number' => $this->normalizeSeatNumber($row['seat_number']),
            'name' => trim((string) $row['name']),
            'gender' => strtolower(trim((string) $row['gender'])),
        ]);

        if ($travellers->count() !== $seatNumbers->count()) {
            throw ValidationException::withMessages([
                'travellers' => 'Passenger details are required for every selected seat.',
            ]);
        }

        $travellerSeats = $travellers->pluck('seat_number')->unique()->sort()->values();
        $selectedSeats = $seatNumbers->sort()->values();

        if ($travellerSeats->all() !== $selectedSeats->all()) {
            throw ValidationException::withMessages([
                'travellers' => 'Passenger details must match all selected seats.',
            ]);
        }

        $booking = DB::transaction(function () use ($passenger, $data, $seatNumbers, $travellers) {
            $trip = DB::table('trips')
                ->join('buses', 'buses.id', '=', 'trips.bus_id')
                ->where('trips.id', $data['trip_id'])
                ->select('trips.*', 'buses.seat_count')
                ->lockForUpdate()
                ->first();

            if (!$trip) {
                abort(404, 'Trip not found.');
            }

            if (!$this->isTripBookable($trip)) {
                throw ValidationException::withMessages([
                    'trip_id' => 'This trip is no longer available for booking.',
                ]);
            }

            $stops = $this->orderedStopsForTrip($trip);
            $boarding = $stops->first(
                fn ($stop) => $this->sameStopName($stop->name, $data['origin']) && (bool) $stop->boarding_allowed
            );
            $dropoff = $stops->first(
                fn ($stop) => $this->sameStopName($stop->name, $data['destination']) && (bool) $stop->dropoff_allowed
            );

            if (!$boarding || !$dropoff || (int) $boarding->_journey_order >= (int) $dropoff->_journey_order) {
                throw ValidationException::withMessages([
                    'route' => 'Selected boarding and dropping stops are not valid for this trip direction.',
                ]);
            }

            $journeyDistance = $this->journeyDistance($boarding, $dropoff);

            if ($journeyDistance < self::MIN_JOURNEY_KM) {
                throw ValidationException::withMessages([
                    'destination' => 'The minimum booking distance is ' . self::MIN_JOURNEY_KM . ' km.',
                ]);
            }

            $validSeats = DB::table('seats')
                ->where('bus_id', $trip->bus_id)
                ->whereIn('seat_number', $seatNumbers->all())
                ->where('is_disabled', false)
                ->pluck('seat_number')
                ->map(fn ($seat) => $this->normalizeSeatNumber($seat))
                ->unique()
                ->values();

            if ($validSeats->count() !== $seatNumbers->count()) {
                throw ValidationException::withMessages([
                    'seat_numbers' => 'One or more selected seats are invalid or unavailable.',
                ]);
            }

            $alreadyBooked = $this->activeReservedSeatsQuery($trip->id)
                ->whereIn('booking_passengers.seat_number', $seatNumbers->all())
                ->pluck('booking_passengers.seat_number')
                ->map(fn ($seat) => $this->normalizeSeatNumber($seat))
                ->unique()
                ->values();

            if ($alreadyBooked->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'seat_numbers' => 'Seat(s) already booked: ' . $alreadyBooked->implode(', '),
                ]);
            }

            $farePerSeat = $this->calculateSegmentFare($trip, $journeyDistance);
            $count = $seatNumbers->count();
            $subtotal = round($farePerSeat * $count, 2);
            $discount = 0.00;
            $total = round($subtotal - $discount, 2);
            $reference = $this->uniqueBookingReference();

            $row = [
                'trip_id' => $trip->id,
                'passenger_user_id' => $passenger->id,
                'booking_reference' => $reference,
                'seat_numbers' => json_encode($seatNumbers->all()),
                'passenger_count' => $count,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'payment_status' => 'pending',
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $optionalBookingColumns = [
                'primary_passenger_name' => trim($data['primary_passenger_name']),
                'primary_passenger_nic' => strtoupper(trim($data['primary_passenger_nic'])),
                'phone' => $data['phone'] ?? null,
                'boarding_stop' => $boarding->name,
                'dropoff_stop' => $dropoff->name,
                'journey_distance_km' => round($journeyDistance, 2),
                'fare_per_seat' => $farePerSeat,
            ];

            foreach ($optionalBookingColumns as $column => $value) {
                if (Schema::hasColumn('bookings', $column)) {
                    $row[$column] = $value;
                }
            }

            if (Schema::hasColumn('bookings', 'hold_expires_at')) {
                $row['hold_expires_at'] = now()->addMinutes(self::HOLD_MINUTES);
            }

            $bookingId = DB::table('bookings')->insertGetId($row);

            foreach ($travellers as $traveller) {
                $passengerRow = [
                    'booking_id' => $bookingId,
                    'seat_number' => $traveller['seat_number'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (Schema::hasColumn('booking_passengers', 'passenger_name')) {
                    $passengerRow['passenger_name'] = $traveller['name'];
                } elseif (Schema::hasColumn('booking_passengers', 'name')) {
                    $passengerRow['name'] = $traveller['name'];
                }

                if (Schema::hasColumn('booking_passengers', 'gender')) {
                    $passengerRow['gender'] = $traveller['gender'];
                }

                if (Schema::hasColumn('booking_passengers', 'checked_in_at')) {
                    $passengerRow['checked_in_at'] = null;
                }

                if (Schema::hasColumn('booking_passengers', 'checked_in_by_staff_id')) {
                    $passengerRow['checked_in_by_staff_id'] = null;
                }

                DB::table('booking_passengers')->insert($passengerRow);
            }

            return (object) [
                'id' => $bookingId,
                'reference' => $reference,
                'seat_numbers' => $seatNumbers->all(),
                'passenger_count' => $count,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'fare_per_seat' => $farePerSeat,
                'journey_distance_km' => round($journeyDistance, 2),
            ];
        }, 3);

        return response()->json([
            'success' => true,
            'message' => 'Seats held for 10 minutes. Complete card payment to confirm the booking.',
            'booking_id' => $booking->id,
            'booking_reference' => $booking->reference,
            'seat_numbers' => $booking->seat_numbers,
            'passenger_count' => $booking->passenger_count,
            'fare_per_seat' => $booking->fare_per_seat,
            'journey_distance_km' => $booking->journey_distance_km,
            'subtotal' => $booking->subtotal,
            'discount' => $booking->discount,
            'total' => $booking->total,
            'payment_status' => 'pending',
            'status' => 'pending',
            'hold_minutes' => self::HOLD_MINUTES,
        ], 201);
    }

    public function availableSeats($id)
    {
        $trip = $this->tripQuery()->where('trips.id', $id)->first();

        if (!$trip) {
            return response()->json([
                'success' => false,
                'message' => 'Trip not found.',
            ], 404);
        }

        $bookedSeats = $this->activeReservedSeatsQuery($id)
            ->pluck('booking_passengers.seat_number')
            ->map(fn ($seat) => $this->normalizeSeatNumber($seat))
            ->values()
            ->all();

        $genderBySeat = DB::table('booking_passengers')
            ->join('bookings', 'bookings.id', '=', 'booking_passengers.booking_id')
            ->where('bookings.trip_id', $id)
            ->where('bookings.status', '!=', 'cancelled')
            ->when(
                Schema::hasColumn('booking_passengers', 'gender'),
                fn ($query) => $query->select('booking_passengers.seat_number', 'booking_passengers.gender'),
                fn ($query) => $query->select('booking_passengers.seat_number')
            )
            ->get()
            ->mapWithKeys(fn ($row) => [
                $this->normalizeSeatNumber($row->seat_number) => $row->gender ?? null,
            ]);

        $seats = DB::table('seats')
            ->where('bus_id', $trip->bus_id)
            ->orderByRaw("CAST(REPLACE(seat_number, 'S', '') AS UNSIGNED)")
            ->get()
            ->map(function ($seat) use ($bookedSeats, $genderBySeat) {
                $number = $this->normalizeSeatNumber($seat->seat_number);
                $disabled = (bool) ($seat->is_disabled ?? false);
                $booked = in_array($number, $bookedSeats, true);

                return [
                    'id' => $seat->id,
                    'seat_number' => $number,
                    'status' => $disabled ? 'unavailable' : ($booked ? 'booked' : 'available'),
                    'available' => !$disabled && !$booked,
                    'is_booked' => $booked,
                    'is_disabled' => $disabled,
                    'gender' => $booked ? $genderBySeat->get($number) : null,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'booking_available' => $this->isTripBookable($trip),
            'max_seats_per_booking' => self::MAX_SEATS,
            'minimum_journey_km' => self::MIN_JOURNEY_KM,
            'trip' => [
                'id' => $trip->id,
                'trip_code' => $trip->trip_code,
                'trip_type' => $trip->trip_type,
                'bus_id' => $trip->bus_id,
                'bus_number' => $trip->bus_number,
                'bus_name' => $trip->bus_name,
                'bus_type' => $trip->bus_type,
                'seat_count' => $trip->seat_count,
                'fare' => $trip->fare,
                'status' => $trip->status,
                'service_date' => $trip->service_date,
                'departure_time' => $trip->departure_time,
            ],
            'seats' => $seats,
            'booked_seats' => $bookedSeats,
        ]);
    }

    public function bookingDetails(Request $request, $id)
    {
        $passenger = $this->passenger($request);

        $booking = DB::table('bookings')
            ->where('id', $id)
            ->where('passenger_user_id', $passenger->id)
            ->first();

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'booking' => $booking,
            'passengers' => DB::table('booking_passengers')
                ->where('booking_id', $booking->id)
                ->orderByRaw("CAST(REPLACE(seat_number, 'S', '') AS UNSIGNED)")
                ->get(),
        ]);
    }

    public function payment(Request $request, $id)
    {
        $passenger = $this->passenger($request);

        $data = $request->validate([
            'method' => ['required', 'in:card'],
            'card_number' => ['required', 'string', 'regex:/^[0-9]{16}$/'],
            'card_holder_name' => ['required', 'string', 'max:120'],
            'cvv' => ['required', 'string', 'regex:/^[0-9]{3,4}$/'],
            'expiry_month' => ['required', 'integer', 'between:1,12'],
            'expiry_year' => ['required', 'integer', 'min:' . now()->year, 'max:' . (now()->year + 20)],
        ]);

        $expiry = Carbon::create((int) $data['expiry_year'], (int) $data['expiry_month'], 1)->endOfMonth();

        if ($expiry->isPast()) {
            throw ValidationException::withMessages([
                'expiry_month' => 'The card has expired.',
            ]);
        }

        $result = DB::transaction(function () use ($passenger, $id, $data) {
            $booking = DB::table('bookings')
                ->where('id', $id)
                ->where('passenger_user_id', $passenger->id)
                ->lockForUpdate()
                ->first();

            if (!$booking) {
                abort(404, 'Booking not found.');
            }

            if ($booking->status === 'cancelled') {
                throw ValidationException::withMessages([
                    'booking' => 'Cancelled booking cannot be paid.',
                ]);
            }

            if ($booking->payment_status === 'paid') {
                return (object) [
                    'booking' => $booking,
                    'reference' => null,
                    'ticket_token' => $booking->ticket_token ?? null,
                    'already_paid' => true,
                ];
            }

            if (
                Schema::hasColumn('bookings', 'hold_expires_at') &&
                $booking->hold_expires_at &&
                Carbon::parse($booking->hold_expires_at)->isPast()
            ) {
                DB::table('bookings')->where('id', $booking->id)->update([
                    'status' => 'cancelled',
                    'updated_at' => now(),
                ]);

                throw ValidationException::withMessages([
                    'booking' => 'Seat hold expired. Please select seats again.',
                ]);
            }

            $reference = 'PAY-' . now()->format('ymdHis') . '-' . strtoupper(Str::random(6));
            $ticketToken = Str::uuid()->toString();
            $last4 = substr($data['card_number'], -4);

            $payment = [
                'booking_id' => $booking->id,
                'amount' => $booking->total,
                'status' => 'success',
                'created_at' => now(),
            ];

            $possible = [
                'user_id' => $passenger->id,
                'payment_method' => 'card',
                'transaction_id' => $reference,
                'transaction_reference' => $reference,
                'card_holder' => $data['card_holder_name'],
                'card_holder_name' => $data['card_holder_name'],
                'card_last4' => $last4,
                'expiry_month' => str_pad((string) $data['expiry_month'], 2, '0', STR_PAD_LEFT),
                'expiry_year' => (string) $data['expiry_year'],
                'paid_at' => now(),
                'updated_at' => now(),
            ];

            foreach ($possible as $column => $value) {
                if (Schema::hasColumn('payments', $column)) {
                    $payment[$column] = $value;
                }
            }

            DB::table('payments')->insert($payment);

            $update = [
                'payment_status' => 'paid',
                'status' => 'confirmed',
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('bookings', 'ticket_token')) {
                $update['ticket_token'] = $ticketToken;
            }

            if (Schema::hasColumn('bookings', 'ticket_status')) {
                $update['ticket_status'] = 'valid';
            }

            if (Schema::hasColumn('bookings', 'hold_expires_at')) {
                $update['hold_expires_at'] = null;
            }

            DB::table('bookings')->where('id', $booking->id)->update($update);

            return (object) [
                'booking' => DB::table('bookings')->where('id', $booking->id)->first(),
                'reference' => $reference,
                'ticket_token' => $ticketToken,
                'already_paid' => false,
            ];
        }, 3);

        if (!$result->already_paid) {
            app(EastBusMailService::class)->bookingTicket(
                (int) $result->booking->id,
                $result->reference
            );
        }

        return response()->json([
            'success' => true,
            'message' => $result->already_paid
                ? 'This booking is already paid.'
                : 'Payment successful. Booking confirmed and QR ticket generated.',
            'booking_id' => $result->booking->id,
            'booking_reference' => $result->booking->booking_reference,
            'payment_status' => $result->booking->payment_status,
            'status' => $result->booking->status,
            'transaction_reference' => $result->reference,
            'qr_token' => $result->ticket_token ?? ($result->booking->ticket_token ?? null),
        ]);
    }

    public function ticket(Request $request, $id)
    {
        $passenger = $this->passenger($request);

        $booking = DB::table('bookings')
            ->join('trips', 'trips.id', '=', 'bookings.trip_id')
            ->join('routes', 'routes.id', '=', 'trips.route_id')
            ->join('buses', 'buses.id', '=', 'trips.bus_id')
            ->leftJoin('operators', 'operators.id', '=', 'trips.operator_id')
            ->where('bookings.id', $id)
            ->where('bookings.passenger_user_id', $passenger->id)
            ->select(
                'bookings.*',
                'trips.trip_code',
                'trips.service_date',
                'trips.departure_time',
                'trips.arrival_time',
                'trips.trip_type',
                'routes.origin',
                'routes.destination',
                'buses.bus_number',
                'buses.bus_name',
                'operators.company_name as operator_name'
            )
            ->first();

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found.',
            ], 404);
        }

        if ($booking->payment_status !== 'paid' || $booking->status !== 'confirmed') {
            return response()->json([
                'success' => false,
                'message' => 'Successful payment is required before the QR ticket is available.',
            ], 403);
        }

        if ($this->isReturnTrip($booking)) {
            $originalOrigin = $booking->origin;
            $booking->origin = $booking->destination;
            $booking->destination = $originalOrigin;
        }

        $passengers = DB::table('booking_passengers')
            ->where('booking_id', $booking->id)
            ->orderByRaw("CAST(REPLACE(seat_number, 'S', '') AS UNSIGNED)")
            ->get();

        $qrToken = Schema::hasColumn('bookings', 'ticket_token')
            ? $booking->ticket_token
            : hash('sha256', $booking->booking_reference . '|' . $booking->id);

        return response()->json([
            'success' => true,
            'ticket' => [
                'booking_id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'ticket_code' => 'TKT-' . strtoupper($booking->booking_reference),
                'qr_token' => $qrToken,
                'ticket_status' => $booking->ticket_status ?? 'valid',
                'company_name' => $booking->operator_name,
                'bus_name' => $booking->bus_name,
                'bus_number' => $booking->bus_number,
                'seat_numbers' => $this->decodeSeatNumbers($booking->seat_numbers),
                'passengers' => $passengers,
                'primary_passenger_name' => $booking->primary_passenger_name ?? null,
                'primary_passenger_nic' => $booking->primary_passenger_nic ?? null,
                'boarding_stop' => $booking->boarding_stop ?? $booking->origin,
                'dropoff_stop' => $booking->dropoff_stop ?? $booking->destination,
                'journey_distance_km' => $booking->journey_distance_km ?? null,
                'fare_per_seat' => $booking->fare_per_seat ?? null,
                'total' => $booking->total,
                'payment_status' => $booking->payment_status,
                'booking_status' => $booking->status,
                'trip_id' => $booking->trip_id,
                'trip_code' => $booking->trip_code,
                'origin' => $booking->origin,
                'destination' => $booking->destination,
                'service_date' => $booking->service_date,
                'departure_time' => $booking->departure_time,
                'arrival_time' => $booking->arrival_time,
            ],
        ]);
    }

    public function tracking(Request $request, $id)
    {
        $passenger = $this->passenger($request);

        $booking = DB::table('bookings')
            ->join('trips', 'trips.id', '=', 'bookings.trip_id')
            ->where('bookings.id', $id)
            ->where('bookings.passenger_user_id', $passenger->id)
            ->select(
                'bookings.id as booking_id',
                'bookings.booking_reference',
                'bookings.status as booking_status',
                'bookings.payment_status',
                'trips.id as trip_id',
                'trips.trip_code',
                'trips.status as trip_status'
            )
            ->first();

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking or trip not found.',
            ], 404);
        }

        if ($booking->booking_status !== 'confirmed' || $booking->payment_status !== 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'A paid and confirmed booking is required for live tracking.',
            ], 403);
        }

        if ($booking->trip_status !== 'active') {
            return response()->json([
                'success' => false,
                'tracking_available' => false,
                'message' => 'Live tracking becomes available after authorised staff starts the trip.',
                'trip_status' => $booking->trip_status,
            ], 409);
        }

        return response()->json([
            'success' => true,
            'tracking_available' => true,
            'booking_id' => $booking->booking_id,
            'booking_reference' => $booking->booking_reference,
            'trip_id' => $booking->trip_id,
            'trip_code' => $booking->trip_code,
            'trip_status' => $booking->trip_status,
            'firebase_path' => 'live_trips/' . $booking->trip_code,
        ]);
    }

    public function notifications(Request $request)
    {
        $passenger = $this->passenger($request);

        if (!Schema::hasTable('notifications')) {
            return response()->json([
                'success' => true,
                'notifications' => [],
            ]);
        }

        $query = DB::table('notifications');

        if (Schema::hasColumn('notifications', 'user_id')) {
            $query->where('user_id', $passenger->id);
        } elseif (Schema::hasColumn('notifications', 'passenger_user_id')) {
            $query->where('passenger_user_id', $passenger->id);
        } elseif (Schema::hasColumn('notifications', 'email')) {
            $query->where('email', $passenger->email);
        } else {
            return response()->json([
                'success' => true,
                'notifications' => [],
            ]);
        }

        return response()->json([
            'success' => true,
            'notifications' => $query->orderByDesc('created_at')->get(),
        ]);
    }

    public function cancelBooking(Request $request, $id)
    {
        $passenger = $this->passenger($request);

        $booking = DB::table('bookings')
            ->join('trips', 'trips.id', '=', 'bookings.trip_id')
            ->where('bookings.id', $id)
            ->where('bookings.passenger_user_id', $passenger->id)
            ->select('bookings.*', 'trips.status as trip_status')
            ->first();

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking not found.',
            ], 404);
        }

        if ($booking->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'Booking is already cancelled.',
            ], 422);
        }

        if (in_array($booking->trip_status, ['active', 'completed'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'An active or completed trip cannot be cancelled.',
            ], 422);
        }

        $checkedIn = Schema::hasColumn('booking_passengers', 'checked_in_at')
            && DB::table('booking_passengers')
                ->where('booking_id', $booking->id)
                ->whereNotNull('checked_in_at')
                ->exists();

        if ($checkedIn) {
            return response()->json([
                'success' => false,
                'message' => 'Checked-in booking cannot be cancelled.',
            ], 422);
        }

        $update = [
            'status' => 'cancelled',
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('bookings', 'ticket_status')) {
            $update['ticket_status'] = 'cancelled';
        }

        if (Schema::hasColumn('bookings', 'hold_expires_at')) {
            $update['hold_expires_at'] = null;
        }

        DB::table('bookings')->where('id', $id)->update($update);

        app(EastBusMailService::class)->bookingCancelled($passenger, $booking);

        return response()->json([
            'success' => true,
            'message' => 'Booking cancelled successfully.',
        ]);
    }

    private function passenger(Request $request)
    {
        $passenger = $request->attributes->get('passenger');

        abort_unless($passenger, 401, 'Passenger authentication required.');

        return $passenger;
    }

    private function tripQuery()
    {
        return DB::table('trips')
            ->join('routes', 'routes.id', '=', 'trips.route_id')
            ->join('buses', 'buses.id', '=', 'trips.bus_id')
            ->leftJoin('operators', 'operators.id', '=', 'trips.operator_id')
            ->select(
                'trips.*',
                'routes.name as route_name',
                'routes.origin',
                'routes.destination',
                'routes.distance_km',
                'routes.duration_minutes',
                'routes.base_fare',
                'buses.bus_number',
                'buses.bus_name',
                'buses.bus_type',
                'buses.seat_count',
                'buses.facilities',
                'operators.company_name as operator_name'
            );
    }

    private function orderedStopsForTrip($trip)
    {
        $stops = DB::table('route_stops')
            ->where('route_id', $trip->route_id)
            ->orderBy('stop_order')
            ->get();

        if ($this->isReturnTrip($trip)) {
            $stops = $stops->reverse()->values();
        }

        foreach ($stops as $index => $stop) {
            $stop->_journey_order = $index + 1;
        }

        return $stops;
    }

    private function journeyDistance($boarding, $dropoff): float
    {
        if (
            $boarding->distance_from_origin_km !== null &&
            $dropoff->distance_from_origin_km !== null
        ) {
            return abs(
                (float) $dropoff->distance_from_origin_km -
                (float) $boarding->distance_from_origin_km
            );
        }

        return $this->haversine(
            (float) $boarding->latitude,
            (float) $boarding->longitude,
            (float) $dropoff->latitude,
            (float) $dropoff->longitude
        );
    }

    private function calculateSegmentFare($trip, float $journeyDistance): float
    {
        $fullDistance = (float) ($trip->distance_km ?? 0);
        $fullFare = (float) ($trip->fare ?? $trip->base_fare ?? 0);

        if ($fullDistance > 0 && $fullFare > 0) {
            return round(($fullFare / $fullDistance) * $journeyDistance, 2);
        }

        return round($fullFare, 2);
    }

    private function activeReservedSeatsQuery(int $tripId)
    {
        $query = DB::table('booking_passengers')
            ->join('bookings', 'bookings.id', '=', 'booking_passengers.booking_id')
            ->where('bookings.trip_id', $tripId)
            ->where('bookings.status', '!=', 'cancelled');

        if (Schema::hasColumn('bookings', 'hold_expires_at')) {
            $query->where(function ($outer) {
                $outer->where('bookings.status', '!=', 'pending')
                    ->orWhere(function ($pending) {
                        $pending->where('bookings.status', 'pending')
                            ->where('bookings.hold_expires_at', '>', now());
                    });
            });
        }

        return $query;
    }

    private function isTripBookable($trip): bool
    {
        if (!(bool) $trip->is_published) {
            return false;
        }

        if (strtolower((string) $trip->status) !== 'scheduled') {
            return false;
        }

        if (!empty($trip->booking_closed_at)) {
            return false;
        }

        $departure = Carbon::parse(
            Carbon::parse($trip->service_date)->toDateString() . ' ' . $trip->departure_time
        );

        return $departure->isFuture();
    }

    private function normalizeSeatNumber($value): string
    {
        $number = preg_replace('/[^0-9]/', '', trim((string) $value));

        if ($number === '' || (int) $number <= 0) {
            return '';
        }

        return 'S' . (int) $number;
    }

    private function sameStopName(string $a, string $b): bool
    {
        return mb_strtolower(trim($a)) === mb_strtolower(trim($b));
    }

    private function isReturnTrip($trip): bool
    {
        return strtolower(trim((string) ($trip->trip_type ?? 'starting'))) === 'return';
    }

    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earth = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1))
            * cos(deg2rad($lat2))
            * sin($dLon / 2) ** 2;

        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function uniqueBookingReference(): string
    {
        do {
            $reference = 'EBK-' . now()->format('ymdHis') . '-' . strtoupper(Str::random(5));
        } while (
            DB::table('bookings')
                ->where('booking_reference', $reference)
                ->exists()
        );

        return $reference;
    }

    private function decodeSeatNumbers($value): array
    {
        $decoded = json_decode((string) $value, true);

        if (is_array($decoded)) {
            return collect($decoded)
                ->map(fn ($seat) => $this->normalizeSeatNumber($seat))
                ->filter()
                ->values()
                ->all();
        }

        return collect(explode(',', (string) $value))
            ->map(fn ($seat) => $this->normalizeSeatNumber($seat))
            ->filter()
            ->values()
            ->all();
    }
}
