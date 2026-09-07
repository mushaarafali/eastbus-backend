<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AdminFixedScheduleController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Index
    |--------------------------------------------------------------------------
    */

    public function index()
    {
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
            ->leftJoin(
                'operators as o',
                'o.id',
                '=',
                'fs.operator_id'
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

                'b.bus_number as linked_bus_number',
                'b.bus_name as linked_bus_name',
                'b.bus_type as linked_bus_type',

                'o.company_name as operator_name'
            )
            ->orderByDesc('fs.id')
            ->get();

        foreach ($services as $service) {
            $service->starting_stops =
                $this->getServiceStops(
                    (int) $service->id,
                    'starting'
                );

            $service->return_stops =
                $this->getServiceStops(
                    (int) $service->id,
                    'return'
                );
        }

        return view(
            'admin.fixed_schedules.index',
            compact('services')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Create
    |--------------------------------------------------------------------------
    */

    public function create()
    {
        $routes = DB::table('routes')
            ->where('is_active', true)
            ->orderBy('route_number')
            ->orderBy('origin')
            ->get();

        $operators = DB::table('operators')
            ->where('status', 'active')
            ->orderBy('company_name')
            ->get();

        $buses = DB::table('buses')
            ->where('is_active', true)
            ->orderBy('bus_number')
            ->get();

        return view(
            'admin.fixed_schedules.form',
            [
                'service' => null,
                'routes' => $routes,
                'operators' => $operators,
                'buses' => $buses,
                'routeStops' => collect(),
                'startingStops' => collect(),
                'returnStops' => collect(),
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Load Master Route Roadway
    |--------------------------------------------------------------------------
    |
    | Example:
    |
    | Route 76
    | Akkaraipattu
    | Addalaichenai
    | Kalmunai
    | Batticaloa
    | ...
    | Trincomalee
    |
    */

    public function routeStops(int $routeId)
    {
        $route = DB::table('routes')
            ->where('id', $routeId)
            ->where('is_active', true)
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
            ->orderBy('stop_order')
            ->get()
            ->map(function ($stop) {
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
            })
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
    | Store
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        $data =
            $this->validateData(
                $request
            );

        $this->validateMasterRoute(
            (int) $data['route_id']
        );

        $this->validateBusOperator(
            (int) $data['bus_id'],
            (int) $data['operator_id']
        );

        $this->validateDuplicateService(
            (int) $data['operator_id'],
            (int) $data['bus_id'],
            (int) $data['route_id']
        );

        $startingStops =
            $this->prepareStops(
                (int) $data['route_id'],
                $data['starting_stops'],
                'starting'
            );

        $returnStops =
            $this->prepareStops(
                (int) $data['route_id'],
                $data['return_stops'],
                'return'
            );

        $serviceId =
            DB::transaction(
                function () use (
                    $data,
                    $request,
                    $startingStops,
                    $returnStops
                ) {
                    $serviceId =
                        DB::table(
                            'fixed_services'
                        )->insertGetId([
                            'operator_id' =>
                                $data['operator_id'],

                            'route_id' =>
                                $data['route_id'],

                            'bus_id' =>
                                $data['bus_id'],

                            'service_name' =>
                                !empty($data['service_name'])
                                    ? trim(
                                        $data['service_name']
                                    )
                                    : null,

                            'starting_time' =>
                                $this->getFirstTime(
                                    $startingStops
                                ),

                            'return_time' =>
                                $this->getFirstTime(
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

                    $this->saveStops(
                        $serviceId,
                        'starting',
                        $startingStops
                    );

                    $this->saveStops(
                        $serviceId,
                        'return',
                        $returnStops
                    );

                    return $serviceId;
                }
            );

        return redirect()
            ->route(
                'admin.fixed-schedules.edit',
                $serviceId
            )
            ->with(
                'success',
                'Daily bus service created successfully.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Edit
    |--------------------------------------------------------------------------
    */

    public function edit(int $id)
    {
        $service = DB::table(
            'fixed_services'
        )
            ->where(
                'id',
                $id
            )
            ->first();

        abort_unless(
            $service,
            404
        );

        $routes = DB::table('routes')
            ->where('is_active', true)
            ->orderBy('route_number')
            ->orderBy('origin')
            ->get();

        $operators = DB::table('operators')
            ->where('status', 'active')
            ->orderBy('company_name')
            ->get();

        $buses = DB::table('buses')
            ->where('is_active', true)
            ->orderBy('bus_number')
            ->get();

        $routeStops =
            !empty($service->route_id)
                ? DB::table('route_stops')
                    ->where(
                        'route_id',
                        $service->route_id
                    )
                    ->orderBy('stop_order')
                    ->get()
                : collect();

        $startingStops =
            $this->getServiceStops(
                $id,
                'starting'
            );

        $returnStops =
            $this->getServiceStops(
                $id,
                'return'
            );

        return view(
            'admin.fixed_schedules.form',
            compact(
                'service',
                'routes',
                'operators',
                'buses',
                'routeStops',
                'startingStops',
                'returnStops'
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
        $service = DB::table(
            'fixed_services'
        )
            ->where(
                'id',
                $id
            )
            ->first();

        abort_unless(
            $service,
            404
        );

        $data =
            $this->validateData(
                $request
            );

        $this->validateMasterRoute(
            (int) $data['route_id']
        );

        $this->validateBusOperator(
            (int) $data['bus_id'],
            (int) $data['operator_id']
        );

        $this->validateDuplicateService(
            (int) $data['operator_id'],
            (int) $data['bus_id'],
            (int) $data['route_id'],
            $id
        );

        $startingStops =
            $this->prepareStops(
                (int) $data['route_id'],
                $data['starting_stops'],
                'starting'
            );

        $returnStops =
            $this->prepareStops(
                (int) $data['route_id'],
                $data['return_stops'],
                'return'
            );

        DB::transaction(
            function () use (
                $id,
                $data,
                $request,
                $startingStops,
                $returnStops
            ) {
                DB::table(
                    'fixed_services'
                )
                    ->where(
                        'id',
                        $id
                    )
                    ->update([
                        'operator_id' =>
                            $data['operator_id'],

                        'route_id' =>
                            $data['route_id'],

                        'bus_id' =>
                            $data['bus_id'],

                        'service_name' =>
                            !empty($data['service_name'])
                                ? trim(
                                    $data['service_name']
                                )
                                : null,

                        'starting_time' =>
                            $this->getFirstTime(
                                $startingStops
                            ),

                        'return_time' =>
                            $this->getFirstTime(
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
                 * Rebuild service booking points.
                 *
                 * Master Route remains unchanged.
                 */
                DB::table(
                    'fixed_service_stops'
                )
                    ->where(
                        'fixed_service_id',
                        $id
                    )
                    ->delete();

                $this->saveStops(
                    $id,
                    'starting',
                    $startingStops
                );

                $this->saveStops(
                    $id,
                    'return',
                    $returnStops
                );
            }
        );

        return redirect()
            ->route(
                'admin.fixed-schedules.edit',
                $id
            )
            ->with(
                'success',
                'Daily bus service updated successfully.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Toggle Publish
    |--------------------------------------------------------------------------
    */

    public function togglePublish(int $id)
    {
        $service =
            DB::table(
                'fixed_services'
            )
                ->where(
                    'id',
                    $id
                )
                ->first();

        abort_unless(
            $service,
            404
        );

        if (
            empty($service->operator_id)
            ||
            empty($service->route_id)
            ||
            empty($service->bus_id)
        ) {
            throw ValidationException::withMessages([
                'service' =>
                    'Link this service to an operator, bus and Master Route before publishing.',
            ]);
        }

        DB::table(
            'fixed_services'
        )
            ->where(
                'id',
                $id
            )
            ->update([
                'is_published' =>
                    !(bool)
                    $service->is_published,

                'updated_at' =>
                    now(),
            ]);

        return back()->with(
            'success',
            'Publish status updated.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Toggle Active
    |--------------------------------------------------------------------------
    */

    public function toggleActive(int $id)
    {
        $service =
            DB::table(
                'fixed_services'
            )
                ->where(
                    'id',
                    $id
                )
                ->first();

        abort_unless(
            $service,
            404
        );

        DB::table(
            'fixed_services'
        )
            ->where(
                'id',
                $id
            )
            ->update([
                'is_active' =>
                    !(bool)
                    $service->is_active,

                'updated_at' =>
                    now(),
            ]);

        return back()->with(
            'success',
            'Active status updated.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Delete
    |--------------------------------------------------------------------------
    */

    public function destroy(int $id)
    {
        $service =
            DB::table(
                'fixed_services'
            )
                ->where(
                    'id',
                    $id
                )
                ->first();

        abort_unless(
            $service,
            404
        );

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
                    'This service cannot be deleted because trips already use it. Disable the service instead.',
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
                'admin.fixed-schedules.index'
            )
            ->with(
                'success',
                'Daily bus service deleted successfully.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Form
    |--------------------------------------------------------------------------
    */

    private function validateData(
        Request $request
    ): array {
        return $request->validate([
            'operator_id' => [
                'required',
                'integer',
                'exists:operators,id',
            ],

            'bus_id' => [
                'required',
                'integer',
                'exists:buses,id',
            ],

            'route_id' => [
                'required',
                'integer',
                'exists:routes,id',
            ],

            'service_name' => [
                'nullable',
                'string',
                'max:150',
            ],

            /*
             * Starting
             */
            'starting_stops' => [
                'required',
                'array',
                'min:2',
            ],

            'starting_stops.*.route_stop_id' => [
                'required',
                'integer',
                'exists:route_stops,id',
            ],

            'starting_stops.*.arrival_time' => [
                'nullable',
                'date_format:H:i',
            ],

            'starting_stops.*.departure_time' => [
                'nullable',
                'date_format:H:i',
            ],

            'starting_stops.*.boarding_allowed' => [
                'nullable',
                'boolean',
            ],

            'starting_stops.*.dropoff_allowed' => [
                'nullable',
                'boolean',
            ],

            /*
             * Return
             */
            'return_stops' => [
                'required',
                'array',
                'min:2',
            ],

            'return_stops.*.route_stop_id' => [
                'required',
                'integer',
                'exists:route_stops,id',
            ],

            'return_stops.*.arrival_time' => [
                'nullable',
                'date_format:H:i',
            ],

            'return_stops.*.departure_time' => [
                'nullable',
                'date_format:H:i',
            ],

            'return_stops.*.boarding_allowed' => [
                'nullable',
                'boolean',
            ],

            'return_stops.*.dropoff_allowed' => [
                'nullable',
                'boolean',
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
        $route =
            DB::table('routes')
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
    | Prepare / Validate Stops
    |--------------------------------------------------------------------------
    */

    private function prepareStops(
        int $routeId,
        array $submittedStops,
        string $direction
    ): array {
        $routeStops =
            DB::table('route_stops')
                ->where(
                    'route_id',
                    $routeId
                )
                ->orderBy('stop_order')
                ->get()
                ->keyBy('id');

        if ($routeStops->isEmpty()) {
            throw ValidationException::withMessages([
                'route_id' =>
                    'Selected Master Route does not contain roadway stops.',
            ]);
        }

        $prepared = [];

        foreach (
            $submittedStops
            as
            $index => $stop
        ) {
            $routeStopId =
                (int)
                $stop['route_stop_id'];

            $routeStop =
                $routeStops->get(
                    $routeStopId
                );

            if (!$routeStop) {
                throw ValidationException::withMessages([
                    "{$direction}_stops.$index.route_stop_id" =>
                        'Selected booking point does not belong to the selected Master Route.',
                ]);
            }

            $arrivalTime =
                !empty(
                    $stop['arrival_time']
                )
                    ? $stop['arrival_time']
                    : null;

            $departureTime =
                !empty(
                    $stop['departure_time']
                )
                    ? $stop['departure_time']
                    : null;

            if (
                !$arrivalTime
                &&
                !$departureTime
            ) {
                throw ValidationException::withMessages([
                    "{$direction}_stops.$index.arrival_time" =>
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

                'boarding_allowed' =>
                    !empty(
                        $stop[
                            'boarding_allowed'
                        ]
                    ),

                'dropoff_allowed' =>
                    !empty(
                        $stop[
                            'dropoff_allowed'
                        ]
                    ),
            ];
        }

        /*
         * Duplicate stops not allowed.
         */
        $ids =
            collect(
                $prepared
            )->pluck(
                'route_stop_id'
            );

        if (
            $ids->unique()->count()
            !==
            $ids->count()
        ) {
            throw ValidationException::withMessages([
                "{$direction}_stops" =>
                    'The same booking point cannot be selected more than once.',
            ]);
        }

        $orders =
            collect(
                $prepared
            )
                ->pluck(
                    'master_stop_order'
                )
                ->values()
                ->all();

        /*
         * Starting direction:
         * route order must increase.
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
                        'starting_stops' =>
                            'Starting booking points must follow the Master Route roadway order.',
                    ]);
                }
            }
        }

        /*
         * Return direction:
         * route order must decrease.
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
                        'return_stops' =>
                            'Return booking points must follow the Master Route in reverse order.',
                    ]);
                }
            }
        }

        return $prepared;
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Bus Operator
    |--------------------------------------------------------------------------
    */

    private function validateBusOperator(
        int $busId,
        int $operatorId
    ): void {
        $bus =
            DB::table('buses')
                ->where(
                    'id',
                    $busId
                )
                ->where(
                    'is_active',
                    true
                )
                ->first();

        if (!$bus) {
            throw ValidationException::withMessages([
                'bus_id' =>
                    'Selected bus does not exist or is inactive.',
            ]);
        }

        if (
            (int)
            $bus->operator_id
            !==
            $operatorId
        ) {
            throw ValidationException::withMessages([
                'bus_id' =>
                    'Selected bus does not belong to the selected operator.',
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Prevent Duplicate Service
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
            $ignoreServiceId
            !==
            null
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
    | Save Service Stops
    |--------------------------------------------------------------------------
    */

    private function saveStops(
        int $serviceId,
        string $direction,
        array $stops
    ): void {
        foreach (
            $stops
            as
            $index => $stop
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
                    $stop['boarding_allowed'],

                'dropoff_allowed' =>
                    $stop['dropoff_allowed'],

                'created_at' =>
                    now(),

                'updated_at' =>
                    now(),
            ];

            /*
             * Temporary compatibility.
             *
             * Stop name always comes from
             * Master Route roadway.
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
            )->insert(
                $row
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Get Existing Service Stops
    |--------------------------------------------------------------------------
    */

    private function getServiceStops(
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

                'rs.name as route_stop_name',
                'rs.name as stop_name',

                'rs.stop_order as master_stop_order',
                'rs.fare_stage_no',
                'rs.distance_from_origin_km'
            )
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | First Service Time
    |--------------------------------------------------------------------------
    */

    private function getFirstTime(
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
}