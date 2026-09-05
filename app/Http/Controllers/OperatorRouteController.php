<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class OperatorRouteController extends Controller
{
    public function index(Request $request)
    {
        $operatorId = $this->operatorId($request);

        $routes = DB::table('routes')
            ->where('operator_id', $operatorId)
            ->orderByDesc('id')
            ->get();

        foreach ($routes as $route) {
            $route->stops = DB::table('route_stops')
                ->where('route_id', $route->id)
                ->orderBy('stop_order')
                ->get();

            $route->starting_booking_stops = DB::table('route_booking_stops')
                ->join(
                    'route_stops',
                    'route_stops.id',
                    '=',
                    'route_booking_stops.route_stop_id'
                )
                ->where(
                    'route_booking_stops.route_id',
                    $route->id
                )
                ->where(
                    'route_booking_stops.direction',
                    'starting'
                )
                ->orderBy(
                    'route_booking_stops.stop_order'
                )
                ->select(
                    'route_booking_stops.*',
                    'route_stops.name as stop_name'
                )
                ->get();

            $route->return_booking_stops = DB::table('route_booking_stops')
                ->join(
                    'route_stops',
                    'route_stops.id',
                    '=',
                    'route_booking_stops.route_stop_id'
                )
                ->where(
                    'route_booking_stops.route_id',
                    $route->id
                )
                ->where(
                    'route_booking_stops.direction',
                    'return'
                )
                ->orderBy(
                    'route_booking_stops.stop_order'
                )
                ->select(
                    'route_booking_stops.*',
                    'route_stops.name as stop_name'
                )
                ->get();
        }

        return view(
            'operator.routes.index',
            compact('routes')
        );
    }

    public function create()
    {
        return view('operator.routes.form', [
            'route' => null,
            'stops' => collect(),
            'startingBookingStops' => collect(),
            'returnBookingStops' => collect(),
        ]);
    }

    public function store(Request $request)
    {
        $operatorId = $this->operatorId($request);

        $data = $this->validateRoute($request);

        $routeId = DB::transaction(function () use (
            $operatorId,
            $data
        ) {
            $firstStop = $data['stops'][0];

            $lastStop = $data['stops'][
                count($data['stops']) - 1
            ];

            $routeRow = [
                'operator_id' => $operatorId,
                'name' =>
                    $firstStop['name'] .
                    ' - ' .
                    $lastStop['name'],
                'origin' => $firstStop['name'],
                'destination' => $lastStop['name'],
                'distance_km' =>
                    $lastStop['distance_from_origin'],
                'duration_minutes' =>
                    $data['duration_minutes'] ?? null,
                'base_fare' => 0,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (
                Schema::hasColumn(
                    'routes',
                    'route_number'
                )
            ) {
                $routeRow['route_number'] =
                    $data['route_number'] ?? null;
            }

            $routeId = DB::table('routes')
                ->insertGetId($routeRow);

            $roadStopIds = $this->saveRoadStops(
                $routeId,
                $data['stops']
            );

            $this->saveBookingStops(
                $routeId,
                'starting',
                $data['starting_booking_stops'],
                $data['stops'],
                $roadStopIds
            );

            $this->saveBookingStops(
                $routeId,
                'return',
                $data['return_booking_stops'],
                $data['stops'],
                $roadStopIds
            );

            return $routeId;
        });

        return redirect()
            ->route(
                'operator.routes.edit',
                $routeId
            )
            ->with(
                'success',
                'Route created successfully.'
            );
    }

    public function edit(
        Request $request,
        int $id
    ) {
        $operatorId = $this->operatorId(
            $request
        );

        $route = DB::table('routes')
            ->where('id', $id)
            ->where(
                'operator_id',
                $operatorId
            )
            ->first();

        abort_unless($route, 404);

        $stops = DB::table('route_stops')
            ->where(
                'route_id',
                $route->id
            )
            ->orderBy('stop_order')
            ->get();

        $startingBookingStops =
            $this->bookingStopsForEdit(
                $route->id,
                'starting'
            );

        $returnBookingStops =
            $this->bookingStopsForEdit(
                $route->id,
                'return'
            );

        return view(
            'operator.routes.form',
            compact(
                'route',
                'stops',
                'startingBookingStops',
                'returnBookingStops'
            )
        );
    }

    public function update(
        Request $request,
        int $id
    ) {
        $operatorId = $this->operatorId(
            $request
        );

        $route = DB::table('routes')
            ->where('id', $id)
            ->where(
                'operator_id',
                $operatorId
            )
            ->first();

        abort_unless($route, 404);

        $data = $this->validateRoute(
            $request
        );

        DB::transaction(function () use (
            $id,
            $data
        ) {
            $firstStop = $data['stops'][0];

            $lastStop = $data['stops'][
                count($data['stops']) - 1
            ];

            $routeRow = [
                'name' =>
                    $firstStop['name'] .
                    ' - ' .
                    $lastStop['name'],
                'origin' => $firstStop['name'],
                'destination' => $lastStop['name'],
                'distance_km' =>
                    $lastStop['distance_from_origin'],
                'duration_minutes' =>
                    $data['duration_minutes'] ?? null,
                'base_fare' => 0,
                'updated_at' => now(),
            ];

            if (
                Schema::hasColumn(
                    'routes',
                    'route_number'
                )
            ) {
                $routeRow['route_number'] =
                    $data['route_number'] ?? null;
            }

            DB::table('routes')
                ->where('id', $id)
                ->update($routeRow);

            /*
             * Delete booking points first because
             * they reference route_stops.
             */
            DB::table('route_booking_stops')
                ->where('route_id', $id)
                ->delete();

            DB::table('route_stops')
                ->where('route_id', $id)
                ->delete();

            $roadStopIds = $this->saveRoadStops(
                $id,
                $data['stops']
            );

            $this->saveBookingStops(
                $id,
                'starting',
                $data['starting_booking_stops'],
                $data['stops'],
                $roadStopIds
            );

            $this->saveBookingStops(
                $id,
                'return',
                $data['return_booking_stops'],
                $data['stops'],
                $roadStopIds
            );
        });

        return back()->with(
            'success',
            'Route updated successfully.'
        );
    }

    public function destroy(
        Request $request,
        int $id
    ) {
        $operatorId = $this->operatorId(
            $request
        );

        $route = DB::table('routes')
            ->where('id', $id)
            ->where(
                'operator_id',
                $operatorId
            )
            ->first();

        abort_unless($route, 404);

        $hasTrips = DB::table('trips')
            ->where('route_id', $id)
            ->exists();

        if ($hasTrips) {
            throw ValidationException::withMessages([
                'route' =>
                    'This route cannot be deleted because trips already use it.',
            ]);
        }

        DB::transaction(function () use ($id) {
            DB::table('route_booking_stops')
                ->where('route_id', $id)
                ->delete();

            DB::table('route_stops')
                ->where('route_id', $id)
                ->delete();

            DB::table('routes')
                ->where('id', $id)
                ->delete();
        });

        return redirect()
            ->route('operator.routes.index')
            ->with(
                'success',
                'Route deleted successfully.'
            );
    }

    private function validateRoute(
        Request $request
    ): array {
        $data = $request->validate([
            'route_number' => [
                'nullable',
                'string',
                'max:50',
            ],

            'duration_minutes' => [
                'nullable',
                'integer',
                'min:1',
            ],

            /*
            |--------------------------------------------------------------------------
            | Full Road Way
            |--------------------------------------------------------------------------
            */

            'stops' => [
                'required',
                'array',
                'min:2',
            ],

            'stops.*.name' => [
                'required',
                'string',
                'max:150',
            ],

            'stops.*.fare_stage_no' => [
                'required',
                'integer',
                'min:1',
                'max:350',
            ],

            'stops.*.distance_from_origin' => [
                'required',
                'numeric',
                'min:0',
            ],

            /*
            |--------------------------------------------------------------------------
            | Starting Booking Points
            |--------------------------------------------------------------------------
            */

            'starting_booking_stops' => [
                'required',
                'array',
                'min:2',
            ],

            'starting_booking_stops.*.road_stop_index' => [
                'required',
                'integer',
                'min:0',
            ],

            'starting_booking_stops.*.schedule_time' => [
                'required',
                'date_format:H:i',
            ],

            /*
            |--------------------------------------------------------------------------
            | Return Booking Points
            |--------------------------------------------------------------------------
            */

            'return_booking_stops' => [
                'required',
                'array',
                'min:2',
            ],

            'return_booking_stops.*.road_stop_index' => [
                'required',
                'integer',
                'min:0',
            ],

            'return_booking_stops.*.schedule_time' => [
                'required',
                'date_format:H:i',
            ],
        ]);

        $stops = collect(
            $data['stops']
        )
            ->map(function ($stop) {
                return [
                    'name' => trim(
                        (string) $stop['name']
                    ),

                    'fare_stage_no' =>
                        (int) $stop[
                            'fare_stage_no'
                        ],

                    'distance_from_origin' =>
                        (float) $stop[
                            'distance_from_origin'
                        ],
                ];
            })
            ->values();

        /*
        |--------------------------------------------------------------------------
        | First Road Way Stop
        |--------------------------------------------------------------------------
        */

        if (
            (float) $stops->first()[
                'distance_from_origin'
            ] !== 0.0
        ) {
            throw ValidationException::withMessages([
                'stops.0.distance_from_origin' =>
                    'The first road-way stop distance must be 0 km.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Road Way Distance Order
        |--------------------------------------------------------------------------
        */

        $previousDistance = -1;

        foreach (
            $stops as $index => $stop
        ) {
            if (
                $stop['distance_from_origin'] <=
                $previousDistance
            ) {
                throw ValidationException::withMessages([
                    "stops.$index.distance_from_origin" =>
                        'Stop distances must increase in road-way order.',
                ]);
            }

            $previousDistance =
                $stop['distance_from_origin'];

            $stageExists = DB::table(
                'stage_fares'
            )
                ->where(
                    'stage_no',
                    $stop['fare_stage_no']
                )
                ->exists();

            if (!$stageExists) {
                throw ValidationException::withMessages([
                    "stops.$index.fare_stage_no" =>
                        'The selected NTC Fare Stage No does not exist.',
                ]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Duplicate Road Way Stops
        |--------------------------------------------------------------------------
        */

        $names = $stops
            ->pluck('name')
            ->map(
                fn ($name) =>
                    mb_strtolower(
                        trim($name)
                    )
            );

        if (
            $names->unique()->count() !==
            $names->count()
        ) {
            throw ValidationException::withMessages([
                'stops' =>
                    'The same road-way stop cannot be added more than once.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Duplicate Fare Stage Numbers
        |--------------------------------------------------------------------------
        */

        $fareStages = $stops->pluck(
            'fare_stage_no'
        );

        if (
            $fareStages->unique()->count() !==
            $fareStages->count()
        ) {
            throw ValidationException::withMessages([
                'stops' =>
                    'The same Fare Stage No cannot be assigned to more than one road-way stop.',
            ]);
        }

        $starting = $this->normalizeBookingPoints(
            $data['starting_booking_stops']
        );

        $return = $this->normalizeBookingPoints(
            $data['return_booking_stops']
        );

        /*
        |--------------------------------------------------------------------------
        | Validate Starting Direction
        |--------------------------------------------------------------------------
        */

        $this->validateBookingPoints(
            $starting,
            $stops->all(),
            'starting'
        );

        /*
        |--------------------------------------------------------------------------
        | Validate Return Direction
        |--------------------------------------------------------------------------
        */

        $this->validateBookingPoints(
            $return,
            $stops->all(),
            'return'
        );

        $data['stops'] =
            $stops->all();

        $data['starting_booking_stops'] =
            $starting;

        $data['return_booking_stops'] =
            $return;

        return $data;
    }

    private function normalizeBookingPoints(
        array $points
    ): array {
        return collect($points)
            ->map(function ($point) {
                return [
                    'road_stop_index' =>
                        (int) $point[
                            'road_stop_index'
                        ],

                    'schedule_time' =>
                        trim(
                            (string) $point[
                                'schedule_time'
                            ]
                        ),
                ];
            })
            ->values()
            ->all();
    }

    private function validateBookingPoints(
        array $points,
        array $roadStops,
        string $direction
    ): void {
        $maximumIndex =
            count($roadStops) - 1;

        $usedIndexes = [];

        $previousIndex =
            $direction === 'starting'
                ? -1
                : count($roadStops);

        foreach (
            $points as $index => $point
        ) {
            $roadIndex =
                $point['road_stop_index'];

            if (
                $roadIndex < 0 ||
                $roadIndex > $maximumIndex
            ) {
                throw ValidationException::withMessages([
                    "{$direction}_booking_stops.$index.road_stop_index" =>
                        'The selected booking point is not a valid road-way stop.',
                ]);
            }

            if (
                in_array(
                    $roadIndex,
                    $usedIndexes,
                    true
                )
            ) {
                throw ValidationException::withMessages([
                    "{$direction}_booking_stops.$index.road_stop_index" =>
                        'The same booking stop cannot be selected more than once.',
                ]);
            }

            /*
             * Starting direction:
             * Road stop indexes must increase.
             *
             * Return direction:
             * Road stop indexes must decrease.
             */
            if (
                $direction === 'starting' &&
                $roadIndex <= $previousIndex
            ) {
                throw ValidationException::withMessages([
                    "{$direction}_booking_stops.$index.road_stop_index" =>
                        'Starting booking stops must follow the road-way order.',
                ]);
            }

            if (
                $direction === 'return' &&
                $roadIndex >= $previousIndex
            ) {
                throw ValidationException::withMessages([
                    "{$direction}_booking_stops.$index.road_stop_index" =>
                        'Return booking stops must follow the reverse road-way order.',
                ]);
            }

            $usedIndexes[] =
                $roadIndex;

            $previousIndex =
                $roadIndex;
        }

        /*
         * Starting must begin from route origin
         * and end at route destination.
         */
        if ($direction === 'starting') {
            if (
                $points[0]['road_stop_index'] !== 0
            ) {
                throw ValidationException::withMessages([
                    'starting_booking_stops' =>
                        'Starting schedule must begin at the route origin.',
                ]);
            }

            if (
                $points[
                    count($points) - 1
                ]['road_stop_index'] !==
                $maximumIndex
            ) {
                throw ValidationException::withMessages([
                    'starting_booking_stops' =>
                        'Starting schedule must end at the route destination.',
                ]);
            }
        }

        /*
         * Return must begin from destination
         * and finish at route origin.
         */
        if ($direction === 'return') {
            if (
                $points[0]['road_stop_index'] !==
                $maximumIndex
            ) {
                throw ValidationException::withMessages([
                    'return_booking_stops' =>
                        'Return schedule must begin at the route destination.',
                ]);
            }

            if (
                $points[
                    count($points) - 1
                ]['road_stop_index'] !== 0
            ) {
                throw ValidationException::withMessages([
                    'return_booking_stops' =>
                        'Return schedule must end at the route origin.',
                ]);
            }
        }
    }

    private function saveRoadStops(
        int $routeId,
        array $stops
    ): array {
        $ids = [];

        foreach (
            $stops as $index => $stop
        ) {
            $row = [
                'route_id' => $routeId,
                'name' => $stop['name'],
                'stop_order' => $index + 1,
                'fare_stage_no' =>
                    $stop['fare_stage_no'],
                'distance_from_origin' =>
                    $stop['distance_from_origin'],
                'latitude' => null,
                'longitude' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            /*
             * Existing old columns are retained
             * only for DB compatibility.
             */
            if (
                Schema::hasColumn(
                    'route_stops',
                    'booking_radius_km'
                )
            ) {
                $row['booking_radius_km'] = 0;
            }

            if (
                Schema::hasColumn(
                    'route_stops',
                    'boarding_allowed'
                )
            ) {
                $row['boarding_allowed'] = false;
            }

            if (
                Schema::hasColumn(
                    'route_stops',
                    'dropoff_allowed'
                )
            ) {
                $row['dropoff_allowed'] = false;
            }

            $ids[$index] =
                DB::table('route_stops')
                    ->insertGetId($row);
        }

        return $ids;
    }

    private function saveBookingStops(
        int $routeId,
        string $direction,
        array $bookingPoints,
        array $roadStops,
        array $roadStopIds
    ): void {
        foreach (
            $bookingPoints as $index => $point
        ) {
            $roadIndex =
                $point['road_stop_index'];

            $roadStop =
                $roadStops[$roadIndex];

            DB::table(
                'route_booking_stops'
            )->insert([
                'route_id' => $routeId,

                'route_stop_id' =>
                    $roadStopIds[$roadIndex],

                'direction' => $direction,

                'stop_order' =>
                    $index + 1,

                'schedule_time' =>
                    $point['schedule_time'],

                /*
                 * These are copied automatically
                 * from the full Road Way stop.
                 */
                'fare_stage_no' =>
                    $roadStop['fare_stage_no'],

                'distance_from_origin' =>
                    $roadStop[
                        'distance_from_origin'
                    ],

                'boarding_allowed' => true,

                'dropoff_allowed' => true,

                'is_active' => true,

                'created_at' => now(),

                'updated_at' => now(),
            ]);
        }
    }

    private function bookingStopsForEdit(
        int $routeId,
        string $direction
    ) {
        return DB::table(
            'route_booking_stops'
        )
            ->join(
                'route_stops',
                'route_stops.id',
                '=',
                'route_booking_stops.route_stop_id'
            )
            ->where(
                'route_booking_stops.route_id',
                $routeId
            )
            ->where(
                'route_booking_stops.direction',
                $direction
            )
            ->orderBy(
                'route_booking_stops.stop_order'
            )
            ->select(
                'route_booking_stops.*',
                'route_stops.name as stop_name',
                'route_stops.stop_order as road_stop_order',
                'route_stops.fare_stage_no as road_fare_stage_no',
                'route_stops.distance_from_origin as road_distance'
            )
            ->get();
    }

    private function operatorId(
        Request $request
    ): int {
        $user = $request->user();

        if ($user) {
            $operatorId = DB::table(
                'operators'
            )
                ->where(
                    'user_id',
                    $user->id
                )
                ->value('id');

            if ($operatorId) {
                return (int) $operatorId;
            }

            abort(
                403,
                'No bus operator account is linked to this user.'
            );
        }

        if (
            session()->has(
                'operator_id'
            )
        ) {
            $operatorId =
                (int) session(
                    'operator_id'
                );

            $exists = DB::table(
                'operators'
            )
                ->where(
                    'id',
                    $operatorId
                )
                ->exists();

            if ($exists) {
                return $operatorId;
            }
        }

        abort(
            401,
            'Operator authentication required.'
        );
    }
}