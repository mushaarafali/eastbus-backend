<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Bus;
use App\Models\EmergencyAlert;
use App\Models\Notification;
use App\Models\Operator;
use App\Models\Payment;
use App\Models\SystemLog;
use App\Models\Trip;
use App\Models\User;
use App\Services\EastBusMailService;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */

    public function dashboard()
    {
        return view('admin.dashboard', [
            'stats' => [
                'operators' =>
                    Operator::count(),

                'buses' =>
                    Bus::count(),

                'passengers' =>
                    User::where(
                        'role',
                        'passenger'
                    )->count(),

                'bookings' =>
                    Booking::count(),

                'activeTrips' =>
                    Trip::where(
                        'status',
                        'active'
                    )->count(),

                'completedTrips' =>
                    Trip::where(
                        'status',
                        'completed'
                    )->count(),

                'revenue' =>
                    Payment::where(
                        'status',
                        'success'
                    )->sum('amount'),
            ],

            'recentBookings' =>
                Booking::with([
                    'trip.route',
                    'trip.fixedService',
                    'passenger',
                ])
                    ->latest()
                    ->take(8)
                    ->get(),

            'recentLogs' =>
                SystemLog::with('user')
                    ->latest('created_at')
                    ->take(8)
                    ->get(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Operators
    |--------------------------------------------------------------------------
    */

    public function operators()
    {
        return view('admin.operators', [
            'operators' =>
                Operator::with('user')
                    ->withCount([
                        'buses',
                        'trips',
                        'fixedServices',
                    ])
                    ->latest()
                    ->paginate(20),
        ]);
    }

    public function toggleOperator(
        Operator $operator
    ) {
        $operator->status =
            $operator->status === 'active'
                ? 'inactive'
                : 'active';

        $operator->is_published =
            $operator->status === 'active';

        $operator->save();

        $operator->user()->update([
            'is_active' =>
                $operator->status === 'active',
        ]);

        Audit::log(
            'Toggle operator',
            'Operators',
            $operator->company_name
            . ' -> '
            . $operator->status
        );

        app(EastBusMailService::class)
            ->operatorStatus($operator);

        return back()->with(
            'success',
            'Operator status updated.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Passengers
    |--------------------------------------------------------------------------
    */

    public function passengers()
    {
        return view('admin.passengers', [
            'passengers' =>
                User::where(
                    'role',
                    'passenger'
                )
                    ->latest()
                    ->paginate(25),
        ]);
    }

    public function togglePassenger(
        User $user
    ) {
        abort_unless(
            strtolower($user->role)
            ===
            'passenger',
            404
        );

        $user->update([
            'is_active' =>
                !$user->is_active,
        ]);

        Audit::log(
            'Toggle passenger',
            'Passengers',
            $user->email
        );

        app(EastBusMailService::class)
            ->accountStatus(
                $user,
                $user->is_active
                    ? 'Activated'
                    : 'Disabled'
            );

        return back()->with(
            'success',
            'Passenger status updated.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Buses
    |--------------------------------------------------------------------------
    */

    public function buses()
    {
        return view('admin.buses', [
            'buses' =>
                Bus::with([
                    'operator',
                    'fixedServices.route',
                ])
                    ->latest()
                    ->paginate(25),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Master Routes
    |--------------------------------------------------------------------------
    */

    public function masterRoutes()
    {
        $routes =
            DB::table('routes')
                ->orderBy('route_number')
                ->orderBy('origin')
                ->paginate(25);

        foreach ($routes as $route) {
            $route->stops =
                DB::table('route_stops')
                    ->where(
                        'route_id',
                        $route->id
                    )
                    ->orderBy('stop_order')
                    ->get();

            $route->stop_count =
                $route->stops->count();

            $route->service_count =
                DB::table('fixed_services')
                    ->where(
                        'route_id',
                        $route->id
                    )
                    ->count();

            $route->trip_count =
                DB::table('trips')
                    ->where(
                        'route_id',
                        $route->id
                    )
                    ->count();
        }

        return view(
            'admin.routes.index',
            compact('routes')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Create Master Route
    |--------------------------------------------------------------------------
    */

    public function createMasterRoute()
    {
        return view(
            'admin.routes.form',
            [
                'route' => null,
                'stops' => collect(),
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Store Master Route
    |--------------------------------------------------------------------------
    */

    public function storeMasterRoute(
        Request $request
    ) {
        $data =
            $this->validateMasterRoute(
                $request
            );

        $routeId =
            DB::transaction(
                function () use ($data) {
                    $firstStop =
                        $data['stops'][0];

                    $lastStop =
                        $data['stops'][
                            count($data['stops']) - 1
                        ];

                    $routeRow = [
                        'route_number' =>
                            $data['route_number'],

                        'name' =>
                            $firstStop['name']
                            . ' - '
                            . $lastStop['name'],

                        'origin' =>
                            $firstStop['name'],

                        'destination' =>
                            $lastStop['name'],

                        'duration_minutes' =>
                            $data[
                                'duration_minutes'
                            ] ?? null,

                        'distance_km' =>
                            $lastStop[
                                'distance_from_origin_km'
                            ],

                        'base_fare' => 0,

                        'is_active' => true,

                        'created_at' => now(),

                        'updated_at' => now(),
                    ];

                    /*
                     * Master Route has no
                     * individual operator owner.
                     */
                    if (
                        Schema::hasColumn(
                            'routes',
                            'operator_id'
                        )
                    ) {
                        $routeRow[
                            'operator_id'
                        ] = null;
                    }

                    $routeId =
                        DB::table('routes')
                            ->insertGetId(
                                $routeRow
                            );

                    $this->saveMasterRouteStops(
                        $routeId,
                        $data['stops']
                    );

                    return $routeId;
                }
            );

        Audit::log(
            'Create master route',
            'Routes',
            $data['route_number']
        );

        return redirect()
            ->route(
                'admin.routes.edit',
                $routeId
            )
            ->with(
                'success',
                'Master route created successfully.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Edit Master Route
    |--------------------------------------------------------------------------
    */

    public function editMasterRoute(
        int $id
    ) {
        $route =
            DB::table('routes')
                ->where(
                    'id',
                    $id
                )
                ->first();

        abort_unless(
            $route,
            404
        );

        $stops =
            DB::table('route_stops')
                ->where(
                    'route_id',
                    $id
                )
                ->orderBy('stop_order')
                ->get();

        return view(
            'admin.routes.form',
            compact(
                'route',
                'stops'
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Update Master Route
    |--------------------------------------------------------------------------
    */

    public function updateMasterRoute(
        Request $request,
        int $id
    ) {
        $route =
            DB::table('routes')
                ->where(
                    'id',
                    $id
                )
                ->first();

        abort_unless(
            $route,
            404
        );

        $data =
            $this->validateMasterRoute(
                $request,
                $id
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
                        count($data['stops']) - 1
                    ];

                DB::table('routes')
                    ->where(
                        'id',
                        $id
                    )
                    ->update([
                        'route_number' =>
                            $data[
                                'route_number'
                            ],

                        'name' =>
                            $firstStop['name']
                            . ' - '
                            . $lastStop['name'],

                        'origin' =>
                            $firstStop['name'],

                        'destination' =>
                            $lastStop['name'],

                        'duration_minutes' =>
                            $data[
                                'duration_minutes'
                            ] ?? null,

                        'distance_km' =>
                            $lastStop[
                                'distance_from_origin_km'
                            ],

                        'updated_at' =>
                            now(),
                    ]);

                /*
                 * Preserve existing route_stop IDs
                 * because FixedServiceStop references them.
                 */
                $this->updateMasterRouteStops(
                    $id,
                    $data['stops']
                );
            }
        );

        Audit::log(
            'Update master route',
            'Routes',
            $data['route_number']
        );

        return back()->with(
            'success',
            'Master route updated successfully.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Delete Master Route
    |--------------------------------------------------------------------------
    */

    public function destroyMasterRoute(
        int $id
    ) {
        $route =
            DB::table('routes')
                ->where(
                    'id',
                    $id
                )
                ->first();

        abort_unless(
            $route,
            404
        );

        /*
         * Master Route cannot be removed
         * when a Fixed Service uses it.
         */
        $hasServices =
            DB::table('fixed_services')
                ->where(
                    'route_id',
                    $id
                )
                ->exists();

        if ($hasServices) {
            throw ValidationException::withMessages([
                'route' =>
                    'This master route cannot be deleted because bus services are already using it.',
            ]);
        }

        /*
         * Master Route cannot be removed
         * when Trips already reference it.
         */
        $hasTrips =
            DB::table('trips')
                ->where(
                    'route_id',
                    $id
                )
                ->exists();

        if ($hasTrips) {
            throw ValidationException::withMessages([
                'route' =>
                    'This master route cannot be deleted because trips already use it.',
            ]);
        }

        DB::transaction(
            function () use ($id) {
               /*
                * Master Route deletion only removes
                * the roadway stops and route record.
                *
                * Operator booking points are managed
                * through Fixed Service Stops.
                */

                DB::table('route_stops')
                    ->where(
                        'route_id',
                        $id
                    )
                    ->delete();

                DB::table('routes')
                    ->where(
                        'id',
                        $id
                    )
                    ->delete();
            }
        );

        Audit::log(
            'Delete master route',
            'Routes',
            $route->route_number
        );

        return redirect()
            ->route(
                'admin.routes.index'
            )
            ->with(
                'success',
                'Master route deleted successfully.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Trips
    |--------------------------------------------------------------------------
    */

    public function trips(
        Request $request
    ) {
        $query =
            Trip::with([
                'operator',
                'fixedService',
                'bus',
                'route',
                'driver',
                'conductor',
            ])
                ->latest(
                    'service_date'
                );

        if (
            $request->filled(
                'status'
            )
        ) {
            $query->where(
                'status',
                $request->status
            );
        }

        return view(
            'admin.trips',
            [
                'trips' =>
                    $query
                        ->paginate(25),
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Bookings
    |--------------------------------------------------------------------------
    */

    public function bookings()
    {
        return view(
            'admin.bookings',
            [
                'bookings' =>
                    Booking::with([
                        'trip.route',
                        'trip.fixedService',
                        'trip.operator',
                        'passenger',
                        'payment',
                    ])
                        ->latest()
                        ->paginate(25),
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Payments
    |--------------------------------------------------------------------------
    */

    public function payments()
    {
        return view(
            'admin.payments',
            [
                'payments' =>
                    Payment::with(
                        'booking.trip.operator'
                    )
                        ->latest()
                        ->paginate(25),

                'total' =>
                    Payment::where(
                        'status',
                        'success'
                    )
                        ->sum('amount'),
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Tracking
    |--------------------------------------------------------------------------
    */

    public function tracking()
    {
        return view(
            'admin.tracking',
            [
                'trips' =>
                    Trip::with([
                        'operator',
                        'fixedService',
                        'bus',
                        'route',
                        'liveLocation',
                    ])
                        ->where(
                            'status',
                            'active'
                        )
                        ->get(),
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Reports
    |--------------------------------------------------------------------------
    */

    public function reports(
        Request $request
    ) {
        $from =
            $request->date('from')
            ??
            now()->startOfMonth();

        $to =
            $request->date('to')
            ??
            now()->endOfMonth();

        $payments =
            Payment::where(
                'status',
                'success'
            )
                ->whereBetween(
                    'paid_at',
                    [
                        $from
                            ->copy()
                            ->startOfDay(),

                        $to
                            ->copy()
                            ->endOfDay(),
                    ]
                );

        $data = [
            'from' => $from,

            'to' => $to,

            'revenue' =>
                (clone $payments)
                    ->sum('amount'),

            'transactions' =>
                (clone $payments)
                    ->count(),

            'bookings' =>
                Booking::whereBetween(
                    'created_at',
                    [
                        $from
                            ->copy()
                            ->startOfDay(),

                        $to
                            ->copy()
                            ->endOfDay(),
                    ]
                )->count(),

            'trips' =>
                Trip::whereBetween(
                    'service_date',
                    [
                        $from
                            ->toDateString(),

                        $to
                            ->toDateString(),
                    ]
                )->count(),

            'operatorRevenue' =>
                Payment::query()
                    ->select(
                        'operators.company_name',

                        DB::raw(
                            'SUM(payments.amount) total'
                        )
                    )
                    ->join(
                        'bookings',
                        'bookings.id',
                        '=',
                        'payments.booking_id'
                    )
                    ->join(
                        'trips',
                        'trips.id',
                        '=',
                        'bookings.trip_id'
                    )
                    ->join(
                        'operators',
                        'operators.id',
                        '=',
                        'trips.operator_id'
                    )
                    ->where(
                        'payments.status',
                        'success'
                    )
                    ->whereBetween(
                        'payments.paid_at',
                        [
                            $from
                                ->copy()
                                ->startOfDay(),

                            $to
                                ->copy()
                                ->endOfDay(),
                        ]
                    )
                    ->groupBy(
                        'operators.id',
                        'operators.company_name'
                    )
                    ->orderByDesc(
                        'total'
                    )
                    ->get(),
        ];

        return view(
            'admin.reports',
            $data
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    */

    public function notifications()
    {
        return view(
            'admin.notifications',
            [
                'notifications' =>
                    Notification::latest()
                        ->paginate(20),
            ]
        );
    }

    public function sendNotification(
        Request $request
    ) {
        $data =
            $request->validate([
                'target_type' => [
                    'required',
                    'in:all,operators,passengers',
                ],

                'title' => [
                    'required',
                    'string',
                    'max:150',
                ],

                'message' => [
                    'required',
                    'string',
                    'max:1000',
                ],
            ]);

        Notification::create(
            $data + [
                'sent_at' =>
                    now(),
            ]
        );

        Audit::log(
            'Send notification',
            'Notifications',
            $data['title']
        );

        return back()->with(
            'success',
            'Notification recorded for delivery.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Logs
    |--------------------------------------------------------------------------
    */

    public function logs()
    {
        return view(
            'admin.logs',
            [
                'logs' =>
                    SystemLog::with(
                        'user'
                    )
                        ->latest(
                            'created_at'
                        )
                        ->paginate(40),
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Settings
    |--------------------------------------------------------------------------
    */

    public function settings()
    {
        return view(
            'admin.settings'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Backup
    |--------------------------------------------------------------------------
    */

    public function backup()
    {
        Audit::log(
            'Backup requested',
            'System',
            'Database backup requested from admin panel'
        );

        return back()->with(
            'success',
            'Backup request recorded. Run: php artisan db:backup or use your hosting backup tool for the physical dump.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Emergency Alerts
    |--------------------------------------------------------------------------
    */

    public function alerts()
    {
        return view(
            'admin.alerts',
            [
                'alerts' =>
                    EmergencyAlert::with([
                        'trip.bus',
                        'trip.route',
                        'trip.fixedService',
                        'staff',
                    ])
                        ->latest()
                        ->paginate(20),
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Master Route Validation
    |--------------------------------------------------------------------------
    */

    private function validateMasterRoute(
        Request $request,
        ?int $routeId = null
    ): array {
        $data =
            $request->validate([
                'route_number' => [
                    'required',
                    'string',
                    'max:50',

                    Rule::unique(
                        'routes',
                        'route_number'
                    )->ignore(
                        $routeId
                    ),
                ],

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

                'stops.*.id' => [
                    'nullable',
                    'integer',
                ],

                'stops.*.name' => [
                    'required',
                    'string',
                    'max:150',
                ],

                'stops.*.fare_stage_no' => [
                    'nullable',
                    'integer',
                    'min:1',
                    'max:350',
                ],

                'stops.*.distance_from_origin_km' => [
                    'required',
                    'numeric',
                    'min:0',
                ],
            ]);

        $stops =
            collect(
                $data['stops']
            )
                ->map(
                    function ($stop) {
                        return [
                            'id' =>
                                !empty($stop['id'])
                                    ? (int) $stop['id']
                                    : null,

                            'name' =>
                                trim(
                                    (string)
                                    $stop['name']
                                ),

                            'fare_stage_no' =>
                                !empty(
                                    $stop[
                                        'fare_stage_no'
                                    ]
                                )
                                    ? (int)
                                        $stop[
                                            'fare_stage_no'
                                        ]
                                    : null,

                            'distance_from_origin_km' =>
                                (float)
                                $stop[
                                    'distance_from_origin_km'
                                ],
                        ];
                    }
                )
                ->values();

        /*
         * First roadway stop must start
         * from 0 KM.
         */
        if (
            (float)
            $stops->first()[
                'distance_from_origin_km'
            ]
            !==
            0.0
        ) {
            throw ValidationException::withMessages([
                'stops.0.distance_from_origin_km' =>
                    'The first road-way stop must start at 0 km.',
            ]);
        }

        /*
         * Distances must strictly increase.
         */
        $previousDistance = -1;

        foreach (
            $stops
            as
            $index => $stop
        ) {
            $distance =
                $stop[
                    'distance_from_origin_km'
                ];

            if (
                $distance
                <=
                $previousDistance
            ) {
                throw ValidationException::withMessages([
                    "stops.$index.distance_from_origin_km" =>
                        'Road-way stop distances must increase in route order.',
                ]);
            }

            $previousDistance =
                $distance;

            /*
             * Validate NTC Fare Stage.
             */
            if (
                $stop[
                    'fare_stage_no'
                ] !== null
            ) {
                $stageExists =
                    DB::table(
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
        }

        /*
         * Duplicate roadway stop names are
         * not allowed within a Master Route.
         */
        $names =
            $stops
                ->pluck('name')
                ->map(
                    fn ($name) =>
                        mb_strtolower(
                            trim($name)
                        )
                );

        if (
            $names
                ->unique()
                ->count()
            !==
            $names->count()
        ) {
            throw ValidationException::withMessages([
                'stops' =>
                    'The same road-way stop cannot be added more than once.',
            ]);
        }

        /*
         * Existing stop IDs submitted while editing
         * must belong to this Master Route.
         */
        if (
            $routeId !== null
        ) {
            foreach (
                $stops
                as
                $index => $stop
            ) {
                if (
                    !$stop['id']
                ) {
                    continue;
                }

                $belongsToRoute =
                    DB::table(
                        'route_stops'
                    )
                        ->where(
                            'id',
                            $stop['id']
                        )
                        ->where(
                            'route_id',
                            $routeId
                        )
                        ->exists();

                if (
                    !$belongsToRoute
                ) {
                    throw ValidationException::withMessages([
                        "stops.$index.id" =>
                            'Invalid road-way stop.',
                    ]);
                }
            }
        }

        $data['stops'] =
            $stops->all();

        return $data;
    }

    /*
    |--------------------------------------------------------------------------
    | Save Master Route Stops
    |--------------------------------------------------------------------------
    */

    private function saveMasterRouteStops(
        int $routeId,
        array $stops
    ): void {
        foreach (
            $stops
            as
            $index => $stop
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

                'latitude' =>
                    null,

                'longitude' =>
                    null,

                'created_at' =>
                    now(),

                'updated_at' =>
                    now(),
            ];

            if (
                Schema::hasColumn(
                    'route_stops',
                    'distance_from_origin_km'
                )
            ) {
                $row[
                    'distance_from_origin_km'
                ] =
                    $stop[
                        'distance_from_origin_km'
                    ];
            }

            /*
             * Temporary database compatibility.
             */
            if (
                Schema::hasColumn(
                    'route_stops',
                    'distance_from_origin'
                )
            ) {
                $row[
                    'distance_from_origin'
                ] =
                    $stop[
                        'distance_from_origin_km'
                    ];
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

    /*
    |--------------------------------------------------------------------------
    | Update Master Route Stops
    |--------------------------------------------------------------------------
    |
    | Existing route_stop IDs are preserved because FixedServiceStop
    | records reference route_stop_id.
    |
    */

    private function updateMasterRouteStops(
        int $routeId,
        array $stops
    ): void {
        $existingIds =
            DB::table(
                'route_stops'
            )
                ->where(
                    'route_id',
                    $routeId
                )
                ->pluck('id')
                ->map(
                    fn ($id) =>
                        (int) $id
                )
                ->all();

        $submittedIds = [];

        foreach (
            $stops
            as
            $index => $stop
        ) {
            $row = [
                'name' =>
                    $stop['name'],

                'stop_order' =>
                    $index + 1,

                'fare_stage_no' =>
                    $stop[
                        'fare_stage_no'
                    ],

                'updated_at' =>
                    now(),
            ];

            if (
                Schema::hasColumn(
                    'route_stops',
                    'distance_from_origin_km'
                )
            ) {
                $row[
                    'distance_from_origin_km'
                ] =
                    $stop[
                        'distance_from_origin_km'
                    ];
            }

            if (
                Schema::hasColumn(
                    'route_stops',
                    'distance_from_origin'
                )
            ) {
                $row[
                    'distance_from_origin'
                ] =
                    $stop[
                        'distance_from_origin_km'
                    ];
            }

            /*
             * Existing roadway stop.
             */
            if (
                !empty(
                    $stop['id']
                )
                &&
                in_array(
                    $stop['id'],
                    $existingIds,
                    true
                )
            ) {
                DB::table(
                    'route_stops'
                )
                    ->where(
                        'id',
                        $stop['id']
                    )
                    ->where(
                        'route_id',
                        $routeId
                    )
                    ->update(
                        $row
                    );

                $submittedIds[] =
                    (int) $stop['id'];

                continue;
            }

            /*
             * New roadway stop.
             */
            $row['route_id'] =
                $routeId;

            $row['latitude'] =
                null;

            $row['longitude'] =
                null;

            $row['created_at'] =
                now();

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

            $newStopId =
                DB::table(
                    'route_stops'
                )
                    ->insertGetId(
                        $row
                    );

            $submittedIds[] =
                (int) $newStopId;
        }

        /*
         * Roadway stops removed from
         * the Admin form.
         */
        $removedIds =
            array_diff(
                $existingIds,
                $submittedIds
            );

        foreach (
            $removedIds
            as
            $stopId
        ) {
            /*
             * A Master Route stop cannot be removed
             * when a bus service already references it.
             */
            $usedByService =
                DB::table(
                    'fixed_service_stops'
                )
                    ->where(
                        'route_stop_id',
                        $stopId
                    )
                    ->exists();

            if (
                $usedByService
            ) {
                throw ValidationException::withMessages([
                    'stops' =>
                        'A road-way stop cannot be removed because a bus service is already using it.',
                ]);
            }

            DB::table(
                'route_stops'
            )
                ->where(
                    'id',
                    $stopId
                )
                ->where(
                    'route_id',
                    $routeId
                )
                ->delete();
        }
    }
}