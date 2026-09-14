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
    private const TIMEZONE = 'Asia/Colombo';

    public function index(Request $request)
    {
        $passenger = $this->passenger($request);

        if (
            !Schema::hasTable('buses') ||
            !Schema::hasTable('trips') ||
            !Schema::hasTable('routes') ||
            !Schema::hasTable('bookings')
        ) {
            return $this->emptyResponse();
        }

        $preferredRoutes = $this->preferredRoutes((int) $passenger->id);
        $busRatings = $this->busRatings();
        $personalRatings = $this->personalRatings((int) $passenger->id);
        $busRoutes = $this->busRoutes();

        $buses = DB::table('buses as bus')
            ->leftJoin('operators as o', 'o.id', '=', 'bus.operator_id')
            ->where('bus.is_active', 1)
            ->select(
                'bus.*',
                'o.company_name as operator_name'
            )
            ->get();

        $recommendations = [];

        foreach ($buses as $bus) {
            $trip = $this->findUpcomingTripForBus((int) $bus->id);

            if (!$trip) {
                continue;
            }

            $availability = $this->seatAvailability(
                (int) $trip->id,
                (int) $trip->bus_id
            );

            if ($availability['available_seats'] <= 0) {
                continue;
            }

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

            $routeMatchScore = $this->routeMatchScore(
                (int) $bus->id,
                $preferredRoutes,
                $busRoutes
            );

            $availabilityScore = $availability['seat_count'] > 0
                ? ($availability['available_seats'] / $availability['seat_count']) * 100
                : 0;

            $overallScore = $reviewCount > 0 ? ($overall / 5) * 100 : 50;
            $punctualityScore = $reviewCount > 0 ? ($punctuality / 5) * 100 : 50;
            $comfortScore = $reviewCount > 0 ? ($comfort / 5) * 100 : 50;
            $safetyScore = $reviewCount > 0 ? ($safety / 5) * 100 : 50;
            $travelAgainScore = $reviewCount > 0 ? $travelAgainRatio * 100 : 50;
            $confidenceScore = min($reviewCount / 20, 1) * 100;
            $personalScore = $personalRating !== null
                ? ($personalRating / 5) * 100
                : 50;

            $score =
                ($overallScore * 0.20) +
                ($personalScore * 0.20) +
                ($routeMatchScore * 0.20) +
                ($punctualityScore * 0.10) +
                ($comfortScore * 0.05) +
                ($safetyScore * 0.05) +
                ($travelAgainScore * 0.05) +
                ($confidenceScore * 0.05) +
                ($availabilityScore * 0.10);

            $reasons = $this->buildReasons(
                $personalRating,
                $routeMatchScore,
                $overall,
                $punctuality,
                $cleanliness,
                $staffRating,
                $comfort,
                $safety,
                $travelAgainRatio,
                $availability['available_seats']
            );

            $bookingClosesAt = $this->bookingCloseTime($trip);
            $bookingAvailable = Carbon::now(self::TIMEZONE)->lt($bookingClosesAt)
                && $availability['available_seats'] > 0;

            if (!$bookingAvailable) {
                continue;
            }

            $isReturn = strtolower(trim((string) ($trip->trip_type ?? 'starting'))) === 'return';

            $tripData = [
                'id' => (int) $trip->id,
                'trip_code' => $trip->trip_code,
                'trip_type' => $trip->trip_type,
                'fixed_service_id' => $trip->fixed_service_id !== null
                    ? (int) $trip->fixed_service_id
                    : null,
                'service_name' => $trip->service_name
                    ?: ($bus->bus_name ?: $bus->bus_number),
                'service_date' => $trip->service_date,
                'departure_time' => $trip->departure_time,
                'arrival_time' => $trip->arrival_time,
                'route_id' => (int) $trip->route_id,
                'route_number' => $trip->route_number,
                'origin' => $isReturn ? $trip->destination : $trip->origin,
                'destination' => $isReturn ? $trip->origin : $trip->destination,
                'distance_km' => (float) ($trip->distance_km ?? 0),
                'fare' => $trip->fare !== null ? (float) $trip->fare : null,
                'seat_count' => $availability['seat_count'],
                'booked_seats_count' => $availability['booked_seats_count'],
                'available_seats' => $availability['available_seats'],
                'booking_available' => true,
                'booking_status' => 'available',
                'booking_closes_at' => $bookingClosesAt->toDateTimeString(),
            ];

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

        usort(
            $recommendations,
            fn ($a, $b) => $b['recommendation_score'] <=> $a['recommendation_score']
        );

        return response()->json([
            'success' => true,
            'title' => 'Recommended For You',
            'subtitle' => 'Based on available trips, your journeys and passenger feedback.',
            'recommendations' => array_slice(
                $recommendations,
                0,
                self::MAX_RECOMMENDATIONS
            ),
        ]);
    }

    private function preferredRoutes(int $passengerId)
    {
        return DB::table('bookings as b')
            ->join('trips as t', 't.id', '=', 'b.trip_id')
            ->join('routes as r', 'r.id', '=', 't.route_id')
            ->where('b.passenger_user_id', $passengerId)
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
    }

    private function busRatings()
    {
        if (!Schema::hasTable('trip_feedback')) {
            return collect();
        }

        return DB::table('trip_feedback')
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
    }

    private function personalRatings(int $passengerId)
    {
        if (!Schema::hasTable('trip_feedback')) {
            return collect();
        }

        return DB::table('trip_feedback')
            ->where('passenger_id', $passengerId)
            ->whereNotNull('bus_id')
            ->select(
                'bus_id',
                DB::raw('AVG(overall_rating) as personal_rating'),
                DB::raw('COUNT(*) as personal_review_count')
            )
            ->groupBy('bus_id')
            ->get()
            ->keyBy('bus_id');
    }

    private function busRoutes()
    {
        return DB::table('trips')
            ->whereNotNull('bus_id')
            ->whereNotNull('route_id')
            ->select('bus_id', 'route_id')
            ->distinct()
            ->get()
            ->groupBy('bus_id');
    }

    private function routeMatchScore(int $busId, $preferredRoutes, $busRoutes): float
    {
        $routesForBus = $busRoutes->get($busId, collect());

        foreach ($preferredRoutes as $route) {
            $hasRoute = $routesForBus->contains(
                fn ($busRoute) => (int) $busRoute->route_id === (int) $route->id
            );

            if ($hasRoute) {
                return min(
                    60 + ((int) $route->booking_count * 10),
                    100
                );
            }
        }

        return 0;
    }

    private function buildReasons(
        ?float $personalRating,
        float $routeMatchScore,
        float $overall,
        float $punctuality,
        float $cleanliness,
        float $staffRating,
        float $comfort,
        float $safety,
        float $travelAgainRatio,
        int $availableSeats
    ): array {
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

        if ($availableSeats > 0) {
            $reasons[] = $availableSeats . ' seats available';
        }

        if (empty($reasons)) {
            $reasons[] = 'Upcoming EastBus service available';
        }

        return $reasons;
    }

    private function findUpcomingTripForBus(int $busId)
    {
        $now = Carbon::now(self::TIMEZONE);

        $trips = DB::table('trips as t')
            ->join('routes as r', 'r.id', '=', 't.route_id')
            ->join('buses as bus', 'bus.id', '=', 't.bus_id')
            ->leftJoin('fixed_services as fs', 'fs.id', '=', 't.fixed_service_id')
            ->where('t.bus_id', $busId)
            ->where('t.is_published', 1)
            ->where('t.status', 'scheduled')
            ->where('bus.is_active', 1)
            ->whereDate('t.service_date', '>=', $now->toDateString())
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
                'r.route_number',
                'r.origin',
                'r.destination',
                'r.distance_km',
                'fs.service_name'
            )
            ->orderBy('t.service_date')
            ->orderBy('t.departure_time')
            ->get();

        foreach ($trips as $trip) {
            $departure = $this->tripDepartureTime($trip);

            if (!$departure) {
                continue;
            }

            $bookingClose = $departure->copy()
                ->subMinutes(self::BOOKING_CLOSE_MINUTES);

            if ($now->gte($bookingClose)) {
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

    private function tripDepartureTime($trip): ?Carbon
    {
        if (empty($trip->service_date) || empty($trip->departure_time)) {
            return null;
        }

        try {
            $date = Carbon::parse($trip->service_date, self::TIMEZONE)
                ->toDateString();

            $time = trim((string) $trip->departure_time);

            return Carbon::parse(
                $date . ' ' . $time,
                self::TIMEZONE
            );
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function bookingCloseTime($trip): Carbon
    {
        $departure = $this->tripDepartureTime($trip);

        if (!$departure) {
            return Carbon::now(self::TIMEZONE);
        }

        return $departure->copy()
            ->subMinutes(self::BOOKING_CLOSE_MINUTES);
    }

    private function seatAvailability(int $tripId, int $busId): array
    {
        $seatCount = 0;

        if (Schema::hasTable('seats')) {
            $seatQuery = DB::table('seats')
                ->where('bus_id', $busId);

            if (Schema::hasColumn('seats', 'is_disabled')) {
                $seatQuery->where('is_disabled', 0);
            }

            $seatCount = (int) $seatQuery->count();
        }

        if ($seatCount <= 0) {
            $seatCount = (int) (
                DB::table('buses')
                    ->where('id', $busId)
                    ->value('seat_count') ?? 0
            );
        }

        $bookedSeatsCount = 0;

        if (
            Schema::hasTable('booking_passengers') &&
            Schema::hasTable('bookings')
        ) {
            $bookedSeatsCount = (int) $this->activeReservedSeatsQuery($tripId)
                ->distinct()
                ->count('booking_passengers.seat_number');
        }

        return [
            'seat_count' => $seatCount,
            'booked_seats_count' => $bookedSeatsCount,
            'available_seats' => max(
                0,
                $seatCount - $bookedSeatsCount
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
            ->whereNotIn('bookings.status', [
                'cancelled',
                'canceled',
                'expired',
                'refunded',
            ]);

        if (Schema::hasColumn('bookings', 'hold_expires_at')) {
            $query->where(function ($outer) {
                $outer
                    ->where('bookings.status', '!=', 'pending')
                    ->orWhere(function ($pending) {
                        $pending
                            ->where('bookings.status', 'pending')
                            ->where('bookings.hold_expires_at', '>', Carbon::now(self::TIMEZONE));
                    });
            });
        }

        return $query;
    }

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

    private function emptyResponse()
    {
        return response()->json([
            'success' => true,
            'title' => 'Recommended For You',
            'subtitle' => 'Based on available trips, your journeys and passenger feedback.',
            'recommendations' => [],
        ]);
    }

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
