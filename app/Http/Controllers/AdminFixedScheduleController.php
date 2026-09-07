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
            ->leftJoin('routes as r', 'r.id', '=', 'fs.route_id')
            ->leftJoin('buses as b', 'b.id', '=', 'fs.bus_id')
            ->leftJoin('operators as o', 'o.id', '=', 'fs.operator_id')
            ->select(
                'fs.*',
                'r.route_number',
                'r.name as route_name',
                'r.origin as route_origin',
                'r.destination as route_destination',
                'b.bus_number',
                'o.company_name as operator_name'
            )
            ->orderByDesc('fs.id')
            ->get();

        foreach ($services as $service) {
            $service->starting_stops = $this->getServiceStops(
                $service->id,
                'starting'
            );

            $service->return_stops = $this->getServiceStops(
                $service->id,
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
            ->get();

        $buses = DB::table('buses')
            ->orderBy('bus_number')
            ->get();

        $operators = DB::table('operators')
            ->orderBy('name')
            ->get();

        return view('admin.fixed_schedules.form', [
            'service' => null,
            'routes' => $routes,
            'buses' => $buses,
            'operators' => $operators,
            'routeStops' => collect(),
            'startingStops' => collect(),
            'returnStops' => collect(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Load Selected Route Roadway
    |--------------------------------------------------------------------------
    |
    | AJAX / Fetch endpoint.
    |
    | Example:
    | Route 76 selected
    | -> only Route 76 roadway stops are returned.
    |
    */

    public function routeStops(int $routeId)
    {
        $route = DB::table('routes')
            ->where('id', $routeId)
            ->first();

        abort_unless($route, 404);

        $stops = DB::table('route_stops')
            ->where('route_id', $routeId)
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
            'route' => [
                'id' => $route->id,
                'route_number' => $route->route_number,
                'name' => $route->name,
                'origin' => $route->origin,
                'destination' => $route->destination,
                'distance_km' => $route->distance_km,
            ],

            'stops' => $stops,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Store
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $this->validateBusOperator(
            $data['bus_id'],
            $data['operator_id']
        );

        $startingStops = $this->prepareStops(
            $data['route_id'],
            $data['starting_stops'],
            'starting'
        );

        $returnStops = $this->prepareStops(
            $data['route_id'],
            $data['return_stops'],
            'return'
        );

        DB::transaction(function () use (
            $data,
            $request,
            $startingStops,
            $returnStops
        ) {
            $serviceId = DB::table('fixed_services')
                ->insertGetId([
                    'operator_id' => $data['operator_id'],
                    'route_id' => $data['route_id'],
                    'bus_id' => $data['bus_id'],

                    'service_name' => $data['service_name'] ?? null,

                    'starting_time' => $this->getFirstTime(
                        $startingStops
                    ),

                    'return_time' => $this->getFirstTime(
                        $returnStops
                    ),

                    'is_active' => $request->boolean('is_active'),
                    'is_published' => $request->boolean(
                        'is_published'
                    ),

                    'created_at' => now(),
                    'updated_at' => now(),
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
        });

        return redirect()
            ->route('admin.fixed-schedules.index')
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
        $service = DB::table('fixed_services')
            ->where('id', $id)
            ->first();

        abort_unless($service, 404);

        $routes = DB::table('routes')
            ->where('is_active', true)
            ->orderBy('route_number')
            ->get();

        $buses = DB::table('buses')
            ->orderBy('bus_number')
            ->get();

        $operators = DB::table('operators')
            ->orderBy('name')
            ->get();

        $routeStops = DB::table('route_stops')
            ->where('route_id', $service->route_id)
            ->orderBy('stop_order')
            ->get();

        $startingStops = $this->getServiceStops(
            $id,
            'starting'
        );

        $returnStops = $this->getServiceStops(
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
        $service = DB::table('fixed_services')
            ->where('id', $id)
            ->first();

        abort_unless($service, 404);

        $data = $this->validateData($request);

        $this->validateBusOperator(
            $data['bus_id'],
            $data['operator_id']
        );

        $startingStops = $this->prepareStops(
            $data['route_id'],
            $data['starting_stops'],
            'starting'
        );

        $returnStops = $this->prepareStops(
            $data['route_id'],
            $data['return_stops'],
            'return'
        );

        DB::transaction(function () use (
            $id,
            $data,
            $request,
            $startingStops,
            $returnStops
        ) {
            DB::table('fixed_services')
                ->where('id', $id)
                ->update([
                    'operator_id' => $data['operator_id'],
                    'route_id' => $data['route_id'],
                    'bus_id' => $data['bus_id'],

                    'service_name' => $data['service_name'] ?? null,

                    'starting_time' => $this->getFirstTime(
                        $startingStops
                    ),

                    'return_time' => $this->getFirstTime(
                        $returnStops
                    ),

                    'is_active' => $request->boolean('is_active'),

                    'is_published' => $request->boolean(
                        'is_published'
                    ),

                    'updated_at' => now(),
                ]);

            DB::table('fixed_service_stops')
                ->where('fixed_service_id', $id)
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
        });

        return back()->with(
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
        $service = DB::table('fixed_services')
            ->where('id', $id)
            ->first();

        abort_unless($service, 404);

        DB::table('fixed_services')
            ->where('id', $id)
            ->update([
                'is_published' => !$service->is_published,
                'updated_at' => now(),
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
        $service = DB::table('fixed_services')
            ->where('id', $id)
            ->first();

        abort_unless($service, 404);

        DB::table('fixed_services')
            ->where('id', $id)
            ->update([
                'is_active' => !$service->is_active,
                'updated_at' => now(),
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
        DB::transaction(function () use ($id) {
            DB::table('fixed_service_stops')
                ->where('fixed_service_id', $id)
                ->delete();

            DB::table('fixed_services')
                ->where('id', $id)
                ->delete();
        });

        return redirect()
            ->route('admin.fixed-schedules.index')
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
                'min:1',
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
                'min:1',
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
    | Prepare / Validate Stops
    |--------------------------------------------------------------------------
    */

    private function prepareStops(
        int $routeId,
        array $submittedStops,
        string $direction
    ): array {
        $routeStops = DB::table('route_stops')
            ->where('route_id', $routeId)
            ->orderBy('stop_order')
            ->get()
            ->keyBy('id');

        if ($routeStops->isEmpty()) {
            throw ValidationException::withMessages([
                'route_id' => 'Selected route does not contain roadway stops.',
            ]);
        }

        $prepared = [];

        foreach ($submittedStops as $index => $stop) {
            $routeStopId = (int) $stop['route_stop_id'];

            $routeStop = $routeStops->get(
                $routeStopId
            );

            /*
             * Very important:
             * stop must belong to selected route.
             */

            if (!$routeStop) {
                throw ValidationException::withMessages([
                    "{$direction}_stops.{$index}.route_stop_id"
                        => 'Selected booking point does not belong to the selected route.',
                ]);
            }

            $arrivalTime = !empty(
                $stop['arrival_time']
            )
                ? $stop['arrival_time']
                : null;

            $departureTime = !empty(
                $stop['departure_time']
            )
                ? $stop['departure_time']
                : null;

            if (!$arrivalTime && !$departureTime) {
                throw ValidationException::withMessages([
                    "{$direction}_stops.{$index}.arrival_time"
                        => 'Enter an arrival time or departure time.',
                ]);
            }

            $prepared[] = [
                'route_stop_id' => $routeStop->id,

                /*
                 * stop_name is NOT accepted from operator.
                 * It is automatically taken from master roadway.
                 */

                'stop_name' => $routeStop->name,

                'master_stop_order' => (int) $routeStop->stop_order,

                'arrival_time' => $arrivalTime,
                'departure_time' => $departureTime,

                'boarding_allowed' => !empty(
                    $stop['boarding_allowed']
                ),

                'dropoff_allowed' => !empty(
                    $stop['dropoff_allowed']
                ),
            ];
        }

        /*
         * Prevent duplicate booking points.
         */

        $ids = collect($prepared)
            ->pluck('route_stop_id');

        if ($ids->unique()->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                "{$direction}_stops"
                    => 'The same booking point cannot be selected more than once.',
            ]);
        }

        /*
         * Starting direction:
         * roadway order must increase.
         *
         * Example:
         * Akkaraipattu -> Kalmunai -> Batticaloa
         */

        if ($direction === 'starting') {
            $orders = collect($prepared)
                ->pluck('master_stop_order')
                ->values()
                ->all();

            for ($i = 1; $i < count($orders); $i++) {
                if ($orders[$i] <= $orders[$i - 1]) {
                    throw ValidationException::withMessages([
                        'starting_stops'
                            => 'Starting booking points must follow the master roadway order.',
                    ]);
                }
            }
        }

        /*
         * Return direction:
         * roadway order must decrease.
         *
         * Example:
         * Trincomalee -> Batticaloa -> Kalmunai -> Akkaraipattu
         */

        if ($direction === 'return') {
            $orders = collect($prepared)
                ->pluck('master_stop_order')
                ->values()
                ->all();

            for ($i = 1; $i < count($orders); $i++) {
                if ($orders[$i] >= $orders[$i - 1]) {
                    throw ValidationException::withMessages([
                        'return_stops'
                            => 'Return booking points must follow the master roadway in reverse order.',
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
    |
    | Prevent an operator's bus from being linked to another operator.
    |
    */

    private function validateBusOperator(
        int $busId,
        int $operatorId
    ): void {
        $bus = DB::table('buses')
            ->where('id', $busId)
            ->first();

        if (!$bus) {
            throw ValidationException::withMessages([
                'bus_id' => 'Selected bus does not exist.',
            ]);
        }

        if (
            isset($bus->operator_id) &&
            (int) $bus->operator_id !== $operatorId
        ) {
            throw ValidationException::withMessages([
                'bus_id'
                    => 'Selected bus does not belong to the selected operator.',
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Save Stops
    |--------------------------------------------------------------------------
    */

    private function saveStops(
        int $serviceId,
        string $direction,
        array $stops
    ): void {
        foreach ($stops as $index => $stop) {
            DB::table('fixed_service_stops')
                ->insert([
                    'fixed_service_id' => $serviceId,

                    'route_stop_id'
                        => $stop['route_stop_id'],

                    'direction' => $direction,

                    /*
                     * Temporary backward compatibility.
                     *
                     * Operator does not type this value.
                     * Name comes automatically from route_stops.
                     */

                    'stop_name' => $stop['stop_name'],

                    'stop_order' => $index + 1,

                    'arrival_time'
                        => $stop['arrival_time'],

                    'departure_time'
                        => $stop['departure_time'],

                    'boarding_allowed'
                        => $stop['boarding_allowed'],

                    'dropoff_allowed'
                        => $stop['dropoff_allowed'],

                    'created_at' => now(),
                    'updated_at' => now(),
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
        return DB::table('fixed_service_stops as fss')
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
            ->orderBy('fss.stop_order')
            ->select(
                'fss.*',
                'rs.name as route_stop_name',
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
        if (empty($stops)) {
            return null;
        }

        return $stops[0]['departure_time']
            ?? $stops[0]['arrival_time']
            ?? null;
    }
}