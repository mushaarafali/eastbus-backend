<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
                'fs.*',

                'r.route_number',
                'r.name as route_name',
                'r.origin as route_origin',
                'r.destination as route_destination',

                'b.bus_number as linked_bus_number',
                'b.bus_name as linked_bus_name',
                'b.bus_type',

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

        $buses = DB::table('buses')
            ->where('is_active', true)
            ->orderBy('bus_number')
            ->get();

        /*
         * IMPORTANT:
         * operators table has company_name,
         * not name.
         */
        $operators = DB::table('operators')
            ->where('status', 'active')
            ->orderBy('company_name')
            ->get();

        return view(
            'admin.fixed_schedules.form',
            [
                'service' => null,

                'routes' => $routes,

                'buses' => $buses,

                'operators' => $operators,

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
            ->select(
                'id',
                'route_id',
                'name',
                'stop_order',
                'fare_stage_no',
                'distance_from_origin_km',
                'latitude',
                'longitude'
            )
            ->get();

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
                            !empty(
                                $data['service_name']
                            )
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
            }
        );

        return redirect()
            ->route(
                'admin.fixed-schedules.index'
            )
            ->with(
                'success',
                'Fixed bus service created successfully.'
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

        $buses = DB::table('buses')
            ->where('is_active', true)
            ->orderBy('bus_number')
            ->get();

        /*
         * FIX:
         * orderBy('name') was causing the 500 error.
         */
        $operators = DB::table('operators')
            ->where('status', 'active')
            ->orderBy('company_name')
            ->get();

        $routeStops =
            !empty(
                $service->route_id
            )
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
                'buses',
                'operators',
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
                            !empty(
                                $data['service_name']
                            )
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
                 * Rebuild this service's
                 * booking-point timetable.
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
                'Fixed bus service updated successfully.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Toggle Publish
    |--------------------------------------------------------------------------
    */

    public function togglePublish(int $id)
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

        /*
         * Do not publish an unlinked legacy record.
         */
        if (
            empty($service->operator_id)
            ||
            empty($service->route_id)
            ||
            empty($service->bus_id)
        ) {
            throw ValidationException::withMessages([
                'service' =>
                    'This service must be linked to an operator, bus and master route before it can be published.',
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

        /*
         * Do not delete a service already
         * linked to Trip records.
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
                    'This bus service cannot be deleted because trips already use it. Disable the service instead.',
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
                'Fixed bus service deleted successfully.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Validation
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
             * Starting booking points
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
             * Return booking points
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
    | Prepare Stops
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
                ->orderBy(
                    'stop_order'
                )
                ->get()
                ->keyBy('id');

        if (
            $routeStops->isEmpty()
        ) {
            throw ValidationException::withMessages([
                'route_id' =>
                    'Selected route does not contain roadway stops.',
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

            /*
             * Booking point must belong to
             * the selected Master Route.
             */
            if (!$routeStop) {
                throw ValidationException::withMessages([
                    "{$direction}_stops.{$index}.route_stop_id" =>
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
                    "{$direction}_stops.{$index}.arrival_time" =>
                        'Enter an arrival time or departure time for every booking point.',
                ]);
            }

            $prepared[] = [
                'route_stop_id' =>
                    (int) $routeStop->id,

                /*
                 * Temporary DB compatibility only.
                 * Operator never types stop_name.
                 */
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
         * Prevent duplicate stops.
         */
        $ids =
            collect($prepared)
                ->pluck(
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
            collect($prepared)
                ->pluck(
                    'master_stop_order'
                )
                ->values()
                ->all();

        /*
         * Starting:
         *
         * Akkaraipattu
         *      ↓
         * Kalmunai
         *      ↓
         * Batticaloa
         *      ↓
         * Trincomalee
         */
        if (
            $direction
            ===
            'starting'
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
         * Return:
         *
         * Trincomalee
         *      ↓
         * Batticaloa
         *      ↓
         * Kalmunai
         *      ↓
         * Akkaraipattu
         */
        if (
            $direction
            ===
            'return'
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
        $bus = DB::table('buses')
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
            (int) $bus->operator_id
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
    | Duplicate Service Validation
    |--------------------------------------------------------------------------
    |
    | Prevent the exact same bus + route link from
    | being created twice accidentally.
    |
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

        if (
            $query->exists()
        ) {
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
            DB::table(
                'fixed_service_stops'
            )->insert([
                'fixed_service_id' =>
                    $serviceId,

                'route_stop_id' =>
                    $stop[
                        'route_stop_id'
                    ],

                'direction' =>
                    $direction,

                /*
                 * Temporary backward compatibility.
                 * Can be removed after stop_name
                 * column is retired.
                 */
                'stop_name' =>
                    $stop[
                        'stop_name'
                    ],

                'stop_order' =>
                    $index + 1,

                'arrival_time' =>
                    $stop[
                        'arrival_time'
                    ],

                'departure_time' =>
                    $stop[
                        'departure_time'
                    ],

                'boarding_allowed' =>
                    $stop[
                        'boarding_allowed'
                    ],

                'dropoff_allowed' =>
                    $stop[
                        'dropoff_allowed'
                    ],

                'created_at' =>
                    now(),

                'updated_at' =>
                    now(),
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Get Service Stops
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
            $stops[0][
                'departure_time'
            ]
            ??
            $stops[0][
                'arrival_time'
            ]
            ??
            null;
    }
}