<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class OperatorRouteController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Index
    |--------------------------------------------------------------------------
    |
    | Operator sees only their configured Bus Route Services.
    |
    | Master Routes themselves are managed only by System Admin.
    |
    */

    public function index(Request $request)
    {
        $operatorId = $this->operatorId($request);

        $services = DB::table('fixed_services as fs')
            ->leftJoin(
                'routes as r',
                'r.id',
                '=',
                'fs.route_id'
            )
            ->leftJoin(
                'buses as b',
                'b.id',
                '=',
                'fs.bus_id'
            )
            ->where(
                'fs.operator_id',
                $operatorId
            )
            ->select(
                'fs.id',
                'fs.operator_id',
                'fs.route_id',
                'fs.bus_id',
                'fs.service_name',
                'fs.starting_time',
                'fs.return_time',
                'fs.is_active',
                'fs.is_published',
                'fs.created_at',
                'fs.updated_at',

                'r.route_number',
                'r.name as route_name',
                'r.origin as route_origin',
                'r.destination as route_destination',
                'r.distance_km as route_distance_km',
                'r.duration_minutes as route_duration_minutes',

                'b.bus_number as linked_bus_number',
                'b.bus_name as linked_bus_name',
                'b.bus_type as linked_bus_type'
            )
            ->orderByDesc('fs.id')
            ->get();

        foreach ($services as $service) {
            $service->starting_booking_stops =
                $this->serviceStops(
                    (int) $service->id,
                    'starting'
                );

            $service->return_booking_stops =
                $this->serviceStops(
                    (int) $service->id,
                    'return'
                );

            $service->is_complete =
                !empty($service->route_id)
                &&
                !empty($service->bus_id);
        }

        return view(
            'operator.routes.index',
            compact('services')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Create
    |--------------------------------------------------------------------------
    */

    public function create(Request $request)
    {
        $operatorId =
            $this->operatorId($request);

        /*
         * System Admin managed Master Routes.
         */
        $routes = DB::table('routes')
            ->where(
                'is_active',
                true
            )
            ->orderBy('route_number')
            ->orderBy('origin')
            ->get();

        /*
         * Logged-in operator's active buses only.
         */
        $buses = DB::table('buses')
            ->where(
                'operator_id',
                $operatorId
            )
            ->where(
                'is_active',
                true
            )
            ->orderBy('bus_number')
            ->get();

        return view(
            'operator.routes.form',
            [
                'service' =>
                    null,

                'routes' =>
                    $routes,

                'buses' =>
                    $buses,

                'routeStops' =>
                    collect(),

                'startingBookingStops' =>
                    collect(),

                'returnBookingStops' =>
                    collect(),
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Load Selected Master Route Roadway
    |--------------------------------------------------------------------------
    */

    public function routeStops(
        Request $request,
        int $routeId
    ) {
        $this->operatorId($request);

        $route = DB::table('routes')
            ->where(
                'id',
                $routeId
            )
            ->where(
                'is_active',
                true
            )
            ->first();

        abort_unless(
            $route,
            404
        );

        $stops = DB::table('route_stops')
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
                            (int) $stop->id,

                        'route_id' =>
                            (int) $stop->route_id,

                        'name' =>
                            $stop->name,

                        'stop_order' =>
                            (int) $stop->stop_order,

                        'fare_stage_no' =>
                            $stop->fare_stage_no !== null
                                ? (int) $stop->fare_stage_no
                                : null,

                        'distance_from_origin_km' =>
                            (float) (
                                $stop->distance_from_origin_km
                                ??
                                $stop->distance_from_origin
                                ??
                                0
                            ),

                        'latitude' =>
                            $stop->latitude ?? null,

                        'longitude' =>
                            $stop->longitude ?? null,
                    ];
                }
            )
            ->values();

        return response()->json([
            'success' => true,

            'route' => [
                'id' =>
                    $route->id,

                'route_number' =>
                    $route->route_number,

                'name' =>
                    $route->name,

                'origin' =>
                    $route->origin,

                'destination' =>
                    $route->destination,

                'distance_km' =>
                    $route->distance_km,

                'duration_minutes' =>
                    $route->duration_minutes,
            ],

            'stops' =>
                $stops,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Store Bus Route Service
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        $operatorId =
            $this->operatorId($request);

        $data =
            $this->validateService(
                $request
            );

        $this->validateMasterRoute(
            (int) $data['route_id']
        );

        $this->validateOperatorBus(
            $operatorId,
            (int) $data['bus_id']
        );

        $this->validateDuplicateService(
            $operatorId,
            (int) $data['bus_id'],
            (int) $data['route_id']
        );

        $startingStops =
            $this->prepareBookingStops(
                (int) $data['route_id'],
                $data[
                    'starting_booking_stops'
                ],
                'starting'
            );

        $returnStops =
            $this->prepareBookingStops(
                (int) $data['route_id'],
                $data[
                    'return_booking_stops'
                ],
                'return'
            );

        $serviceId =
            DB::transaction(
                function () use (
                    $operatorId,
                    $data,
                    $startingStops,
                    $returnStops,
                    $request
                ) {
                    $serviceId =
                        DB::table(
                            'fixed_services'
                        )
                            ->insertGetId([
                                'operator_id' =>
                                    $operatorId,

                                'route_id' =>
                                    $data['route_id'],

                                'bus_id' =>
                                    $data['bus_id'],

                                'service_name' =>
                                    !empty(
                                        $data[
                                            'service_name'
                                        ]
                                    )
                                        ? trim(
                                            $data[
                                                'service_name'
                                            ]
                                        )
                                        : null,

                                'starting_time' =>
                                    $this->firstServiceTime(
                                        $startingStops
                                    ),

                                'return_time' =>
                                    $this->firstServiceTime(
                                        $returnStops
                                    ),

                                'is_active' =>
                                    $request->boolean(
                                        'is_active'
                                    ),

                                'is_published' =>
                                    $request->boolean(
                                        'is_published'
                                    ),

                                'created_at' =>
                                    now(),

                                'updated_at' =>
                                    now(),
                            ]);

                    $this->saveServiceStops(
                        $serviceId,
                        'starting',
                        $startingStops
                    );

                    $this->saveServiceStops(
                        $serviceId,
                        'return',
                        $returnStops
                    );

                    return $serviceId;
                }
            );

        return redirect()
            ->route(
                'operator.routes.edit',
                $serviceId
            )
            ->with(
                'success',
                'Bus route service created successfully.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Edit
    |--------------------------------------------------------------------------
    */

    public function edit(
        Request $request,
        int $id
    ) {
        $operatorId =
            $this->operatorId($request);

        $service = DB::table(
            'fixed_services'
        )
            ->where(
                'id',
                $id
            )
            ->where(
                'operator_id',
                $operatorId
            )
            ->first();

        abort_unless(
            $service,
            404
        );

        /*
         * Legacy records without a Master Route
         * can still open safely.
         */
        $routes = DB::table('routes')
            ->where(
                'is_active',
                true
            )
            ->orderBy('route_number')
            ->orderBy('origin')
            ->get();

        $buses = DB::table('buses')
            ->where(
                'operator_id',
                $operatorId
            )
            ->where(
                'is_active',
                true
            )
            ->orderBy('bus_number')
            ->get();

        $routeStops =
            !empty($service->route_id)
                ? DB::table('route_stops')
                    ->where(
                        'route_id',
                        $service->route_id
                    )
                    ->orderBy(
                        'stop_order'
                    )
                    ->get()
                : collect();

        $startingBookingStops =
            $this->serviceStops(
                $id,
                'starting'
            );

        $returnBookingStops =
            $this->serviceStops(
                $id,
                'return'
            );

        return view(
            'operator.routes.form',
            compact(
                'service',
                'routes',
                'buses',
                'routeStops',
                'startingBookingStops',
                'returnBookingStops'
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    public function update(
        Request $request,
        int $id
    ) {
        $operatorId =
            $this->operatorId($request);

        $service = DB::table(
            'fixed_services'
        )
            ->where(
                'id',
                $id
            )
            ->where(
                'operator_id',
                $operatorId
            )
            ->first();

        abort_unless(
            $service,
            404
        );

        $data =
            $this->validateService(
                $request
            );

        $this->validateMasterRoute(
            (int) $data['route_id']
        );

        $this->validateOperatorBus(
            $operatorId,
            (int) $data['bus_id']
        );

        $this->validateDuplicateService(
            $operatorId,
            (int) $data['bus_id'],
            (int) $data['route_id'],
            $id
        );

        $startingStops =
            $this->prepareBookingStops(
                (int) $data['route_id'],
                $data[
                    'starting_booking_stops'
                ],
                'starting'
            );

        $returnStops =
            $this->prepareBookingStops(
                (int) $data['route_id'],
                $data[
                    'return_booking_stops'
                ],
                'return'
            );

        DB::transaction(
            function () use (
                $id,
                $data,
                $startingStops,
                $returnStops,
                $request
            ) {
                DB::table(
                    'fixed_services'
                )
                    ->where(
                        'id',
                        $id
                    )
                    ->update([
                        'route_id' =>
                            $data['route_id'],

                        'bus_id' =>
                            $data['bus_id'],

                        'service_name' =>
                            !empty(
                                $data[
                                    'service_name'
                                ]
                            )
                                ? trim(
                                    $data[
                                        'service_name'
                                    ]
                                )
                                : null,

                        'starting_time' =>
                            $this->firstServiceTime(
                                $startingStops
                            ),

                        'return_time' =>
                            $this->firstServiceTime(
                                $returnStops
                            ),

                        'is_active' =>
                            $request->boolean(
                                'is_active'
                            ),

                        'is_published' =>
                            $request->boolean(
                                'is_published'
                            ),

                        'updated_at' =>
                            now(),
                    ]);

                /*
                 * Rebuild only service-level
                 * booking points and times.
                 *
                 * Master Route remains untouched.
                 */
                DB::table(
                    'fixed_service_stops'
                )
                    ->where(
                        'fixed_service_id',
                        $id
                    )
                    ->delete();

                $this->saveServiceStops(
                    $id,
                    'starting',
                    $startingStops
                );

                $this->saveServiceStops(
                    $id,
                    'return',
                    $returnStops
                );
            }
        );

        return redirect()
            ->route(
                'operator.routes.edit',
                $id
            )
            ->with(
                'success',
                'Bus route service updated successfully.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Delete
    |--------------------------------------------------------------------------
    */

    public function destroy(
        Request $request,
        int $id
    ) {
        $operatorId =
            $this->operatorId($request);

        $service = DB::table(
            'fixed_services'
        )
            ->where(
                'id',
                $id
            )
            ->where(
                'operator_id',
                $operatorId
            )
            ->first();

        abort_unless(
            $service,
            404
        );

        /*
         * Do not delete a service already used by Trips.
         */
        $hasTrips =
            DB::table('trips')
                ->where(
                    'fixed_service_id',
                    $id
                )
                ->exists();

        if ($hasTrips) {
            throw ValidationException::withMessages([
                'service' =>
                    'This bus route service cannot be deleted because trips already use it. Disable the service instead.',
            ]);
        }

        DB::transaction(
            function () use ($id) {
                DB::table(
                    'fixed_service_stops'
                )
                    ->where(
                        'fixed_service_id',
                        $id
                    )
                    ->delete();

                DB::table(
                    'fixed_services'
                )
                    ->where(
                        'id',
                        $id
                    )
                    ->delete();
            }
        );

        return redirect()
            ->route(
                'operator.routes.index'
            )
            ->with(
                'success',
                'Bus route service removed successfully.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    private function validateService(
        Request $request
    ): array {
        return $request->validate([
            'route_id' => [
                'required',
                'integer',
                'exists:routes,id',
            ],

            'bus_id' => [
                'required',
                'integer',
                'exists:buses,id',
            ],

            'service_name' => [
                'nullable',
                'string',
                'max:150',
            ],

            /*
             * Starting Booking Points
             */
            'starting_booking_stops' => [
                'required',
                'array',
                'min:2',
            ],

            'starting_booking_stops.*.route_stop_id' => [
                'required',
                'integer',
                'exists:route_stops,id',
            ],

            'starting_booking_stops.*.arrival_time' => [
                'nullable',
                'date_format:H:i',
            ],

            'starting_booking_stops.*.departure_time' => [
                'nullable',
                'date_format:H:i',
            ],

            /*
             * Return Booking Points
             */
            'return_booking_stops' => [
                'required',
                'array',
                'min:2',
            ],

            'return_booking_stops.*.route_stop_id' => [
                'required',
                'integer',
                'exists:route_stops,id',
            ],

            'return_booking_stops.*.arrival_time' => [
                'nullable',
                'date_format:H:i',
            ],

            'return_booking_stops.*.departure_time' => [
                'nullable',
                'date_format:H:i',
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Master Route
    |--------------------------------------------------------------------------
    */

    private function validateMasterRoute(
        int $routeId
    ): void {
        $route = DB::table('routes')
            ->where(
                'id',
                $routeId
            )
            ->where(
                'is_active',
                true
            )
            ->first();

        if (!$route) {
            throw ValidationException::withMessages([
                'route_id' =>
                    'Selected Master Route is invalid or inactive.',
            ]);
        }

        $stopCount =
            DB::table('route_stops')
                ->where(
                    'route_id',
                    $routeId
                )
                ->count();

        if ($stopCount < 2) {
            throw ValidationException::withMessages([
                'route_id' =>
                    'Selected Master Route must contain at least two roadway stops.',
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Prepare Booking Points
    |--------------------------------------------------------------------------
    */

    private function prepareBookingStops(
        int $routeId,
        array $points,
        string $direction
    ): array {
        $roadStops =
            DB::table('route_stops')
                ->where(
                    'route_id',
                    $routeId
                )
                ->orderBy(
                    'stop_order'
                )
                ->get()
                ->keyBy('id');

        if (
            $roadStops->isEmpty()
        ) {
            throw ValidationException::withMessages([
                'route_id' =>
                    'Selected Master Route does not contain roadway stops.',
            ]);
        }

        $prepared = [];

        foreach (
            $points as $index => $point
        ) {
            $routeStopId =
                (int)
                $point['route_stop_id'];

            $routeStop =
                $roadStops->get(
                    $routeStopId
                );

            if (!$routeStop) {
                throw ValidationException::withMessages([
                    "{$direction}_booking_stops.$index.route_stop_id" =>
                        'Selected booking point does not belong to the selected Master Route.',
                ]);
            }

            $arrivalTime =
                !empty(
                    $point['arrival_time']
                )
                    ? $point['arrival_time']
                    : null;

            $departureTime =
                !empty(
                    $point['departure_time']
                )
                    ? $point['departure_time']
                    : null;

            if (
                !$arrivalTime
                &&
                !$departureTime
            ) {
                throw ValidationException::withMessages([
                    "{$direction}_booking_stops.$index.arrival_time" =>
                        'Enter an arrival time or departure time for every booking point.',
                ]);
            }

            $prepared[] = [
                'route_stop_id' =>
                    (int)
                    $routeStop->id,

                'stop_name' =>
                    $routeStop->name,

                'master_stop_order' =>
                    (int)
                    $routeStop->stop_order,

                'arrival_time' =>
                    $arrivalTime,

                'departure_time' =>
                    $departureTime,
            ];
        }

        /*
         * Duplicate booking points are not allowed.
         */
        $stopIds =
            collect($prepared)
                ->pluck(
                    'route_stop_id'
                );

        if (
            $stopIds
                ->unique()
                ->count()
            !==
            $stopIds->count()
        ) {
            throw ValidationException::withMessages([
                "{$direction}_booking_stops" =>
                    'The same booking point cannot be selected more than once.',
            ]);
        }

        $orders =
            collect($prepared)
                ->pluck(
                    'master_stop_order'
                )
                ->values()
                ->all();

        /*
         * Starting direction:
         * Master Route order must increase.
         */
        if (
            $direction === 'starting'
        ) {
            for (
                $i = 1;
                $i < count($orders);
                $i++
            ) {
                if (
                    $orders[$i]
                    <=
                    $orders[$i - 1]
                ) {
                    throw ValidationException::withMessages([
                        'starting_booking_stops' =>
                            'Starting booking points must follow the Master Route roadway order.',
                    ]);
                }
            }
        }

        /*
         * Return direction:
         * Master Route order must decrease.
         */
        if (
            $direction === 'return'
        ) {
            for (
                $i = 1;
                $i < count($orders);
                $i++
            ) {
                if (
                    $orders[$i]
                    >=
                    $orders[$i - 1]
                ) {
                    throw ValidationException::withMessages([
                        'return_booking_stops' =>
                            'Return booking points must follow the Master Route in reverse order.',
                    ]);
                }
            }
        }

        /*
         * Exact route endpoints.
         */
        $orderedRoadStops =
            $roadStops
                ->sortBy(
                    'stop_order'
                )
                ->values();

        $firstMasterStop =
            $orderedRoadStops->first();

        $lastMasterStop =
            $orderedRoadStops->last();

        if (
            $direction === 'starting'
        ) {
            if (
                (int)
                $prepared[0]['route_stop_id']
                !==
                (int)
                $firstMasterStop->id
            ) {
                throw ValidationException::withMessages([
                    'starting_booking_stops' =>
                        'Starting service must begin from the Master Route origin.',
                ]);
            }

            if (
                (int)
                $prepared[
                    count($prepared) - 1
                ]['route_stop_id']
                !==
                (int)
                $lastMasterStop->id
            ) {
                throw ValidationException::withMessages([
                    'starting_booking_stops' =>
                        'Starting service must end at the Master Route destination.',
                ]);
            }
        }

        if (
            $direction === 'return'
        ) {
            if (
                (int)
                $prepared[0]['route_stop_id']
                !==
                (int)
                $lastMasterStop->id
            ) {
                throw ValidationException::withMessages([
                    'return_booking_stops' =>
                        'Return service must begin from the Master Route destination.',
                ]);
            }

            if (
                (int)
                $prepared[
                    count($prepared) - 1
                ]['route_stop_id']
                !==
                (int)
                $firstMasterStop->id
            ) {
                throw ValidationException::withMessages([
                    'return_booking_stops' =>
                        'Return service must end at the Master Route origin.',
                ]);
            }
        }

        return $prepared;
    }

    /*
    |--------------------------------------------------------------------------
    | Save Service Stops
    |--------------------------------------------------------------------------
    */

    private function saveServiceStops(
        int $serviceId,
        string $direction,
        array $stops
    ): void {
        foreach (
            $stops as $index => $stop
        ) {
            $row = [
                'fixed_service_id' =>
                    $serviceId,

                'route_stop_id' =>
                    $stop['route_stop_id'],

                'direction' =>
                    $direction,

                'stop_order' =>
                    $index + 1,

                'arrival_time' =>
                    $stop['arrival_time'],

                'departure_time' =>
                    $stop['departure_time'],

                'boarding_allowed' =>
                    true,

                'dropoff_allowed' =>
                    true,

                'created_at' =>
                    now(),

                'updated_at' =>
                    now(),
            ];

            /*
             * Temporary legacy compatibility.
             */
            if (
                Schema::hasColumn(
                    'fixed_service_stops',
                    'stop_name'
                )
            ) {
                $row['stop_name'] =
                    $stop['stop_name'];
            }

            DB::table(
                'fixed_service_stops'
            )->insert($row);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Get Service Stops
    |--------------------------------------------------------------------------
    */

    private function serviceStops(
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
                'fss.id',
                'fss.fixed_service_id',
                'fss.route_stop_id',
                'fss.direction',
                'fss.stop_order',
                'fss.arrival_time',
                'fss.departure_time',
                'fss.boarding_allowed',
                'fss.dropoff_allowed',

                'rs.name as stop_name',
                'rs.stop_order as master_stop_order',
                'rs.fare_stage_no',
                'rs.distance_from_origin_km'
            )
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Operator Bus
    |--------------------------------------------------------------------------
    */

    private function validateOperatorBus(
        int $operatorId,
        int $busId
    ): void {
        $exists =
            DB::table('buses')
                ->where(
                    'id',
                    $busId
                )
                ->where(
                    'operator_id',
                    $operatorId
                )
                ->where(
                    'is_active',
                    true
                )
                ->exists();

        if (!$exists) {
            throw ValidationException::withMessages([
                'bus_id' =>
                    'Selected bus does not belong to your operator account or is inactive.',
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Prevent Duplicate Bus + Route Service
    |--------------------------------------------------------------------------
    */

    private function validateDuplicateService(
        int $operatorId,
        int $busId,
        int $routeId,
        ?int $ignoreServiceId = null
    ): void {
        $query =
            DB::table(
                'fixed_services'
            )
                ->where(
                    'operator_id',
                    $operatorId
                )
                ->where(
                    'bus_id',
                    $busId
                )
                ->where(
                    'route_id',
                    $routeId
                );

        if (
            $ignoreServiceId !== null
        ) {
            $query->where(
                'id',
                '!=',
                $ignoreServiceId
            );
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'bus_id' =>
                    'This bus is already linked to the selected Master Route.',
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | First Service Time
    |--------------------------------------------------------------------------
    */

    private function firstServiceTime(
        array $stops
    ): ?string {
        if (
            empty($stops)
        ) {
            return null;
        }

        return
            $stops[0]['departure_time']
            ??
            $stops[0]['arrival_time']
            ??
            null;
    }

    /*
    |--------------------------------------------------------------------------
    | Current Operator
    |--------------------------------------------------------------------------
    */

    private function operatorId(
        Request $request
    ): int {
        $user =
            $request->user();

        if ($user) {
            $operatorId =
                DB::table(
                    'operators'
                )
                    ->where(
                        'user_id',
                        $user->id
                    )
                    ->value('id');

            if ($operatorId) {
                return
                    (int) $operatorId;
            }

            abort(
                403,
                'No bus operator account is linked to this user.'
            );
        }

        /*
         * Session fallback.
         */
        if (
            session()->has(
                'operator_id'
            )
        ) {
            $operatorId =
                (int)
                session(
                    'operator_id'
                );

            $exists =
                DB::table(
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