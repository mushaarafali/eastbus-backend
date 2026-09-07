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
    |
    | Admin-created information-only daily bus schedules.
    |
    | No Operator
    | No Bus relation
    | No Booking
    | No Payment
    |
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
            ->whereNull('fs.operator_id')
            ->whereNull('fs.bus_id')
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

                'fs.created_at',
                'fs.updated_at',

                'r.route_number',
                'r.name as route_name',

                'r.origin as route_origin',
                'r.destination as route_destination',

                'r.distance_km as route_distance_km',
                'r.duration_minutes as route_duration_minutes'
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

            /*
             * Useful display values.
             */
            $service->starting_route =
                ($service->route_origin ?? '-')
                .
                ' → '
                .
                ($service->route_destination ?? '-');

            $service->return_route =
                ($service->route_destination ?? '-')
                .
                ' → '
                .
                ($service->route_origin ?? '-');

            $service->route_display =
                ($service->route_origin ?? '-')
                .
                ' ↔ '
                .
                ($service->route_destination ?? '-');
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

        return view(
            'admin.fixed_schedules.form',
            [
                'service' =>
                    null,

                'routes' =>
                    $routes,

                'routeStops' =>
                    collect(),

                'startingStops' =>
                    collect(),

                'returnStops' =>
                    collect(),
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Master Route Stops
    |--------------------------------------------------------------------------
    |
    | One Master Route supports BOTH directions.
    |
    | Example:
    |
    | Route 76
    |
    | Master:
    | Akkaraipattu -> Trincomalee
    |
    | Starting:
    | Akkaraipattu -> Trincomalee
    |
    | Return:
    | Trincomalee -> Akkaraipattu
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
            'success' =>
                true,

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

                'route_display' =>
                    $route->origin
                    .
                    ' ↔ '
                    .
                    $route->destination,

                'starting_direction' =>
                    $route->origin
                    .
                    ' → '
                    .
                    $route->destination,

                'return_direction' =>
                    $route->destination
                    .
                    ' → '
                    .
                    $route->origin,

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
                    $row = [
                        /*
                         * Admin timetable only.
                         */
                        'operator_id' =>
                            null,

                        'bus_id' =>
                            null,

                        /*
                         * Master Route.
                         */
                        'route_id' =>
                            $data['route_id'],

                        /*
                         * Bus information.
                         */
                        'bus_name' =>
                            trim(
                                $data['bus_name']
                            ),

                        'bus_number' =>
                            !empty(
                                $data['bus_number']
                            )
                                ? trim(
                                    $data['bus_number']
                                )
                                : null,

                        'contact_number_1' =>
                            trim(
                                $data['contact_number']
                            ),

                        'contact_number_2' =>
                            null,

                        'contact_number_3' =>
                            null,

                        /*
                         * Compatibility field.
                         */
                        'service_name' =>
                            trim(
                                $data['bus_name']
                            ),

                        /*
                         * First time in each direction.
                         */
                        'starting_time' =>
                            $this->firstTime(
                                $startingStops
                            ),

                        'return_time' =>
                            $this->firstTime(
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
                    ];

                    if (
                        Schema::hasColumn(
                            'fixed_services',
                            'created_by_admin_id'
                        )
                    ) {
                        $row['created_by_admin_id'] =
                            auth()->id();
                    }

                    $serviceId =
                        DB::table(
                            'fixed_services'
                        )->insertGetId(
                            $row
                        );

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
                'Daily service timetable created successfully.'
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
            ->whereNull('operator_id')
            ->whereNull('bus_id')
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
            ->whereNull('operator_id')
            ->whereNull('bus_id')
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
                DB::table('fixed_services')
                    ->where(
                        'id',
                        $id
                    )
                    ->update([
                        'operator_id' =>
                            null,

                        'bus_id' =>
                            null,

                        'route_id' =>
                            $data['route_id'],

                        'bus_name' =>
                            trim(
                                $data['bus_name']
                            ),

                        'bus_number' =>
                            !empty(
                                $data['bus_number']
                            )
                                ? trim(
                                    $data['bus_number']
                                )
                                : null,

                        'contact_number_1' =>
                            trim(
                                $data['contact_number']
                            ),

                        'contact_number_2' =>
                            null,

                        'contact_number_3' =>
                            null,

                        'service_name' =>
                            trim(
                                $data['bus_name']
                            ),

                        'starting_time' =>
                            $this->firstTime(
                                $startingStops
                            ),

                        'return_time' =>
                            $this->firstTime(
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
                 * Rebuild timetable only.
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
                'Daily service timetable updated successfully.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Publish / Unpublish
    |--------------------------------------------------------------------------
    */

    public function togglePublish(int $id)
    {
        $service = DB::table('fixed_services')
            ->where('id', $id)
            ->whereNull('operator_id')
            ->whereNull('bus_id')
            ->first();

        abort_unless(
            $service,
            404
        );

        if (
            empty(
                $service->route_id
            )
        ) {
            throw ValidationException::withMessages([
                'route_id' =>
                    'Select a Master Route before publishing this timetable.',
            ]);
        }

        /*
         * Must have both directions.
         */
        $startingExists =
            DB::table(
                'fixed_service_stops'
            )
                ->where(
                    'fixed_service_id',
                    $id
                )
                ->where(
                    'direction',
                    'starting'
                )
                ->exists();

        $returnExists =
            DB::table(
                'fixed_service_stops'
            )
                ->where(
                    'fixed_service_id',
                    $id
                )
                ->where(
                    'direction',
                    'return'
                )
                ->exists();

        if (
            !$startingExists
            ||
            !$returnExists
        ) {
            throw ValidationException::withMessages([
                'service' =>
                    'Both Starting and Return timetables are required before publishing.',
            ]);
        }

        DB::table('fixed_services')
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
    | Active / Inactive
    |--------------------------------------------------------------------------
    */

    public function toggleActive(int $id)
    {
        $service = DB::table('fixed_services')
            ->where('id', $id)
            ->whereNull('operator_id')
            ->whereNull('bus_id')
            ->first();

        abort_unless(
            $service,
            404
        );

        DB::table('fixed_services')
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
        $service = DB::table('fixed_services')
            ->where('id', $id)
            ->whereNull('operator_id')
            ->whereNull('bus_id')
            ->first();

        abort_unless(
            $service,
            404
        );

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
                'Daily service timetable deleted successfully.'
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
            'bus_name' => [
                'required',
                'string',
                'max:150',
            ],

            'bus_number' => [
                'nullable',
                'string',
                'max:100',
            ],

            'contact_number' => [
                'required',
                'string',
                'max:30',
            ],

            'route_id' => [
                'required',
                'integer',
                'exists:routes,id',
            ],

            /*
             * Starting timetable.
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

            /*
             * Return timetable.
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

        if (
            $stopCount
            <
            2
        ) {
            throw ValidationException::withMessages([
                'route_id' =>
                    'Selected Master Route must contain at least two road-way stops.',
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Prepare Timetable Stops
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
                    'Selected Master Route does not contain road-way stops.',
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
                $stop[
                    'route_stop_id'
                ];

            $routeStop =
                $routeStops->get(
                    $routeStopId
                );

            if (!$routeStop) {
                throw ValidationException::withMessages([
                    "{$direction}_stops.$index.route_stop_id" =>
                        'Selected stop does not belong to the selected Master Route.',
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
                        'Enter arrival or departure time for every timetable stop.',
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

                'fare_stage_no' =>
                    $routeStop->fare_stage_no !== null
                        ? (int)
                        $routeStop->fare_stage_no
                        : null,

                'distance_from_origin_km' =>
                    (float)
                    (
                        $routeStop->distance_from_origin_km
                        ??
                        0
                    ),

                'arrival_time' =>
                    $arrivalTime,

                'departure_time' =>
                    $departureTime,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Duplicate Validation
        |--------------------------------------------------------------------------
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
                    'The same road-way stop cannot be selected more than once.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Master Route Order Validation
        |--------------------------------------------------------------------------
        */

        $orders =
            collect(
                $prepared
            )
                ->pluck(
                    'master_stop_order'
                )
                ->values()
                ->all();

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
                            'Starting timetable must follow the Master Route forward order.',
                    ]);
                }
            }
        }

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
                            'Return timetable must follow the Master Route reverse order.',
                    ]);
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Endpoint Validation
        |--------------------------------------------------------------------------
        |
        | Starting must begin at Master Route origin
        | and finish at Master Route destination.
        |
        | Return must do exactly the opposite.
        |
        */

        $orderedMasterStops =
            $routeStops
                ->sortBy(
                    'stop_order'
                )
                ->values();

        $masterFirst =
            $orderedMasterStops->first();

        $masterLast =
            $orderedMasterStops->last();

        $submittedFirst =
            $prepared[0];

        $submittedLast =
            $prepared[
                count($prepared) - 1
            ];

        if (
            $direction
            ===
            'starting'
        ) {
            if (
                (int)
                $submittedFirst['route_stop_id']
                !==
                (int)
                $masterFirst->id
            ) {
                throw ValidationException::withMessages([
                    'starting_stops' =>
                        'Starting timetable must begin from the Master Route origin: '
                        .
                        $masterFirst->name
                        .
                        '.',
                ]);
            }

            if (
                (int)
                $submittedLast['route_stop_id']
                !==
                (int)
                $masterLast->id
            ) {
                throw ValidationException::withMessages([
                    'starting_stops' =>
                        'Starting timetable must end at the Master Route destination: '
                        .
                        $masterLast->name
                        .
                        '.',
                ]);
            }
        }

        if (
            $direction
            ===
            'return'
        ) {
            if (
                (int)
                $submittedFirst['route_stop_id']
                !==
                (int)
                $masterLast->id
            ) {
                throw ValidationException::withMessages([
                    'return_stops' =>
                        'Return timetable must begin from the Master Route destination: '
                        .
                        $masterLast->name
                        .
                        '.',
                ]);
            }

            if (
                (int)
                $submittedLast['route_stop_id']
                !==
                (int)
                $masterFirst->id
            ) {
                throw ValidationException::withMessages([
                    'return_stops' =>
                        'Return timetable must end at the Master Route origin: '
                        .
                        $masterFirst->name
                        .
                        '.',
                ]);
            }
        }

        return $prepared;
    }

    /*
    |--------------------------------------------------------------------------
    | Save Timetable Stops
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
                    $stop[
                        'route_stop_id'
                    ],

                'direction' =>
                    $direction,

                /*
                 * Journey order.
                 *
                 * Return also stores:
                 * 1,2,3...
                 *
                 * but actual Master Route order is reversed.
                 */
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

                /*
                 * Admin schedule is information only.
                 */
                'boarding_allowed' =>
                    false,

                'dropoff_allowed' =>
                    false,

                'created_at' =>
                    now(),

                'updated_at' =>
                    now(),
            ];

            /*
             * Legacy DB compatibility.
             */
            if (
                Schema::hasColumn(
                    'fixed_service_stops',
                    'stop_name'
                )
            ) {
                $row['stop_name'] =
                    $stop[
                        'stop_name'
                    ];
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
    | Existing Timetable Stops
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
    | First Timetable Time
    |--------------------------------------------------------------------------
    */

    private function firstTime(
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