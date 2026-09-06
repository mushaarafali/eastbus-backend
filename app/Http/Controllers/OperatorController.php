<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Bus;
use App\Models\EmergencyAlert;
use App\Models\Notification;
use App\Models\Operator;
use App\Models\Payment;
use App\Models\Seat;
use App\Models\Staff;
use App\Models\Trip;
use App\Support\Audit;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OperatorController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Current Operator
    |--------------------------------------------------------------------------
    */

    private function op(): Operator
    {
        return auth()->user()->operator
            ?: abort(
                403,
                'Operator profile missing.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */

    public function dashboard()
    {
        $operator = $this->op();

        $routeCount = DB::table('fixed_services')
            ->where('operator_id', $operator->id)
            ->where('is_active', true)
            ->distinct()
            ->count('route_id');

        $stats = [
            'buses' => $operator->buses()->count(),

            'staff' => $operator->staff()->count(),

            'routes' => $routeCount,

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
                'fixedService',
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
            compact(
                'operator',
                'stats',
                'upcoming'
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Profile
    |--------------------------------------------------------------------------
    */

    public function profile()
    {
        return view(
            'operator.profile',
            [
                'operator' => $this->op(),
            ]
        );
    }

    public function updateProfile(
        Request $request
    ) {
        $operator = $this->op();

        $data = $request->validate([
            'company_name' => [
                'required',
                'string',
                'max:150',
            ],

            'owner_name' => [
                'required',
                'string',
                'max:150',
            ],

            'phone' => [
                'required',
                'string',
                'max:30',
            ],

            'address' => [
                'nullable',
                'string',
                'max:255',
            ],

            'permit_or_registration_no' => [
                'nullable',
                'string',
                'max:100',
            ],
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

    /*
    |--------------------------------------------------------------------------
    | Buses
    |--------------------------------------------------------------------------
    */

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

    public function storeBus(
        Request $request
    ) {
        $operator = $this->op();

        $data = $request->validate([
            'bus_number' => [
                'required',
                'string',
                'max:30',
                'unique:buses,bus_number',
            ],

            'bus_name' => [
                'nullable',
                'string',
                'max:100',
            ],

            'route_permit_number' => [
                'required',
                'string',
                'max:100',
            ],

            'seat_count' => [
                'required',
                'integer',

                function (
                    $attribute,
                    $value,
                    $fail
                ) use ($request) {
                    $type = $request->input(
                        'bus_type'
                    );

                    if (
                        $type === 'A/C'
                        &&
                        !in_array(
                            (int) $value,
                            [49, 51, 53],
                            true
                        )
                    ) {
                        $fail(
                            'A/C buses must have 49, 51 or 53 seats.'
                        );
                    }

                    if (
                        in_array(
                            $type,
                            [
                                'Normal',
                                'Semi-Luxury',
                            ],
                            true
                        )
                        &&
                        (int) $value !== 54
                    ) {
                        $fail(
                            'Normal and Semi-Luxury buses must have exactly 54 seats.'
                        );
                    }
                },
            ],

            'bus_type' => [
                'required',
                'in:Normal,Semi-Luxury,A/C',
            ],

            'facilities' => [
                'nullable',
                'string',
                'max:500',
            ],
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
            $bus->seats()->create([
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
                'string',
                'max:30',

                Rule::unique(
                    'buses',
                    'bus_number'
                )->ignore($bus->id),
            ],

            'bus_name' => [
                'nullable',
                'string',
                'max:100',
            ],

            'route_permit_number' => [
                'required',
                'string',
                'max:100',
            ],

            'bus_type' => [
                'required',
                'in:Normal,Semi-Luxury,A/C',
            ],

            'facilities' => [
                'nullable',
                'string',
                'max:500',
            ],

            'is_active' => [
                'nullable',
                'boolean',
            ],
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

    /*
    |--------------------------------------------------------------------------
    | Seats
    |--------------------------------------------------------------------------
    */

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
            (int) $seat->bus_id ===
            (int) $bus->id,
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

    /*
    |--------------------------------------------------------------------------
    | Staff
    |--------------------------------------------------------------------------
    */

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
            'full_name' => [
                'required',
                'string',
                'max:150',
            ],

            'role' => [
                'required',
                'in:driver,conductor',
            ],

            'nic' => [
                'required',
                'string',
                'max:20',
            ],

            'driving_licence_no' => [
                'nullable',
                'string',
                'max:50',
            ],

            'ntc_licence_no' => [
                'required',
                'string',
                'max:50',
            ],

            'phone' => [
                'required',
                'string',
                'max:30',
            ],

            'email' => [
                'nullable',
                'email',
            ],

            'password' => [
                'required',
                'min:8',
            ],
        ]);

        if (
            $data['role'] === 'driver'
            &&
            empty(
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

    /*
    |--------------------------------------------------------------------------
    | Trips & Schedule
    |--------------------------------------------------------------------------
    */

    public function trips()
    {
        $operator = $this->op();

        /*
         * Exact configured bus services.
         */
        $services = DB::table(
            'fixed_services as fs'
        )
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
                $operator->id
            )
            ->where(
                'fs.is_active',
                true
            )
            ->where(
                'r.is_active',
                true
            )
            ->where(
                'b.is_active',
                true
            )
            ->select(
                'fs.id as service_id',
                'fs.route_id',
                'fs.bus_id',
                'fs.service_name',
                'fs.starting_time',
                'fs.return_time',
                'r.route_number',
                'r.origin',
                'r.destination',
                'r.distance_km',
                'b.bus_number',
                'b.bus_name',
                'b.bus_type'
            )
            ->orderBy('r.route_number')
            ->orderBy('b.bus_number')
            ->get();

        $trips = $operator
            ->trips()
            ->with([
                'fixedService',
                'route',
                'bus',
                'driver',
                'conductor',
            ])
            ->latest('service_date')
            ->get();

        $drivers = $operator
            ->staff()
            ->where('role', 'driver')
            ->where('is_active', true)
            ->orderBy('full_name')
            ->get();

        $conductors = $operator
            ->staff()
            ->where('role', 'conductor')
            ->where('is_active', true)
            ->orderBy('full_name')
            ->get();

        return view(
            'operator.trips',
            compact(
                'services',
                'trips',
                'drivers',
                'conductors'
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Store Trip
    |--------------------------------------------------------------------------
    */

    public function storeTrip(
        Request $request
    ) {
        $operator = $this->op();

        $data = $request->validate([
            'fixed_service_id' => [
                'required',
                'integer',
                'exists:fixed_services,id',
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
                'in:starting,return',
            ],

            'service_date' => [
                'required',
                'date',
                'after_or_equal:today',
            ],

            'schedule_days' => [
                'required',
                'integer',
                'in:1,7',
            ],

            'is_published' => [
                'nullable',
                'boolean',
            ],
        ]);

        /*
         * Exact Fixed Service.
         *
         * Do not trust route_id or bus_id from browser.
         */
        $service = DB::table(
            'fixed_services'
        )
            ->where(
                'id',
                $data['fixed_service_id']
            )
            ->where(
                'operator_id',
                $operator->id
            )
            ->where(
                'is_active',
                true
            )
            ->first();

        if (!$service) {
            throw ValidationException::withMessages([
                'fixed_service_id' =>
                    'The selected Bus Route Service is invalid or inactive.',
            ]);
        }

        /*
         * Route from Fixed Service.
         */
        $route = DB::table('routes')
            ->where(
                'id',
                $service->route_id
            )
            ->where(
                'is_active',
                true
            )
            ->first();

        if (!$route) {
            throw ValidationException::withMessages([
                'fixed_service_id' =>
                    'The Master Route linked to this service is inactive.',
            ]);
        }

        /*
         * Bus from Fixed Service.
         */
        $bus = Bus::where(
            'id',
            $service->bus_id
        )
            ->where(
                'operator_id',
                $operator->id
            )
            ->first();

        if (
            !$bus
            ||
            !$bus->is_active
        ) {
            throw ValidationException::withMessages([
                'fixed_service_id' =>
                    'The bus linked to this service is inactive or unavailable.',
            ]);
        }

        /*
         * Staff validation.
         */
        $this->validateTripStaff(
            $operator,
            $data
        );

        /*
         * Exact service timetable.
         */
        $schedule =
            $this->serviceScheduleTimes(
                (int) $service->id,
                $data['trip_type']
            );

        $scheduleDays =
            (int) $data['schedule_days'];

        $startDate =
            Carbon::parse(
                $data['service_date']
            )->startOfDay();

        $firstDeparture =
            Carbon::createFromFormat(
                'Y-m-d H:i:s',
                $startDate->format('Y-m-d')
                . ' '
                . $schedule['departure_time'],
                config('app.timezone')
            );

        if (
            !$firstDeparture->isFuture()
        ) {
            throw ValidationException::withMessages([
                'service_date' =>
                    'The first scheduled trip departure must be in the future.',
            ]);
        }

        DB::transaction(
            function () use (
                $operator,
                $service,
                $route,
                $bus,
                $data,
                $scheduleDays,
                $startDate,
                $schedule,
                $request
            ) {
                for (
                    $i = 0;
                    $i < $scheduleDays;
                    $i++
                ) {
                    $serviceDate =
                        $startDate
                            ->copy()
                            ->addDays($i);

                    /*
                     * Duplicate check now uses
                     * fixed_service_id.
                     */
                    $duplicate =
                        Trip::where(
                            'operator_id',
                            $operator->id
                        )
                            ->where(
                                'fixed_service_id',
                                $service->id
                            )
                            ->where(
                                'trip_type',
                                $data['trip_type']
                            )
                            ->whereDate(
                                'service_date',
                                $serviceDate->format(
                                    'Y-m-d'
                                )
                            )
                            ->where(
                                'status',
                                '!=',
                                'cancelled'
                            )
                            ->exists();

                    if ($duplicate) {
                        throw ValidationException::withMessages([
                            'service_date' =>
                                'A trip already exists for this Bus Route Service, direction and date: '
                                . $serviceDate->format('Y-m-d')
                                . '.',
                        ]);
                    }

                    $trip = $operator
                        ->trips()
                        ->create([
                            /*
                             * Exact service link.
                             */
                            'fixed_service_id' =>
                                $service->id,

                            /*
                             * Keep route_id and bus_id
                             * for direct reporting/searching.
                             */
                            'route_id' =>
                                $route->id,

                            'bus_id' =>
                                $bus->id,

                            'driver_id' =>
                                $data['driver_id']
                                ?? null,

                            'conductor_id' =>
                                $data['conductor_id']
                                ?? null,

                            'trip_code' =>
                                $this->generateTripCode(
                                    $operator
                                ),

                            'trip_type' =>
                                $data['trip_type'],

                            'service_date' =>
                                $serviceDate->format(
                                    'Y-m-d'
                                ),

                            'departure_time' =>
                                $schedule[
                                    'departure_time'
                                ],

                            'arrival_time' =>
                                $schedule[
                                    'arrival_time'
                                ],

                            'fare' => 0,

                            'status' =>
                                'scheduled',

                            'is_published' =>
                                $request->boolean(
                                    'is_published'
                                ),
                        ]);

                    Audit::log(
                        'Create trip',
                        'Trips',
                        $trip->trip_code
                    );
                }
            }
        );

        return back()->with(
            'success',
            $scheduleDays === 7
                ? 'Seven-day trip schedule created successfully.'
                : 'Trip scheduled successfully.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Publish / Unpublish Trip
    |--------------------------------------------------------------------------
    */

    public function toggleTripPublish(
        Trip $trip
    ) {
        $this->own($trip);

        if (!$trip->is_published) {
            if (
                $trip->status !==
                'scheduled'
            ) {
                return back()->with(
                    'error',
                    'Only scheduled trips can be published.'
                );
            }

            $serviceDate =
                Carbon::parse(
                    $trip->service_date
                )->format('Y-m-d');

            $departureTime =
                Carbon::parse(
                    $trip->departure_time
                )->format('H:i:s');

            $departureAt =
                Carbon::createFromFormat(
                    'Y-m-d H:i:s',
                    $serviceDate
                    . ' '
                    . $departureTime,
                    config('app.timezone')
                );

            if (
                !$departureAt->isFuture()
            ) {
                return back()->with(
                    'error',
                    'Past or started trips cannot be published.'
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
                ? 'Trip published successfully.'
                : 'Trip unpublished successfully.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Edit Trip
    |--------------------------------------------------------------------------
    */

    public function editTrip(
        Trip $trip
    ) {
        $this->own($trip);

        $operator = $this->op();

        return view(
            'operator.trip_edit',
            [
                'trip' =>
                    $trip->load([
                        'fixedService',
                        'route',
                        'bus',
                        'driver',
                        'conductor',
                    ]),

                'drivers' =>
                    $operator
                        ->staff()
                        ->where(
                            'role',
                            'driver'
                        )
                        ->where(
                            'is_active',
                            true
                        )
                        ->orderBy(
                            'full_name'
                        )
                        ->get(),

                'conductors' =>
                    $operator
                        ->staff()
                        ->where(
                            'role',
                            'conductor'
                        )
                        ->where(
                            'is_active',
                            true
                        )
                        ->orderBy(
                            'full_name'
                        )
                        ->get(),
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Update Trip
    |--------------------------------------------------------------------------
    */

    public function updateTrip(
        Request $request,
        Trip $trip
    ) {
        $this->own($trip);

        if (
            $trip->status !==
            'scheduled'
        ) {
            return back()->with(
                'error',
                'Only scheduled trips can be edited.'
            );
        }

        $operator = $this->op();

        $data = $request->validate([
            'driver_id' => [
                'nullable',
                'integer',
            ],

            'conductor_id' => [
                'nullable',
                'integer',
            ],

            'service_date' => [
                'required',
                'date',
                'after_or_equal:today',
            ],
        ]);

        $this->validateTripStaff(
            $operator,
            $data
        );

        /*
         * Use exact fixed_service_id saved in trip.
         */
        if (!$trip->fixed_service_id) {
            throw ValidationException::withMessages([
                'service_date' =>
                    'This trip does not have a Bus Route Service linked to it.',
            ]);
        }

        $service = DB::table(
            'fixed_services'
        )
            ->where(
                'id',
                $trip->fixed_service_id
            )
            ->where(
                'operator_id',
                $operator->id
            )
            ->where(
                'is_active',
                true
            )
            ->first();

        if (!$service) {
            throw ValidationException::withMessages([
                'service_date' =>
                    'The Bus Route Service linked to this trip is inactive or unavailable.',
            ]);
        }

        $schedule =
            $this->serviceScheduleTimes(
                (int) $service->id,
                (string) $trip->trip_type
            );

        $serviceDate =
            Carbon::parse(
                $data['service_date']
            )->format('Y-m-d');

        $departureAt =
            Carbon::createFromFormat(
                'Y-m-d H:i:s',
                $serviceDate
                . ' '
                . $schedule['departure_time'],
                config('app.timezone')
            );

        if (
            !$departureAt->isFuture()
        ) {
            throw ValidationException::withMessages([
                'service_date' =>
                    'The trip departure must be in the future.',
            ]);
        }

        $duplicate =
            Trip::where(
                'operator_id',
                $operator->id
            )
                ->where(
                    'fixed_service_id',
                    $service->id
                )
                ->where(
                    'trip_type',
                    $trip->trip_type
                )
                ->whereDate(
                    'service_date',
                    $serviceDate
                )
                ->where(
                    'id',
                    '!=',
                    $trip->id
                )
                ->where(
                    'status',
                    '!=',
                    'cancelled'
                )
                ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'service_date' =>
                    'Another trip already exists for this Bus Route Service, direction and date.',
            ]);
        }

        $trip->update([
            'driver_id' =>
                $data['driver_id']
                ?? null,

            'conductor_id' =>
                $data['conductor_id']
                ?? null,

            'service_date' =>
                $serviceDate,

            'departure_time' =>
                $schedule[
                    'departure_time'
                ],

            'arrival_time' =>
                $schedule[
                    'arrival_time'
                ],

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

    /*
    |--------------------------------------------------------------------------
    | Bookings
    |--------------------------------------------------------------------------
    */

    public function bookings()
    {
        $operator = $this->op();

        $bookings =
            Booking::with([
                'trip.route',
                'trip.fixedService',
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
            compact('bookings')
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
            'status' => [
                'required',
                'in:confirmed,cancelled,completed,no_show',
            ],
        ]);

        $booking->update($data);

        return back()->with(
            'success',
            'Booking status updated.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Tracking
    |--------------------------------------------------------------------------
    */

    public function tracking()
    {
        $operator = $this->op();

        $activeTrips =
            $operator
                ->trips()
                ->with([
                    'fixedService',
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
            compact('activeTrips')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Payments
    |--------------------------------------------------------------------------
    */

    public function payments()
    {
        $operator = $this->op();

        $payments =
            Payment::with(
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
            compact('payments')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Reports
    |--------------------------------------------------------------------------
    */

    public function reports()
    {
        $operator = $this->op();

        $revenue =
            Payment::where(
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

        $bookings =
            Booking::whereHas(
                'trip',
                function ($query) use ($operator) {
                    $query->where(
                        'operator_id',
                        $operator->id
                    );
                }
            )->count();

        $completedTrips =
            $operator
                ->trips()
                ->where(
                    'status',
                    'completed'
                )
                ->count();

        $routeStats =
            Booking::select(
                'routes.name',
                'routes.route_number',
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
                    'routes.name',
                    'routes.route_number'
                )
                ->get();

        return view(
            'operator.reports',
            compact(
                'revenue',
                'bookings',
                'completedTrips',
                'routeStats'
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    */

    public function notifications()
    {
        $operator = $this->op();

        $notifications =
            Notification::where(
                function ($query) use ($operator) {
                    $query
                        ->whereNull('operator_id')
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
            compact('notifications')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Emergency Alerts
    |--------------------------------------------------------------------------
    */

    public function alerts()
    {
        $operator = $this->op();

        $alerts =
            EmergencyAlert::with([
                'trip.bus',
                'trip.route',
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
            compact('alerts')
        );
    }

    public function resolveAlert(
        EmergencyAlert $alert
    ) {
        $this->own($alert);

        $alert->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        return back()->with(
            'success',
            'Alert resolved.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Trip Staff
    |--------------------------------------------------------------------------
    */

    private function validateTripStaff(
        Operator $operator,
        array $data
    ): void {
        if (
            !empty(
                $data['driver_id']
            )
        ) {
            $driver = Staff::where(
                'id',
                $data['driver_id']
            )
                ->where(
                    'operator_id',
                    $operator->id
                )
                ->first();

            if (
                !$driver
                ||
                $driver->role !== 'driver'
                ||
                !$driver->is_active
            ) {
                throw ValidationException::withMessages([
                    'driver_id' =>
                        'Select an active driver belonging to your operator account.',
                ]);
            }
        }

        if (
            !empty(
                $data['conductor_id']
            )
        ) {
            $conductor = Staff::where(
                'id',
                $data['conductor_id']
            )
                ->where(
                    'operator_id',
                    $operator->id
                )
                ->first();

            if (
                !$conductor
                ||
                $conductor->role !== 'conductor'
                ||
                !$conductor->is_active
            ) {
                throw ValidationException::withMessages([
                    'conductor_id' =>
                        'Select an active conductor belonging to your operator account.',
                ]);
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Fixed Service Schedule Times
    |--------------------------------------------------------------------------
    */

    private function serviceScheduleTimes(
        int $serviceId,
        string $direction
    ): array {
        $stops =
            DB::table(
                'fixed_service_stops'
            )
                ->where(
                    'fixed_service_id',
                    $serviceId
                )
                ->where(
                    'direction',
                    $direction
                )
                ->orderBy(
                    'stop_order'
                )
                ->get();

        if (
            $stops->count() < 2
        ) {
            throw ValidationException::withMessages([
                'trip_type' =>
                    'The selected Bus Route Service does not have a complete '
                    . ucfirst($direction)
                    . ' timetable.',
            ]);
        }

        $first =
            $stops->first();

        $last =
            $stops->last();

        $firstTime =
            $first->departure_time
            ?? $first->arrival_time
            ?? null;

        $lastTime =
            $last->arrival_time
            ?? $last->departure_time
            ?? null;

        if (
            !$firstTime
            ||
            !$lastTime
        ) {
            throw ValidationException::withMessages([
                'trip_type' =>
                    'The selected Bus Route Service timetable is missing departure or arrival time.',
            ]);
        }

        return [
            'departure_time' =>
                Carbon::parse(
                    $firstTime
                )->format('H:i:s'),

            'arrival_time' =>
                Carbon::parse(
                    $lastTime
                )->format('H:i:s'),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Generate Trip Code
    |--------------------------------------------------------------------------
    */

    private function generateTripCode(
        Operator $operator
    ): string {
        $prefix =
            'TRP'
            . now()->format('ymd');

        $nextNumber =
            $operator
                ->trips()
                ->count()
            + 1;

        do {
            $tripCode =
                $prefix
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

        return $tripCode;
    }

    /*
    |--------------------------------------------------------------------------
    | Generate Staff Login ID
    |--------------------------------------------------------------------------
    */

    private function generateStaffLoginId(
        string $role
    ): string {
        $prefix =
            strtolower($role)
            === 'driver'
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
                    function (
                        $loginId
                    ) use ($prefix) {
                        $suffix =
                            str_replace(
                                $prefix,
                                '',
                                (string) $loginId
                            );

                        return ctype_digit(
                            $suffix
                        )
                            ? (int) $suffix
                            : 0;
                    }
                )
                ->max()
            ?? 0;

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

    /*
    |--------------------------------------------------------------------------
    | Ownership Check
    |--------------------------------------------------------------------------
    */

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
            (int) $this->op()->id,
            403
        );
    }
}