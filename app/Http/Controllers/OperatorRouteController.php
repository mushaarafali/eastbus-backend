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
    | Show only services belonging to the logged-in operator.
    |
    | Important:
    | Operator does NOT own or edit the master route.
    |
    */

    public function index(Request $request)
    {
        $operatorId = $this->operatorId($request);

        $services = DB::table('fixed_services as fs')
            ->join(
                'routes as r',
                'r.id',
                '=',
                'fs.route_id'
            )
            ->join(
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
                'fs.*',
                'r.route_number',
                'r.name as route_name',
                'r.origin',
                'r.destination',
                'r.distance_km',
                'b.bus_number'
            )
            ->orderByDesc('fs.id')
            ->get();

        foreach ($services as $service) {
            $service->starting_booking_stops =
                $this->serviceStops(
                    $service->id,
                    'starting'
                );

            $service->return_booking_stops =
                $this->serviceStops(
                    $service->id,
                    'return'
                );
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
    |
    | Operator selects:
    |
    | 1. Bus
    | 2. Existing Master Route
    | 3. Starting booking points
    | 4. Starting times
    | 5. Return booking points
    | 6. Return times
    |
    */

    public function create(Request $request)
    {
        $operatorId = $this->operatorId(
            $request
        );

        /*
         * All active master routes.
         *
         * Do NOT filter by operator_id because routes
         * are now System Admin master data.
         */
        $routes = DB::table('routes')
            ->where('is_active', true)
            ->orderBy('route_number')
            ->orderBy('origin')
            ->get();

        /*
         * Only this operator's buses.
         */
        $buses = DB::table('buses')
            ->where(
                'operator_id',
                $operatorId
            )
            ->orderBy('bus_number')
            ->get();

        return view(
            'operator.routes.form',
            [
                'service' => null,
                'routes' => $routes,
                'buses' => $buses,
                'routeStops' => collect(),
                'startingBookingStops' => collect(),
                'returnBookingStops' => collect(),
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Get Master Roadway
    |--------------------------------------------------------------------------
    |
    | Called when operator selects a master route.
    |
    | Example:
    |
    | Route 76
    |     ↓
    | Akkaraipattu
    | Addalaichenai
    | Kalmunai
    | Batticaloa
    | ...
    | Trincomalee
    |
    */

    public function routeStops(
        Request $request,
        int $routeId
    ) {
        $this->operatorId($request);

        $route = DB::table('routes')
            ->where('id', $routeId)
            ->where('is_active', true)
            ->first();

        abort_unless($route, 404);

        $stops = DB::table('route_stops')
            ->where(
                'route_id',
                $routeId
            )
            ->orderBy('stop_order')
            ->get();

        $formattedStops = $stops->map(
            function ($stop) {
                return [
                    'id' => $stop->id,

                    'name' => $stop->name,

                    'stop_order' =>
                        (int) $stop->stop_order,

                    'fare_stage_no' =>
                        $stop->fare_stage_no,

                    /*
                     * Support both old and new
                     * distance column names.
                     */
                    'distance_from_origin_km' =>
                        $stop->distance_from_origin_km
                        ?? $stop->distance_from_origin
                        ?? 0,

                    'latitude' =>
                        $stop->latitude ?? null,

                    'longitude' =>
                        $stop->longitude ?? null,
                ];
            }
        );

        return response()->json([
            'route' => [
                'id' => $route->id,

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

            'stops' => $formattedStops,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Store Operator Bus Service
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        $operatorId = $this->operatorId(
            $request
        );

        $data = $this->validateService(
            $request
        );

        /*
         * Make sure selected bus belongs
         * to logged-in operator.
         */
        $this->validateOperatorBus(
            $operatorId,
            $data['bus_id']
        );

        /*
         * Validate booking points against
         * selected master route.
         */
        $startingStops =
            $this->prepareBookingStops(
                $data['route_id'],
                $data['starting_booking_stops'],
                'starting'
            );

        $returnStops =
            $this->prepareBookingStops(
                $data['route_id'],
                $data['return_booking_stops'],
                'return'
            );

        $serviceId = DB::transaction(
            function () use (
                $operatorId,
                $data,
                $startingStops,
                $returnStops,
                $request
            ) {
                $serviceId = DB::table(
                    'fixed_services'
                )->insertGetId([
                    'operator_id' =>
                        $operatorId,

                    'route_id' =>
                        $data['route_id'],

                    'bus_id' =>
                        $data['bus_id'],

                    'service_name' =>
                        $data['service_name']
                        ?? null,

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

                    'created_at' => now(),
                    'updated_at' => now(),
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
    | Edit Operator Service
    |--------------------------------------------------------------------------
    */

    public function edit(
        Request $request,
        int $id
    ) {
        $operatorId = $this->operatorId(
            $request
        );

        $service = DB::table(
            'fixed_services'
        )
            ->where('id', $id)
            ->where(
                'operator_id',
                $operatorId
            )
            ->first();

        abort_unless($service, 404);

        $routes = DB::table('routes')
            ->where('is_active', true)
            ->orderBy('route_number')
            ->orderBy('origin')
            ->get();

        $buses = DB::table('buses')
            ->where(
                'operator_id',
                $operatorId
            )
            ->orderBy('bus_number')
            ->get();

        /*
         * Fixed roadway from selected
         * System Admin master route.
         */
        $routeStops = DB::table(
            'route_stops'
        )
            ->where(
                'route_id',
                $service->route_id
            )
            ->orderBy('stop_order')
            ->get();

        $startingBookingStops =
            $this->serviceStops(
                $service->id,
                'starting'
            );

        $returnBookingStops =
            $this->serviceStops(
                $service->id,
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
    | Update Operator Service
    |--------------------------------------------------------------------------
    */

    public function update(
        Request $request,
        int $id
    ) {
        $operatorId = $this->operatorId(
            $request
        );

        $service = DB::table(
            'fixed_services'
        )
            ->where('id', $id)
            ->where(
                'operator_id',
                $operatorId
            )
            ->first();

        abort_unless($service, 404);

        $data = $this->validateService(
            $request
        );

        $this->validateOperatorBus(
            $operatorId,
            $data['bus_id']
        );

        $startingStops =
            $this->prepareBookingStops(
                $data['route_id'],
                $data['starting_booking_stops'],
                'starting'
            );

        $returnStops =
            $this->prepareBookingStops(
                $data['route_id'],
                $data['return_booking_stops'],
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
                DB::table('fixed_services')
                    ->where('id', $id)
                    ->update([
                        'route_id' =>
                            $data['route_id'],

                        'bus_id' =>
                            $data['bus_id'],

                        'service_name' =>
                            $data['service_name']
                            ?? null,

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

                        'updated_at' => now(),
                    ]);

                /*
                 * Only service booking points
                 * are replaced.
                 *
                 * Master route / roadway is
                 * NEVER changed here.
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

        return back()->with(
            'success',
            'Bus route service updated successfully.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Delete Operator Service
    |--------------------------------------------------------------------------
    |
    | Important:
    | This deletes only the operator's service.
    |
    | It DOES NOT delete:
    | - routes
    | - route_stops
    |
    */

    public function destroy(
        Request $request,
        int $id
    ) {
        $operatorId = $this->operatorId(
            $request
        );

        $service = DB::table(
            'fixed_services'
        )
            ->where('id', $id)
            ->where(
                'operator_id',
                $operatorId
            )
            ->first();

        abort_unless($service, 404);

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
                    ->where('id', $id)
                    ->delete();
            }
        );

        return redirect()
            ->route(
                'operator.routes.index'
            )
            ->with(
                'success',
                'Bus service removed successfully.'
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
            /*
             * Selected Master Route
             */
            'route_id' => [
                'required',
                'integer',
                'exists:routes,id',
            ],

            /*
             * Selected Operator Bus
             */
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

            'starting_booking_stops.*.route_stop_id'
                => [
                    'required',
                    'integer',
                    'exists:route_stops,id',
                ],

            'starting_booking_stops.*.arrival_time'
                => [
                    'nullable',
                    'date_format:H:i',
                ],

            'starting_booking_stops.*.departure_time'
                => [
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

            'return_booking_stops.*.route_stop_id'
                => [
                    'required',
                    'integer',
                    'exists:route_stops,id',
                ],

            'return_booking_stops.*.arrival_time'
                => [
                    'nullable',
                    'date_format:H:i',
                ],

            'return_booking_stops.*.departure_time'
                => [
                    'nullable',
                    'date_format:H:i',
                ],
        ]);
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
        /*
         * Load ONLY roadway stops from
         * selected master route.
         */
        $roadStops = DB::table(
            'route_stops'
        )
            ->where(
                'route_id',
                $routeId
            )
            ->orderBy('stop_order')
            ->get()
            ->keyBy('id');

        if ($roadStops->isEmpty()) {
            throw ValidationException::withMessages([
                'route_id' =>
                    'Selected route does not contain any roadway stops.',
            ]);
        }

        $prepared = [];

        foreach (
            $points as $index => $point
        ) {
            $routeStopId =
                (int) $point[
                    'route_stop_id'
                ];

            $routeStop =
                $roadStops->get(
                    $routeStopId
                );

            /*
             * Security validation.
             *
             * Route 76 selected means operator
             * cannot manually submit a Route 48 stop.
             */
            if (!$routeStop) {
                throw ValidationException::withMessages([
                    "{$direction}_booking_stops.$index.route_stop_id"
                        => 'Selected booking point does not belong to the selected route.',
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

            /*
             * One time is enough.
             */
            if (
                !$arrivalTime &&
                !$departureTime
            ) {
                throw ValidationException::withMessages([
                    "{$direction}_booking_stops.$index.arrival_time"
                        => 'Enter an arrival time or departure time.',
                ]);
            }

            $prepared[] = [
                'route_stop_id' =>
                    (int) $routeStop->id,

                /*
                 * Never trust operator-entered name.
                 *
                 * Name automatically comes from
                 * Admin Master Roadway.
                 */
                'stop_name' =>
                    $routeStop->name,

                'master_stop_order' =>
                    (int) $routeStop
                        ->stop_order,

                'arrival_time' =>
                    $arrivalTime,

                'departure_time' =>
                    $departureTime,
            ];
        }

        /*
         * Duplicate booking points
         * are not allowed.
         */
        $stopIds = collect(
            $prepared
        )->pluck(
            'route_stop_id'
        );

        if (
            $stopIds->unique()->count()
            !==
            $stopIds->count()
        ) {
            throw ValidationException::withMessages([
                "{$direction}_booking_stops"
                    => 'The same booking point cannot be selected more than once.',
            ]);
        }

        /*
         * Validate roadway order.
         */
        $orders = collect(
            $prepared
        )
            ->pluck(
                'master_stop_order'
            )
            ->values()
            ->all();

        if ($direction === 'starting') {
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
                        'starting_booking_stops'
                            => 'Starting booking points must follow the master roadway order.',
                    ]);
                }
            }
        }

        if ($direction === 'return') {
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
                        'return_booking_stops'
                            => 'Return booking points must follow the master roadway in reverse order.',
                    ]);
                }
            }
        }

        /*
         * Route endpoint validation.
         */
        $firstMasterStop =
            $roadStops->sortBy(
                'stop_order'
            )->first();

        $lastMasterStop =
            $roadStops->sortBy(
                'stop_order'
            )->last();

        if ($direction === 'starting') {
            if (
                (int) $prepared[0][
                    'route_stop_id'
                ]
                !==
                (int) $firstMasterStop->id
            ) {
                throw ValidationException::withMessages([
                    'starting_booking_stops'
                        => 'Starting service must begin from the route origin.',
                ]);
            }

            if (
                (int) $prepared[
                    count($prepared) - 1
                ]['route_stop_id']
                !==
                (int) $lastMasterStop->id
            ) {
                throw ValidationException::withMessages([
                    'starting_booking_stops'
                        => 'Starting service must end at the route destination.',
                ]);
            }
        }

        if ($direction === 'return') {
            if (
                (int) $prepared[0][
                    'route_stop_id'
                ]
                !==
                (int) $lastMasterStop->id
            ) {
                throw ValidationException::withMessages([
                    'return_booking_stops'
                        => 'Return service must begin from the route destination.',
                ]);
            }

            if (
                (int) $prepared[
                    count($prepared) - 1
                ]['route_stop_id']
                !==
                (int) $firstMasterStop->id
            ) {
                throw ValidationException::withMessages([
                    'return_booking_stops'
                        => 'Return service must end at the route origin.',
                ]);
            }
        }

        return $prepared;
    }

    /*
    |--------------------------------------------------------------------------
    | Save Fixed Service Booking Points
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

                /*
                 * Selected points are
                 * passenger booking points.
                 */
                'boarding_allowed' => true,

                'dropoff_allowed' => true,

                'created_at' => now(),
                'updated_at' => now(),
            ];

            /*
             * Temporary compatibility with
             * old fixed_service_stops table.
             *
             * Operator never types stop_name.
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
    | Get Existing Service Stops
    |--------------------------------------------------------------------------
    */

    private function serviceStops(
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
                'fss.*',
                'rs.name as stop_name',
                'rs.stop_order as master_stop_order',
                'rs.fare_stage_no'
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
        $exists = DB::table('buses')
            ->where('id', $busId)
            ->where(
                'operator_id',
                $operatorId
            )
            ->exists();

        if (!$exists) {
            throw ValidationException::withMessages([
                'bus_id' =>
                    'Selected bus does not belong to your operator account.',
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Starting / Return Service Time
    |--------------------------------------------------------------------------
    */

    private function firstServiceTime(
        array $stops
    ): ?string {
        if (empty($stops)) {
            return null;
        }

        return $stops[0][
            'departure_time'
        ]
            ?? $stops[0][
                'arrival_time'
            ]
            ?? null;
    }

    /*
    |--------------------------------------------------------------------------
    | Current Operator
    |--------------------------------------------------------------------------
    */

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