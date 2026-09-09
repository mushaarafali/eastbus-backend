<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PassengerFeedbackController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Pending Feedback
    |--------------------------------------------------------------------------
    */

    public function pending(Request $request)
    {
        $passenger = $this->passenger($request);

        if (!Schema::hasTable('trip_feedback')) {
            return response()->json([
                'success' => true,
                'feedback_required' => false,
                'count' => 0,
                'bookings' => [],
            ]);
        }

        $bookings = DB::table('bookings as b')
            ->join('trips as t', 't.id', '=', 'b.trip_id')
            ->join('buses as bus', 'bus.id', '=', 't.bus_id')
            ->join('routes as r', 'r.id', '=', 't.route_id')
            ->leftJoin('operators as o', 'o.id', '=', 't.operator_id')
            ->leftJoin('trip_feedback as f', function ($join) use ($passenger) {
                $join->on('f.booking_id', '=', 'b.id')
                    ->where('f.passenger_id', '=', $passenger->id);
            })
            ->where('b.passenger_user_id', $passenger->id)
            ->where('b.payment_status', 'paid')
            ->whereIn('b.status', ['confirmed', 'completed'])
            ->where('t.status', 'completed')
            ->whereNull('f.id')
            ->select(
                'b.id as booking_id',
                'b.booking_reference',
                'b.status as booking_status',
                'b.payment_status',
                'b.boarding_stop',
                'b.dropoff_stop',

                't.id as trip_id',
                't.trip_code',
                't.trip_type',
                't.status as trip_status',
                't.service_date',
                't.departure_time',
                't.arrival_time',

                'bus.id as bus_id',
                'bus.bus_name',
                'bus.bus_number',
                'bus.bus_type',

                't.operator_id',
                'o.company_name',

                'r.id as route_id',
                'r.route_number',
                'r.origin',
                'r.destination'
            )
            ->orderByDesc('t.service_date')
            ->orderByDesc('t.departure_time')
            ->get()
            ->map(function ($booking) {
                $routeOrigin = $booking->origin;
                $routeDestination = $booking->destination;

                if (
                    strtolower(
                        trim((string) $booking->trip_type)
                    ) === 'return'
                ) {
                    $routeOrigin = $booking->destination;
                    $routeDestination = $booking->origin;
                }

                return [
                    'booking_id' => (int) $booking->booking_id,
                    'booking_reference' => $booking->booking_reference,
                    'booking_status' => $booking->booking_status,
                    'payment_status' => $booking->payment_status,

                    'trip_id' => (int) $booking->trip_id,
                    'trip_code' => $booking->trip_code,
                    'trip_type' => $booking->trip_type,
                    'trip_status' => $booking->trip_status,
                    'service_date' => $booking->service_date,
                    'departure_time' => $booking->departure_time,
                    'arrival_time' => $booking->arrival_time,

                    'bus_id' => (int) $booking->bus_id,
                    'bus_name' => $booking->bus_name,
                    'bus_number' => $booking->bus_number,
                    'bus_type' => $booking->bus_type,

                    'operator_id' => $booking->operator_id !== null
                        ? (int) $booking->operator_id
                        : null,

                    'company_name' => $booking->company_name,

                    'route_id' => (int) $booking->route_id,
                    'route_number' => $booking->route_number,

                    'route_origin' => $routeOrigin,
                    'route_destination' => $routeDestination,

                    'boarding_stop' => $booking->boarding_stop,
                    'dropoff_stop' => $booking->dropoff_stop,

                    'display_origin' =>
                        $booking->boarding_stop
                        ?: $routeOrigin,

                    'display_destination' =>
                        $booking->dropoff_stop
                        ?: $routeDestination,

                    'feedback_available' => true,
                    'feedback_submitted' => false,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'feedback_required' => $bookings->isNotEmpty(),
            'count' => $bookings->count(),
            'bookings' => $bookings,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Submit Feedback
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        $passenger = $this->passenger($request);

        if (!Schema::hasTable('trip_feedback')) {
            return response()->json([
                'success' => false,
                'message' => 'Feedback storage is not available.',
            ], 500);
        }

        $data = $request->validate([
            'booking_id' => [
                'required',
                'integer',
                'exists:bookings,id',
            ],

            'overall_rating' => [
                'required',
                'integer',
                'between:1,5',
            ],

            'punctuality_rating' => [
                'nullable',
                'integer',
                'between:1,5',
            ],

            'cleanliness_rating' => [
                'nullable',
                'integer',
                'between:1,5',
            ],

            'staff_rating' => [
                'nullable',
                'integer',
                'between:1,5',
            ],

            'comfort_rating' => [
                'nullable',
                'integer',
                'between:1,5',
            ],

            'safety_rating' => [
                'nullable',
                'integer',
                'between:1,5',
            ],

            'travel_again' => [
                'nullable',
                'boolean',
            ],

            'comment' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        $booking = DB::table('bookings as b')
            ->join('trips as t', 't.id', '=', 'b.trip_id')
            ->join('buses as bus', 'bus.id', '=', 't.bus_id')
            ->where('b.id', $data['booking_id'])
            ->where('b.passenger_user_id', $passenger->id)
            ->select(
                'b.id as booking_id',
                'b.booking_reference',
                'b.status as booking_status',
                'b.payment_status',

                't.id as trip_id',
                't.status as trip_status',
                't.operator_id',

                'bus.id as bus_id',
                'bus.bus_name',
                'bus.bus_number'
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
                (string) $booking->payment_status
            ) !== 'paid'
        ) {
            throw ValidationException::withMessages([
                'booking_id' =>
                    'Only paid bookings can be reviewed.',
            ]);
        }

        if (
            !in_array(
                strtolower((string) $booking->booking_status),
                ['confirmed', 'completed'],
                true
            )
        ) {
            throw ValidationException::withMessages([
                'booking_id' =>
                    'This booking is not eligible for feedback.',
            ]);
        }

        if (
            strtolower(
                (string) $booking->trip_status
            ) !== 'completed'
        ) {
            throw ValidationException::withMessages([
                'booking_id' =>
                    'Feedback is available only after the trip is completed.',
            ]);
        }

        $alreadyExists = DB::table('trip_feedback')
            ->where('passenger_id', $passenger->id)
            ->where('booking_id', $booking->booking_id)
            ->exists();

        if ($alreadyExists) {
            throw ValidationException::withMessages([
                'booking_id' =>
                    'Feedback has already been submitted for this journey.',
            ]);
        }

        $feedbackId = DB::transaction(
            function () use (
                $passenger,
                $booking,
                $data
            ) {
                $bookingStillValid = DB::table('bookings')
                    ->where('id', $booking->booking_id)
                    ->where('passenger_user_id', $passenger->id)
                    ->lockForUpdate()
                    ->first();

                if (!$bookingStillValid) {
                    throw ValidationException::withMessages([
                        'booking_id' =>
                            'Booking is no longer available.',
                    ]);
                }

                $duplicate = DB::table('trip_feedback')
                    ->where('passenger_id', $passenger->id)
                    ->where('booking_id', $booking->booking_id)
                    ->exists();

                if ($duplicate) {
                    throw ValidationException::withMessages([
                        'booking_id' =>
                            'Feedback has already been submitted for this journey.',
                    ]);
                }

                return DB::table('trip_feedback')->insertGetId([
                    'passenger_id' => $passenger->id,
                    'booking_id' => $booking->booking_id,
                    'trip_id' => $booking->trip_id,
                    'bus_id' => $booking->bus_id,
                    'operator_id' => $booking->operator_id,

                    'overall_rating' =>
                        $data['overall_rating'],

                    'punctuality_rating' =>
                        $data['punctuality_rating'] ?? null,

                    'cleanliness_rating' =>
                        $data['cleanliness_rating'] ?? null,

                    'staff_rating' =>
                        $data['staff_rating'] ?? null,

                    'comfort_rating' =>
                        $data['comfort_rating'] ?? null,

                    'safety_rating' =>
                        $data['safety_rating'] ?? null,

                    'travel_again' =>
                        array_key_exists('travel_again', $data)
                            ? (bool) $data['travel_again']
                            : null,

                    'comment' =>
                        !empty(trim((string) ($data['comment'] ?? '')))
                            ? trim((string) $data['comment'])
                            : null,

                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            },
            3
        );

        $summary = $this->busFeedbackSummary(
            (int) $booking->bus_id
        );

        return response()->json([
            'success' => true,
            'message' => 'Thank you for your feedback.',
            'feedback_id' => (int) $feedbackId,
            'feedback_submitted' => true,

            'bus_rating' => [
                'bus_id' => (int) $booking->bus_id,
                'bus_name' => $booking->bus_name,
                'bus_number' => $booking->bus_number,

                'overall_rating' =>
                    round($summary['overall'], 1),

                'punctuality_rating' =>
                    round($summary['punctuality'], 1),

                'cleanliness_rating' =>
                    round($summary['cleanliness'], 1),

                'staff_rating' =>
                    round($summary['staff'], 1),

                'comfort_rating' =>
                    round($summary['comfort'], 1),

                'safety_rating' =>
                    round($summary['safety'], 1),

                'travel_again_percentage' =>
                    round(
                        $summary['travel_again_ratio'] * 100
                    ),

                'review_count' =>
                    $summary['review_count'],

                'quality_score' =>
                    round($summary['quality_score'], 2),
            ],
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | Bus Feedback Summary
    |--------------------------------------------------------------------------
    */

    private function busFeedbackSummary(int $busId): array
    {
        $summary = DB::table('trip_feedback')
            ->where('bus_id', $busId)
            ->selectRaw('
                COUNT(*) as review_count,
                AVG(overall_rating) as overall_rating,
                AVG(punctuality_rating) as punctuality_rating,
                AVG(cleanliness_rating) as cleanliness_rating,
                AVG(staff_rating) as staff_rating,
                AVG(comfort_rating) as comfort_rating,
                AVG(safety_rating) as safety_rating,
                AVG(
                    CASE
                        WHEN travel_again = 1 THEN 1
                        WHEN travel_again = 0 THEN 0
                        ELSE NULL
                    END
                ) as travel_again_ratio
            ')
            ->first();

        $overall =
            (float) ($summary->overall_rating ?? 0);

        $punctuality =
            (float) ($summary->punctuality_rating ?? 0);

        $cleanliness =
            (float) ($summary->cleanliness_rating ?? 0);

        $staff =
            (float) ($summary->staff_rating ?? 0);

        $comfort =
            (float) ($summary->comfort_rating ?? 0);

        $safety =
            (float) ($summary->safety_rating ?? 0);

        $travelAgainRatio =
            (float) ($summary->travel_again_ratio ?? 0);

        /*
         * Bus Quality Score:
         *
         * Overall       30%
         * Punctuality   15%
         * Cleanliness   10%
         * Staff         10%
         * Comfort       15%
         * Safety        15%
         * Travel Again   5%
         */

        $qualityScore =
            (($overall / 5) * 100 * 0.30) +
            (($punctuality / 5) * 100 * 0.15) +
            (($cleanliness / 5) * 100 * 0.10) +
            (($staff / 5) * 100 * 0.10) +
            (($comfort / 5) * 100 * 0.15) +
            (($safety / 5) * 100 * 0.15) +
            ($travelAgainRatio * 100 * 0.05);

        return [
            'review_count' =>
                (int) ($summary->review_count ?? 0),

            'overall' => $overall,
            'punctuality' => $punctuality,
            'cleanliness' => $cleanliness,
            'staff' => $staff,
            'comfort' => $comfort,
            'safety' => $safety,

            'travel_again_ratio' =>
                $travelAgainRatio,

            'quality_score' =>
                $qualityScore,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Logged-in Passenger
    |--------------------------------------------------------------------------
    */

    private function passenger(Request $request)
    {
        $passenger = $request->attributes->get(
            'passenger'
        );

        abort_unless(
            $passenger,
            401,
            'Passenger authentication required.'
        );

        return $passenger;
    }
}