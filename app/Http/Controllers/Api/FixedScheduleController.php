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
    |
    | Admin-created information-only timetables.
    |
    | Example:
    |
    | Master Route 76
    | Akkaraipattu ↔ Trincomalee
    |
    | Starting:
    | Akkaraipattu -> ... -> Trincomalee
    |
    | Return:
    | Trincomalee -> ... -> Akkaraipattu
    |
    | Supports intermediate stop search in BOTH directions.
    |
    */

    public function search(Request $request)
    {
        $data = $request->validate([
            'origin' => [
                'required',
                'string',
                'max:150',
            ],

            'destination' => [
                'required',
                'string',
                'max:150',
                'different:origin',
            ],
        ]);

        $origin = trim(
            $data['origin']
        );

        $destination = trim(
            $data['destination']
        );

        /*
        |--------------------------------------------------------------------------
        | Admin Fixed Timetables Only
        |--------------------------------------------------------------------------
        |
        | Admin timetable:
        |
        | operator_id = NULL
        | bus_id      = NULL
        |
        | Operator bookable services are excluded.
        |
        */

        $services = DB::table('fixed_services as fs')
            ->join(
                'routes as r',
                'r.id',
                '=',
                'fs.route_id'
            )
            ->whereNull(
                'fs.operator_id'
            )
            ->whereNull(
                'fs.bus_id'
            )
            ->where(
                'fs.is_active',
                true
            )
            ->where(
                'fs.is_published',
                true
            )
            ->where(
                'r.is_active',
                true
            )
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
            ->orderBy(
                'fs.bus_name'
            )
            ->get();

        $results = [];

        foreach (
            $services as $service
        ) {
            /*
            |--------------------------------------------------------------------------
            | Check Starting Direction
            |--------------------------------------------------------------------------
            */

            $startingJourney =
                $this->findJourney(
                    (int) $service->id,
                    'starting',
                    $origin,
                    $destination
                );

            if ($startingJourney) {
                $key =
                    $service->id
                    .
                    '-starting-'
                    .
                    $startingJourney['boarding_stop_id']
                    .
                    '-'
                    .
                    $startingJourney['dropoff_stop_id'];

                $results[$key] =
                    $this->buildSearchResult(
                        $service,
                        $startingJourney
                    );
            }

            /*
            |--------------------------------------------------------------------------
            | Check Return Direction
            |--------------------------------------------------------------------------
            */

            $returnJourney =
                $this->findJourney(
                    (int) $service->id,
                    'return',
                    $origin,
                    $destination
                );

            if ($returnJourney) {
                $key =
                    $service->id
                    .
                    '-return-'
                    .
                    $returnJourney['boarding_stop_id']
                    .
                    '-'
                    .
                    $returnJourney['dropoff_stop_id'];

                $results[$key] =
                    $this->buildSearchResult(
                        $service,
                        $returnJourney
                    );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Sort by passenger boarding time
        |--------------------------------------------------------------------------
        */

        $results =
            collect(
                array_values($results)
            )
                ->sortBy(
                    fn ($row) =>
                        $row['boarding_time']
                        ??
                        '23:59:59'
                )
                ->values();

        return response()->json([
            'success' => true,

            'search' => [
                'origin' =>
                    $origin,

                'destination' =>
                    $destination,
            ],

            'count' =>
                $results->count(),

            /*
             * Preferred field.
             */
            'results' =>
                $results,

            /*
             * Compatibility.
             */
            'services' =>
                $results,

            'schedules' =>
                $results,
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
            ->join(
                'routes as r',
                'r.id',
                '=',
                'fs.route_id'
            )
            ->where(
                'fs.id',
                $id
            )
            ->whereNull(
                'fs.operator_id'
            )
            ->whereNull(
                'fs.bus_id'
            )
            ->where(
                'fs.is_active',
                true
            )
            ->where(
                'fs.is_published',
                true
            )
            ->where(
                'r.is_active',
                true
            )
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

                'message' =>
                    'Fixed bus schedule not found.',
            ], 404);
        }

        $roadWay =
            $this->masterRoadWay(
                (int) $service->route_id
            );

        $startingSchedule =
            $this->schedule(
                (int) $service->id,
                'starting'
            );

        $returnSchedule =
            $this->schedule(
                (int) $service->id,
                'return'
            );

        return response()->json([
            'success' => true,

            'service' => [
                'id' =>
                    $service->id,

                'fixed_service_id' =>
                    $service->id,

                'type' =>
                    'fixed_schedule',

                'service_type' =>
                    'fixed_timetable',

                'label' =>
                    'Daily Service',

                /*
                 * Information only.
                 */
                'bookable' =>
                    false,

                'booking_available' =>
                    false,

                'timetable_only' =>
                    true,

                /*
                 * Bus information.
                 */
                'bus_name' =>
                    $service->bus_name
                    ??
                    $service->service_name
                    ??
                    'Daily Service',

                'bus_number' =>
                    $service->bus_number,

                /*
                 * Contact.
                 */
                'contact_number' =>
                    $service->contact_number_1,

                'contact_numbers' =>
                    $this->contactNumbers(
                        $service
                    ),

                /*
                 * Master Route.
                 */
                'route_id' =>
                    $service->route_id,

                'route_number' =>
                    $service->route_number,

                'route_name' =>
                    $service->route_name,

                'master_route_origin' =>
                    $service->route_origin,

                'master_route_destination' =>
                    $service->route_destination,

                'master_route_display' =>
                    $service->route_origin
                    .
                    ' ↔ '
                    .
                    $service->route_destination,

                /*
                 * Starting route.
                 */
                'starting_origin' =>
                    $service->route_origin,

                'starting_destination' =>
                    $service->route_destination,

                'starting_route_display' =>
                    $service->route_origin
                    .
                    ' → '
                    .
                    $service->route_destination,

                /*
                 * Return route.
                 */
                'return_origin' =>
                    $service->route_destination,

                'return_destination' =>
                    $service->route_origin,

                'return_route_display' =>
                    $service->route_destination
                    .
                    ' → '
                    .
                    $service->route_origin,

                'distance_km' =>
                    $service->route_distance_km,

                'duration_minutes' =>
                    $service->route_duration_minutes,

                /*
                 * First timetable times.
                 */
                'starting_time' =>
                    $service->starting_time,

                'return_time' =>
                    $service->return_time,

                /*
                 * No online fare / booking.
                 */
                'fare' =>
                    null,

                'is_active' =>
                    (bool)
                    $service->is_active,

                'is_published' =>
                    (bool)
                    $service->is_published,

                'message' =>
                    'Online seat booking is not available for this daily service.',
            ],

            /*
             * Master Route road-way in forward order.
             */
            'road_way' =>
                $roadWay,

            /*
             * Actual daily service timetables.
             */
            'starting_schedule' =>
                $startingSchedule,

            'return_schedule' =>
                $returnSchedule,

            'booking_available' =>
                false,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Find Journey In Exact Direction
    |--------------------------------------------------------------------------
    |
    | fixed_service_stops.stop_order is journey order.
    |
    | starting:
    | 1,2,3... = forward
    |
    | return:
    | 1,2,3... = reverse journey
    |
    */

    private function findJourney(
        int $serviceId,
        string $direction,
        string $origin,
        string $destination
    ): ?array {
        $stops =
            $this->schedule(
                $serviceId,
                $direction
            );

        if (
            $stops->count()
            <
            2
        ) {
            return null;
        }

        $boarding =
            $this->findStop(
                $stops,
                $origin
            );

        $dropoff =
            $this->findStop(
                $stops,
                $destination
            );

        if (
            !$boarding
            ||
            !$dropoff
        ) {
            return null;
        }

        /*
         * Must travel forward in the CURRENT timetable direction.
         */
        if (
            (int)
            $boarding['journey_order']
            >=
            (int)
            $dropoff['journey_order']
        ) {
            return null;
        }

        $boardingTime =
            $boarding['departure_time']
            ??
            $boarding['arrival_time'];

        $dropoffTime =
            $dropoff['arrival_time']
            ??
            $dropoff['departure_time'];

        /*
         * Master Route distance remains measured from
         * the fixed Master Route origin.
         *
         * ABS makes return direction correct.
         */
        $journeyDistance =
            abs(
                (float)
                $dropoff[
                    'distance_from_origin_km'
                ]
                -
                (float)
                $boarding[
                    'distance_from_origin_km'
                ]
            );

        /*
         * Fare stage difference is returned as useful
         * journey information.
         *
         * This fixed timetable itself has no online fare.
         */
        $fareStageDifference =
            null;

        if (
            $boarding['fare_stage_no'] !== null
            &&
            $dropoff['fare_stage_no'] !== null
        ) {
            $fareStageDifference =
                abs(
                    (int)
                    $dropoff['fare_stage_no']
                    -
                    (int)
                    $boarding['fare_stage_no']
                );
        }

        return [
            'direction' =>
                $direction,

            'boarding_stop_id' =>
                $boarding[
                    'fixed_service_stop_id'
                ],

            'dropoff_stop_id' =>
                $dropoff[
                    'fixed_service_stop_id'
                ],

            'boarding_route_stop_id' =>
                $boarding[
                    'route_stop_id'
                ],

            'dropoff_route_stop_id' =>
                $dropoff[
                    'route_stop_id'
                ],

            'boarding_stop' =>
                $boarding['name'],

            'dropoff_stop' =>
                $dropoff['name'],

            'boarding_stop_order' =>
                (int)
                $boarding[
                    'journey_order'
                ],

            'dropoff_stop_order' =>
                (int)
                $dropoff[
                    'journey_order'
                ],

            'boarding_time' =>
                $boardingTime,

            'dropoff_time' =>
                $dropoffTime,

            'journey_distance_km' =>
                round(
                    $journeyDistance,
                    2
                ),

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
        $direction =
            $journey['direction'];

        if (
            $direction
            ===
            'return'
        ) {
            $fullOrigin =
                $service->route_destination;

            $fullDestination =
                $service->route_origin;
        } else {
            $fullOrigin =
                $service->route_origin;

            $fullDestination =
                $service->route_destination;
        }

        return [
            'id' =>
                $service->id,

            'fixed_service_id' =>
                $service->id,

            'type' =>
                'fixed_schedule',

            'service_type' =>
                'fixed_timetable',

            'label' =>
                'Daily Service',

            'timetable_only' =>
                true,

            /*
             * Never bookable.
             */
            'bookable' =>
                false,

            'booking_available' =>
                false,

            /*
             * Bus.
             */
            'bus_name' =>
                $service->bus_name
                ??
                $service->service_name
                ??
                'Daily Service',

            'bus_number' =>
                $service->bus_number,

            /*
             * Contact.
             */
            'contact_number' =>
                $service->contact_number_1,

            'contact_numbers' =>
                $this->contactNumbers(
                    $service
                ),

            /*
             * Master Route.
             */
            'route_id' =>
                $service->route_id,

            'route_number' =>
                $service->route_number,

            'route_name' =>
                $service->route_name,

            'master_route_origin' =>
                $service->route_origin,

            'master_route_destination' =>
                $service->route_destination,

            'master_route_display' =>
                $service->route_origin
                .
                ' ↔ '
                .
                $service->route_destination,

            /*
             * Actual service direction.
             */
            'direction' =>
                $direction,

            'full_route_origin' =>
                $fullOrigin,

            'full_route_destination' =>
                $fullDestination,

            'full_route_display' =>
                $fullOrigin
                .
                ' → '
                .
                $fullDestination,

            /*
             * Passenger requested segment.
             */
            'origin' =>
                $journey[
                    'boarding_stop'
                ],

            'destination' =>
                $journey[
                    'dropoff_stop'
                ],

            'requested_origin' =>
                $journey[
                    'boarding_stop'
                ],

            'requested_destination' =>
                $journey[
                    'dropoff_stop'
                ],

            'boarding_stop' =>
                $journey[
                    'boarding_stop'
                ],

            'dropoff_stop' =>
                $journey[
                    'dropoff_stop'
                ],

            'boarding_stop_order' =>
                $journey[
                    'boarding_stop_order'
                ],

            'dropoff_stop_order' =>
                $journey[
                    'dropoff_stop_order'
                ],

            /*
             * Timetable.
             */
            'boarding_time' =>
                $journey[
                    'boarding_time'
                ],

            'dropoff_time' =>
                $journey[
                    'dropoff_time'
                ],

            /*
             * Compatibility names.
             */
            'departure_time' =>
                $journey[
                    'boarding_time'
                ],

            'arrival_time' =>
                $journey[
                    'dropoff_time'
                ],

            /*
             * Journey data.
             */
            'journey_distance_km' =>
                $journey[
                    'journey_distance_km'
                ],

            'fare_stage_difference' =>
                $journey[
                    'fare_stage_difference'
                ],

            /*
             * Admin Daily Service:
             * informational timetable only.
             */
            'fare' =>
                null,

            'message' =>
                'Online seat booking is not available for this daily service.',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Fixed Service Schedule
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    |
    | Stop name comes from route_stops.
    |
    | We do NOT depend on legacy:
    |
    | fixed_service_stops.stop_name
    |
    */

    private function schedule(
        int $serviceId,
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
                $serviceId
            )
            ->where(
                'fss.direction',
                $direction
            )
            ->orderBy(
                'fss.stop_order'
            )
            ->select(
                'fss.id as fixed_service_stop_id',

                'fss.fixed_service_id',

                'fss.route_stop_id',

                'fss.direction',

                'fss.stop_order as journey_order',

                'fss.arrival_time',

                'fss.departure_time',

                'rs.name',

                'rs.stop_order as master_stop_order',

                'rs.fare_stage_no',

                'rs.distance_from_origin_km'
            )
            ->get()
            ->map(
                function ($stop) {
                    return [
                        'fixed_service_stop_id' =>
                            (int)
                            $stop
                                ->fixed_service_stop_id,

                        'fixed_service_id' =>
                            (int)
                            $stop
                                ->fixed_service_id,

                        'route_stop_id' =>
                            (int)
                            $stop
                                ->route_stop_id,

                        'direction' =>
                            $stop
                                ->direction,

                        /*
                         * Journey order in current timetable.
                         */
                        'journey_order' =>
                            (int)
                            $stop
                                ->journey_order,

                        /*
                         * Master Route order.
                         */
                        'master_stop_order' =>
                            (int)
                            $stop
                                ->master_stop_order,

                        'name' =>
                            $stop->name,

                        /*
                         * Compatibility.
                         */
                        'stop_name' =>
                            $stop->name,

                        'arrival_time' =>
                            $stop
                                ->arrival_time,

                        'departure_time' =>
                            $stop
                                ->departure_time,

                        'schedule_time' =>
                            $stop
                                ->departure_time
                            ??
                            $stop
                                ->arrival_time,

                        'fare_stage_no' =>
                            $stop->fare_stage_no
                            !==
                            null
                                ? (int)
                                $stop
                                    ->fare_stage_no
                                : null,

                        'distance_from_origin_km' =>
                            (float)
                            (
                                $stop
                                    ->distance_from_origin_km
                                ??
                                0
                            ),
                    ];
                }
            )
            ->values();
    }

    /*
    |--------------------------------------------------------------------------
    | Master Road Way
    |--------------------------------------------------------------------------
    */

    private function masterRoadWay(
        int $routeId
    ) {
        return DB::table(
            'route_stops'
        )
            ->where(
                'route_id',
                $routeId
            )
            ->orderBy(
                'stop_order'
            )
            ->get()
            ->map(
                function ($stop) {
                    return [
                        'id' =>
                            (int)
                            $stop->id,

                        'route_stop_id' =>
                            (int)
                            $stop->id,

                        'name' =>
                            $stop->name,

                        'stop_order' =>
                            (int)
                            $stop->stop_order,

                        'fare_stage_no' =>
                            $stop->fare_stage_no
                            !==
                            null
                                ? (int)
                                $stop
                                    ->fare_stage_no
                                : null,

                        'distance_from_origin_km' =>
                            (float)
                            (
                                $stop
                                    ->distance_from_origin_km
                                ??
                                $stop
                                    ->distance_from_origin
                                ??
                                0
                            ),

                        'latitude' =>
                            $stop->latitude
                            ??
                            null,

                        'longitude' =>
                            $stop->longitude
                            ??
                            null,
                    ];
                }
            )
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
         * 1. Exact normalized match.
         */
        foreach (
            $stops as $stop
        ) {
            if (
                $this->same(
                    $stop['name'],
                    $search
                )
            ) {
                return $stop;
            }
        }

        /*
         * 2. Safe partial match.
         *
         * Example:
         *
         * Search:
         * Batticaloa
         *
         * Schedule:
         * Batticaloa Bus Stand
         */
        foreach (
            $stops as $stop
        ) {
            if (
                $this->similar(
                    $stop['name'],
                    $search
                )
            ) {
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
        return
            $this->normalize(
                $first
            )
            ===
            $this->normalize(
                $second
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Partial Match
    |--------------------------------------------------------------------------
    */

    private function similar(
        string $first,
        string $second
    ): bool {
        $a =
            $this->normalize(
                $first
            );

        $b =
            $this->normalize(
                $second
            );

        if (
            $a === ''
            ||
            $b === ''
        ) {
            return false;
        }

        /*
         * Avoid unsafe tiny searches.
         */
        if (
            mb_strlen($a) < 4
            ||
            mb_strlen($b) < 4
        ) {
            return false;
        }

        return
            str_contains(
                $a,
                $b
            )
            ||
            str_contains(
                $b,
                $a
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Normalize Location
    |--------------------------------------------------------------------------
    */

    private function normalize(
        string $value
    ): string {
        $value =
            trim(
                mb_strtolower(
                    $value
                )
            );

        $value =
            preg_replace(
                '/[^\p{L}\p{N}]+/u',
                '',
                $value
            );

        return
            $value
            ??
            '';
    }

    /*
    |--------------------------------------------------------------------------
    | Contact Numbers
    |--------------------------------------------------------------------------
    */

    private function contactNumbers(
        $service
    ): array {
        return collect([
            $service->contact_number_1
            ?? null,

            $service->contact_number_2
            ?? null,

            $service->contact_number_3
            ?? null,
        ])
            ->filter(
                fn ($number) =>
                    !empty(
                        trim(
                            (string)
                            $number
                        )
                    )
            )
            ->map(
                fn ($number) =>
                    trim(
                        (string)
                        $number
                    )
            )
            ->unique()
            ->values()
            ->all();
    }
}