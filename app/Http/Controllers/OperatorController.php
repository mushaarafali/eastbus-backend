<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
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
use Illuminate\Validation\ValidationException;

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
                    ->get(),

                'buses' => $operator
                    ->buses()
                    ->where(
                        'is_active',
                        true
                    )
                    ->get(),

                'routes' => $operator
                    ->routes()
                    ->where(
                        'is_active',
                        true
                    )
                    ->get(),

                'drivers' => $operator
                    ->staff()
                    ->where(
                        'role',
                        'driver'
                    )
                    ->where(
                        'is_active',
                        true
                    )
                    ->get(),

                'conductors' => $operator
                    ->staff()
                    ->where(
                        'role',
                        'conductor'
                    )
                    ->where(
                        'is_active',
                        true
                    )
                    ->get(),
            ]
        );
    }

    public function storeTrip(Request $request)
    {
        $operator = $this->op();

        $data = $request->validate([
            'route_id' => ['required', 'integer'],
            'bus_id' => ['required', 'integer'],
            'driver_id' => ['nullable', 'integer'],
            'conductor_id' => ['nullable', 'integer'],
            'trip_type' => ['required', 'in:starting,return'],
            'service_date' => ['required', 'date', 'after_or_equal:today'],
            'schedule_days' => ['required', 'integer', 'in:1,7'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $route = Route::findOrFail($data['route_id']);
        $bus = Bus::findOrFail($data['bus_id']);

        $this->own($route);
        $this->own($bus);

        if (!$route->is_active) {
            throw ValidationException::withMessages([
                'route_id' => 'The selected route is not active.',
            ]);
        }

        if (!$bus->is_active) {
            throw ValidationException::withMessages([
                'bus_id' => 'The selected bus is not active.',
            ]);
        }

        if (!empty($data['driver_id'])) {
            $driver = Staff::findOrFail($data['driver_id']);

            $this->own($driver);

            if ($driver->role !== 'driver' || !$driver->is_active) {
                throw ValidationException::withMessages([
                    'driver_id' => 'Select an active driver.',
                ]);
            }
        }

        if (!empty($data['conductor_id'])) {
            $conductor = Staff::findOrFail($data['conductor_id']);

            $this->own($conductor);

            if ($conductor->role !== 'conductor' || !$conductor->is_active) {
                throw ValidationException::withMessages([
                    'conductor_id' => 'Select an active conductor.',
                ]);
            }
        }

        $schedule = $this->routeScheduleTimes(
            (int) $route->id,
            $data['trip_type']
        );

        $scheduleDays = (int) $data['schedule_days'];
        $startDate = Carbon::parse($data['service_date'])->startOfDay();

        $firstDeparture = Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $startDate->format('Y-m-d') . ' ' . $schedule['departure_time'],
            config('app.timezone')
        );

        if (!$firstDeparture->isFuture()) {
            throw ValidationException::withMessages([
                'service_date' => 'The first scheduled trip departure must be in the future.',
            ]);
        }

        DB::transaction(function () use (
            $operator,
            $route,
            $bus,
            $data,
            $scheduleDays,
            $startDate,
            $schedule,
            $request
        ) {
            for ($i = 0; $i < $scheduleDays; $i++) {
                $serviceDate = $startDate->copy()->addDays($i);

                $duplicate = Trip::where('operator_id', $operator->id)
                    ->where('route_id', $route->id)
                    ->where('bus_id', $bus->id)
                    ->where('trip_type', $data['trip_type'])
                    ->whereDate('service_date', $serviceDate->format('Y-m-d'))
                    ->where('status', '!=', 'cancelled')
                    ->exists();

                if ($duplicate) {
                    throw ValidationException::withMessages([
                        'service_date' =>
                            'A trip already exists for this bus, route, direction and date: '
                            . $serviceDate->format('Y-m-d') . '.',
                    ]);
                }

                $trip = $operator->trips()->create([
                    'route_id' => $route->id,
                    'bus_id' => $bus->id,
                    'driver_id' => $data['driver_id'] ?? null,
                    'conductor_id' => $data['conductor_id'] ?? null,
                    'trip_code' => $this->generateTripCode($operator),
                    'trip_type' => $data['trip_type'],
                    'service_date' => $serviceDate->format('Y-m-d'),
                    'departure_time' => $schedule['departure_time'],
                    'arrival_time' => $schedule['arrival_time'],
                    'fare' => 0,
                    'status' => 'scheduled',
                    'is_published' => $request->boolean('is_published'),
                ]);

                Audit::log(
                    'Create trip',
                    'Trips',
                    $trip->trip_code
                );
            }
        });

        return back()->with(
            'success',
            $scheduleDays === 7
                ? 'Seven-day trip schedule created successfully.'
                : 'Trip scheduled successfully.'
        );
    }

    public function toggleTripPublish(Trip $trip)
    {
        $this->own($trip);

        if (!$trip->is_published) {
            if ($trip->status !== 'scheduled') {
                return back()->with(
                    'error',
                    'Only scheduled trips can be published.'
                );
            }

            $serviceDate = Carbon::parse($trip->service_date)->format('Y-m-d');
            $departureTime = Carbon::parse($trip->departure_time)->format('H:i:s');

            $departureAt = Carbon::createFromFormat(
                'Y-m-d H:i:s',
                $serviceDate . ' ' . $departureTime,
                config('app.timezone')
            );

            if (!$departureAt->isFuture()) {
                return back()->with(
                    'error',
                    'Past or started trips cannot be published.'
                );
            }
        }

        $trip->update([
            'is_published' => !$trip->is_published,
        ]);

        Audit::log(
            $trip->is_published ? 'Publish trip' : 'Unpublish trip',
            'Trips',
            $trip->trip_code
        );

        return back()->with(
            'success',
            $trip->is_published
                ? 'Trip published successfully.'
                : 'Trip unpublished successfully.'
        );
    }

    public function editTrip(Trip $trip)
    {
        $this->own($trip);
        $operator = $this->op();

        return view('operator.trip_edit', [
            'trip' => $trip->load(['route', 'bus', 'driver', 'conductor']),
            'drivers' => $operator->staff()->where('role', 'driver')->where('is_active', true)->orderBy('full_name')->get(),
            'conductors' => $operator->staff()->where('role', 'conductor')->where('is_active', true)->orderBy('full_name')->get(),
        ]);
    }

    public function updateTrip(Request $request, Trip $trip)
    {
        $this->own($trip);

        if ($trip->status !== 'scheduled') {
            return back()->with(
                'error',
                'Only scheduled trips can be edited.'
            );
        }

        $data = $request->validate([
            'driver_id' => ['nullable', 'integer'],
            'conductor_id' => ['nullable', 'integer'],
            'service_date' => ['required', 'date', 'after_or_equal:today'],
        ]);

        if (!empty($data['driver_id'])) {
            $driver = Staff::findOrFail($data['driver_id']);

            $this->own($driver);

            if ($driver->role !== 'driver' || !$driver->is_active) {
                throw ValidationException::withMessages([
                    'driver_id' => 'Select an active driver.',
                ]);
            }
        }

        if (!empty($data['conductor_id'])) {
            $conductor = Staff::findOrFail($data['conductor_id']);

            $this->own($conductor);

            if ($conductor->role !== 'conductor' || !$conductor->is_active) {
                throw ValidationException::withMessages([
                    'conductor_id' => 'Select an active conductor.',
                ]);
            }
        }

        $schedule = $this->routeScheduleTimes(
            (int) $trip->route_id,
            (string) $trip->trip_type
        );

        $serviceDate = Carbon::parse(
            $data['service_date']
        )->format('Y-m-d');

        $departureAt = Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $serviceDate . ' ' . $schedule['departure_time'],
            config('app.timezone')
        );

        if (!$departureAt->isFuture()) {
            throw ValidationException::withMessages([
                'service_date' => 'The trip departure must be in the future.',
            ]);
        }

        $duplicate = Trip::where('operator_id', $trip->operator_id)
            ->where('route_id', $trip->route_id)
            ->where('bus_id', $trip->bus_id)
            ->where('trip_type', $trip->trip_type)
            ->whereDate('service_date', $serviceDate)
            ->where('id', '!=', $trip->id)
            ->where('status', '!=', 'cancelled')
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'service_date' =>
                    'Another trip already exists for this bus, route, direction and date.',
            ]);
        }

        $trip->update([
            'driver_id' => $data['driver_id'] ?? null,
            'conductor_id' => $data['conductor_id'] ?? null,
            'service_date' => $serviceDate,
            'departure_time' => $schedule['departure_time'],
            'arrival_time' => $schedule['arrival_time'],
            'fare' => 0,
        ]);

        Audit::log(
            'Update trip',
            'Trips',
            $trip->trip_code
        );

        return redirect()
            ->route('operator.trips')
            ->with(
                'success',
                'Trip updated successfully.'
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

    private function routeScheduleTimes(
        int $routeId,
        string $direction
    ): array {
        $stops = DB::table('route_booking_stops')
            ->where('route_id', $routeId)
            ->where('direction', $direction)
            ->where('is_active', true)
            ->orderBy('stop_order')
            ->get();

        if ($stops->count() < 2) {
            throw ValidationException::withMessages([
                'trip_type' =>
                    'The selected route does not have a complete '
                    . ucfirst($direction)
                    . ' booking timetable.',
            ]);
        }

        $first = $stops->first();
        $last = $stops->last();

        if (
            empty($first->schedule_time) ||
            empty($last->schedule_time)
        ) {
            throw ValidationException::withMessages([
                'trip_type' =>
                    'The selected route timetable is missing departure or arrival time.',
            ]);
        }

        return [
            'departure_time' => Carbon::parse(
                $first->schedule_time
            )->format('H:i:s'),

            'arrival_time' => Carbon::parse(
                $last->schedule_time
            )->format('H:i:s'),
        ];
    }

    private function generateTripCode(Operator $operator): string
    {
        $prefix = 'TRP' . now()->format('ymd');
        $nextNumber = $operator->trips()->count() + 1;

        do {
            $tripCode = $prefix . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
            $nextNumber++;
        } while (Trip::where('trip_code', $tripCode)->exists());

        return $tripCode;
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