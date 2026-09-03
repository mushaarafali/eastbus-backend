<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PassengerRecommendationController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Personalized Bus Recommendations
    |--------------------------------------------------------------------------
    |
    | Score:
    |
    | Overall passenger rating       25%
    | Passenger's own feedback       25%
    | Previous route match           20%
    | Punctuality                    10%
    | Comfort                         5%
    | Safety                          5%
    | Travel again                    5%
    | Review confidence               5%
    |
    */

    public function index(Request $request)
    {
        $passenger = $this->passenger($request);

        /*
        |--------------------------------------------------------------------------
        | Check Required Tables
        |--------------------------------------------------------------------------
        */

        if (
            !Schema::hasTable('trip_feedback') ||
            !Schema::hasTable('buses') ||
            !Schema::hasTable('trips')
        ) {
            return response()->json([
                'success' => true,
                'title' => 'Recommended For You',
                'subtitle' => 'Based on your journeys and passenger feedback.',
                'recommendations' => [],
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Passenger Travel History
        |--------------------------------------------------------------------------
        */

        $preferredRoutes = DB::table('bookings as b')
            ->join('trips as t', 't.id', '=', 'b.trip_id')
            ->join('routes as r', 'r.id', '=', 't.route_id')
            ->where('b.passenger_user_id', $passenger->id)
            ->whereIn('b.status', [
                'confirmed',
                'completed',
            ])
            ->select(
                'r.id',
                'r.origin',
                'r.destination',
                DB::raw('COUNT(*) as booking_count')
            )
            ->groupBy(
                'r.id',
                'r.origin',
                'r.destination'
            )
            ->orderByDesc('booking_count')
            ->limit(10)
            ->get();

        $preferredRouteIds = $preferredRoutes
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        /*
        |--------------------------------------------------------------------------
        | Global Bus Feedback Statistics
        |--------------------------------------------------------------------------
        */

        $busRatings = DB::table('trip_feedback')
            ->select(
                'bus_id',

                DB::raw(
                    'AVG(overall_rating) as overall_rating'
                ),

                DB::raw(
                    'AVG(punctuality_rating) as punctuality_rating'
                ),

                DB::raw(
                    'AVG(cleanliness_rating) as cleanliness_rating'
                ),

                DB::raw(
                    'AVG(staff_rating) as staff_rating'
                ),

                DB::raw(
                    'AVG(comfort_rating) as comfort_rating'
                ),

                DB::raw(
                    'AVG(safety_rating) as safety_rating'
                ),

                DB::raw(
                    '
                    AVG(
                        CASE
                            WHEN travel_again = 1 THEN 1
                            WHEN travel_again = 0 THEN 0
                            ELSE NULL
                        END
                    ) as travel_again_ratio
                    '
                ),

                DB::raw(
                    'COUNT(*) as review_count'
                )
            )
            ->groupBy('bus_id')
            ->get()
            ->keyBy('bus_id');

        /*
        |--------------------------------------------------------------------------
        | Logged-in Passenger Personal Feedback
        |--------------------------------------------------------------------------
        */

        $personalRatings = DB::table('trip_feedback')
            ->where('passenger_id', $passenger->id)
            ->select(
                'bus_id',
                DB::raw(
                    'AVG(overall_rating) as personal_rating'
                ),
                DB::raw(
                    'COUNT(*) as personal_review_count'
                )
            )
            ->groupBy('bus_id')
            ->get()
            ->keyBy('bus_id');

        /*
        |--------------------------------------------------------------------------
        | Bus Route Mapping
        |--------------------------------------------------------------------------
        */

        $busRoutes = DB::table('trips')
            ->select(
                'bus_id',
                'route_id'
            )
            ->distinct()
            ->get()
            ->groupBy('bus_id');

        /*
        |--------------------------------------------------------------------------
        | Active Buses
        |--------------------------------------------------------------------------
        */

        $buses = DB::table('buses as bus')
            ->leftJoin(
                'operators as o',
                'o.id',
                '=',
                'bus.operator_id'
            )
            ->where('bus.is_active', true)
            ->select(
                'bus.*',
                'o.company_name as operator_name'
            )
            ->get();

        $recommendations = [];

        /*
        |--------------------------------------------------------------------------
        | Calculate Recommendation
        |--------------------------------------------------------------------------
        */

        foreach ($buses as $bus) {

            $rating = $busRatings->get($bus->id);

            $personal =
                $personalRatings->get($bus->id);

            /*
            |--------------------------------------------------------------------------
            | Global Rating Values
            |--------------------------------------------------------------------------
            */

            $overall = (float) (
                $rating->overall_rating ?? 0
            );

            $punctuality = (float) (
                $rating->punctuality_rating ?? 0
            );

            $cleanliness = (float) (
                $rating->cleanliness_rating ?? 0
            );

            $staff = (float) (
                $rating->staff_rating ?? 0
            );

            $comfort = (float) (
                $rating->comfort_rating ?? 0
            );

            $safety = (float) (
                $rating->safety_rating ?? 0
            );

            $travelAgainRatio = (float) (
                $rating->travel_again_ratio ?? 0
            );

            $reviewCount = (int) (
                $rating->review_count ?? 0
            );

            /*
            |--------------------------------------------------------------------------
            | Personal Feedback
            |--------------------------------------------------------------------------
            */

            $personalRating =
                $personal !== null
                    ? (float) $personal->personal_rating
                    : null;

            /*
            |--------------------------------------------------------------------------
            | Convert Ratings to 0 - 100
            |--------------------------------------------------------------------------
            */

            $overallScore =
                ($overall / 5) * 100;

            $punctualityScore =
                ($punctuality / 5) * 100;

            $comfortScore =
                ($comfort / 5) * 100;

            $safetyScore =
                ($safety / 5) * 100;

            $travelAgainScore =
                $travelAgainRatio * 100;

            /*
            |--------------------------------------------------------------------------
            | Review Confidence
            |--------------------------------------------------------------------------
            |
            | 20 reviews = maximum confidence.
            |
            */

            $confidenceScore =
                min(
                    $reviewCount / 20,
                    1
                ) * 100;

            /*
            |--------------------------------------------------------------------------
            | Personal Preference Score
            |--------------------------------------------------------------------------
            |
            | Passenger's own feedback gets high importance.
            |
            | No previous feedback:
            | neutral score = 50.
            |
            */

            if ($personalRating !== null) {
                $personalScore =
                    ($personalRating / 5) * 100;
            } else {
                $personalScore = 50;
            }

            /*
            |--------------------------------------------------------------------------
            | Route Match
            |--------------------------------------------------------------------------
            */

            $routeMatchScore = 0;

            $routesForBus =
                $busRoutes->get($bus->id, collect());

            foreach ($preferredRoutes as $route) {

                $hasRoute =
                    $routesForBus->contains(
                        function ($busRoute) use ($route) {
                            return
                                (int) $busRoute->route_id ===
                                (int) $route->id;
                        }
                    );

                if ($hasRoute) {

                    $routeMatchScore =
                        min(
                            60 +
                            (
                                ((int) $route->booking_count)
                                * 10
                            ),
                            100
                        );

                    break;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Final Recommendation Score
            |--------------------------------------------------------------------------
            */

            $score =
                ($overallScore * 0.25)
                +
                ($personalScore * 0.25)
                +
                ($routeMatchScore * 0.20)
                +
                ($punctualityScore * 0.10)
                +
                ($comfortScore * 0.05)
                +
                ($safetyScore * 0.05)
                +
                ($travelAgainScore * 0.05)
                +
                ($confidenceScore * 0.05);

            /*
            |--------------------------------------------------------------------------
            | Recommendation Reasons
            |--------------------------------------------------------------------------
            */

            $reasons = [];

            if (
                $personalRating !== null &&
                $personalRating >= 4.0
            ) {
                $reasons[] =
                    'You previously rated this bus highly';
            }

            if ($routeMatchScore >= 60) {
                $reasons[] =
                    'Matches your previous journeys';
            }

            if ($overall >= 4.3) {
                $reasons[] =
                    'Highly rated by passengers';
            }

            if ($punctuality >= 4.0) {
                $reasons[] =
                    'Good punctuality';
            }

            if ($comfort >= 4.0) {
                $reasons[] =
                    'Good passenger comfort';
            }

            if ($safety >= 4.0) {
                $reasons[] =
                    'Highly rated for safety';
            }

            if ($cleanliness >= 4.0) {
                $reasons[] =
                    'Good cleanliness';
            }

            if ($staff >= 4.0) {
                $reasons[] =
                    'Good staff service';
            }

            if ($travelAgainRatio >= 0.80) {
                $reasons[] =
                    'Most passengers would travel again';
            }

            /*
            |--------------------------------------------------------------------------
            | Find Upcoming Bookable Trip
            |--------------------------------------------------------------------------
            */

            $upcomingTrip = DB::table('trips as t')
                ->join(
                    'routes as r',
                    'r.id',
                    '=',
                    't.route_id'
                )
                ->where(
                    't.bus_id',
                    $bus->id
                )
                ->where(
                    't.is_published',
                    true
                )
                ->where(
                    't.status',
                    'scheduled'
                )
                ->where(function ($query) {

                    $query
                        ->whereDate(
                            't.service_date',
                            '>',
                            today()
                        )
                        ->orWhere(function ($todayQuery) {

                            $todayQuery
                                ->whereDate(
                                    't.service_date',
                                    today()
                                )
                                ->where(
                                    't.departure_time',
                                    '>',
                                    now()->format('H:i:s')
                                );
                        });
                })
                ->select(
                    't.id',
                    't.trip_code',
                    't.trip_type',
                    't.service_date',
                    't.departure_time',
                    't.arrival_time',
                    't.fare',

                    'r.id as route_id',
                    'r.origin',
                    'r.destination',
                    'r.distance_km'
                )
                ->orderBy(
                    't.service_date'
                )
                ->orderBy(
                    't.departure_time'
                )
                ->first();

            /*
            |--------------------------------------------------------------------------
            | Skip Completely Unknown Buses
            |--------------------------------------------------------------------------
            */

            if (
                $reviewCount === 0 &&
                $routeMatchScore === 0 &&
                $personalRating === null
            ) {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Resolve Route Direction
            |--------------------------------------------------------------------------
            */

            $tripData = null;

            if ($upcomingTrip) {

                $isReturn =
                    strtolower(
                        (string) $upcomingTrip->trip_type
                    ) === 'return';

                $tripData = [
                    'id' =>
                        (int) $upcomingTrip->id,

                    'trip_code' =>
                        $upcomingTrip->trip_code,

                    'trip_type' =>
                        $upcomingTrip->trip_type,

                    'service_date' =>
                        $upcomingTrip->service_date,

                    'departure_time' =>
                        $upcomingTrip->departure_time,

                    'arrival_time' =>
                        $upcomingTrip->arrival_time,

                    'fare' =>
                        (float) $upcomingTrip->fare,

                    'route_id' =>
                        (int) $upcomingTrip->route_id,

                    'origin' =>
                        $isReturn
                            ? $upcomingTrip->destination
                            : $upcomingTrip->origin,

                    'destination' =>
                        $isReturn
                            ? $upcomingTrip->origin
                            : $upcomingTrip->destination,

                    'distance_km' =>
                        (float) (
                            $upcomingTrip->distance_km ?? 0
                        ),
                ];
            }

            /*
            |--------------------------------------------------------------------------
            | Build Recommendation
            |--------------------------------------------------------------------------
            */

            $recommendations[] = [

                'bus_id' =>
                    (int) $bus->id,

                'bus_name' =>
                    $bus->bus_name,

                'bus_number' =>
                    $bus->bus_number,

                'bus_type' =>
                    $bus->bus_type ?? null,

                'operator_id' =>
                    $bus->operator_id ?? null,

                'operator_name' =>
                    $bus->operator_name ?? null,

                /*
                |--------------------------------------------------------------------------
                | Global Feedback
                |--------------------------------------------------------------------------
                */

                'rating' =>
                    round($overall, 1),

                'review_count' =>
                    $reviewCount,

                'ratings' => [

                    'overall' =>
                        round($overall, 1),

                    'punctuality' =>
                        round($punctuality, 1),

                    'cleanliness' =>
                        round($cleanliness, 1),

                    'staff' =>
                        round($staff, 1),

                    'comfort' =>
                        round($comfort, 1),

                    'safety' =>
                        round($safety, 1),
                ],

                /*
                |--------------------------------------------------------------------------
                | Personal Feedback
                |--------------------------------------------------------------------------
                */

                'your_rating' =>
                    $personalRating !== null
                        ? round($personalRating, 1)
                        : null,

                /*
                |--------------------------------------------------------------------------
                | Recommendation
                |--------------------------------------------------------------------------
                */

                'travel_again_percentage' =>
                    round(
                        $travelAgainRatio * 100
                    ),

                'route_match' =>
                    round(
                        $routeMatchScore
                    ),

                'recommendation_score' =>
                    round(
                        $score,
                        2
                    ),

                'reasons' =>
                    array_slice(
                        array_values(
                            array_unique($reasons)
                        ),
                        0,
                        3
                    ),

                'upcoming_trip' =>
                    $tripData,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Highest Recommendation First
        |--------------------------------------------------------------------------
        */

        usort(
            $recommendations,
            function ($a, $b) {

                return
                    $b['recommendation_score']
                    <=>
                    $a['recommendation_score'];
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Top 5
        |--------------------------------------------------------------------------
        */

        $recommendations =
            array_slice(
                $recommendations,
                0,
                5
            );

        return response()->json([
            'success' => true,

            'title' =>
                'Recommended For You',

            'subtitle' =>
                'Based on your journeys and passenger feedback.',

            'recommendations' =>
                $recommendations,
        ]);
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
                ->get('passenger');

        abort_unless(
            $passenger,
            401,
            'Passenger authentication required.'
        );

        return $passenger;
    }
}