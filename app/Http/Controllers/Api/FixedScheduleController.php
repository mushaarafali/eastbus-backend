<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FixedScheduleController extends Controller
{
    public function search(Request $request)
    {
        $data = $request->validate([
            'origin' => ['required', 'string', 'max:150'],
            'destination' => ['required', 'string', 'max:150', 'different:origin'],
        ]);

        $origin = trim($data['origin']);
        $destination = trim($data['destination']);

        /*
        |--------------------------------------------------------------------------
        | Published Fixed Timetable Services
        |--------------------------------------------------------------------------
        |
        | Do NOT require operator_id or bus_id to be NULL.
        |
        | A fixed timetable is identified by:
        | - fixed_services record
        | - active
        | - published
        | - valid active master route
        |
        */

        $services = DB::table('fixed_services as fs')
            ->join('routes as r', 'r.id', '=', 'fs.route_id')
            ->where('fs.is_active', 1)
            ->where('fs.is_published', 1)
            ->where('r.is_active', 1)
            ->select(
                'fs.id',
                'fs.route_id',
                'fs.operator_id',
                'fs.bus_id',
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

                if ($journey === null) {
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
            ->sortBy(function ($row) {
                return $row['departure_time'] ?? '23:59:59';
            })
            ->values();

        return response()->json([
            'success' => true,

            'search' => [
                'origin' => $origin,
                'destination' => $destination,
            ],

            'count' => $results->count(),

            /*
            |--------------------------------------------------------------------------
            | Passenger App Compatibility
            |--------------------------------------------------------------------------
            */

            'fixed_services' => $results,
            'fixed_schedules' => $results,
            'fixed_timetables' => $results,
            'results' => $results,
            'services' => $results,
            'schedules' => $results,
        ]);
    }

    public function show(int $id)
    {
        $service = DB::table('fixed_services as fs')
            ->join('routes as r', 'r.id', '=', 'fs.route_id')
            ->where('fs.id', $id)
            ->where('fs.is_active', 1)
            ->where('fs.is_published', 1)
            ->where('r.is_active', 1)
            ->select(
                'fs.id',
                'fs.route_id',
                'fs.operator_id',
                'fs.bus_id',
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

                'operator_id' => $service->operator_id !== null
                    ? (int) $service->operator_id
                    : null,

                'bus_id' => $service->bus_id !== null
                    ? (int) $service->bus_id
                    : null,

                'type' => 'fixed_schedule',
                'service_type' => 'fixed_timetable',
                'label' => 'TIMETABLE ONLY',

                'bookable' => false,
                'booking_available' => false,
                'timetable_only' => true,

                'bus_name' => $this->busName($service),
                'bus_number' => (string) ($service->bus_number ?? ''),

                'contact_number' => $service->contact_number_1,
                'contact_numbers' => $this->contactNumbers($service),

                'route_id' => (int) $service->route_id,
                'route_number' => $service->route_number,
                'route_name' => $service->route_name,

                'master_route_origin' => $service->route_origin,
                'master_route_destination' => $service->route_destination,

                'master_route_display' =>
                    $service->route_origin .
                    ' ↔ ' .
                    $service->route_destination,

                'starting_origin' => $service->route_origin,
                'starting_destination' => $service->route_destination,

                'starting_route_display' =>
                    $service->route_origin .
                    ' → ' .
                    $service->route_destination,

                'return_origin' => $service->route_destination,
                'return_destination' => $service->route_origin,

                'return_route_display' =>
                    $service->route_destination .
                    ' → ' .
                    $service->route_origin,

                'distance_km' =>
                    $service->route_distance_km !== null
                        ? (float) $service->route_distance_km
                        : null,

                'duration_minutes' =>
                    $service->route_duration_minutes !== null
                        ? (int) $service->route_duration_minutes
                        : null,

                'starting_time' => $service->starting_time,
                'return_time' => $service->return_time,

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

        if (
            (int) $boarding['journey_order'] >=
            (int) $dropoff['journey_order']
        ) {
            return null;
        }

        if (!$boarding['boarding_allowed']) {
            return null;
        }

        if (!$dropoff['dropoff_allowed']) {
            return null;
        }

        $boardingTime =
            $boarding['departure_time']
            ?: $boarding['arrival_time'];

        $dropoffTime =
            $dropoff['arrival_time']
            ?: $dropoff['departure_time'];

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

            'boarding_stop' =>
                $boarding['name'],

            'dropoff_stop' =>
                $dropoff['name'],

            'boarding_stop_order' =>
                (int) $boarding['journey_order'],

            'dropoff_stop_order' =>
                (int) $dropoff['journey_order'],

            'boarding_time' =>
                $boardingTime,

            'dropoff_time' =>
                $dropoffTime,

            'journey_distance_km' =>
                round($journeyDistance, 2),

            'fare_stage_difference' =>
                $fareStageDifference,
        ];
    }

    private function buildSearchResult(
        $service,
        array $journey
    ): array {
        $direction = $journey['direction'];

        if ($direction === 'return') {
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
            'id' => (int) $service->id,
            'fixed_service_id' => (int) $service->id,

            'operator_id' =>
                $service->operator_id !== null
                    ? (int) $service->operator_id
                    : null,

            'bus_id' =>
                $service->bus_id !== null
                    ? (int) $service->bus_id
                    : null,

            'type' => 'fixed_schedule',
            'service_type' => 'fixed_timetable',
            'label' => 'TIMETABLE ONLY',

            'timetable_only' => true,
            'bookable' => false,
            'booking_available' => false,

            'bus_name' => $this->busName($service),

            'bus_number' =>
                (string) ($service->bus_number ?? ''),

            'contact_number' =>
                $service->contact_number_1,

            'contact_numbers' =>
                $this->contactNumbers($service),

            'route_id' =>
                (int) $service->route_id,

            'route_number' =>
                $service->route_number,

            'route_name' =>
                $service->route_name,

            'master_route_origin' =>
                $service->route_origin,

            'master_route_destination' =>
                $service->route_destination,

            'master_route_display' =>
                $service->route_origin .
                ' ↔ ' .
                $service->route_destination,

            'direction' =>
                $direction,

            'full_route_origin' =>
                $fullOrigin,

            'full_route_destination' =>
                $fullDestination,

            'full_route_display' =>
                $fullOrigin .
                ' → ' .
                $fullDestination,

            /*
            |--------------------------------------------------------------------------
            | Requested Passenger Journey
            |--------------------------------------------------------------------------
            */

            'origin' =>
                $journey['boarding_stop'],

            'destination' =>
                $journey['dropoff_stop'],

            'requested_origin' =>
                $journey['boarding_stop'],

            'requested_destination' =>
                $journey['dropoff_stop'],

            'boarding_stop' =>
                $journey['boarding_stop'],

            'dropoff_stop' =>
                $journey['dropoff_stop'],

            'boarding_stop_order' =>
                $journey['boarding_stop_order'],

            'dropoff_stop_order' =>
                $journey['dropoff_stop_order'],

            /*
            |--------------------------------------------------------------------------
            | Times
            |--------------------------------------------------------------------------
            */

            'boarding_time' =>
                $journey['boarding_time'],

            'dropoff_time' =>
                $journey['dropoff_time'],

            'departure_time' =>
                $journey['boarding_time'],

            'arrival_time' =>
                $journey['dropoff_time'],

            'starting_time' =>
                $service->starting_time,

            'return_time' =>
                $service->return_time,

            'journey_distance_km' =>
                $journey['journey_distance_km'],

            'fare_stage_difference' =>
                $journey['fare_stage_difference'],

            'fare' => null,

            'is_active' =>
                (bool) $service->is_active,

            'is_published' =>
                (bool) $service->is_published,

            'message' =>
                'This is a timetable-only daily service. Online seat booking is not available.',
        ];
    }

    private function schedule(
        int $serviceId,
        string $direction
    ) {
        return DB::table(
            'fixed_service_stops as fss'
        )
            ->leftJoin(
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

                $distance =
                    $stop->distance_from_origin_km;

                if ($distance === null) {
                    $distance =
                        $stop->distance_from_origin;
                }

                /*
                 * Older records may contain NULL permission values.
                 * NULL should not automatically make the stop unusable.
                 */

                $boardingAllowed =
                    $stop->boarding_allowed === null
                        ? true
                        : (bool) $stop->boarding_allowed;

                $dropoffAllowed =
                    $stop->dropoff_allowed === null
                        ? true
                        : (bool) $stop->dropoff_allowed;

                return [
                    'fixed_service_stop_id' =>
                        (int) $stop->fixed_service_stop_id,

                    'fixed_service_id' =>
                        (int) $stop->fixed_service_id,

                    'route_stop_id' =>
                        $stop->route_stop_id !== null
                            ? (int) $stop->route_stop_id
                            : null,

                    'direction' =>
                        $stop->direction,

                    'journey_order' =>
                        (int) $stop->journey_order,

                    'master_stop_order' =>
                        $stop->master_stop_order !== null
                            ? (int) $stop->master_stop_order
                            : null,

                    'name' => $name,
                    'stop_name' => $name,

                    'arrival_time' =>
                        $stop->arrival_time,

                    'departure_time' =>
                        $stop->departure_time,

                    'schedule_time' =>
                        $stop->departure_time
                        ?: $stop->arrival_time,

                    'boarding_allowed' =>
                        $boardingAllowed,

                    'dropoff_allowed' =>
                        $dropoffAllowed,

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
            ->filter(function ($stop) {
                return trim(
                    (string) $stop['name']
                ) !== '';
            })
            ->values();
    }

    private function masterRoadWay(
        int $routeId
    ) {
        return DB::table('route_stops')
            ->where(
                'route_id',
                $routeId
            )
            ->orderBy(
                'stop_order'
            )
            ->get()
            ->map(function ($stop) {
                $distance =
                    $stop->distance_from_origin_km
                    ?? $stop->distance_from_origin
                    ?? 0;

                return [
                    'id' =>
                        (int) $stop->id,

                    'route_stop_id' =>
                        (int) $stop->id,

                    'name' =>
                        $stop->name,

                    'stop_name' =>
                        $stop->name,

                    'stop_order' =>
                        (int) $stop->stop_order,

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
                        isset(
                            $stop->boarding_allowed
                        )
                            ? (bool) $stop->boarding_allowed
                            : true,

                    'dropoff_allowed' =>
                        isset(
                            $stop->dropoff_allowed
                        )
                            ? (bool) $stop->dropoff_allowed
                            : true,
                ];
            })
            ->values();
    }

    private function findStop(
        $stops,
        string $search
    ): ?array {
        foreach ($stops as $stop) {
            if (
                $this->same(
                    $stop['name'],
                    $search
                )
            ) {
                return $stop;
            }
        }

        foreach ($stops as $stop) {
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

    private function same(
        string $first,
        string $second
    ): bool {
        return $this->normalize($first) ===
            $this->normalize($second);
    }

    private function similar(
        string $first,
        string $second
    ): bool {
        $a = $this->normalize($first);
        $b = $this->normalize($second);

        if ($a === '' || $b === '') {
            return false;
        }

        if (
            mb_strlen($a) < 4 ||
            mb_strlen($b) < 4
        ) {
            return false;
        }

        return str_contains($a, $b) ||
            str_contains($b, $a);
    }

    private function normalize(
        string $value
    ): string {
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

    private function contactNumbers(
        $service
    ): array {
        return collect([
            $service->contact_number_1 ?? null,
            $service->contact_number_2 ?? null,
            $service->contact_number_3 ?? null,
        ])
            ->map(
                fn ($number) =>
                    trim((string) $number)
            )
            ->filter(
                fn ($number) =>
                    $number !== ''
            )
            ->unique()
            ->values()
            ->all();
    }

    private function busName(
        $service
    ): string {
        $busName = trim(
            (string) (
                $service->bus_name ?? ''
            )
        );

        if ($busName !== '') {
            return $busName;
        }

        $serviceName = trim(
            (string) (
                $service->service_name ?? ''
            )
        );

        return $serviceName !== ''
            ? $serviceName
            : 'Fixed Bus Service';
    }
}