<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PassengerRecommendationController extends Controller
{
    private const BOOKING_CLOSE_MINUTES = 60;
    private const MAX_RECOMMENDATIONS = 5;

    /*
    |--------------------------------------------------------------------------
    | Personalized Bus Recommendations
    |--------------------------------------------------------------------------
    |
    | Score:
    |
    | Overall passenger rating  25%
    | Passenger's own feedback  25%
    | Previous route match      20%
    | Punctuality               10%
    | Comfort                    5%
    | Safety                     5%
    | Travel again               5%
    | Review confidence          5%
    |
    */

    public function index(Request $request)
    {
        $passenger = $this->passenger($request);

        if (
            !Schema::hasTable('trip_feedback') ||
            !Schema::hasTable('buses') ||
            !Schema::hasTable('trips') ||
            !Schema::hasTable('routes') ||
            !Schema::hasTable('bookings')
        ) {
            return $this->emptyResponse();
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
            ->whereIn('b.status', ['confirmed', 'completed'])
            ->where('b.payment_status', 'paid')
            ->select(
                'r.id',
                'r.origin',
                'r.destination',
                DB::raw('COUNT(*) as booking_count')
            )
            ->groupBy('r.id', 'r.origin', 'r.destination')
            ->orderByDesc('booking_count')
            ->limit(10)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Global Passenger Feedback
        |--------------------------------------------------------------------------
        */

        $busRatings = DB::table('trip_feedback')
            ->whereNotNull('bus_id')
            ->select(
                'bus_id',
                DB::raw('AVG(overall_rating) as overall_rating'),
                DB::raw('AVG(punctuality_rating) as punctuality_rating'),
                DB::raw('AVG(cleanliness_rating) as cleanliness_rating'),
                DB::raw('AVG(staff_rating) as staff_rating'),
                DB::raw('AVG(comfort_rating) as comfort_rating'),
                DB::raw('AVG(safety_rating) as safety_rating'),
                DB::raw("
                    AVG(
                        CASE
                            WHEN travel_again = 1 THEN 1
                            WHEN travel_again = 0 THEN 0
                            ELSE NULL
                        END
                    ) as travel_again_ratio
                "),
                DB::raw('COUNT(*) as review_count')
            )
            ->groupBy('bus_id')
            ->get()
            ->keyBy('bus_id');

        /*
        |--------------------------------------------------------------------------
        | Logged-in Passenger Feedback
        |--------------------------------------------------------------------------
        */

        $personalRatings = DB::table('trip_feedback')
            ->where('passenger_id', $passenger->id)
            ->whereNotNull('bus_id')
            ->select(
                'bus_id',
                DB::raw('AVG(overall_rating) as personal_rating'),
                DB::raw('COUNT(*) as personal_review_count')
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
            ->whereNotNull('bus_id')
            ->whereNotNull('route_id')
            ->select('bus_id', 'route_id')
            ->distinct()
            ->get()
            ->groupBy('bus_id');

        /*
        |--------------------------------------------------------------------------
        | Active Buses
        |--------------------------------------------------------------------------
        */

        $buses = DB::table('buses as bus')
            ->leftJoin('operators as o', 'o.id', '=', 'bus.operator_id')
            ->where('bus.is_active', true)
            ->where(function ($query) {
                $query->whereNull('o.id')
                    ->orWhere('o.status', 'approved');
            })
            ->where(function ($query) {
                $query->whereNull('o.id')
                    ->orWhere('o.is_published', true);
            })
            ->select(
                'bus.*',
                'o.company_name as operator_name'
            )
            ->get();

        $recommendations = [];

        foreach ($buses as $bus) {
            $rating = $busRatings->get($bus->id);
            $personal = $personalRatings->get($bus->id);

            $overall = (float) ($rating->overall_rating ?? 0);
            $punctuality = (float) ($rating->punctuality_rating ?? 0);
            $cleanliness = (float) ($rating->cleanliness_rating ?? 0);
            $staffRating = (float) ($rating->staff_rating ?? 0);
            $comfort = (float) ($rating->comfort_rating ?? 0);
            $safety = (float) ($rating->safety_rating ?? 0);
            $travelAgainRatio = (float) ($rating->travel_again_ratio ?? 0);
            $reviewCount = (int) ($rating->review_count ?? 0);

            $personalRating = $personal !== null
                ? (float) $personal->personal_rating
                : null;

            /*
            |--------------------------------------------------------------------------
            | Convert Rating Values to 0-100
            |--------------------------------------------------------------------------
            */

            $overallScore = ($overall / 5) * 100;
            $punctualityScore = ($punctuality / 5) * 100;
            $comfortScore = ($comfort / 5) * 100;
            $safetyScore = ($safety / 5) * 100;
            $travelAgainScore = $travelAgainRatio * 100;
            $confidenceScore = min($reviewCount / 20, 1) * 100;

            $personalScore = $personalRating !== null
                ? ($personalRating / 5) * 100
                : 50;

            /*
            |--------------------------------------------------------------------------
            | Previous Route Match
            |--------------------------------------------------------------------------
            */

            $routeMatchScore = 0;
            $routesForBus = $busRoutes->get($bus->id, collect());

            foreach ($preferredRoutes as $route) {
                $hasRoute = $routesForBus->contains(
                    fn ($busRoute) =>
                        (int) $busRoute->route_id === (int) $route->id
                );

                if ($hasRoute) {
                    $routeMatchScore = min(
                        60 + ((int) $route->booking_count * 10),
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
                ($overallScore * 0.25) +
                ($personalScore * 0.25) +
                ($routeMatchScore * 0.20) +
                ($punctualityScore * 0.10) +
                ($comfortScore * 0.05) +
                ($safetyScore * 0.05) +
                ($travelAgainScore * 0.05) +
                ($confidenceScore * 0.05);

            /*
            |--------------------------------------------------------------------------
            | Recommendation Reasons
            |--------------------------------------------------------------------------
            */

            $reasons = [];

            if ($personalRating !== null && $personalRating >= 4.0) {
                $reasons[] = 'You previously rated this bus highly';
            }

            if ($routeMatchScore >= 60) {
                $reasons[] = 'Matches your previous journeys';
            }

            if ($overall >= 4.3) {
                $reasons[] = 'Highly rated by passengers';
            }

            if ($punctuality >= 4.0) {
                $reasons[] = 'Good punctuality';
            }

            if ($comfort >= 4.0) {
                $reasons[] = 'Good passenger comfort';
            }

            if ($safety >= 4.0) {
                $reasons[] = 'Highly rated for safety';
            }

            if ($cleanliness >= 4.0) {
                $reasons[] = 'Good cleanliness';
            }

            if ($staffRating >= 4.0) {
                $reasons[] = 'Good staff service';
            }

            if ($travelAgainRatio >= 0.80) {
                $reasons[] = 'Most passengers would travel again';
            }

            /*
            |--------------------------------------------------------------------------
            | Upcoming Valid Trip
            |--------------------------------------------------------------------------
            */

            $upcomingTrip = $this->findUpcomingTripForBus((int) $bus->id);

            /*
            |--------------------------------------------------------------------------
            | Skip Completely Unknown Bus
            |--------------------------------------------------------------------------
            */

            if (
                $reviewCount === 0 &&
                $routeMatchScore === 0 &&
                $personalRating === null
            ) {
                continue;
            }

            $tripData = null;

            if ($upcomingTrip) {
                $availability = $this->seatAvailability(
                    (int) $upcomingTrip->id,
                    (int) $upcomingTrip->bus_id
                );

                $bookingClosesAt = $this->bookingCloseTime($upcomingTrip);
                $bookingAvailable =
                    now()->lt($bookingClosesAt) &&
                    $availability['available_seats'] > 0;

                $isReturn = strtolower(
                    trim((string) ($upcomingTrip->trip_type ?? 'starting'))
                ) === 'return';

                $tripData = [
                    'id' => (int) $upcomingTrip->id,
                    'trip_code' => $upcomingTrip->trip_code,
                    'trip_type' => $upcomingTrip->trip_type,
                    'fixed_service_id' => (int) $upcomingTrip->fixed_service_id,
                    'service_name' => $upcomingTrip->service_name,
                    'service_date' => $upcomingTrip->service_date,
                    'departure_time' => $upcomingTrip->departure_time,
                    'arrival_time' => $upcomingTrip->arrival_time,
                    'route_id' => (int) $upcomingTrip->route_id,
                    'route_number' => $upcomingTrip->route_number,
                    'origin' => $isReturn
                        ? $upcomingTrip->destination
                        : $upcomingTrip->origin,
                    'destination' => $isReturn
                        ? $upcomingTrip->origin
                        : $upcomingTrip->destination,
                    'distance_km' => (float) ($upcomingTrip->distance_km ?? 0),
                    'fare' => $upcomingTrip->fare !== null
                        ? (float) $upcomingTrip->fare
                        : null,
                    'seat_count' => $availability['seat_count'],
                    'booked_seats_count' => $availability['booked_seats_count'],
                    'available_seats' => $availability['available_seats'],
                    'booking_available' => $bookingAvailable,
                    'booking_status' => !$bookingAvailable
                        ? (
                            $availability['available_seats'] <= 0
                                ? 'sold_out'
                                : 'closed'
                        )
                        : 'available',
                    'booking_closes_at' => $bookingClosesAt->toDateTimeString(),
                ];
            }

            /*
            |--------------------------------------------------------------------------
            | Build Recommendation
            |--------------------------------------------------------------------------
            */

            $recommendations[] = [
                'bus_id' => (int) $bus->id,
                'bus_name' => $bus->bus_name,
                'bus_number' => $bus->bus_number,
                'bus_type' => $bus->bus_type ?? null,
                'facilities' => $this->decodeFacilities($bus->facilities ?? null),
                'operator_id' => $bus->operator_id !== null
                    ? (int) $bus->operator_id
                    : null,
                'operator_name' => $bus->operator_name ?? null,

                'rating' => round($overall, 1),
                'review_count' => $reviewCount,

                'ratings' => [
                    'overall' => round($overall, 1),
                    'punctuality' => round($punctuality, 1),
                    'cleanliness' => round($cleanliness, 1),
                    'staff' => round($staffRating, 1),
                    'comfort' => round($comfort, 1),
                    'safety' => round($safety, 1),
                ],

                'your_rating' => $personalRating !== null
                    ? round($personalRating, 1)
                    : null,

                'travel_again_percentage' => round($travelAgainRatio * 100),
                'route_match' => round($routeMatchScore),
                'recommendation_score' => round($score, 2),

                'reasons' => array_slice(
                    array_values(array_unique($reasons)),
                    0,
                    3
                ),

                'upcoming_trip' => $tripData,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Highest Score First
        |--------------------------------------------------------------------------
        */

        usort(
            $recommendations,
            fn ($a, $b) =>
                $b['recommendation_score'] <=> $a['recommendation_score']
        );

        $recommendations = array_slice(
            $recommendations,
            0,
            self::MAX_RECOMMENDATIONS
        );

        return response()->json([
            'success' => true,
            'title' => 'Recommended For You',
            'subtitle' => 'Based on your journeys and passenger feedback.',
            'recommendations' => $recommendations,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Find Upcoming Valid Trip
    |--------------------------------------------------------------------------
    */

    private function findUpcomingTripForBus(int $busId)
    {
        $trips = DB::table('trips as t')
            ->join('routes as r', 'r.id', '=', 't.route_id')
            ->join('buses as bus', 'bus.id', '=', 't.bus_id')
            ->join('fixed_services as fs', 'fs.id', '=', 't.fixed_service_id')
            ->leftJoin('operators as o', 'o.id', '=', 't.operator_id')
            ->where('t.bus_id', $busId)
            ->whereNotNull('t.fixed_service_id')
            ->where('t.is_published', true)
            ->where('t.status', 'scheduled')
            ->where('bus.is_active', true)
            ->where('fs.is_active', true)
            ->where('fs.is_published', true)
            ->whereNotNull('fs.operator_id')
            ->whereNotNull('fs.bus_id')
            ->whereDate('t.service_date', '>=', today()->toDateString())
            ->select(
                't.id',
                't.bus_id',
                't.route_id',
                't.fixed_service_id',
                't.trip_code',
                't.trip_type',
                't.service_date',
                't.departure_time',
                't.arrival_time',
                't.fare',
                't.booking_closed_at',
                'r.route_number',
                'r.origin',
                'r.destination',
                'r.distance_km',
                'fs.service_name',
                'fs.operator_id as service_operator_id',
                'fs.bus_id as service_bus_id',
                'o.company_name as operator_name'
            )
            ->orderBy('t.service_date')
            ->orderBy('t.departure_time')
            ->limit(20)
            ->get();

        foreach ($trips as $trip) {
            if (!$this->isTripBookable($trip)) {
                continue;
            }

            $availability = $this->seatAvailability(
                (int) $trip->id,
                (int) $trip->bus_id
            );

            if ($availability['available_seats'] <= 0) {
                continue;
            }

            return $trip;
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Trip Booking Rules
    |--------------------------------------------------------------------------
    */

    private function isTripBookable($trip): bool
    {
        if (empty($trip->fixed_service_id)) {
            return false;
        }

        if (
            isset($trip->service_operator_id) &&
            empty($trip->service_operator_id)
        ) {
            return false;
        }

        if (
            isset($trip->service_bus_id) &&
            empty($trip->service_bus_id)
        ) {
            return false;
        }

        if (
            empty($trip->service_date) ||
            empty($trip->departure_time)
        ) {
            return false;
        }

        if (
            Carbon::parse($trip->service_date)
                ->startOfDay()
                ->lt(today())
        ) {
            return false;
        }

        return now()->lt(
            $this->bookingCloseTime($trip)
        );
    }

    private function bookingCloseTime($trip): Carbon
    {
        return Carbon::parse(
            Carbon::parse($trip->service_date)->toDateString() .
            ' ' .
            $trip->departure_time
        )->subMinutes(self::BOOKING_CLOSE_MINUTES);
    }

    /*
    |--------------------------------------------------------------------------
    | Seat Availability
    |--------------------------------------------------------------------------
    */

    private function seatAvailability(int $tripId, int $busId): array
    {
        if (!Schema::hasTable('seats')) {
            return [
                'seat_count' => 0,
                'booked_seats_count' => 0,
                'available_seats' => 0,
            ];
        }

        $seatCount = DB::table('seats')
            ->where('bus_id', $busId)
            ->where('is_disabled', false)
            ->count();

        $bookedSeatsCount = $this->activeReservedSeatsQuery($tripId)
            ->distinct()
            ->count('booking_passengers.seat_number');

        return [
            'seat_count' => (int) $seatCount,
            'booked_seats_count' => (int) $bookedSeatsCount,
            'available_seats' => max(
                0,
                (int) $seatCount - (int) $bookedSeatsCount
            ),
        ];
    }

    private function activeReservedSeatsQuery(int $tripId)
    {
        $query = DB::table('booking_passengers')
            ->join(
                'bookings',
                'bookings.id',
                '=',
                'booking_passengers.booking_id'
            )
            ->where('bookings.trip_id', $tripId)
            ->where('bookings.status', '!=', 'cancelled');

        if (Schema::hasColumn('bookings', 'hold_expires_at')) {
            $query->where(function ($outer) {
                $outer
                    ->where('bookings.status', '!=', 'pending')
                    ->orWhere(function ($pending) {
                        $pending
                            ->where('bookings.status', 'pending')
                            ->where('bookings.hold_expires_at', '>', now());
                    });
            });
        }

        return $query;
    }

    /*
    |--------------------------------------------------------------------------
    | Facilities
    |--------------------------------------------------------------------------
    */

    private function decodeFacilities($value): array
    {
        if ($value === null || trim((string) $value) === '') {
            return [];
        }

        if (is_array($value)) {
            return array_values($value);
        }

        $decoded = json_decode((string) $value, true);

        if (is_array($decoded)) {
            return array_values($decoded);
        }

        return collect(explode(',', (string) $value))
            ->map(fn ($item) => trim($item))
            ->filter()
            ->values()
            ->all();
    }

    /*
    |--------------------------------------------------------------------------
    | Empty Response
    |--------------------------------------------------------------------------
    */

    private function emptyResponse()
    {
        return response()->json([
            'success' => true,
            'title' => 'Recommended For You',
            'subtitle' => 'Based on your journeys and passenger feedback.',
            'recommendations' => [],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Logged-in Passenger
    |--------------------------------------------------------------------------
    */

    private function passenger(Request $request)
    {
        $passenger = $request->attributes->get('passenger');

        abort_unless(
            $passenger,
            401,
            'Passenger authentication required.'
        );

        return $passenger;
    }
}