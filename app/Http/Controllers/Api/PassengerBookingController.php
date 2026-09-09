<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location;
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
    private const BOOKING_CLOSE_MINUTES = 60;

    /*
    |--------------------------------------------------------------------------
    | Locations
    |--------------------------------------------------------------------------
    */

    public function locations(Request $request)
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $term = trim((string) ($data['search'] ?? ''));

        $query = Location::query()
            ->where('is_active', true);

        if ($term !== '') {
            $query->whereRaw(
                'LOWER(name) LIKE ?',
                ['%' . mb_strtolower($term) . '%']
            );
        }

        return response()->json([
            'success' => true,
            'locations' => $query
                ->select(['id', 'name'])
                ->orderBy('name')
                ->limit(50)
                ->get(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Search Trips
    |--------------------------------------------------------------------------
    */

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

        $matchingRouteIds = $this->findRoutesContainingStops(
            $origin,
            $destination
        );

        if ($matchingRouteIds->isEmpty()) {
            return response()->json([
                'success' => true,
                'origin' => $origin,
                'destination' => $destination,
                'date' => $date,
                'minimum_journey_km' => self::MIN_JOURNEY_KM,
                'trips' => [],
            ]);
        }

        $trips = $this->tripQuery()
            ->whereIn('trips.route_id', $matchingRouteIds->all())
            ->where('trips.is_published', true)
            ->where('trips.status', 'scheduled')
            ->whereNotNull('trips.fixed_service_id')
            ->whereDate('trips.service_date', $date)
            ->orderBy('trips.departure_time')
            ->get();

        $matches = $trips
            ->map(function ($trip) use ($origin, $destination) {
                if (
                    empty($trip->service_operator_id) ||
                    empty($trip->service_bus_id) ||
                    !(bool) $trip->service_active ||
                    !(bool) $trip->service_published
                ) {
                    return null;
                }

                $bookingStops = $this->bookingStopsForTrip($trip);

                if ($bookingStops->isEmpty()) {
                    return null;
                }

                $boarding = $bookingStops->first(
                    fn ($stop) =>
                        $this->sameStopName($stop->name, $origin) &&
                        (bool) $stop->boarding_allowed
                );

                $dropoff = $bookingStops->first(
                    fn ($stop) =>
                        $this->sameStopName($stop->name, $destination) &&
                        (bool) $stop->dropoff_allowed
                );

                if (!$boarding || !$dropoff) {
                    return null;
                }

                if (
                    (int) $boarding->_journey_order >=
                    (int) $dropoff->_journey_order
                ) {
                    return null;
                }

                $journeyDistance = $this->journeyDistance(
                    $boarding,
                    $dropoff
                );

                if ($journeyDistance < self::MIN_JOURNEY_KM) {
                    return null;
                }

                $fareStageDifference = abs(
                    (int) $dropoff->fare_stage_no -
                    (int) $boarding->fare_stage_no
                );

                if ($fareStageDifference < 1) {
                    return null;
                }

                $segmentFare = $this->calculateStageFare(
                    $trip,
                    $boarding,
                    $dropoff
                );

                if ($segmentFare === null) {
                    return null;
                }

                $trip->requested_origin = $boarding->name;
                $trip->requested_destination = $dropoff->name;

                $trip->boarding_stop = $boarding->name;
                $trip->dropoff_stop = $dropoff->name;

                $trip->boarding_time = $boarding->schedule_time;
                $trip->dropoff_time = $dropoff->schedule_time;

                $trip->journey_distance_km = round(
                    $journeyDistance,
                    2
                );

                $trip->minimum_journey_km = self::MIN_JOURNEY_KM;
                $trip->fare_stage_difference = $fareStageDifference;

                $trip->segment_fare = $segmentFare;
                $trip->fare = $segmentFare;

                $trip->direction = $this->isReturnTrip($trip)
                    ? 'return'
                    : 'starting';

                if ($this->isReturnTrip($trip)) {
                    $trip->origin = $trip->route_destination;
                    $trip->destination = $trip->route_origin;
                } else {
                    $trip->origin = $trip->route_origin;
                    $trip->destination = $trip->route_destination;
                }

                $availability = $this->seatAvailability($trip);
                $timeBookable = $this->isTripBookable($trip);

                $trip->seat_count = $availability['seat_count'];
                $trip->booked_seats_count = $availability['booked_seats_count'];
                $trip->available_seats = $availability['available_seats'];

                $trip->booking_closes_at =
                    $this->bookingCloseTime($trip)->toDateTimeString();

                $trip->booking_available =
                    $timeBookable &&
                    $availability['available_seats'] > 0;

                if (!$timeBookable) {
                    $trip->booking_status = 'closed';
                } elseif ($availability['available_seats'] <= 0) {
                    $trip->booking_status = 'sold_out';
                } else {
                    $trip->booking_status = 'available';
                }

                return $trip;
            })
            ->filter()
            ->sortBy(
                fn ($trip) =>
                    $trip->boarding_time ??
                    $trip->departure_time ??
                    '23:59:59'
            )
            ->values();

        return response()->json([
            'success' => true,
            'origin' => $origin,
            'destination' => $destination,
            'date' => $date,
            'minimum_journey_km' => self::MIN_JOURNEY_KM,
            'trips' => $matches,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Trip Details
    |--------------------------------------------------------------------------
    */

    public function tripDetails($id)
    {
        $trip = $this->tripQuery()
            ->where('trips.id', $id)
            ->first();

        if (!$trip) {
            return response()->json([
                'success' => false,
                'message' => 'Trip not found.',
            ], 404);
        }

        $roadWay = $this->roadWayForRoute(
            (int) $trip->route_id
        );

        $startingSchedule = !empty($trip->fixed_service_id)
            ? $this->bookingScheduleForService(
                (int) $trip->fixed_service_id,
                'starting'
            )
            : collect();

        $returnSchedule = !empty($trip->fixed_service_id)
            ? $this->bookingScheduleForService(
                (int) $trip->fixed_service_id,
                'return'
            )
            : collect();

        $bookableStops = $this->bookingStopsForTrip($trip)
            ->map(fn ($stop) => [
                'fixed_service_stop_id' => (int) $stop->id,
                'route_booking_stop_id' => (int) $stop->id,
                'route_stop_id' => (int) $stop->route_stop_id,
                'name' => $stop->name,
                'stop_order' => (int) $stop->_journey_order,
                'schedule_time' => $stop->schedule_time,
                'arrival_time' => $stop->arrival_time,
                'departure_time' => $stop->departure_time,
                'fare_stage_no' => $stop->fare_stage_no !== null
                    ? (int) $stop->fare_stage_no
                    : null,
                'distance_from_origin' => (float) $stop->distance_from_origin,
                'distance_from_origin_km' => (float) $stop->distance_from_origin_km,
                'boarding_allowed' => (bool) $stop->boarding_allowed,
                'dropoff_allowed' => (bool) $stop->dropoff_allowed,
            ])
            ->values();

        if ($this->isReturnTrip($trip)) {
            $trip->origin = $trip->route_destination;
            $trip->destination = $trip->route_origin;
        } else {
            $trip->origin = $trip->route_origin;
            $trip->destination = $trip->route_destination;
        }

        $availability = $this->seatAvailability($trip);
        $timeBookable = $this->isTripBookable($trip);

        $trip->seat_count = $availability['seat_count'];
        $trip->booked_seats_count = $availability['booked_seats_count'];
        $trip->available_seats = $availability['available_seats'];

        $trip->booking_closes_at =
            $this->bookingCloseTime($trip)->toDateTimeString();

        $trip->booking_available =
            $timeBookable &&
            $availability['available_seats'] > 0;

        $trip->booking_status = !$timeBookable
            ? 'closed'
            : (
                $availability['available_seats'] <= 0
                    ? 'sold_out'
                    : 'available'
            );

        return response()->json([
            'success' => true,
            'trip' => $trip,
            'road_way' => $roadWay,
            'starting_schedule' => $startingSchedule,
            'return_schedule' => $returnSchedule,
            'bookable_stops' => $bookableStops,

            'booking_available' => $trip->booking_available,
            'booking_status' => $trip->booking_status,

            'available_seats' => $availability['available_seats'],
            'booked_seats_count' => $availability['booked_seats_count'],
            'seat_count' => $availability['seat_count'],

            'booking_closes_at' => $trip->booking_closes_at,
            'minimum_journey_km' => self::MIN_JOURNEY_KM,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | My Bookings
    |--------------------------------------------------------------------------
    */

    public function bookings(Request $request)
    {
        $passenger = $this->passenger($request);

        $bookings = DB::table('bookings')
            ->join('trips', 'trips.id', '=', 'bookings.trip_id')
            ->join('routes', 'routes.id', '=', 'trips.route_id')
            ->join('buses', 'buses.id', '=', 'trips.bus_id')
            ->leftJoin(
                'fixed_services',
                'fixed_services.id',
                '=',
                'trips.fixed_service_id'
            )
            ->leftJoin(
                'operators',
                'operators.id',
                '=',
                'trips.operator_id'
            )
            ->where(
                'bookings.passenger_user_id',
                $passenger->id
            )
            ->select(
                'bookings.*',
                'trips.trip_code',
                'trips.route_id',
                'trips.fixed_service_id',
                'trips.bus_id',
                'trips.service_date',
                'trips.departure_time',
                'trips.arrival_time',
                'trips.status as trip_status',
                'trips.trip_type',
                'routes.route_number',
                'routes.origin as route_origin',
                'routes.destination as route_destination',
                'buses.bus_number',
                'buses.bus_name',
                'buses.bus_type',
                'fixed_services.service_name',
                'operators.company_name',
                'operators.company_name as operator_name'
            )
            ->orderByDesc('bookings.id')
            ->get();

        foreach ($bookings as $booking) {
            if ($this->isReturnTrip($booking)) {
                $booking->origin = $booking->route_destination;
                $booking->destination = $booking->route_origin;
            } else {
                $booking->origin = $booking->route_origin;
                $booking->destination = $booking->route_destination;
            }

            $booking->passengers = DB::table('booking_passengers')
                ->where('booking_id', $booking->id)
                ->orderByRaw(
                    "CAST(REPLACE(seat_number, 'S', '') AS UNSIGNED)"
                )
                ->get();

            $booking->seats = $this->decodeSeatNumbers(
                $booking->seat_numbers
            );

            $journeyMeta = $this->bookingJourneyMeta($booking);

            $booking->boarding_time =
                $journeyMeta['boarding_time'];

            $booking->dropoff_time =
                $journeyMeta['dropoff_time'];

            $booking->fare_stage_difference =
                $journeyMeta['fare_stage_difference'];

            if ($booking->journey_distance_km === null) {
                $booking->journey_distance_km =
                    $journeyMeta['journey_distance_km'];
            }

            if ($booking->fare_per_seat === null) {
                $booking->fare_per_seat =
                    $journeyMeta['fare_per_seat'];
            }

            $booking->feedback_submitted =
                Schema::hasTable('trip_feedback') &&
                DB::table('trip_feedback')
                    ->where('booking_id', $booking->id)
                    ->where('passenger_id', $passenger->id)
                    ->exists();

            $bookingStatus = strtolower(
                (string) $booking->status
            );

            $paymentStatus = strtolower(
                (string) $booking->payment_status
            );

            $tripStatus = strtolower(
                (string) $booking->trip_status
            );

            $booking->feedback_available =
                $tripStatus === 'completed' &&
                in_array(
                    $bookingStatus,
                    ['confirmed', 'completed'],
                    true
                ) &&
                $paymentStatus === 'paid' &&
                !$booking->feedback_submitted;

            $booking->tracking_available =
                $tripStatus === 'active' &&
                $bookingStatus === 'confirmed' &&
                $paymentStatus === 'paid';
        }

        return response()->json([
            'success' => true,
            'bookings' => $bookings,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Create Booking
    |--------------------------------------------------------------------------
    */

    public function createBooking(Request $request)
    {
        $passenger = $this->passenger($request);

        $data = $request->validate([
            'trip_id' => [
                'required',
                'integer',
                'exists:trips,id',
            ],

            'origin' => [
                'required',
                'string',
                'max:120',
            ],

            'destination' => [
                'required',
                'string',
                'max:120',
                'different:origin',
            ],

            'boarding_stop' => [
                'nullable',
                'string',
                'max:120',
            ],

            'dropoff_stop' => [
                'nullable',
                'string',
                'max:120',
            ],

            'seat_numbers' => [
                'required',
                'array',
                'min:1',
                'max:' . self::MAX_SEATS,
            ],

            'seat_numbers.*' => [
                'required',
                'string',
                'max:20',
            ],

            'primary_passenger_name' => [
                'required',
                'string',
                'max:150',
            ],

            'primary_passenger_nic' => [
                'required',
                'string',
                'regex:/^([0-9]{9}[VvXx]|[0-9]{12})$/',
            ],

            'phone' => [
                'nullable',
                'string',
                'regex:/^(?:\+94|0)7[0-9]{8}$/',
            ],

            'travellers' => [
                'required',
                'array',
                'min:1',
                'max:' . self::MAX_SEATS,
            ],

            'travellers.*.seat_number' => [
                'required',
                'string',
                'max:20',
            ],

            /*
             * IMPORTANT:
             * Every traveller must provide own identity.
             */
            'travellers.*.passenger_name' => [
                'required',
                'string',
                'max:150',
            ],

            'travellers.*.nic' => [
                'required',
                'string',
                'regex:/^([0-9]{9}[VvXx]|[0-9]{12})$/',
            ],

            'travellers.*.gender' => [
                'required',
                'in:male,female',
            ],
        ], [
            'primary_passenger_nic.regex' =>
                'Enter a valid Sri Lankan NIC number.',

            'travellers.*.passenger_name.required' =>
                'Passenger name is required for every selected seat.',

            'travellers.*.nic.required' =>
                'NIC is required for every selected seat.',

            'travellers.*.nic.regex' =>
                'Enter a valid Sri Lankan NIC number for each traveller.',

            'phone.regex' =>
                'Phone number must use +947XXXXXXXX or 07XXXXXXXX.',
        ]);

        $seatNumbers = collect($data['seat_numbers'])
            ->map(
                fn ($seat) =>
                    $this->normalizeSeatNumber($seat)
            )
            ->filter()
            ->unique()
            ->values();

        if (
            $seatNumbers->count() !==
            count($data['seat_numbers'])
        ) {
            throw ValidationException::withMessages([
                'seat_numbers' =>
                    'Duplicate or invalid seats were selected.',
            ]);
        }

        /*
         * Every traveller uses own name and NIC.
         *
         * Never copy the primary passenger identity
         * into other traveller records.
         */
        $travellers = collect($data['travellers'])
            ->map(fn ($row) => [
                'seat_number' =>
                    $this->normalizeSeatNumber(
                        $row['seat_number']
                    ),

                'passenger_name' =>
                    trim($row['passenger_name']),

                'nic' =>
                    strtoupper(
                        trim($row['nic'])
                    ),

                'gender' =>
                    strtolower(
                        trim($row['gender'])
                    ),
            ])
            ->values();

        if (
            $travellers->count() !==
            $seatNumbers->count()
        ) {
            throw ValidationException::withMessages([
                'travellers' =>
                    'Traveller details are required for every selected seat.',
            ]);
        }

        $travellerSeats = $travellers
            ->pluck('seat_number');

        if (
            $travellerSeats->contains('') ||
            $travellerSeats->unique()->count() !==
            $travellerSeats->count()
        ) {
            throw ValidationException::withMessages([
                'travellers' =>
                    'Each traveller must be assigned to one unique valid seat.',
            ]);
        }

        $selectedSeats = $seatNumbers
            ->sort()
            ->values();

        $travellerSeats = $travellerSeats
            ->sort()
            ->values();

        if (
            $travellerSeats->all() !==
            $selectedSeats->all()
        ) {
            throw ValidationException::withMessages([
                'travellers' =>
                    'Traveller seat details must match all selected seats.',
            ]);
        }

        $booking = DB::transaction(
            function () use (
                $passenger,
                $data,
                $seatNumbers,
                $travellers
            ) {
                $trip = DB::table('trips')
                    ->join(
                        'buses',
                        'buses.id',
                        '=',
                        'trips.bus_id'
                    )
                    ->leftJoin(
                        'fixed_services',
                        'fixed_services.id',
                        '=',
                        'trips.fixed_service_id'
                    )
                    ->where(
                        'trips.id',
                        $data['trip_id']
                    )
                    ->select(
                        'trips.*',
                        'buses.seat_count',
                        'buses.bus_type',
                        'fixed_services.operator_id as service_operator_id',
                        'fixed_services.bus_id as service_bus_id',
                        'fixed_services.is_active as service_active',
                        'fixed_services.is_published as service_published'
                    )
                    ->lockForUpdate()
                    ->first();

                if (!$trip) {
                    abort(404, 'Trip not found.');
                }

                if (
                    Carbon::parse($trip->service_date)
                        ->startOfDay()
                        ->lt(today())
                ) {
                    throw ValidationException::withMessages([
                        'trip_id' =>
                            'Past trips cannot be booked.',
                    ]);
                }

                if (
                    empty($trip->fixed_service_id) ||
                    empty($trip->service_operator_id) ||
                    empty($trip->service_bus_id)
                ) {
                    throw ValidationException::withMessages([
                        'trip_id' =>
                            'This trip is not linked to a valid bookable Bus Route Service.',
                    ]);
                }

                if (
                    !(bool) $trip->service_active ||
                    !(bool) $trip->service_published
                ) {
                    throw ValidationException::withMessages([
                        'trip_id' =>
                            'The Bus Route Service for this trip is currently unavailable.',
                    ]);
                }

                if (!$this->isTripBookable($trip)) {
                    throw ValidationException::withMessages([
                        'trip_id' =>
                            'Booking is closed. Online booking closes 1 hour before departure.',
                    ]);
                }

                $bookingStops =
                    $this->bookingStopsForTrip($trip);

                $originName = trim(
                    (string) (
                        $data['boarding_stop']
                        ?? $data['origin']
                    )
                );

                $destinationName = trim(
                    (string) (
                        $data['dropoff_stop']
                        ?? $data['destination']
                    )
                );

                $boarding = $bookingStops->first(
                    fn ($stop) =>
                        $this->sameStopName(
                            $stop->name,
                            $originName
                        ) &&
                        (bool) $stop->boarding_allowed
                );

                $dropoff = $bookingStops->first(
                    fn ($stop) =>
                        $this->sameStopName(
                            $stop->name,
                            $destinationName
                        ) &&
                        (bool) $stop->dropoff_allowed
                );

                if (
                    !$boarding ||
                    !$dropoff ||
                    (int) $boarding->_journey_order >=
                    (int) $dropoff->_journey_order
                ) {
                    throw ValidationException::withMessages([
                        'route' =>
                            'Selected boarding and drop-off stops are not valid for this trip direction.',
                    ]);
                }

                /*
                 * Also close booking one hour before
                 * passenger's selected boarding time.
                 */
                if (!empty($boarding->schedule_time)) {
                    $boardingDateTime =
                        $this->scheduledDateTime(
                            $trip->service_date,
                            $boarding->schedule_time
                        );

                    if (
                        now()->gte(
                            $boardingDateTime
                                ->copy()
                                ->subMinutes(
                                    self::BOOKING_CLOSE_MINUTES
                                )
                        )
                    ) {
                        throw ValidationException::withMessages([
                            'trip_id' =>
                                'Booking is closed for the selected boarding point.',
                        ]);
                    }
                }

                $journeyDistance =
                    $this->journeyDistance(
                        $boarding,
                        $dropoff
                    );

                if (
                    $journeyDistance <
                    self::MIN_JOURNEY_KM
                ) {
                    throw ValidationException::withMessages([
                        'destination' =>
                            'The minimum online-booking distance is ' .
                            self::MIN_JOURNEY_KM .
                            ' km.',
                    ]);
                }

                $fareStageDifference = abs(
                    (int) $dropoff->fare_stage_no -
                    (int) $boarding->fare_stage_no
                );

                if ($fareStageDifference < 1) {
                    throw ValidationException::withMessages([
                        'route' =>
                            'Invalid fare-stage difference for the selected journey.',
                    ]);
                }

                $farePerSeat =
                    $this->calculateStageFare(
                        $trip,
                        $boarding,
                        $dropoff
                    );

                if ($farePerSeat === null) {
                    throw ValidationException::withMessages([
                        'route' =>
                            'Fare is not available for the selected journey and bus type.',
                    ]);
                }

                /*
                 * Validate selected seats.
                 */
                $validSeats = DB::table('seats')
                    ->where(
                        'bus_id',
                        $trip->bus_id
                    )
                    ->whereIn(
                        'seat_number',
                        $seatNumbers->all()
                    )
                    ->where(
                        'is_disabled',
                        false
                    )
                    ->pluck('seat_number')
                    ->map(
                        fn ($seat) =>
                            $this->normalizeSeatNumber($seat)
                    )
                    ->unique()
                    ->values();

                if (
                    $validSeats->count() !==
                    $seatNumbers->count()
                ) {
                    throw ValidationException::withMessages([
                        'seat_numbers' =>
                            'One or more selected seats are invalid or unavailable.',
                    ]);
                }

                /*
                 * Final seat availability check inside transaction.
                 */
                $alreadyBooked =
                    $this->activeReservedSeatsQuery(
                        (int) $trip->id
                    )
                    ->whereIn(
                        'booking_passengers.seat_number',
                        $seatNumbers->all()
                    )
                    ->pluck(
                        'booking_passengers.seat_number'
                    )
                    ->map(
                        fn ($seat) =>
                            $this->normalizeSeatNumber($seat)
                    )
                    ->unique()
                    ->values();

                if ($alreadyBooked->isNotEmpty()) {
                    throw ValidationException::withMessages([
                        'seat_numbers' =>
                            'Seat(s) already booked: ' .
                            $alreadyBooked->implode(', '),
                    ]);
                }

                $count = $seatNumbers->count();

                $subtotal = round(
                    $farePerSeat * $count,
                    2
                );

                $discount = 0.00;

                $total = round(
                    $subtotal - $discount,
                    2
                );

                $reference =
                    $this->uniqueBookingReference();

                $bookingId = DB::table('bookings')
                    ->insertGetId([
                        'trip_id' => $trip->id,
                        'passenger_user_id' => $passenger->id,
                        'booking_reference' => $reference,
                        'seat_numbers' => json_encode(
                            $seatNumbers->all()
                        ),
                        'passenger_count' => $count,

                        'primary_passenger_name' =>
                            trim(
                                $data['primary_passenger_name']
                            ),

                        'primary_passenger_nic' =>
                            strtoupper(
                                trim(
                                    $data['primary_passenger_nic']
                                )
                            ),

                        'boarding_stop' =>
                            $boarding->name,

                        'dropoff_stop' =>
                            $dropoff->name,

                        'journey_distance_km' =>
                            round(
                                $journeyDistance,
                                2
                            ),

                        'fare_per_seat' =>
                            $farePerSeat,

                        'subtotal' =>
                            $subtotal,

                        'discount' =>
                            $discount,

                        'total' =>
                            $total,

                        'payment_status' =>
                            'pending',

                        'ticket_token' =>
                            null,

                        'ticket_status' =>
                            null,

                        'status' =>
                            'pending',

                        'hold_expires_at' =>
                            now()->addMinutes(
                                self::HOLD_MINUTES
                            ),

                        'created_at' =>
                            now(),

                        'updated_at' =>
                            now(),
                    ]);

                /*
                 * Store each passenger's own name and NIC.
                 */
                foreach ($travellers as $traveller) {
                    DB::table(
                        'booking_passengers'
                    )->insert([
                        'booking_id' =>
                            $bookingId,

                        'passenger_name' =>
                            $traveller['passenger_name'],

                        'nic' =>
                            $traveller['nic'],

                        'seat_number' =>
                            $traveller['seat_number'],

                        'gender' =>
                            $traveller['gender'],

                        'checked_in_at' =>
                            null,

                        'checked_in_by_staff_id' =>
                            null,

                        'created_at' =>
                            now(),

                        'updated_at' =>
                            now(),
                    ]);
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
                    'fare_stage_difference' => $fareStageDifference,
                    'journey_distance_km' => round(
                        $journeyDistance,
                        2
                    ),
                    'boarding_stop' => $boarding->name,
                    'dropoff_stop' => $dropoff->name,
                    'boarding_time' => $boarding->schedule_time,
                    'dropoff_time' => $dropoff->schedule_time,
                ];
            },
            3
        );

        return response()->json([
            'success' => true,

            'message' =>
                'Seats held for 10 minutes. Complete card payment to confirm the booking.',

            'booking_id' =>
                $booking->id,

            'booking_reference' =>
                $booking->reference,

            'seat_numbers' =>
                $booking->seat_numbers,

            'passenger_count' =>
                $booking->passenger_count,

            'boarding_stop' =>
                $booking->boarding_stop,

            'dropoff_stop' =>
                $booking->dropoff_stop,

            'boarding_time' =>
                $booking->boarding_time,

            'dropoff_time' =>
                $booking->dropoff_time,

            'fare_stage_difference' =>
                $booking->fare_stage_difference,

            'fare_per_seat' =>
                $booking->fare_per_seat,

            'journey_distance_km' =>
                $booking->journey_distance_km,

            'subtotal' =>
                $booking->subtotal,

            'discount' =>
                $booking->discount,

            'total' =>
                $booking->total,

            'payment_status' =>
                'pending',

            'status' =>
                'pending',

            'hold_minutes' =>
                self::HOLD_MINUTES,
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | Available Seats
    |--------------------------------------------------------------------------
    */

    public function availableSeats($id)
    {
        $trip = $this->tripQuery()
            ->where('trips.id', $id)
            ->first();

        if (!$trip) {
            return response()->json([
                'success' => false,
                'message' => 'Trip not found.',
            ], 404);
        }

        $activeSeats = $this->activeReservedSeatsQuery(
            (int) $id
        )
            ->select(
                'booking_passengers.seat_number',
                'booking_passengers.gender'
            )
            ->get();

        $bookedSeats = $activeSeats
            ->pluck('seat_number')
            ->map(
                fn ($seat) =>
                    $this->normalizeSeatNumber($seat)
            )
            ->filter()
            ->unique()
            ->values()
            ->all();

        $genderBySeat = $activeSeats
            ->mapWithKeys(fn ($row) => [
                $this->normalizeSeatNumber(
                    $row->seat_number
                ) => $row->gender,
            ]);

        $seats = DB::table('seats')
            ->where('bus_id', $trip->bus_id)
            ->orderByRaw(
                "CAST(REPLACE(seat_number, 'S', '') AS UNSIGNED)"
            )
            ->get()
            ->map(function ($seat) use (
                $bookedSeats,
                $genderBySeat
            ) {
                $number =
                    $this->normalizeSeatNumber(
                        $seat->seat_number
                    );

                $disabled =
                    (bool) $seat->is_disabled;

                $booked = in_array(
                    $number,
                    $bookedSeats,
                    true
                );

                return [
                    'id' => (int) $seat->id,
                    'seat_number' => $number,

                    'status' => $disabled
                        ? 'unavailable'
                        : (
                            $booked
                                ? 'booked'
                                : 'available'
                        ),

                    'available' =>
                        !$disabled && !$booked,

                    'is_booked' =>
                        $booked,

                    'is_disabled' =>
                        $disabled,

                    'gender' =>
                        $booked
                            ? $genderBySeat->get($number)
                            : null,
                ];
            })
            ->values();

        $availableCount =
            $seats
                ->where('available', true)
                ->count();

        $timeBookable =
            $this->isTripBookable($trip);

        $bookingAvailable =
            $timeBookable &&
            $availableCount > 0;

        $bookingStatus = !$timeBookable
            ? 'closed'
            : (
                $availableCount <= 0
                    ? 'sold_out'
                    : 'available'
            );

        return response()->json([
            'success' => true,

            'booking_available' =>
                $bookingAvailable,

            'booking_status' =>
                $bookingStatus,

            'booking_closes_at' =>
                $this->bookingCloseTime(
                    $trip
                )->toDateTimeString(),

            'max_seats_per_booking' =>
                self::MAX_SEATS,

            'minimum_journey_km' =>
                self::MIN_JOURNEY_KM,

            'trip' => [
                'id' => (int) $trip->id,
                'fixed_service_id' => $trip->fixed_service_id,
                'trip_code' => $trip->trip_code,
                'trip_type' => $trip->trip_type,
                'bus_id' => (int) $trip->bus_id,
                'bus_number' => $trip->bus_number,
                'bus_name' => $trip->bus_name,
                'bus_type' => $trip->bus_type,
                'seat_count' => $seats->count(),
                'available_seats' => $availableCount,
                'booked_seats_count' => count($bookedSeats),
                'fare' => null,
                'status' => $trip->status,
                'service_date' => $trip->service_date,
                'departure_time' => $trip->departure_time,
            ],

            'seats' => $seats,
            'booked_seats' => $bookedSeats,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Booking Details
    |--------------------------------------------------------------------------
    */

    public function bookingDetails(Request $request, $id)
    {
        $passenger = $this->passenger($request);

        $booking = DB::table('bookings')
            ->where('id', $id)
            ->where(
                'passenger_user_id',
                $passenger->id
            )
            ->first();

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking not found.',
            ], 404);
        }

        $passengers = DB::table(
            'booking_passengers'
        )
            ->where(
                'booking_id',
                $booking->id
            )
            ->orderByRaw(
                "CAST(REPLACE(seat_number, 'S', '') AS UNSIGNED)"
            )
            ->get();

        return response()->json([
            'success' => true,
            'booking' => $booking,
            'passengers' => $passengers,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Demonstration Card Payment
    |--------------------------------------------------------------------------
    */

    public function payment(Request $request, $id)
    {
        $passenger = $this->passenger($request);

        $data = $request->validate([
            'method' => ['required', 'in:card'],

            'card_number' => [
                'required',
                'string',
                'regex:/^[0-9]{16}$/',
            ],

            'card_holder_name' => [
                'required',
                'string',
                'max:120',
            ],

            'cvv' => [
                'required',
                'string',
                'regex:/^[0-9]{3,4}$/',
            ],

            'expiry_month' => [
                'required',
                'integer',
                'between:1,12',
            ],

            'expiry_year' => [
                'required',
                'integer',
                'min:' . now()->year,
                'max:' . (now()->year + 20),
            ],
        ]);

        $expiry = Carbon::create(
            (int) $data['expiry_year'],
            (int) $data['expiry_month'],
            1
        )->endOfMonth();

        if ($expiry->isPast()) {
            throw ValidationException::withMessages([
                'expiry_month' =>
                    'The card has expired.',
            ]);
        }

        $result = DB::transaction(
            function () use (
                $passenger,
                $id,
                $data
            ) {
                $booking = DB::table('bookings')
                    ->where('id', $id)
                    ->where(
                        'passenger_user_id',
                        $passenger->id
                    )
                    ->lockForUpdate()
                    ->first();

                if (!$booking) {
                    abort(404, 'Booking not found.');
                }

                if (
                    strtolower(
                        (string) $booking->status
                    ) === 'cancelled'
                ) {
                    throw ValidationException::withMessages([
                        'booking' =>
                            'Cancelled booking cannot be paid.',
                    ]);
                }

                if (
                    strtolower(
                        (string) $booking->payment_status
                    ) === 'paid'
                ) {
                    return (object) [
                        'booking' => $booking,
                        'reference' => null,
                        'ticket_token' =>
                            $booking->ticket_token,
                        'already_paid' => true,
                    ];
                }

                /*
                 * Existing seat hold may complete payment
                 * until hold_expires_at.
                 *
                 * No NEW booking is accepted after the
                 * one-hour booking closure.
                 */
                if (
                    empty($booking->hold_expires_at) ||
                    Carbon::parse(
                        $booking->hold_expires_at
                    )->lte(now())
                ) {
                    DB::table('bookings')
                        ->where(
                            'id',
                            $booking->id
                        )
                        ->update([
                            'status' => 'cancelled',
                            'hold_expires_at' => null,
                            'updated_at' => now(),
                        ]);

                    throw ValidationException::withMessages([
                        'booking' =>
                            'Seat hold expired. Please select seats again.',
                    ]);
                }

                $trip = DB::table('trips')
                    ->where(
                        'id',
                        $booking->trip_id
                    )
                    ->first();

                if (
                    !$trip ||
                    strtolower(
                        (string) $trip->status
                    ) !== 'scheduled'
                ) {
                    throw ValidationException::withMessages([
                        'booking' =>
                            'This trip is no longer available for payment.',
                    ]);
                }

                if (
                    $this->tripDepartureTime($trip)
                        ->lte(now())
                ) {
                    throw ValidationException::withMessages([
                        'booking' =>
                            'This trip has already reached its scheduled departure time.',
                    ]);
                }

                $reference =
                    'PAY-' .
                    now()->format('ymdHis') .
                    '-' .
                    strtoupper(Str::random(6));

                $ticketToken =
                    Str::uuid()->toString();

                $last4 = substr(
                    $data['card_number'],
                    -4
                );

                DB::table('payments')->insert([
                    'booking_id' =>
                        $booking->id,

                    'transaction_id' =>
                        $reference,

                    'amount' =>
                        $booking->total,

                    'method' =>
                        'card',

                    'status' =>
                        'success',

                    'paid_at' =>
                        now(),

                    'gateway_reference' =>
                        $reference,

                    'payment_method' =>
                        'card',

                    'transaction_reference' =>
                        $reference,

                    'card_last4' =>
                        $last4,

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

                DB::table('bookings')
                    ->where(
                        'id',
                        $booking->id
                    )
                    ->update([
                        'payment_status' =>
                            'paid',

                        'status' =>
                            'confirmed',

                        'ticket_token' =>
                            $ticketToken,

                        'ticket_status' =>
                            'valid',

                        'hold_expires_at' =>
                            null,

                        'updated_at' =>
                            now(),
                    ]);

                return (object) [
                    'booking' =>
                        DB::table('bookings')
                            ->where(
                                'id',
                                $booking->id
                            )
                            ->first(),

                    'reference' =>
                        $reference,

                    'ticket_token' =>
                        $ticketToken,

                    'already_paid' =>
                        false,
                ];
            },
            3
        );

        return response()->json([
            'success' => true,

            'message' =>
                $result->already_paid
                    ? 'This booking is already paid.'
                    : 'Payment successful. Booking confirmed and QR ticket generated.',

            'booking_id' =>
                (int) $result->booking->id,

            'booking_reference' =>
                $result->booking->booking_reference,

            'payment_status' =>
                $result->booking->payment_status,

            'status' =>
                $result->booking->status,

            'transaction_reference' =>
                $result->reference,

            'qr_token' =>
                $result->ticket_token
                ?? $result->booking->ticket_token,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Ticket
    |--------------------------------------------------------------------------
    */

    public function ticket(Request $request, $id)
    {
        $passenger = $this->passenger($request);

        $booking = DB::table('bookings')
            ->join(
                'trips',
                'trips.id',
                '=',
                'bookings.trip_id'
            )
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
            ->leftJoin(
                'fixed_services',
                'fixed_services.id',
                '=',
                'trips.fixed_service_id'
            )
            ->leftJoin(
                'operators',
                'operators.id',
                '=',
                'trips.operator_id'
            )
            ->where(
                'bookings.id',
                $id
            )
            ->where(
                'bookings.passenger_user_id',
                $passenger->id
            )
            ->select(
                'bookings.*',
                'trips.trip_code',
                'trips.route_id',
                'trips.fixed_service_id',
                'trips.bus_id',
                'trips.service_date',
                'trips.departure_time',
                'trips.arrival_time',
                'trips.trip_type',
                'trips.status as trip_status',
                'routes.route_number',
                'routes.origin as route_origin',
                'routes.destination as route_destination',
                'buses.bus_number',
                'buses.bus_name',
                'buses.bus_type',
                'fixed_services.service_name',
                'operators.company_name as operator_name'
            )
            ->first();

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found.',
            ], 404);
        }

        if (
            strtolower(
                (string) $booking->payment_status
            ) !== 'paid' ||
            strtolower(
                (string) $booking->status
            ) !== 'confirmed'
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Successful payment is required before the QR ticket is available.',
            ], 403);
        }

        if ($this->isReturnTrip($booking)) {
            $booking->origin =
                $booking->route_destination;

            $booking->destination =
                $booking->route_origin;
        } else {
            $booking->origin =
                $booking->route_origin;

            $booking->destination =
                $booking->route_destination;
        }

        /*
         * Individual passenger identity comes only
         * from booking_passengers.
         *
         * No primary-passenger fallback is used here.
         */
        $passengers = DB::table(
            'booking_passengers'
        )
            ->where(
                'booking_id',
                $booking->id
            )
            ->orderByRaw(
                "CAST(REPLACE(seat_number, 'S', '') AS UNSIGNED)"
            )
            ->get([
                'id',
                'booking_id',
                'passenger_name',
                'nic',
                'seat_number',
                'gender',
                'checked_in_at',
                'checked_in_by_staff_id',
            ]);

        $journeyMeta =
            $this->bookingJourneyMeta($booking);

        return response()->json([
            'success' => true,

            'ticket' => [
                'booking_id' =>
                    (int) $booking->id,

                'booking_reference' =>
                    $booking->booking_reference,

                'ticket_code' =>
                    'TKT-' .
                    strtoupper(
                        $booking->booking_reference
                    ),

                'qr_token' =>
                    $booking->ticket_token,

                'ticket_status' =>
                    $booking->ticket_status
                    ?? 'valid',

                'company_name' =>
                    $booking->operator_name,

                'bus_name' =>
                    $booking->bus_name,

                'bus_number' =>
                    $booking->bus_number,

                'seat_numbers' =>
                    $this->decodeSeatNumbers(
                        $booking->seat_numbers
                    ),

                'passengers' =>
                    $passengers,

                /*
                 * Booking-level primary identity.
                 */
                'primary_passenger_name' =>
                    $booking->primary_passenger_name,

                'primary_passenger_nic' =>
                    $booking->primary_passenger_nic,

                'boarding_stop' =>
                    $booking->boarding_stop
                    ?: $booking->origin,

                'dropoff_stop' =>
                    $booking->dropoff_stop
                    ?: $booking->destination,

                'boarding_time' =>
                    $journeyMeta['boarding_time'],

                'dropoff_time' =>
                    $journeyMeta['dropoff_time'],

                'journey_distance_km' =>
                    $booking->journey_distance_km
                    ?? $journeyMeta['journey_distance_km'],

                'fare_stage_difference' =>
                    $journeyMeta['fare_stage_difference'],

                'fare_per_seat' =>
                    $booking->fare_per_seat
                    ?? $journeyMeta['fare_per_seat'],

                'total' =>
                    $booking->total,

                'payment_status' =>
                    $booking->payment_status,

                'booking_status' =>
                    $booking->status,

                'trip_id' =>
                    (int) $booking->trip_id,

                'trip_code' =>
                    $booking->trip_code,

                'trip_status' =>
                    $booking->trip_status,

                'fixed_service_id' =>
                    $booking->fixed_service_id,

                'service_name' =>
                    $booking->service_name,

                'route_number' =>
                    $booking->route_number,

                'origin' =>
                    $booking->origin,

                'destination' =>
                    $booking->destination,

                'service_date' =>
                    $booking->service_date,

                'departure_time' =>
                    $booking->departure_time,

                'arrival_time' =>
                    $booking->arrival_time,

                'tracking_available' =>
                    strtolower(
                        (string) $booking->trip_status
                    ) === 'active',
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Live Tracking Authorization
    |--------------------------------------------------------------------------
    */

    public function tracking(Request $request, $id)
    {
        $passenger = $this->passenger($request);

        $booking = DB::table('bookings')
            ->join(
                'trips',
                'trips.id',
                '=',
                'bookings.trip_id'
            )
            ->where(
                'bookings.id',
                $id
            )
            ->where(
                'bookings.passenger_user_id',
                $passenger->id
            )
            ->select(
                'bookings.id as booking_id',
                'bookings.booking_reference',
                'bookings.status as booking_status',
                'bookings.payment_status',
                'trips.id as trip_id',
                'trips.fixed_service_id',
                'trips.trip_code',
                'trips.status as trip_status'
            )
            ->first();

        if (!$booking) {
            return response()->json([
                'success' => false,
                'tracking_available' => false,
                'message' => 'Booking or trip not found.',
            ], 404);
        }

        if (
            strtolower(
                (string) $booking->booking_status
            ) !== 'confirmed' ||
            strtolower(
                (string) $booking->payment_status
            ) !== 'paid'
        ) {
            return response()->json([
                'success' => false,
                'tracking_available' => false,
                'message' =>
                    'Live tracking is available only for passengers with a paid and confirmed booking.',
            ], 403);
        }

        if (
            strtolower(
                (string) $booking->trip_status
            ) !== 'active'
        ) {
            return response()->json([
                'success' => false,
                'tracking_available' => false,
                'message' =>
                    'Live tracking becomes available after authorised staff starts the trip.',
                'trip_status' =>
                    $booking->trip_status,
            ], 409);
        }

        return response()->json([
            'success' => true,
            'tracking_available' => true,

            'booking_id' =>
                (int) $booking->booking_id,

            'booking_reference' =>
                $booking->booking_reference,

            'trip_id' =>
                (int) $booking->trip_id,

            'fixed_service_id' =>
                $booking->fixed_service_id,

            'trip_code' =>
                $booking->trip_code,

            'trip_status' =>
                $booking->trip_status,

            'firebase_path' =>
                'live_trips/' .
                $booking->trip_code,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    */

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

        if (
            Schema::hasColumn(
                'notifications',
                'user_id'
            )
        ) {
            $query->where(
                'user_id',
                $passenger->id
            );
        } elseif (
            Schema::hasColumn(
                'notifications',
                'passenger_user_id'
            )
        ) {
            $query->where(
                'passenger_user_id',
                $passenger->id
            );
        } elseif (
            Schema::hasColumn(
                'notifications',
                'email'
            )
        ) {
            $query->where(
                'email',
                $passenger->email
            );
        } else {
            return response()->json([
                'success' => true,
                'notifications' => [],
            ]);
        }

        return response()->json([
            'success' => true,

            'notifications' =>
                $query
                    ->orderByDesc('created_at')
                    ->get(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Cancel Booking
    |--------------------------------------------------------------------------
    */

    public function cancelBooking(
        Request $request,
        $id
    ) {
        $passenger = $this->passenger($request);

        $booking = DB::table('bookings')
            ->join(
                'trips',
                'trips.id',
                '=',
                'bookings.trip_id'
            )
            ->where(
                'bookings.id',
                $id
            )
            ->where(
                'bookings.passenger_user_id',
                $passenger->id
            )
            ->select(
                'bookings.*',
                'trips.status as trip_status'
            )
            ->first();

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking not found.',
            ], 404);
        }

        if (
            strtolower(
                (string) $booking->status
            ) === 'cancelled'
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Booking is already cancelled.',
            ], 422);
        }

        if (
            in_array(
                strtolower(
                    (string) $booking->trip_status
                ),
                ['active', 'completed'],
                true
            )
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'An active or completed trip cannot be cancelled.',
            ], 422);
        }

        $checkedIn =
            DB::table('booking_passengers')
                ->where(
                    'booking_id',
                    $booking->id
                )
                ->whereNotNull(
                    'checked_in_at'
                )
                ->exists();

        if ($checkedIn) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Checked-in booking cannot be cancelled.',
            ], 422);
        }

        DB::table('bookings')
            ->where('id', $booking->id)
            ->update([
                'status' =>
                    'cancelled',

                'ticket_status' =>
                    $booking->ticket_token
                        ? 'cancelled'
                        : $booking->ticket_status,

                'hold_expires_at' =>
                    null,

                'updated_at' =>
                    now(),
            ]);

        return response()->json([
            'success' => true,
            'message' =>
                'Booking cancelled successfully.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Logged-in Passenger
    |--------------------------------------------------------------------------
    */

    private function passenger(Request $request)
    {
        $passenger =
            $request->attributes->get(
                'passenger'
            );

        abort_unless(
            $passenger,
            401,
            'Passenger authentication required.'
        );

        return $passenger;
    }

    /*
    |--------------------------------------------------------------------------
    | Find Master Routes
    |--------------------------------------------------------------------------
    */

    private function findRoutesContainingStops(
        string $origin,
        string $destination
    ) {
        $originRouteIds = DB::table(
            'route_stops'
        )
            ->whereRaw(
                'LOWER(TRIM(name)) = ?',
                [mb_strtolower(trim($origin))]
            )
            ->pluck('route_id');

        $destinationRouteIds = DB::table(
            'route_stops'
        )
            ->whereRaw(
                'LOWER(TRIM(name)) = ?',
                [mb_strtolower(trim($destination))]
            )
            ->pluck('route_id');

        return $originRouteIds
            ->intersect($destinationRouteIds)
            ->unique()
            ->values();
    }

    /*
    |--------------------------------------------------------------------------
    | Trip Query
    |--------------------------------------------------------------------------
    */

    private function tripQuery()
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
            ->leftJoin(
                'fixed_services',
                'fixed_services.id',
                '=',
                'trips.fixed_service_id'
            )
            ->leftJoin(
                'operators',
                'operators.id',
                '=',
                'trips.operator_id'
            )
            ->select(
                'trips.*',
                'routes.route_number',
                'routes.name as route_name',
                'routes.origin as route_origin',
                'routes.destination as route_destination',
                'routes.distance_km',
                'routes.duration_minutes',
                'routes.base_fare',
                'buses.bus_number',
                'buses.bus_name',
                'buses.bus_type',
                'buses.seat_count',
                'buses.facilities',
                'fixed_services.service_name',
                'fixed_services.operator_id as service_operator_id',
                'fixed_services.bus_id as service_bus_id',
                'fixed_services.is_active as service_active',
                'fixed_services.is_published as service_published',
                'operators.company_name as operator_name'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Master Road-Way
    |--------------------------------------------------------------------------
    */

    private function roadWayForRoute(int $routeId)
    {
        return DB::table('route_stops')
            ->where('route_id', $routeId)
            ->orderBy('stop_order')
            ->get()
            ->map(fn ($stop) => [
                'id' => (int) $stop->id,
                'name' => $stop->name,
                'stop_order' => (int) $stop->stop_order,

                'fare_stage_no' =>
                    $stop->fare_stage_no !== null
                        ? (int) $stop->fare_stage_no
                        : null,

                'distance_from_origin_km' =>
                    (float) (
                        $stop->distance_from_origin_km
                        ?? $stop->distance_from_origin
                        ?? 0
                    ),

                'distance_from_origin' =>
                    (float) (
                        $stop->distance_from_origin_km
                        ?? $stop->distance_from_origin
                        ?? 0
                    ),
            ])
            ->values();
    }

    /*
    |--------------------------------------------------------------------------
    | Service Schedule
    |--------------------------------------------------------------------------
    */

    private function bookingScheduleForService(
        int $fixedServiceId,
        string $direction
    ) {
        return DB::table(
            'fixed_service_stops as fss'
        )
            ->join(
                'route_stops as rs',
                'rs.id',
                '=',
                'fss.route_stop_id'
            )
            ->where(
                'fss.fixed_service_id',
                $fixedServiceId
            )
            ->where(
                'fss.direction',
                $direction
            )
            ->orderBy('fss.stop_order')
            ->select(
                'fss.id',
                'fss.route_stop_id',
                'fss.direction',
                'fss.stop_order',
                'fss.arrival_time',
                'fss.departure_time',
                'fss.boarding_allowed',
                'fss.dropoff_allowed',
                'rs.name',
                'rs.fare_stage_no',
                'rs.distance_from_origin_km'
            )
            ->get()
            ->map(fn ($stop) => [
                'id' => (int) $stop->id,
                'fixed_service_stop_id' => (int) $stop->id,
                'route_stop_id' => (int) $stop->route_stop_id,
                'name' => $stop->name,
                'stop_order' => (int) $stop->stop_order,
                'arrival_time' => $stop->arrival_time,
                'departure_time' => $stop->departure_time,

                'schedule_time' =>
                    $stop->departure_time
                    ?? $stop->arrival_time,

                'fare_stage_no' =>
                    $stop->fare_stage_no !== null
                        ? (int) $stop->fare_stage_no
                        : null,

                'distance_from_origin' =>
                    (float) (
                        $stop->distance_from_origin_km
                        ?? 0
                    ),

                'distance_from_origin_km' =>
                    (float) (
                        $stop->distance_from_origin_km
                        ?? 0
                    ),

                'boarding_allowed' =>
                    (bool) $stop->boarding_allowed,

                'dropoff_allowed' =>
                    (bool) $stop->dropoff_allowed,
            ])
            ->values();
    }

    /*
    |--------------------------------------------------------------------------
    | Booking Stops for Trip Direction
    |--------------------------------------------------------------------------
    */

    private function bookingStopsForTrip($trip)
    {
        if (empty($trip->fixed_service_id)) {
            return collect();
        }

        $direction =
            $this->isReturnTrip($trip)
                ? 'return'
                : 'starting';

        $stops = DB::table(
            'fixed_service_stops as fss'
        )
            ->join(
                'route_stops as rs',
                'rs.id',
                '=',
                'fss.route_stop_id'
            )
            ->where(
                'fss.fixed_service_id',
                $trip->fixed_service_id
            )
            ->where(
                'fss.direction',
                $direction
            )
            ->orderBy('fss.stop_order')
            ->select(
                'fss.id',
                'fss.fixed_service_id',
                'fss.route_stop_id',
                'fss.direction',
                'fss.stop_order',
                'fss.arrival_time',
                'fss.departure_time',
                'fss.boarding_allowed',
                'fss.dropoff_allowed',
                'rs.name',
                'rs.fare_stage_no',
                'rs.distance_from_origin_km'
            )
            ->get();

        foreach ($stops as $index => $stop) {
            $stop->_journey_order =
                $index + 1;

            $stop->schedule_time =
                $stop->departure_time
                ?? $stop->arrival_time;

            $stop->distance_from_origin =
                (float) (
                    $stop->distance_from_origin_km
                    ?? 0
                );

            $stop->distance_from_origin_km =
                (float) (
                    $stop->distance_from_origin_km
                    ?? 0
                );
        }

        return $stops;
    }

    /*
    |--------------------------------------------------------------------------
    | Journey Distance
    |--------------------------------------------------------------------------
    */

    private function journeyDistance(
        $boarding,
        $dropoff
    ): float {
        return abs(
            (float) $dropoff->distance_from_origin -
            (float) $boarding->distance_from_origin
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Stage Fare
    |--------------------------------------------------------------------------
    */

    private function calculateStageFare(
        $trip,
        $boarding,
        $dropoff
    ): ?float {
        $boardingStage =
            (int) (
                $boarding->fare_stage_no
                ?? 0
            );

        $dropoffStage =
            (int) (
                $dropoff->fare_stage_no
                ?? 0
            );

        if (
            $boardingStage <= 0 ||
            $dropoffStage <= 0
        ) {
            return null;
        }

        $stageDifference =
            abs(
                $dropoffStage -
                $boardingStage
            );

        if ($stageDifference < 1) {
            return null;
        }

        $serviceClass =
            $this->serviceClassForBusType(
                (string) (
                    $trip->bus_type
                    ?? ''
                )
            );

        if ($serviceClass === null) {
            return null;
        }

        $fare = DB::table('stage_fares')
            ->where(
                'stage_no',
                $stageDifference
            )
            ->where(
                'service_class',
                $serviceClass
            )
            ->value('fare');

        return $fare !== null
            ? round((float) $fare, 2)
            : null;
    }

    /*
    |--------------------------------------------------------------------------
    | Bus Type → Fare Class
    |--------------------------------------------------------------------------
    */

    private function serviceClassForBusType(
        string $busType
    ): ?string {
        $normalized =
            mb_strtolower(
                trim($busType)
            );

        return match ($normalized) {
            'normal' =>
                'normal',

            'semi-luxury',
            'semi luxury',
            'semiluxury',
            'semi_luxury' =>
                'semi_luxury',

            'a/c',
            'ac',
            'luxury',
            'ac luxury',
            'ac-luxury',
            'ac_luxury',
            'air conditioned',
            'air-conditioned' =>
                'luxury',

            default =>
                null,
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Booking Journey Metadata
    |--------------------------------------------------------------------------
    */

    private function bookingJourneyMeta(
        $booking
    ): array {
        $defaults = [
            'boarding_time' => null,
            'dropoff_time' => null,
            'fare_stage_difference' => null,
            'journey_distance_km' => null,
            'fare_per_seat' => null,
        ];

        $boardingName = trim(
            (string) (
                $booking->boarding_stop
                ?? $booking->origin
                ?? ''
            )
        );

        $dropoffName = trim(
            (string) (
                $booking->dropoff_stop
                ?? $booking->destination
                ?? ''
            )
        );

        if (
            empty($booking->fixed_service_id) ||
            $boardingName === '' ||
            $dropoffName === ''
        ) {
            return $defaults;
        }

        $stops =
            $this->bookingStopsForTrip(
                $booking
            );

        $boarding = $stops->first(
            fn ($stop) =>
                $this->sameStopName(
                    $stop->name,
                    $boardingName
                )
        );

        $dropoff = $stops->first(
            fn ($stop) =>
                $this->sameStopName(
                    $stop->name,
                    $dropoffName
                )
        );

        if (!$boarding || !$dropoff) {
            return $defaults;
        }

        if (
            (int) $boarding->_journey_order >=
            (int) $dropoff->_journey_order
        ) {
            return $defaults;
        }

        $journeyDistance =
            $this->journeyDistance(
                $boarding,
                $dropoff
            );

        $fareStageDifference =
            abs(
                (int) $dropoff->fare_stage_no -
                (int) $boarding->fare_stage_no
            );

        $farePerSeat = !empty(
            $booking->bus_type
        )
            ? $this->calculateStageFare(
                $booking,
                $boarding,
                $dropoff
            )
            : null;

        return [
            'boarding_time' =>
                $boarding->schedule_time,

            'dropoff_time' =>
                $dropoff->schedule_time,

            'fare_stage_difference' =>
                $fareStageDifference > 0
                    ? $fareStageDifference
                    : null,

            'journey_distance_km' =>
                round(
                    $journeyDistance,
                    2
                ),

            'fare_per_seat' =>
                $farePerSeat,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Active Reserved Seats
    |--------------------------------------------------------------------------
    */

    private function activeReservedSeatsQuery(
        int $tripId
    ) {
        return DB::table(
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
                $tripId
            )
            ->where(function ($query) {
                /*
                 * Confirmed paid booking always reserves seat.
                 */
                $query->where(function ($confirmed) {
                    $confirmed
                        ->where(
                            'bookings.status',
                            'confirmed'
                        )
                        ->where(
                            'bookings.payment_status',
                            'paid'
                        );
                })

                /*
                 * Pending booking reserves seat only
                 * while 10-minute hold is active.
                 */
                ->orWhere(function ($pending) {
                    $pending
                        ->where(
                            'bookings.status',
                            'pending'
                        )
                        ->where(
                            'bookings.hold_expires_at',
                            '>',
                            now()
                        );
                });
            });
    }

    /*
    |--------------------------------------------------------------------------
    | Seat Availability
    |--------------------------------------------------------------------------
    */

    private function seatAvailability($trip): array
    {
        $seatCount = DB::table('seats')
            ->where(
                'bus_id',
                $trip->bus_id
            )
            ->where(
                'is_disabled',
                false
            )
            ->count();

        /*
         * Compatibility fallback if bus has seat_count
         * but individual seat rows were not created.
         */
        if (
            $seatCount === 0 &&
            !empty($trip->seat_count)
        ) {
            $seatCount =
                (int) $trip->seat_count;
        }

        $bookedSeatsCount =
            $this->activeReservedSeatsQuery(
                (int) $trip->id
            )
                ->distinct()
                ->count(
                    'booking_passengers.seat_number'
                );

        return [
            'seat_count' =>
                (int) $seatCount,

            'booked_seats_count' =>
                (int) $bookedSeatsCount,

            'available_seats' =>
                max(
                    0,
                    (int) $seatCount -
                    (int) $bookedSeatsCount
                ),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Trip Scheduled DateTime
    |--------------------------------------------------------------------------
    */

    private function scheduledDateTime(
        $serviceDate,
        $time
    ): Carbon {
        return Carbon::parse(
            Carbon::parse(
                $serviceDate
            )->toDateString() .
            ' ' .
            $time
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Trip Departure
    |--------------------------------------------------------------------------
    */

    private function tripDepartureTime(
        $trip
    ): Carbon {
        return $this->scheduledDateTime(
            $trip->service_date,
            $trip->departure_time
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Booking Close Time
    |--------------------------------------------------------------------------
    */

    private function bookingCloseTime(
        $trip
    ): Carbon {
        return $this->tripDepartureTime(
            $trip
        )
            ->copy()
            ->subMinutes(
                self::BOOKING_CLOSE_MINUTES
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Is Trip Bookable?
    |--------------------------------------------------------------------------
    */

    private function isTripBookable(
        $trip
    ): bool {
        if (
            isset($trip->is_published) &&
            !(bool) $trip->is_published
        ) {
            return false;
        }

        if (
            strtolower(
                (string) (
                    $trip->status
                    ?? ''
                )
            ) !== 'scheduled'
        ) {
            return false;
        }

        if (
            empty(
                $trip->fixed_service_id
            )
        ) {
            return false;
        }

        if (
            isset(
                $trip->service_operator_id
            ) &&
            empty(
                $trip->service_operator_id
            )
        ) {
            return false;
        }

        if (
            isset(
                $trip->service_bus_id
            ) &&
            empty(
                $trip->service_bus_id
            )
        ) {
            return false;
        }

        if (
            isset(
                $trip->service_active
            ) &&
            !(bool) $trip->service_active
        ) {
            return false;
        }

        if (
            isset(
                $trip->service_published
            ) &&
            !(bool) $trip->service_published
        ) {
            return false;
        }

        if (
            Carbon::parse(
                $trip->service_date
            )
                ->startOfDay()
                ->lt(today())
        ) {
            return false;
        }

        /*
         * Manual closure support.
         *
         * booking_closed_at is treated as a real closure
         * only when it has already been reached.
         */
        if (
            !empty(
                $trip->booking_closed_at
            ) &&
            Carbon::parse(
                $trip->booking_closed_at
            )->lte(now())
        ) {
            return false;
        }

        /*
         * Automatic closure:
         * 60 minutes before scheduled departure.
         */
        return now()->lt(
            $this->bookingCloseTime($trip)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Normalize Seat Number
    |--------------------------------------------------------------------------
    */

    private function normalizeSeatNumber(
        $value
    ): string {
        $number = preg_replace(
            '/[^0-9]/',
            '',
            trim((string) $value)
        );

        if (
            $number === '' ||
            (int) $number <= 0
        ) {
            return '';
        }

        return 'S' . (int) $number;
    }

    /*
    |--------------------------------------------------------------------------
    | Same Stop
    |--------------------------------------------------------------------------
    */

    private function sameStopName(
        string $first,
        string $second
    ): bool {
        return mb_strtolower(
            trim($first)
        ) === mb_strtolower(
            trim($second)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Return Trip
    |--------------------------------------------------------------------------
    */

    private function isReturnTrip(
        $trip
    ): bool {
        return strtolower(
            trim(
                (string) (
                    $trip->trip_type
                    ?? 'starting'
                )
            )
        ) === 'return';
    }

    /*
    |--------------------------------------------------------------------------
    | Unique Booking Reference
    |--------------------------------------------------------------------------
    */

    private function uniqueBookingReference(): string
    {
        do {
            $reference =
                'EBK-' .
                now()->format('ymdHis') .
                '-' .
                strtoupper(
                    Str::random(5)
                );
        } while (
            DB::table('bookings')
                ->where(
                    'booking_reference',
                    $reference
                )
                ->exists()
        );

        return $reference;
    }

    /*
    |--------------------------------------------------------------------------
    | Decode Seat Numbers
    |--------------------------------------------------------------------------
    */

    private function decodeSeatNumbers(
        $value
    ): array {
        $decoded = json_decode(
            (string) $value,
            true
        );

        if (is_array($decoded)) {
            return collect($decoded)
                ->map(
                    fn ($seat) =>
                        $this->normalizeSeatNumber(
                            $seat
                        )
                )
                ->filter()
                ->values()
                ->all();
        }

        return collect(
            explode(
                ',',
                (string) $value
            )
        )
            ->map(
                fn ($seat) =>
                    $this->normalizeSeatNumber(
                        $seat
                    )
            )
            ->filter()
            ->values()
            ->all();
    }
}