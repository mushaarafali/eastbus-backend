<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Bus;
use App\Models\EmergencyAlert;
use App\Models\Notification;
use App\Models\Operator;
use App\Models\Payment;
use App\Models\Route;
use App\Models\Seat;
use App\Models\Staff;
use App\Models\Trip;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class OperatorController extends Controller
{
    private function op(): Operator
    {
        return auth()->user()->operator
            ?: abort(403, 'Operator profile missing.');
    }

    public function dashboard()
    {
        $operator = $this->op();

        $stats = [
            'buses' => $operator->buses()->count(),

            'staff' => $operator->staff()->count(),

            'routes' => $operator->routes()->count(),

            'trips' => $operator->trips()->count(),

            'activeTrips' => $operator
                ->trips()
                ->where('status', 'active')
                ->count(),

            'bookings' => Booking::whereHas(
                'trip',
                function ($query) use ($operator) {
                    $query->where(
                        'operator_id',
                        $operator->id
                    );
                }
            )->count(),

            'revenue' => Payment::where(
                'status',
                'success'
            )
                ->whereHas(
                    'booking.trip',
                    function ($query) use ($operator) {
                        $query->where(
                            'operator_id',
                            $operator->id
                        );
                    }
                )
                ->sum('amount'),
        ];

        $upcoming = $operator
            ->trips()
            ->with([
                'bus',
                'route',
            ])
            ->whereDate(
                'service_date',
                '>=',
                today()
            )
            ->orderBy('service_date')
            ->orderBy('departure_time')
            ->take(8)
            ->get();

        return view(
            'operator.dashboard',
            [
                'operator' => $operator,
                'stats' => $stats,
                'upcoming' => $upcoming,
            ]
        );
    }

    public function profile()
    {
        return view(
            'operator.profile',
            [
                'operator' => $this->op(),
            ]
        );
    }

    public function updateProfile(Request $request)
    {
        $operator = $this->op();

        $data = $request->validate([
            'company_name' =>
                'required|max:150',

            'owner_name' =>
                'required|max:150',

            'phone' =>
                'required|max:30',

            'address' =>
                'nullable|max:255',

            'permit_or_registration_no' =>
                'nullable|max:100',
        ]);

        $operator->update($data);

        Audit::log(
            'Update profile',
            'Operator',
            $operator->company_name
        );

        return back()->with(
            'success',
            'Profile updated.'
        );
    }

    public function buses()
    {
        return view(
            'operator.buses',
            [
                'buses' => $this
                    ->op()
                    ->buses()
                    ->withCount('seats')
                    ->latest()
                    ->get(),
            ]
        );
    }

    public function storeBus(Request $request)
    {
        $operator = $this->op();

        $data = $request->validate([
            'bus_number' =>
                'required|max:30|unique:buses,bus_number',

            'bus_name' =>
                'nullable|max:100',

            'route_permit_number' =>
                'required|max:100',

            'seat_count' => ['required','integer', function ($attribute,$value,$fail) use ($request) {
                $type=$request->input('bus_type');
                if ($type==='A/C' && !in_array((int)$value,[49,51,53],true)) $fail('A/C buses must have 49, 51 or 53 seats.');
                if (in_array($type,['Normal','Semi-Luxury'],true) && (int)$value!==54) $fail('Normal and Semi-Luxury buses must have exactly 54 seats.');
            }],

            'bus_type' =>
                'required|in:Normal,Semi-Luxury,A/C',

            'facilities' =>
                'nullable|max:500',
        ]);

        $bus = $operator
            ->buses()
            ->create(
                $data + [
                    'is_active' => true,
                ]
            );

        for (
            $seatNumber = 1;
            $seatNumber <= $bus->seat_count;
            $seatNumber++
        ) {
            $bus
                ->seats()
                ->create([
                    'seat_number' =>
                        'S' . $seatNumber,
                ]);
        }

        Audit::log(
            'Create bus',
            'Buses',
            $bus->bus_number
        );

        return back()->with(
            'success',
            'Bus created with '
            . $bus->seat_count
            . ' seats.'
        );
    }

    public function updateBus(
        Request $request,
        Bus $bus
    ) {
        $this->own($bus);

        $data = $request->validate([
            'bus_number' => [
                'required',
                'max:30',
                Rule::unique('buses')
                    ->ignore($bus->id),
            ],

            'bus_name' =>
                'nullable|max:100',

            'route_permit_number' =>
                'required|max:100',

            'bus_type' =>
                'required|in:Normal,Semi-Luxury,A/C',

            'facilities' =>
                'nullable|max:500',

            'is_active' =>
                'nullable|boolean',
        ]);

        $bus->update(
            $data + [
                'is_active' =>
                    $request->boolean(
                        'is_active'
                    ),
            ]
        );

        Audit::log(
            'Update bus',
            'Buses',
            $bus->bus_number
        );

        return back()->with(
            'success',
            'Bus updated.'
        );
    }

    public function seats(Bus $bus)
    {
        $this->own($bus);

        return view(
            'operator.seats',
            [
                'bus' => $bus,

                'seats' => $bus
                    ->seats()
                    ->orderByRaw(
                        'CAST(SUBSTRING(seat_number,2) AS UNSIGNED)'
                    )
                    ->get(),
            ]
        );
    }

    public function toggleSeat(
        Bus $bus,
        Seat $seat
    ) {
        $this->own($bus);

        abort_unless(
            $seat->bus_id === $bus->id,
            404
        );

        $seat->update([
            'is_disabled' =>
                !$seat->is_disabled,
        ]);

        return back()->with(
            'success',
            'Seat status changed.'
        );
    }

    public function staff()
    {
        return view(
            'operator.staff',
            [
                'staff' => $this
                    ->op()
                    ->staff()
                    ->latest()
                    ->get(),
            ]
        );
    }

    public function storeStaff(
        Request $request
    ) {
        $operator = $this->op();

        $data = $request->validate([
            'full_name' =>
                'required|max:150',

            'role' =>
                'required|in:driver,conductor',

            'nic' =>
                'required|max:20',

            'driving_licence_no' =>
                'nullable|max:50',

            'ntc_licence_no' =>
                'required|max:50',

            'phone' =>
                'required|max:30',

            'email' =>
                'nullable|email',

            'password' =>
                'required|min:8',
        ]);

        if (
            $data['role'] === 'driver'
            && empty(
                $data['driving_licence_no']
            )
        ) {
            return back()
                ->withErrors([
                    'driving_licence_no' =>
                        'Driving licence is required for drivers.',
                ])
                ->withInput();
        }

        $data['login_id'] =
            $this->generateStaffLoginId(
                $data['role']
            );

        $data['password'] =
            Hash::make(
                $data['password']
            );

        $staff = $operator
            ->staff()
            ->create(
                $data + [
                    'is_active' => true,
                ]
            );

        Audit::log(
            'Create staff',
            'Staff',
            $staff->login_id
        );

        return back()->with(
            'success',
            'Staff account created. Login ID: '
            . $staff->login_id
        );
    }

    public function toggleStaff(
        Staff $staff
    ) {
        $this->own($staff);

        $staff->update([
            'is_active' =>
                !$staff->is_active,
        ]);

        return back()->with(
            'success',
            'Staff status changed.'
        );
    }

    public function routes()
    {
        return view(
            'operator.routes',
            [
                'routes' => $this
                    ->op()
                    ->routes()
                    ->with('stops')
                    ->latest()
                    ->get(),
            ]
        );
    }

    public function storeRoute(
        Request $request
    ) {
        $operator = $this->op();

        $data = $request->validate([
            'name' =>
                'required|max:150',

            'origin' =>
                'required|max:100',

            'destination' =>
                'required|max:100',

            'duration_minutes' =>
                'nullable|integer|min:1',

            'distance_km' =>
                'nullable|numeric|min:0',

            'base_fare' =>
                'required|numeric|min:0',
        ]);

        $route = $operator
            ->routes()
            ->create(
                $data + [
                    'is_active' => true,
                ]
            );

        Audit::log(
            'Create route',
            'Routes',
            $route->name
        );

        return back()->with(
            'success',
            'Route created.'
        );
    }

    public function addStop(
        Request $request,
        Route $route
    ) {
        $this->own($route);

        $data = $request->validate([
            'name' =>
                'required|max:100',

            'stop_order' =>
                'required|integer|min:1',

            'latitude' =>
                'nullable|numeric',

            'longitude' =>
                'nullable|numeric',

            'boarding_allowed' =>
                'nullable|boolean',

            'dropoff_allowed' =>
                'nullable|boolean',

            'booking_radius_km' =>
                'nullable|numeric|min:1|max:50',
        ]);

        $data['booking_radius_km'] = $data['booking_radius_km'] ?? config('eastbus.booking_radius_km',20);

        $route
            ->stops()
            ->create(
                $data + [
                    'boarding_allowed' =>
                        $request->boolean(
                            'boarding_allowed'
                        ),

                    'dropoff_allowed' =>
                        $request->boolean(
                            'dropoff_allowed'
                        ),
                ]
            );

        if (!empty($data['latitude']) && !empty($data['longitude'])) {
            DB::table('locations')->updateOrInsert(
                ['name' => trim($data['name']), 'latitude' => $data['latitude'], 'longitude' => $data['longitude']],
                ['is_active' => true, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        return back()->with('success','Stop added. It is now available to passenger location search.');
    }

    public function trips()
{
    $operator = $this->op();

    return view(
        'operator.trips',
        [
            'trips' => $operator
                ->trips()
                ->with([
                    'route',
                    'bus',
                    'driver',
                    'conductor',
                ])
                ->latest('service_date')
                ->latest('departure_time')
                ->get(),

            'buses' => $operator
                ->buses()
                ->where('is_active', true)
                ->orderBy('bus_number')
                ->get(),

            /*
            |--------------------------------------------------------------------------
            | Online Booking Route Rule
            |--------------------------------------------------------------------------
            |
            | Operator scheduled trips must use routes that are
            | at least 50 km long.
            |
            */

            'routes' => $operator
                ->routes()
                ->where('is_active', true)
                ->whereNotNull('distance_km')
                ->where('distance_km', '>=', 50)
                ->orderBy('name')
                ->get(),

            'drivers' => $operator
                ->staff()
                ->where('role', 'driver')
                ->where('is_active', true)
                ->orderBy('full_name')
                ->get(),

            'conductors' => $operator
                ->staff()
                ->where('role', 'conductor')
                ->where('is_active', true)
                ->orderBy('full_name')
                ->get(),
        ]
    );
}

 public function storeTrip(Request $request)
{
    $operator = $this->op();

    $data = $request->validate([
        'route_id' => [
            'required',
            'integer',
        ],

        'bus_id' => [
            'required',
            'integer',
        ],

        'driver_id' => [
            'nullable',
            'integer',
        ],

        'conductor_id' => [
            'nullable',
            'integer',
        ],

        'trip_type' => [
            'required',
            Rule::in([
                'starting',
                'return',
            ]),
        ],

        'service_date' => [
            'required',
            'date',
            'after_or_equal:today',
        ],

        'departure_time' => [
            'required',
        ],

        'arrival_time' => [
            'nullable',
        ],

        'fare' => [
            'required',
            'numeric',
            'min:0',
        ],

        'is_published' => [
            'nullable',
            'boolean',
        ],
    ]);

    /*
    |--------------------------------------------------------------------------
    | Validate Route Ownership
    |--------------------------------------------------------------------------
    */

    $route = Route::findOrFail(
        $data['route_id']
    );

    $this->own($route);

    /*
    |--------------------------------------------------------------------------
    | Minimum 50 KM Rule
    |--------------------------------------------------------------------------
    |
    | Operator-created trips are online-booking trips.
    | Therefore route distance must be at least 50 km.
    |
    */

    if (
        $route->distance_km === null ||
        (float) $route->distance_km < 50
    ) {
        throw \Illuminate\Validation\ValidationException::withMessages([
            'route_id' =>
                'Online-booking trips can only be scheduled for routes of 50 km or more.',
        ]);
    }

    if (!$route->is_active) {
        throw \Illuminate\Validation\ValidationException::withMessages([
            'route_id' =>
                'The selected route is currently inactive.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Bus Ownership
    |--------------------------------------------------------------------------
    */

    $bus = Bus::findOrFail(
        $data['bus_id']
    );

    $this->own($bus);

    if (!$bus->is_active) {
        throw \Illuminate\Validation\ValidationException::withMessages([
            'bus_id' =>
                'The selected bus is currently inactive.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Driver
    |--------------------------------------------------------------------------
    */

    $driver = null;

    if (!empty($data['driver_id'])) {
        $driver = Staff::findOrFail(
            $data['driver_id']
        );

        $this->own($driver);

        if ($driver->role !== 'driver') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'driver_id' =>
                    'The selected staff member is not a driver.',
            ]);
        }

        if (!$driver->is_active) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'driver_id' =>
                    'The selected driver is inactive.',
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Conductor
    |--------------------------------------------------------------------------
    */

    $conductor = null;

    if (!empty($data['conductor_id'])) {
        $conductor = Staff::findOrFail(
            $data['conductor_id']
        );

        $this->own($conductor);

        if ($conductor->role !== 'conductor') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'conductor_id' =>
                    'The selected staff member is not a conductor.',
            ]);
        }

        if (!$conductor->is_active) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'conductor_id' =>
                    'The selected conductor is inactive.',
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Trip Date & Time
    |--------------------------------------------------------------------------
    */

    $departureAt = \Carbon\Carbon::parse(
        $data['service_date']
        . ' '
        . $data['departure_time']
    );

    if (!$departureAt->isFuture()) {
        throw \Illuminate\Validation\ValidationException::withMessages([
            'departure_time' =>
                'Trip departure date and time must be in the future.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Arrival Time
    |--------------------------------------------------------------------------
    */

    if (!empty($data['arrival_time'])) {
        $arrivalAt = \Carbon\Carbon::parse(
            $data['service_date']
            . ' '
            . $data['arrival_time']
        );

        /*
         * If arrival is earlier than departure,
         * treat it as next-day arrival.
         */

        if ($arrivalAt->lessThanOrEqualTo($departureAt)) {
            $arrivalAt->addDay();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Generate Trip Code
    |--------------------------------------------------------------------------
    */

    $nextNumber =
        $operator->trips()->count() + 1;

    do {
        $tripCode =
            'TRP'
            . now()->format('ymd')
            . str_pad(
                (string) $nextNumber,
                4,
                '0',
                STR_PAD_LEFT
            );

        $nextNumber++;
    } while (
        Trip::where(
            'trip_code',
            $tripCode
        )->exists()
    );

    $data['trip_code'] = $tripCode;

    /*
    |--------------------------------------------------------------------------
    | Create Trip
    |--------------------------------------------------------------------------
    */

    $trip = $operator
        ->trips()
        ->create(
            $data + [
                'status' =>
                    'scheduled',

                'is_published' =>
                    $request->boolean(
                        'is_published'
                    ),
            ]
        );

    Audit::log(
        'Create trip',
        'Trips',
        $trip->trip_code
    );

    return back()->with(
        'success',
        'Trip scheduled successfully for '
        . number_format(
            (float) $route->distance_km,
            1
        )
        . ' km route.'
    );
}    

public function toggleTripPublish(Trip $trip)
{
    $this->own($trip);

    $trip->loadMissing('route');

    /*
    |--------------------------------------------------------------------------
    | Publishing Trip
    |--------------------------------------------------------------------------
    */

    if (!$trip->is_published) {

        if (!$trip->route) {
            return back()->with(
                'error',
                'The trip route could not be found.'
            );
        }

        /*
         * Minimum 50 km rule.
         */

        if (
            $trip->route->distance_km === null ||
            (float) $trip->route->distance_km < 50
        ) {
            return back()->with(
                'error',
                'Only routes of 50 km or more can be published for online booking.'
            );
        }

        /*
         * Route must be active.
         */

        if (!$trip->route->is_active) {
            return back()->with(
                'error',
                'This route is inactive and cannot be published.'
            );
        }

        /*
         * Only scheduled trips can be published.
         */

        if ($trip->status !== 'scheduled') {
            return back()->with(
                'error',
                'Started or completed trips cannot be published.'
            );
        }

        /*
         * Departure must still be in the future.
         */

        $departureAt = \Carbon\Carbon::parse(
    (string) $trip->service_date
    . ' '
    . (string) $trip->departure_time
        );

        if (!$departureAt->isFuture()) {
            return back()->with(
                'error',
                'Past trips cannot be published.'
            );
        }
    }

    $trip->update([
        'is_published' =>
            !$trip->is_published,
    ]);

    Audit::log(
        $trip->is_published
            ? 'Publish trip'
            : 'Unpublish trip',
        'Trips',
        $trip->trip_code
    );

    return back()->with(
        'success',
        $trip->is_published
            ? 'Trip published for passenger online booking.'
            : 'Trip unpublished.'
    );
}

    public function bookings()
    {
        $operator = $this->op();

        $bookings = Booking::with([
            'trip.route',
            'passenger',
            'payment',
        ])
            ->whereHas(
                'trip',
                function ($query) use ($operator) {
                    $query->where(
                        'operator_id',
                        $operator->id
                    );
                }
            )
            ->latest()
            ->get();

        return view(
            'operator.bookings',
            [
                'bookings' => $bookings,
            ]
        );
    }

    public function updateBooking(
        Request $request,
        Booking $booking
    ) {
        abort_unless(
            $booking
                ->trip()
                ->where(
                    'operator_id',
                    $this->op()->id
                )
                ->exists(),
            403
        );

        $data = $request->validate([
            'status' =>
                'required|in:confirmed,cancelled,completed,no_show',
        ]);

        $booking->update($data);

        return back()->with(
            'success',
            'Booking status updated.'
        );
    }

    public function tracking()
    {
        $operator = $this->op();

        $activeTrips = $operator
            ->trips()
            ->with([
                'bus',
                'route',
                'driver',
                'conductor',
                'liveLocation',
            ])
            ->where(
                'status',
                'active'
            )
            ->latest('updated_at')
            ->get();

        return view(
            'operator.tracking',
            [
                'activeTrips' =>
                    $activeTrips,
            ]
        );
    }

    public function payments()
    {
        $operator = $this->op();

        $payments = Payment::with(
            'booking.trip.route'
        )
            ->whereHas(
                'booking.trip',
                function ($query) use ($operator) {
                    $query->where(
                        'operator_id',
                        $operator->id
                    );
                }
            )
            ->latest()
            ->get();

        return view(
            'operator.payments',
            [
                'payments' => $payments,
            ]
        );
    }

    public function reports()
    {
        $operator = $this->op();

        $revenue = Payment::where(
            'status',
            'success'
        )
            ->whereHas(
                'booking.trip',
                function ($query) use ($operator) {
                    $query->where(
                        'operator_id',
                        $operator->id
                    );
                }
            )
            ->sum('amount');

        $bookings = Booking::whereHas(
            'trip',
            function ($query) use ($operator) {
                $query->where(
                    'operator_id',
                    $operator->id
                );
            }
        )->count();

        $completedTrips = $operator
            ->trips()
            ->where(
                'status',
                'completed'
            )
            ->count();

        $routeStats = Booking::select(
            'routes.name',
            DB::raw(
                'COUNT(bookings.id) bookings'
            ),
            DB::raw(
                'SUM(bookings.total) revenue'
            )
        )
            ->join(
                'trips',
                'trips.id',
                '=',
                'bookings.trip_id'
            )
            ->join(
                'routes',
                'routes.id',
                '=',
                'trips.route_id'
            )
            ->where(
                'trips.operator_id',
                $operator->id
            )
            ->groupBy(
                'routes.id',
                'routes.name'
            )
            ->get();

        return view(
            'operator.reports',
            [
                'revenue' => $revenue,
                'bookings' => $bookings,
                'completedTrips' =>
                    $completedTrips,
                'routeStats' =>
                    $routeStats,
            ]
        );
    }

    public function notifications()
    {
        $operator = $this->op();

        $notifications =
            Notification::where(
                function ($query) use ($operator) {
                    $query
                        ->whereNull(
                            'operator_id'
                        )
                        ->orWhere(
                            'operator_id',
                            $operator->id
                        );
                }
            )
                ->latest()
                ->get();

        return view(
            'operator.notifications',
            [
                'notifications' =>
                    $notifications,
            ]
        );
    }

    public function alerts()
    {
        $operator = $this->op();

        $alerts =
            EmergencyAlert::with([
                'trip.bus',
                'staff',
            ])
                ->where(
                    'operator_id',
                    $operator->id
                )
                ->latest()
                ->get();

        return view(
            'operator.alerts',
            [
                'alerts' => $alerts,
            ]
        );
    }

    public function resolveAlert(
        EmergencyAlert $alert
    ) {
        $this->own($alert);

        $alert->update([
            'status' =>
                'resolved',

            'resolved_at' =>
                now(),
        ]);

        return back()->with(
            'success',
            'Alert resolved.'
        );
    }

    private function generateStaffLoginId(
        string $role
    ): string {
        $prefix =
            strtolower($role) === 'driver'
                ? 'EBK-DRV-'
                : 'EBK-CON-';

        $highestNumber =
            Staff::where(
                'login_id',
                'like',
                $prefix . '%'
            )
                ->pluck('login_id')
                ->map(
                    function ($loginId) use ($prefix) {
                        $suffix =
                            str_replace(
                                $prefix,
                                '',
                                (string) $loginId
                            );

                        return ctype_digit($suffix)
                            ? (int) $suffix
                            : 0;
                    }
                )
                ->max() ?? 0;

        $nextNumber =
            $highestNumber + 1;

        do {
            $loginId =
                $prefix
                . str_pad(
                    (string) $nextNumber,
                    4,
                    '0',
                    STR_PAD_LEFT
                );

            $nextNumber++;
        } while (
            Staff::where(
                'login_id',
                $loginId
            )->exists()
        );

        return $loginId;
    }

    private function own(
        $model
    ): void {
        $operatorId =
            $model->operator_id
            ?? (
                $model->trip->operator_id
                ?? null
            );

        abort_unless(
            (int) $operatorId
                ===
                (int) $this
                    ->op()
                    ->id,
            403
        );
    }
}