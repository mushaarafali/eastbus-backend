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
            ->join(
                'trips as t',
                't.id',
                '=',
                'b.trip_id'
            )
            ->join(
                'buses as bus',
                'bus.id',
                '=',
                't.bus_id'
            )
            ->join(
                'routes as r',
                'r.id',
                '=',
                't.route_id'
            )
            ->leftJoin(
                'operators as o',
                'o.id',
                '=',
                't.operator_id'
            )
            ->leftJoin(
                'trip_feedback as f',
                function ($join) use ($passenger) {
                    $join
                        ->on(
                            'f.booking_id',
                            '=',
                            'b.id'
                        )
                        ->where(
                            'f.passenger_id',
                            '=',
                            $passenger->id
                        );
                }
            )

            /*
             * Booking must belong to logged-in passenger.
             */
            ->where(
                'b.passenger_user_id',
                $passenger->id
            )

            /*
             * Payment must be completed.
             */
            ->where(
                'b.payment_status',
                'paid'
            )

            /*
             * Trip must be completed.
             */
            ->where(
                't.status',
                'completed'
            )

            /*
             * Feedback must not already exist.
             */
            ->whereNull(
                'f.id'
            )

            ->select([
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
                'r.origin',
                'r.destination',
            ])

            ->orderByDesc(
                't.service_date'
            )
            ->orderByDesc(
                't.departure_time'
            )
            ->get()

            ->map(function ($booking) {

                /*
                 * Reverse route direction for return trips.
                 */
                if (
                    strtolower(
                        (string) $booking->trip_type
                    ) === 'return'
                ) {
                    $originalOrigin =
                        $booking->origin;

                    $booking->origin =
                        $booking->destination;

                    $booking->destination =
                        $originalOrigin;
                }

                /*
                 * Use actual boarding/drop-off stops when available.
                 */
                $booking->display_origin =
                    $booking->boarding_stop
                    ?: $booking->origin;

                $booking->display_destination =
                    $booking->dropoff_stop
                    ?: $booking->destination;

                $booking->feedback_available =
                    true;

                $booking->feedback_submitted =
                    false;

                return $booking;
            })
            ->values();

        return response()->json([
            'success' => true,

            'feedback_required' =>
                $bookings->isNotEmpty(),

            'count' =>
                $bookings->count(),

            'bookings' =>
                $bookings,
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

        /*
        |--------------------------------------------------------------------------
        | Feedback Table Check
        |--------------------------------------------------------------------------
        */

        if (!Schema::hasTable('trip_feedback')) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Feedback storage is not available.',
            ], 500);
        }

        /*
        |--------------------------------------------------------------------------
        | Find Booking
        |--------------------------------------------------------------------------
        */

        $booking = DB::table('bookings as b')
            ->join(
                'trips as t',
                't.id',
                '=',
                'b.trip_id'
            )
            ->join(
                'buses as bus',
                'bus.id',
                '=',
                't.bus_id'
            )
            ->where(
                'b.id',
                $data['booking_id']
            )
            ->where(
                'b.passenger_user_id',
                $passenger->id
            )
            ->select([
                'b.id as booking_id',
                'b.booking_reference',
                'b.status as booking_status',
                'b.payment_status',

                't.id as trip_id',
                't.status as trip_status',
                't.operator_id',

                'bus.id as bus_id',
                'bus.bus_name',
                'bus.bus_number',
            ])
            ->first();

        /*
        |--------------------------------------------------------------------------
        | Booking Ownership
        |--------------------------------------------------------------------------
        */

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Booking not found.',
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | Payment Must Be Paid
        |--------------------------------------------------------------------------
        */

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

        /*
        |--------------------------------------------------------------------------
        | Trip Must Be Completed
        |--------------------------------------------------------------------------
        */

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

        /*
        |--------------------------------------------------------------------------
        | Prevent Duplicate Feedback
        |--------------------------------------------------------------------------
        */

        $alreadyExists =
            DB::table('trip_feedback')
                ->where(
                    'passenger_id',
                    $passenger->id
                )
                ->where(
                    'booking_id',
                    $booking->booking_id
                )
                ->exists();

        if ($alreadyExists) {
            throw ValidationException::withMessages([
                'booking_id' =>
                    'Feedback has already been submitted for this journey.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Save Feedback
        |--------------------------------------------------------------------------
        */

        $feedbackId = DB::transaction(
            function () use (
                $passenger,
                $booking,
                $data
            ) {
                $duplicate =
                    DB::table('trip_feedback')
                        ->where(
                            'passenger_id',
                            $passenger->id
                        )
                        ->where(
                            'booking_id',
                            $booking->booking_id
                        )
                        ->lockForUpdate()
                        ->exists();

                if ($duplicate) {
                    throw ValidationException::withMessages([
                        'booking_id' =>
                            'Feedback has already been submitted for this journey.',
                    ]);
                }

                return DB::table(
                    'trip_feedback'
                )->insertGetId([
                    'passenger_id' =>
                        $passenger->id,

                    'booking_id' =>
                        $booking->booking_id,

                    'trip_id' =>
                        $booking->trip_id,

                    'bus_id' =>
                        $booking->bus_id,

                    'operator_id' =>
                        $booking->operator_id,

                    'overall_rating' =>
                        $data['overall_rating'],

                    'punctuality_rating' =>
                        $data[
                            'punctuality_rating'
                        ] ?? null,

                    'cleanliness_rating' =>
                        $data[
                            'cleanliness_rating'
                        ] ?? null,

                    'staff_rating' =>
                        $data[
                            'staff_rating'
                        ] ?? null,

                    'comfort_rating' =>
                        $data[
                            'comfort_rating'
                        ] ?? null,

                    'safety_rating' =>
                        $data[
                            'safety_rating'
                        ] ?? null,

                    'travel_again' =>
                        $data[
                            'travel_again'
                        ] ?? null,

                    'comment' =>
                        isset(
                            $data['comment']
                        ) &&
                        trim(
                            $data['comment']
                        ) !== ''
                            ? trim(
                                $data['comment']
                            )
                            : null,

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);
            },
            3
        );

        /*
        |--------------------------------------------------------------------------
        | Calculate Updated Bus Feedback Summary
        |--------------------------------------------------------------------------
        */

        $summary =
            DB::table('trip_feedback')
                ->where(
                    'bus_id',
                    $booking->bus_id
                )
                ->selectRaw(
                    '
                    COUNT(*) as review_count,

                    AVG(overall_rating)
                        as overall_rating,

                    AVG(punctuality_rating)
                        as punctuality_rating,

                    AVG(cleanliness_rating)
                        as cleanliness_rating,

                    AVG(staff_rating)
                        as staff_rating,

                    AVG(comfort_rating)
                        as comfort_rating,

                    AVG(safety_rating)
                        as safety_rating,

                    AVG(
                        CASE
                            WHEN travel_again = 1 THEN 1
                            WHEN travel_again = 0 THEN 0
                            ELSE NULL
                        END
                    ) as travel_again_ratio
                    '
                )
                ->first();

        /*
        |--------------------------------------------------------------------------
        | Rating Values
        |--------------------------------------------------------------------------
        */

        $overall =
            (float) (
                $summary->overall_rating ?? 0
            );

        $punctuality =
            (float) (
                $summary->punctuality_rating ?? 0
            );

        $cleanliness =
            (float) (
                $summary->cleanliness_rating ?? 0
            );

        $staffRating =
            (float) (
                $summary->staff_rating ?? 0
            );

        $comfort =
            (float) (
                $summary->comfort_rating ?? 0
            );

        $safety =
            (float) (
                $summary->safety_rating ?? 0
            );

        $travelAgainRatio =
            (float) (
                $summary->travel_again_ratio ?? 0
            );

        /*
        |--------------------------------------------------------------------------
        | Bus Quality Score
        |--------------------------------------------------------------------------
        |
        | Overall       30%
        | Punctuality   15%
        | Cleanliness   10%
        | Staff         10%
        | Comfort       15%
        | Safety        15%
        | Travel Again   5%
        |
        */

        $overallScore =
            ($overall / 5) * 100;

        $punctualityScore =
            ($punctuality / 5) * 100;

        $cleanlinessScore =
            ($cleanliness / 5) * 100;

        $staffScore =
            ($staffRating / 5) * 100;

        $comfortScore =
            ($comfort / 5) * 100;

        $safetyScore =
            ($safety / 5) * 100;

        $travelAgainScore =
            $travelAgainRatio * 100;

        $qualityScore =
            ($overallScore * 0.30)
            +
            ($punctualityScore * 0.15)
            +
            ($cleanlinessScore * 0.10)
            +
            ($staffScore * 0.10)
            +
            ($comfortScore * 0.15)
            +
            ($safetyScore * 0.15)
            +
            ($travelAgainScore * 0.05);

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,

            'message' =>
                'Thank you for your feedback.',

            'feedback_id' =>
                $feedbackId,

            'feedback_submitted' =>
                true,

            'bus_rating' => [
                'bus_id' =>
                    (int) $booking->bus_id,

                'bus_name' =>
                    $booking->bus_name,

                'bus_number' =>
                    $booking->bus_number,

                'overall_rating' =>
                    round(
                        $overall,
                        1
                    ),

                'punctuality_rating' =>
                    round(
                        $punctuality,
                        1
                    ),

                'cleanliness_rating' =>
                    round(
                        $cleanliness,
                        1
                    ),

                'staff_rating' =>
                    round(
                        $staffRating,
                        1
                    ),

                'comfort_rating' =>
                    round(
                        $comfort,
                        1
                    ),

                'safety_rating' =>
                    round(
                        $safety,
                        1
                    ),

                'travel_again_percentage' =>
                    round(
                        $travelAgainRatio * 100
                    ),

                'review_count' =>
                    (int) (
                        $summary->review_count ?? 0
                    ),

                'quality_score' =>
                    round(
                        $qualityScore,
                        2
                    ),
            ],
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | Logged-in Passenger
    |--------------------------------------------------------------------------
    */

    private function passenger(
        Request $request
    ) {
        $passenger =
            $request
                ->attributes
                ->get(
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