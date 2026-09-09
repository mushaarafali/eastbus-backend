<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FixedScheduleController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Search Fixed Daily Services
    |--------------------------------------------------------------------------
    */

    public function search(Request $request)
    {
        $data = $request->validate([
            'origin' => ['required', 'string', 'max:150'],
            'destination' => ['required', 'string', 'max:150', 'different:origin'],
        ]);

        $origin = trim($data['origin']);
        $destination = trim($data['destination']);

        /*
         * Admin-created timetable-only services:
         *
         * operator_id = NULL
         * bus_id      = NULL
         *
         * Operator-created bookable services are excluded.
         */
        $services = DB::table('fixed_services as fs')
            ->join('routes as r', 'r.id', '=', 'fs.route_id')
            ->whereNull('fs.operator_id')
            ->whereNull('fs.bus_id')
            ->where('fs.is_active', true)
            ->where('fs.is_published', true)
            ->where('r.is_active', true)
            ->select(
                'fs.id',
                'fs.route_id',
                'fs.bus_name',
                'fs.bus_number',
                'fs.contact_number_1',
                'fs.contact_number_2',
                'fs.contact_number_3',
                'fs.service_name',
                'fs.starting_time',
                'fs.return_time',
                'fs.is_active',
                'fs.is_published',
                'r.route_number',
                'r.name as route_name',
                'r.origin as route_origin',
                'r.destination as route_destination',
                'r.distance_km as route_distance_km',
                'r.duration_minutes as route_duration_minutes'
            )
            ->orderBy('fs.bus_name')
            ->orderBy('fs.bus_number')
            ->get();

        $results = [];

        foreach ($services as $service) {
            foreach (['starting', 'return'] as $direction) {
                $journey = $this->findJourney(
                    (int) $service->id,
                    $direction,
                    $origin,
                    $destination
                );

                if (!$journey) {
                    continue;
                }

                $key = implode('-', [
                    $service->id,
                    $direction,
                    $journey['boarding_stop_id'],
                    $journey['dropoff_stop_id'],
                ]);

                $results[$key] = $this->buildSearchResult(
                    $service,
                    $journey
                );
            }
        }

        $results = collect(array_values($results))
            ->sortBy(fn ($row) => $row['boarding_time'] ?? '23:59:59')
            ->values();

        return response()->json([
            'success' => true,
            'search' => [
                'origin' => $origin,
                'destination' => $destination,
            ],
            'count' => $results->count(),
            'results' => $results,

            // Compatibility with existing Passenger App.
            'services' => $results,
            'schedules' => $results,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Fixed Schedule Details
    |--------------------------------------------------------------------------
    */

    public function show(int $id)
    {
        $service = DB::table('fixed_services as fs')
            ->join('routes as r', 'r.id', '=', 'fs.route_id')
            ->where('fs.id', $id)
            ->whereNull('fs.operator_id')
            ->whereNull('fs.bus_id')
            ->where('fs.is_active', true)
            ->where('fs.is_published', true)
            ->where('r.is_active', true)
            ->select(
                'fs.id',
                'fs.route_id',
                'fs.bus_name',
                'fs.bus_number',
                'fs.contact_number_1',
                'fs.contact_number_2',
                'fs.contact_number_3',
                'fs.service_name',
                'fs.starting_time',
                'fs.return_time',
                'fs.is_active',
                'fs.is_published',
                'r.route_number',
                'r.name as route_name',
                'r.origin as route_origin',
                'r.destination as route_destination',
                'r.distance_km as route_distance_km',
                'r.duration_minutes as route_duration_minutes'
            )
            ->first();

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Fixed bus schedule not found.',
            ], 404);
        }

        $roadWay = $this->masterRoadWay(
            (int) $service->route_id
        );

        $startingSchedule = $this->schedule(
            (int) $service->id,
            'starting'
        );

        $returnSchedule = $this->schedule(
            (int) $service->id,
            'return'
        );

        return response()->json([
            'success' => true,

            'service' => [
                'id' => (int) $service->id,
                'fixed_service_id' => (int) $service->id,

                'type' => 'fixed_schedule',
                'service_type' => 'fixed_timetable',
                'label' => 'Daily Service',

                /*
                 * Admin timetable is information-only.
                 */
                'bookable' => false,
                'booking_available' => false,
                'timetable_only' => true,

                /*
                 * Bus information.
                 */
                'bus_name' =>
                    $service->bus_name
                    ?: ($service->service_name ?: 'Daily Service'),

                'bus_number' => $service->bus_number,

                /*
                 * Contact numbers.
                 */
                'contact_number' => $service->contact_number_1,
                'contact_numbers' => $this->contactNumbers($service),

                /*
                 * Master route.
                 */
                'route_id' => (int) $service->route_id,
                'route_number' => $service->route_number,
                'route_name' => $service->route_name,

                'master_route_origin' => $service->route_origin,
                'master_route_destination' => $service->route_destination,

                'master_route_display' =>
                    $service->route_origin .
                    ' ↔ ' .
                    $service->route_destination,

                /*
                 * Starting direction.
                 */
                'starting_origin' => $service->route_origin,
                'starting_destination' => $service->route_destination,

                'starting_route_display' =>
                    $service->route_origin .
                    ' → ' .
                    $service->route_destination,

                /*
                 * Return direction.
                 */
                'return_origin' => $service->route_destination,
                'return_destination' => $service->route_origin,

                'return_route_display' =>
                    $service->route_destination .
                    ' → ' .
                    $service->route_origin,

                /*
                 * Route information.
                 */
                'distance_km' => $service->route_distance_km !== null
                    ? (float) $service->route_distance_km
                    : null,

                'duration_minutes' =>
                    $service->route_duration_minutes !== null
                        ? (int) $service->route_duration_minutes
                        : null,

                /*
                 * Main timetable times.
                 */
                'starting_time' => $service->starting_time,
                'return_time' => $service->return_time,

                /*
                 * No online fare / booking.
                 */
                'fare' => null,

                'is_active' => (bool) $service->is_active,
                'is_published' => (bool) $service->is_published,

                'message' =>
                    'This is a timetable-only daily service. Online seat booking is not available.',
            ],

            'road_way' => $roadWay,
            'starting_schedule' => $startingSchedule,
            'return_schedule' => $returnSchedule,

            'booking_available' => false,
            'timetable_only' => true,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Find Journey
    |--------------------------------------------------------------------------
    */

    private function findJourney(
        int $serviceId,
        string $direction,
        string $origin,
        string $destination
    ): ?array {
        $stops = $this->schedule(
            $serviceId,
            $direction
        );

        if ($stops->count() < 2) {
            return null;
        }

        $boarding = $this->findStop(
            $stops,
            $origin
        );

        $dropoff = $this->findStop(
            $stops,
            $destination
        );

        if (!$boarding || !$dropoff) {
            return null;
        }

        /*
         * Passenger must travel forward in the selected
         * timetable direction.
         */
        if (
            (int) $boarding['journey_order'] >=
            (int) $dropoff['journey_order']
        ) {
            return null;
        }

        /*
         * Respect boarding / drop-off permissions.
         */
        if (!$boarding['boarding_allowed']) {
            return null;
        }

        if (!$dropoff['dropoff_allowed']) {
            return null;
        }

        $boardingTime =
            $boarding['departure_time']
            ?? $boarding['arrival_time'];

        $dropoffTime =
            $dropoff['arrival_time']
            ?? $dropoff['departure_time'];

        /*
         * Master-route distance is measured from the
         * master route origin. ABS supports return trips.
         */
        $journeyDistance = abs(
            (float) $dropoff['distance_from_origin_km'] -
            (float) $boarding['distance_from_origin_km']
        );

        $fareStageDifference = null;

        if (
            $boarding['fare_stage_no'] !== null &&
            $dropoff['fare_stage_no'] !== null
        ) {
            $fareStageDifference = abs(
                (int) $dropoff['fare_stage_no'] -
                (int) $boarding['fare_stage_no']
            );
        }

        return [
            'direction' => $direction,

            'boarding_stop_id' =>
                $boarding['fixed_service_stop_id'],

            'dropoff_stop_id' =>
                $dropoff['fixed_service_stop_id'],

            'boarding_route_stop_id' =>
                $boarding['route_stop_id'],

            'dropoff_route_stop_id' =>
                $dropoff['route_stop_id'],

            'boarding_stop' => $boarding['name'],
            'dropoff_stop' => $dropoff['name'],

            'boarding_stop_order' =>
                (int) $boarding['journey_order'],

            'dropoff_stop_order' =>
                (int) $dropoff['journey_order'],

            'boarding_time' => $boardingTime,
            'dropoff_time' => $dropoffTime,

            'journey_distance_km' =>
                round($journeyDistance, 2),

            'fare_stage_difference' =>
                $fareStageDifference,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Build Search Result
    |--------------------------------------------------------------------------
    */

    private function buildSearchResult(
        $service,
        array $journey
    ): array {
        $direction = $journey['direction'];

        if ($direction === 'return') {
            $fullOrigin = $service->route_destination;
            $fullDestination = $service->route_origin;
        } else {
            $fullOrigin = $service->route_origin;
            $fullDestination = $service->route_destination;
        }

        return [
            'id' => (int) $service->id,
            'fixed_service_id' => (int) $service->id,

            'type' => 'fixed_schedule',
            'service_type' => 'fixed_timetable',
            'label' => 'Daily Service',

            /*
             * Never bookable.
             */
            'timetable_only' => true,
            'bookable' => false,
            'booking_available' => false,

            /*
             * Bus information.
             */
            'bus_name' =>
                $service->bus_name
                ?: ($service->service_name ?: 'Daily Service'),

            'bus_number' => $service->bus_number,

            /*
             * Contact.
             */
            'contact_number' => $service->contact_number_1,
            'contact_numbers' => $this->contactNumbers($service),

            /*
             * Master route.
             */
            'route_id' => (int) $service->route_id,
            'route_number' => $service->route_number,
            'route_name' => $service->route_name,

            'master_route_origin' => $service->route_origin,
            'master_route_destination' => $service->route_destination,

            'master_route_display' =>
                $service->route_origin .
                ' ↔ ' .
                $service->route_destination,

            /*
             * Full service direction.
             */
            'direction' => $direction,

            'full_route_origin' => $fullOrigin,
            'full_route_destination' => $fullDestination,

            'full_route_display' =>
                $fullOrigin .
                ' → ' .
                $fullDestination,

            /*
             * Passenger requested journey.
             */
            'origin' => $journey['boarding_stop'],
            'destination' => $journey['dropoff_stop'],

            'requested_origin' => $journey['boarding_stop'],
            'requested_destination' => $journey['dropoff_stop'],

            'boarding_stop' => $journey['boarding_stop'],
            'dropoff_stop' => $journey['dropoff_stop'],

            'boarding_stop_order' =>
                $journey['boarding_stop_order'],

            'dropoff_stop_order' =>
                $journey['dropoff_stop_order'],

            /*
             * Passenger timetable.
             */
            'boarding_time' => $journey['boarding_time'],
            'dropoff_time' => $journey['dropoff_time'],

            /*
             * Compatibility names.
             */
            'departure_time' => $journey['boarding_time'],
            'arrival_time' => $journey['dropoff_time'],

            /*
             * Journey information.
             */
            'journey_distance_km' =>
                $journey['journey_distance_km'],

            'fare_stage_difference' =>
                $journey['fare_stage_difference'],

            /*
             * Information-only timetable.
             */
            'fare' => null,

            'message' =>
                'This is a timetable-only daily service. Online seat booking is not available.',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Fixed Service Schedule
    |--------------------------------------------------------------------------
    */

    private function schedule(
        int $serviceId,
        string $direction
    ) {
        return DB::table('fixed_service_stops as fss')
            ->leftJoin(
                'route_stops as rs',
                'rs.id',
                '=',
                'fss.route_stop_id'
            )
            ->where('fss.fixed_service_id', $serviceId)
            ->where('fss.direction', $direction)
            ->orderBy('fss.stop_order')
            ->select(
                'fss.id as fixed_service_stop_id',
                'fss.fixed_service_id',
                'fss.route_stop_id',
                'fss.direction',
                'fss.stop_name as legacy_stop_name',
                'fss.stop_order as journey_order',
                'fss.arrival_time',
                'fss.departure_time',
                'fss.boarding_allowed',
                'fss.dropoff_allowed',
                'rs.name as route_stop_name',
                'rs.stop_order as master_stop_order',
                'rs.fare_stage_no',
                'rs.distance_from_origin_km',
                'rs.distance_from_origin'
            )
            ->get()
            ->map(function ($stop) {
                $name = trim(
                    (string) (
                        $stop->route_stop_name
                        ?: $stop->legacy_stop_name
                    )
                );

                $distance = $stop->distance_from_origin_km;

                if ($distance === null) {
                    $distance = $stop->distance_from_origin;
                }

                return [
                    'fixed_service_stop_id' =>
                        (int) $stop->fixed_service_stop_id,

                    'fixed_service_id' =>
                        (int) $stop->fixed_service_id,

                    'route_stop_id' =>
                        $stop->route_stop_id !== null
                            ? (int) $stop->route_stop_id
                            : null,

                    'direction' => $stop->direction,

                    /*
                     * Journey order for this timetable direction.
                     */
                    'journey_order' =>
                        (int) $stop->journey_order,

                    /*
                     * Master route order may be unavailable
                     * for old timetable records.
                     */
                    'master_stop_order' =>
                        $stop->master_stop_order !== null
                            ? (int) $stop->master_stop_order
                            : null,

                    'name' => $name,
                    'stop_name' => $name,

                    'arrival_time' => $stop->arrival_time,
                    'departure_time' => $stop->departure_time,

                    'schedule_time' =>
                        $stop->departure_time
                        ?? $stop->arrival_time,

                    'boarding_allowed' =>
                        (bool) $stop->boarding_allowed,

                    'dropoff_allowed' =>
                        (bool) $stop->dropoff_allowed,

                    'fare_stage_no' =>
                        $stop->fare_stage_no !== null
                            ? (int) $stop->fare_stage_no
                            : null,

                    'distance_from_origin_km' =>
                        $distance !== null
                            ? (float) $distance
                            : 0.0,
                ];
            })
            ->filter(
                fn ($stop) =>
                    trim((string) $stop['name']) !== ''
            )
            ->values();
    }

    /*
    |--------------------------------------------------------------------------
    | Master Route Road-Way
    |--------------------------------------------------------------------------
    */

    private function masterRoadWay(int $routeId)
    {
        return DB::table('route_stops')
            ->where('route_id', $routeId)
            ->orderBy('stop_order')
            ->get()
            ->map(function ($stop) {
                $distance =
                    $stop->distance_from_origin_km
                    ?? $stop->distance_from_origin
                    ?? 0;

                return [
                    'id' => (int) $stop->id,
                    'route_stop_id' => (int) $stop->id,
                    'name' => $stop->name,
                    'stop_name' => $stop->name,
                    'stop_order' => (int) $stop->stop_order,

                    'fare_stage_no' =>
                        $stop->fare_stage_no !== null
                            ? (int) $stop->fare_stage_no
                            : null,

                    'distance_from_origin_km' =>
                        (float) $distance,

                    'latitude' =>
                        $stop->latitude !== null
                            ? (float) $stop->latitude
                            : null,

                    'longitude' =>
                        $stop->longitude !== null
                            ? (float) $stop->longitude
                            : null,

                    'boarding_allowed' =>
                        isset($stop->boarding_allowed)
                            ? (bool) $stop->boarding_allowed
                            : true,

                    'dropoff_allowed' =>
                        isset($stop->dropoff_allowed)
                            ? (bool) $stop->dropoff_allowed
                            : true,
                ];
            })
            ->values();
    }

    /*
    |--------------------------------------------------------------------------
    | Find Matching Stop
    |--------------------------------------------------------------------------
    */

    private function findStop(
        $stops,
        string $search
    ): ?array {
        /*
         * First try exact normalized match.
         */
        foreach ($stops as $stop) {
            if ($this->same($stop['name'], $search)) {
                return $stop;
            }
        }

        /*
         * Then safe partial match.
         *
         * Example:
         * Batticaloa
         * Batticaloa Bus Stand
         */
        foreach ($stops as $stop) {
            if ($this->similar($stop['name'], $search)) {
                return $stop;
            }
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Exact Match
    |--------------------------------------------------------------------------
    */

    private function same(
        string $first,
        string $second
    ): bool {
        return $this->normalize($first) ===
            $this->normalize($second);
    }

    /*
    |--------------------------------------------------------------------------
    | Safe Partial Match
    |--------------------------------------------------------------------------
    */

    private function similar(
        string $first,
        string $second
    ): bool {
        $a = $this->normalize($first);
        $b = $this->normalize($second);

        if ($a === '' || $b === '') {
            return false;
        }

        /*
         * Prevent unsafe very short partial matches.
         */
        if (
            mb_strlen($a) < 4 ||
            mb_strlen($b) < 4
        ) {
            return false;
        }

        return str_contains($a, $b) ||
            str_contains($b, $a);
    }

    /*
    |--------------------------------------------------------------------------
    | Normalize Location Name
    |--------------------------------------------------------------------------
    */

    private function normalize(string $value): string
    {
        $value = trim(
            mb_strtolower($value)
        );

        $value = preg_replace(
            '/[^\p{L}\p{N}]+/u',
            '',
            $value
        );

        return $value ?? '';
    }

    /*
    |--------------------------------------------------------------------------
    | Contact Numbers
    |--------------------------------------------------------------------------
    */

    private function contactNumbers($service): array
    {
        return collect([
            $service->contact_number_1 ?? null,
            $service->contact_number_2 ?? null,
            $service->contact_number_3 ?? null,
        ])
            ->map(fn ($number) => trim((string) $number))
            ->filter(fn ($number) => $number !== '')
            ->unique()
            ->values()
            ->all();
    }
}