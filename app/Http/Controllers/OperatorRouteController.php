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
        }

        return view(
            'operator.routes.index',
            compact('routes')
        );
    }

    public function create()
    {
        return view(
            'operator.routes.form',
            [
                'route' => null,
                'stops' => collect(),
            ]
        );
    }

    public function store(Request $request)
    {
        $operatorId = $this->operatorId(
            $request
        );

        $data = $this->validateRoute(
            $request
        );

        $routeId = DB::transaction(
            function () use (
                $operatorId,
                $data
            ) {
                $firstStop = $data['stops'][0];

                $lastStop = $data['stops'][
                    count($data['stops']) - 1
                ];

                $routeId = DB::table(
                    'routes'
                )->insertGetId([
                    'operator_id' => $operatorId,

                    'name' =>
                        $firstStop['name'] .
                        ' - ' .
                        $lastStop['name'],

                    'origin' =>
                        $firstStop['name'],

                    'destination' =>
                        $lastStop['name'],

                    'distance_km' =>
                        $lastStop[
                            'distance_from_origin'
                        ],

                    'duration_minutes' =>
                        $data[
                            'duration_minutes'
                        ] ?? null,

                    /*
                     * Fare is calculated using
                     * NTC fare-stage difference.
                     *
                     * Operator does not enter
                     * route fare manually.
                     */
                    'base_fare' => 0,

                    'is_active' => true,

                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->saveStops(
                    $routeId,
                    $data['stops']
                );

                return $routeId;
            }
        );

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

        abort_unless(
            $route,
            404
        );

        $stops = DB::table(
            'route_stops'
        )
            ->where(
                'route_id',
                $route->id
            )
            ->orderBy(
                'stop_order'
            )
            ->get();

        return view(
            'operator.routes.form',
            compact(
                'route',
                'stops'
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

        $data = $this->validateRoute(
            $request
        );

        $route = DB::table('routes')
            ->where('id', $id)
            ->where(
                'operator_id',
                $operatorId
            )
            ->first();

        abort_unless(
            $route,
            404
        );

        DB::transaction(
            function () use (
                $id,
                $data
            ) {
                $firstStop =
                    $data['stops'][0];

                $lastStop =
                    $data['stops'][
                        count(
                            $data['stops']
                        ) - 1
                    ];

                DB::table('routes')
                    ->where(
                        'id',
                        $id
                    )
                    ->update([
                        'name' =>
                            $firstStop['name'] .
                            ' - ' .
                            $lastStop['name'],

                        'origin' =>
                            $firstStop['name'],

                        'destination' =>
                            $lastStop['name'],

                        'distance_km' =>
                            $lastStop[
                                'distance_from_origin'
                            ],

                        'duration_minutes' =>
                            $data[
                                'duration_minutes'
                            ] ?? null,

                        'base_fare' => 0,

                        'updated_at' => now(),
                    ]);

                DB::table(
                    'route_stops'
                )
                    ->where(
                        'route_id',
                        $id
                    )
                    ->delete();

                $this->saveStops(
                    $id,
                    $data['stops']
                );
            }
        );

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
            $route,
            404
        );

        $hasTrips = DB::table(
            'trips'
        )
            ->where(
                'route_id',
                $id
            )
            ->exists();

        if ($hasTrips) {
            throw ValidationException::withMessages([
                'route' =>
                    'This route cannot be deleted because trips already use it.',
            ]);
        }

        DB::transaction(
            function () use ($id) {
                DB::table(
                    'route_stops'
                )
                    ->where(
                        'route_id',
                        $id
                    )
                    ->delete();

                DB::table(
                    'routes'
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
                'Route deleted successfully.'
            );
    }

    private function validateRoute(
        Request $request
    ): array {
        $data = $request->validate([
            'duration_minutes' => [
                'nullable',
                'integer',
                'min:1',
            ],

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

            /*
             * Every roadway stop must have
             * an NTC Fare Stage No.
             */
            'stops.*.fare_stage_no' => [
                'required',
                'integer',
                'min:1',
                'max:350',
            ],

            /*
             * Cumulative road distance
             * from route origin.
             */
            'stops.*.distance_from_origin' => [
                'required',
                'numeric',
                'min:0',
            ],

            /*
             * Passenger booking eligibility.
             */
            'stops.*.booking_allowed' => [
                'nullable',
                'boolean',
            ],

            /*
             * Timetable values for
             * bookable stops.
             */
            'stops.*.starting_time' => [
                'nullable',
                'date_format:H:i',
            ],

            'stops.*.return_time' => [
                'nullable',
                'date_format:H:i',
            ],
        ]);

        $stops = collect(
            $data['stops']
        )
            ->map(
                function ($stop) {
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

                        'booking_allowed' =>
                            !empty(
                                $stop[
                                    'booking_allowed'
                                ]
                            ),

                        'starting_time' =>
                            !empty(
                                $stop[
                                    'starting_time'
                                ]
                            )
                                ? $stop[
                                    'starting_time'
                                ]
                                : null,

                        'return_time' =>
                            !empty(
                                $stop[
                                    'return_time'
                                ]
                            )
                                ? $stop[
                                    'return_time'
                                ]
                                : null,
                    ];
                }
            )
            ->values();

        /*
         |--------------------------------------------------------------------------
         | First Stop Distance
         |--------------------------------------------------------------------------
         */

        if (
            (float) $stops->first()[
                'distance_from_origin'
            ] !== 0.0
        ) {
            throw ValidationException::withMessages([
                'stops.0.distance_from_origin' =>
                    'The first stop distance must be 0 km.',
            ]);
        }

        /*
         |--------------------------------------------------------------------------
         | Validate Stop Order
         |--------------------------------------------------------------------------
         */

        $previousDistance = -1;

        foreach (
            $stops as $index => $stop
        ) {
            if (
                $stop[
                    'distance_from_origin'
                ] <= $previousDistance
            ) {
                throw ValidationException::withMessages([
                    "stops.$index.distance_from_origin" =>
                        'Stop distances must increase in route order.',
                ]);
            }

            $previousDistance =
                $stop[
                    'distance_from_origin'
                ];

            /*
             * Verify Fare Stage exists
             * in NTC stage_fares table.
             */
            $stageExists = DB::table(
                'stage_fares'
            )
                ->where(
                    'stage_no',
                    $stop[
                        'fare_stage_no'
                    ]
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
         | Duplicate Stop Names
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
                    'The same stop cannot be added more than once.',
            ]);
        }

        /*
         |--------------------------------------------------------------------------
         | Duplicate Fare Stage Numbers
         |--------------------------------------------------------------------------
         */

        $stageNumbers = $stops
            ->pluck(
                'fare_stage_no'
            );

        if (
            $stageNumbers
                    ->unique()
                    ->count() !==
            $stageNumbers->count()
        ) {
            throw ValidationException::withMessages([
                'stops' =>
                    'The same Fare Stage No cannot be assigned to more than one stop on the same route.',
            ]);
        }

        /*
         |--------------------------------------------------------------------------
         | Minimum Bookable Stops
         |--------------------------------------------------------------------------
         */

        $bookableStops = $stops
            ->filter(
                fn ($stop) =>
                    $stop[
                        'booking_allowed'
                    ]
            );

        if (
            $bookableStops->count() < 2
        ) {
            throw ValidationException::withMessages([
                'stops' =>
                    'At least two passenger booking stops are required.',
            ]);
        }

        $data['stops'] =
            $stops->all();

        return $data;
    }

    private function saveStops(
        int $routeId,
        array $stops
    ): void {
        foreach (
            $stops as $index => $stop
        ) {
            $row = [
                'route_id' =>
                    $routeId,

                'name' =>
                    $stop['name'],

                'stop_order' =>
                    $index + 1,

                'fare_stage_no' =>
                    $stop[
                        'fare_stage_no'
                    ],

                'distance_from_origin' =>
                    $stop[
                        'distance_from_origin'
                    ],

                /*
                 * Latitude / Longitude
                 * are not manually entered.
                 */
                'latitude' => null,
                'longitude' => null,

                /*
                 * One Booking Allowed switch
                 * controls both boarding
                 * and drop-off.
                 */
                'boarding_allowed' =>
                    $stop[
                        'booking_allowed'
                    ],

                'dropoff_allowed' =>
                    $stop[
                        'booking_allowed'
                    ],

                'created_at' => now(),
                'updated_at' => now(),
            ];

            /*
             * Save timetable fields only
             * when these columns exist.
             */
            if (
                Schema::hasColumn(
                    'route_stops',
                    'starting_time'
                )
            ) {
                $row['starting_time'] =
                    $stop[
                        'booking_allowed'
                    ]
                        ? $stop[
                            'starting_time'
                        ]
                        : null;
            }

            if (
                Schema::hasColumn(
                    'route_stops',
                    'return_time'
                )
            ) {
                $row['return_time'] =
                    $stop[
                        'booking_allowed'
                    ]
                        ? $stop[
                            'return_time'
                        ]
                        : null;
            }

            if (
                Schema::hasColumn(
                    'route_stops',
                    'booking_radius_km'
                )
            ) {
                $row[
                    'booking_radius_km'
                ] = 0;
            }

            DB::table(
                'route_stops'
            )->insert(
                $row
            );
        }
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