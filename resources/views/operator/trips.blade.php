@extends('layouts.app')

@section('title', 'Trips & Schedule')
@section('header', 'Trips & Schedule')

@section('content')

@php
    $selectedServiceId = old('fixed_service_id');
@endphp

{{-- ================================================================
     CREATE TRIP
================================================================ --}}

<div class="card">

    <h2>
        Create Scheduled Trip
    </h2>

    <p style="color:#6b7890;">
        Select one of your configured Bus Route Services.
        The bus, master route, departure time and arrival time
        are loaded automatically from the saved service.
    </p>


    {{-- ============================================================
         VALIDATION ERRORS
    ============================================================ --}}

    @if($errors->any())

        <div
            style="
                margin-bottom:16px;
                padding:12px 14px;
                background:#fff1f1;
                border:1px solid #f3b6b6;
                border-radius:10px;
                color:#9b1c1c;
            "
        >

            <strong>
                Please check the following:
            </strong>

            <ul style="margin:8px 0 0 20px;">

                @foreach($errors->all() as $error)

                    <li>
                        {{ $error }}
                    </li>

                @endforeach

            </ul>

        </div>

    @endif


    {{-- ============================================================
         SUCCESS MESSAGE
    ============================================================ --}}

    @if(session('success'))

        <div class="alert alert-success">
            {{ session('success') }}
        </div>

    @endif


    {{-- ============================================================
         ERROR MESSAGE
    ============================================================ --}}

    @if(session('error'))

        <div class="alert alert-danger">
            {{ session('error') }}
        </div>

    @endif


    {{-- ============================================================
         CREATE TRIP FORM
    ============================================================ --}}

    <form
        method="POST"
        action="{{ route('operator.trips.store') }}"
        autocomplete="off"
    >

        @csrf


        <div class="form-grid">


            {{-- ====================================================
                 BUS ROUTE SERVICE
            ==================================================== --}}

            <div class="form-group">

                <label for="fixed_service_id">
                    Bus Route Service
                </label>

                <select
                    id="fixed_service_id"
                    name="fixed_service_id"
                    required
                >

                    <option value="">
                        -- Select Bus Route Service --
                    </option>

                    @foreach($services as $service)

                        <option
                            value="{{ $service->service_id }}"
                            data-route-number="{{ $service->route_number }}"
                            data-origin="{{ $service->origin }}"
                            data-destination="{{ $service->destination }}"
                            data-distance="{{ $service->distance_km ?? '' }}"
                            data-bus-number="{{ $service->bus_number }}"
                            data-bus-name="{{ $service->bus_name ?? '' }}"
                            data-bus-type="{{ $service->bus_type ?? '' }}"
                            data-starting-time="{{ $service->starting_time ?? '' }}"
                            data-return-time="{{ $service->return_time ?? '' }}"
                            data-service-name="{{ $service->service_name ?? '' }}"
                            @selected(
                                (string) $selectedServiceId
                                ===
                                (string) $service->service_id
                            )
                        >

                            {{ $service->bus_number }}

                            —

                            Route {{ $service->route_number }}

                            —

                            {{ $service->origin }}

                            →

                            {{ $service->destination }}

                            @if($service->service_name)
                                — {{ $service->service_name }}
                            @endif

                        </option>

                    @endforeach

                </select>

                <small style="color:#6b7890;">
                    Only active Bus Route Services already configured
                    for your operator account are shown here.
                </small>

            </div>


            {{-- ====================================================
                 SERVICE SUMMARY
            ==================================================== --}}

            <div class="form-group">

                <label>
                    Selected Service
                </label>

                <div
                    id="service-summary"
                    style="
                        min-height:42px;
                        padding:10px 12px;
                        border:1px solid #d9dee7;
                        border-radius:8px;
                        background:#f8fafc;
                        color:#667085;
                    "
                >
                    Select a Bus Route Service.
                </div>

            </div>


            {{-- ====================================================
                 DRIVER
            ==================================================== --}}

            <div class="form-group">

                <label for="driver_id">
                    Driver
                </label>

                <select
                    id="driver_id"
                    name="driver_id"
                >

                    <option value="">
                        -- Optional --
                    </option>

                    @foreach($drivers as $driver)

                        <option
                            value="{{ $driver->id }}"
                            @selected(
                                old('driver_id')
                                ==
                                $driver->id
                            )
                        >
                            {{ $driver->full_name }}
                        </option>

                    @endforeach

                </select>

            </div>


            {{-- ====================================================
                 CONDUCTOR
            ==================================================== --}}

            <div class="form-group">

                <label for="conductor_id">
                    Conductor
                </label>

                <select
                    id="conductor_id"
                    name="conductor_id"
                >

                    <option value="">
                        -- Optional --
                    </option>

                    @foreach($conductors as $conductor)

                        <option
                            value="{{ $conductor->id }}"
                            @selected(
                                old('conductor_id')
                                ==
                                $conductor->id
                            )
                        >
                            {{ $conductor->full_name }}
                        </option>

                    @endforeach

                </select>

            </div>


            {{-- ====================================================
                 DIRECTION
            ==================================================== --}}

            <div class="form-group">

                <label for="trip_type">
                    Direction
                </label>

                <select
                    id="trip_type"
                    name="trip_type"
                    required
                >

                    <option
                        value="starting"
                        @selected(
                            old(
                                'trip_type',
                                'starting'
                            )
                            ===
                            'starting'
                        )
                    >
                        Starting
                    </option>

                    <option
                        value="return"
                        @selected(
                            old('trip_type')
                            ===
                            'return'
                        )
                    >
                        Return
                    </option>

                </select>

                <small style="color:#6b7890;">
                    Starting and Return use the corresponding timetable
                    from the selected Bus Route Service.
                </small>

            </div>


            {{-- ====================================================
                 TIMETABLE PREVIEW
            ==================================================== --}}

            <div class="form-group">

                <label>
                    Timetable
                </label>

                <div
                    id="timetable-summary"
                    style="
                        min-height:42px;
                        padding:10px 12px;
                        border:1px solid #d9dee7;
                        border-radius:8px;
                        background:#f8fafc;
                        color:#667085;
                    "
                >
                    Select a service and direction.
                </div>

            </div>


            {{-- ====================================================
                 DATE
            ==================================================== --}}

            <div class="form-group">

                <label for="service_date">
                    Start Date
                </label>

                <input
                    id="service_date"
                    type="date"
                    name="service_date"
                    min="{{ now()->toDateString() }}"
                    value="{{ old('service_date') }}"
                    required
                >

            </div>


            {{-- ====================================================
                 SCHEDULE DAYS
            ==================================================== --}}

            <div class="form-group">

                <label for="schedule_days">
                    Schedule For
                </label>

                <select
                    id="schedule_days"
                    name="schedule_days"
                    required
                >

                    <option
                        value="1"
                        @selected(
                            old(
                                'schedule_days',
                                '1'
                            )
                            ==
                            '1'
                        )
                    >
                        Selected Date Only
                    </option>

                    <option
                        value="7"
                        @selected(
                            old('schedule_days')
                            ==
                            '7'
                        )
                    >
                        7 Consecutive Days
                    </option>

                </select>

                <small style="color:#6b7890;">
                    7 days creates one separate trip for each consecutive day
                    using the same Bus Route Service timetable.
                </small>

            </div>


            {{-- ====================================================
                 PUBLISH
            ==================================================== --}}

            <div class="form-group">

                <label
                    style="
                        display:flex;
                        align-items:center;
                        gap:8px;
                        cursor:pointer;
                    "
                >

                    <input
                        type="checkbox"
                        name="is_published"
                        value="1"
                        @checked(
                            old('is_published')
                        )
                    >

                    Publish for Passenger Booking

                </label>

                <small style="color:#6b7890;">
                    Passenger booking uses the booking points and timetable
                    configured for this Bus Route Service.
                </small>

            </div>

        </div>


        <div style="margin-top:18px;">

            <button
                type="submit"
                class="btn btn-primary"
            >
                Create Trip
            </button>

        </div>

    </form>

</div>


{{-- ================================================================
     SCHEDULED TRIPS
================================================================ --}}

<div
    class="card"
    style="margin-top:16px;"
>

    <h2>
        Scheduled Trips
    </h2>


    <div style="overflow-x:auto;">

        <table>

            <thead>

                <tr>
                    <th>Trip</th>
                    <th>Service</th>
                    <th>Direction</th>
                    <th>Date & Time</th>
                    <th>Master Route</th>
                    <th>Bus</th>
                    <th>Staff</th>
                    <th>Status</th>
                    <th>Published</th>
                    <th>Action</th>
                </tr>

            </thead>


            <tbody>

                @forelse($trips as $trip)

                    <tr>

                        {{-- =================================================
                             TRIP CODE
                        ================================================= --}}

                        <td>

                            <strong>
                                {{ $trip->trip_code }}
                            </strong>

                        </td>


                        {{-- =================================================
                             FIXED SERVICE
                        ================================================= --}}

                        <td>

                            @if($trip->fixedService)

                                <strong>
                                    Service #{{ $trip->fixed_service_id }}
                                </strong>

                                @if($trip->fixedService->service_name)

                                    <br>

                                    <small>
                                        {{ $trip->fixedService->service_name }}
                                    </small>

                                @endif

                            @else

                                <span style="color:#b42318;">
                                    Not Linked
                                </span>

                            @endif

                        </td>


                        {{-- =================================================
                             DIRECTION
                        ================================================= --}}

                        <td>

                            {{
                                $trip->trip_type
                                ===
                                'starting'
                                    ? 'Starting'
                                    : 'Return'
                            }}

                        </td>


                        {{-- =================================================
                             DATE & TIME
                        ================================================= --}}

                        <td>

                            {{
                                \Carbon\Carbon::parse(
                                    $trip->service_date
                                )->format(
                                    'd M Y'
                                )
                            }}

                            <br>

                            <small>
                                Departure:

                                {{
                                    \Carbon\Carbon::parse(
                                        $trip->departure_time
                                    )->format(
                                        'h:i A'
                                    )
                                }}
                            </small>


                            @if($trip->arrival_time)

                                <br>

                                <small>
                                    Arrival:

                                    {{
                                        \Carbon\Carbon::parse(
                                            $trip->arrival_time
                                        )->format(
                                            'h:i A'
                                        )
                                    }}
                                </small>

                            @endif

                        </td>


                        {{-- =================================================
                             MASTER ROUTE
                        ================================================= --}}

                        <td>

                            @if($trip->route)

                                <strong>
                                    Route
                                    {{ $trip->route->route_number ?: '-' }}
                                </strong>

                                <br>

                                <small>
                                    {{ $trip->route->origin }}
                                    →
                                    {{ $trip->route->destination }}
                                </small>


                                @if($trip->route->distance_km)

                                    <br>

                                    <small>
                                        {{
                                            number_format(
                                                (float) $trip->route->distance_km,
                                                1
                                            )
                                        }}
                                        km
                                    </small>

                                @endif

                            @else
                                -
                            @endif

                        </td>


                        {{-- =================================================
                             BUS
                        ================================================= --}}

                        <td>

                            {{ $trip->bus?->bus_number ?? '-' }}


                            @if($trip->bus?->bus_name)

                                <br>

                                <small>
                                    {{ $trip->bus->bus_name }}
                                </small>

                            @endif


                            @if($trip->bus?->bus_type)

                                <br>

                                <small>
                                    {{ $trip->bus->bus_type }}
                                </small>

                            @endif

                        </td>


                        {{-- =================================================
                             STAFF
                        ================================================= --}}

                        <td>

                            <div>
                                Driver:
                                {{ $trip->driver?->full_name ?? '-' }}
                            </div>

                            <div>
                                Conductor:
                                {{ $trip->conductor?->full_name ?? '-' }}
                            </div>

                        </td>


                        {{-- =================================================
                             STATUS
                        ================================================= --}}

                        <td>
                            {{ ucfirst($trip->status) }}
                        </td>


                        {{-- =================================================
                             PUBLISHED
                        ================================================= --}}

                        <td>

                            {{
                                $trip->is_published
                                    ? 'Yes'
                                    : 'No'
                            }}

                        </td>


                        {{-- =================================================
                             ACTION
                        ================================================= --}}

                        <td style="white-space:nowrap;">

                            @if(
                                $trip->status
                                ===
                                'scheduled'
                            )

                                <a
                                    href="{{
                                        route(
                                            'operator.trips.edit',
                                            $trip->id
                                        )
                                    }}"
                                    class="btn btn-sm"
                                    style="
                                        display:inline-block;
                                        margin-right:6px;
                                    "
                                >
                                    Edit
                                </a>

                            @endif


                            <form
                                method="POST"
                                action="{{
                                    route(
                                        'operator.trips.publish',
                                        $trip->id
                                    )
                                }}"
                                style="display:inline-block;"
                            >

                                @csrf
                                @method('PATCH')


                                <button
                                    type="submit"
                                    class="btn btn-sm"
                                >
                                    {{
                                        $trip->is_published
                                            ? 'Unpublish'
                                            : 'Publish'
                                    }}
                                </button>

                            </form>

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td
                            colspan="10"
                            style="
                                text-align:center;
                                padding:24px;
                            "
                        >
                            No scheduled trips found.
                        </td>

                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

</div>


{{-- ================================================================
     SERVICE SELECTION SCRIPT
================================================================ --}}

<script>
(function () {

    const serviceSelect =
        document.getElementById(
            'fixed_service_id'
        );

    const directionSelect =
        document.getElementById(
            'trip_type'
        );

    const serviceSummary =
        document.getElementById(
            'service-summary'
        );

    const timetableSummary =
        document.getElementById(
            'timetable-summary'
        );


    /*
    |--------------------------------------------------------------------------
    | Format Time
    |--------------------------------------------------------------------------
    */

    function formatTime(value) {

        if (!value) {
            return '-';
        }

        const parts =
            value.split(':');

        let hour =
            parseInt(
                parts[0],
                10
            );

        const minute =
            parts[1] ?? '00';

        const period =
            hour >= 12
                ? 'PM'
                : 'AM';

        hour =
            hour % 12;

        if (hour === 0) {
            hour = 12;
        }

        return `${hour}:${minute} ${period}`;
    }


    /*
    |--------------------------------------------------------------------------
    | Selected Option
    |--------------------------------------------------------------------------
    */

    function selectedServiceOption() {

        if (!serviceSelect) {
            return null;
        }

        const option =
            serviceSelect.options[
                serviceSelect.selectedIndex
            ];

        if (
            !option
            ||
            !option.value
        ) {
            return null;
        }

        return option;
    }


    /*
    |--------------------------------------------------------------------------
    | Update Service Summary
    |--------------------------------------------------------------------------
    */

    function updateService() {

        const option =
            selectedServiceOption();


        if (!option) {

            serviceSummary.textContent =
                'Select a Bus Route Service.';

            timetableSummary.textContent =
                'Select a service and direction.';

            return;
        }


        const routeNumber =
            option.dataset.routeNumber
            || '-';

        const origin =
            option.dataset.origin
            || '-';

        const destination =
            option.dataset.destination
            || '-';

        const distance =
            option.dataset.distance
            || '';

        const busNumber =
            option.dataset.busNumber
            || '-';

        const busName =
            option.dataset.busName
            || '';

        const busType =
            option.dataset.busType
            || '';

        const serviceName =
            option.dataset.serviceName
            || '';


        let summary =
            `Bus ${busNumber}`;

        if (busName) {
            summary +=
                ` (${busName})`;
        }

        if (busType) {
            summary +=
                ` • ${busType}`;
        }

        summary +=
            ` • Route ${routeNumber}`;

        summary +=
            ` • ${origin} → ${destination}`;

        if (distance) {
            summary +=
                ` • ${distance} km`;
        }

        if (serviceName) {
            summary +=
                ` • ${serviceName}`;
        }


        serviceSummary.textContent =
            summary;


        updateTimetable();
    }


    /*
    |--------------------------------------------------------------------------
    | Update Timetable
    |--------------------------------------------------------------------------
    */

    function updateTimetable() {

        const option =
            selectedServiceOption();


        if (!option) {

            timetableSummary.textContent =
                'Select a service and direction.';

            return;
        }


        const direction =
            directionSelect.value;

        const startingTime =
            option.dataset.startingTime
            || '';

        const returnTime =
            option.dataset.returnTime
            || '';


        if (
            direction ===
            'starting'
        ) {

            timetableSummary.textContent =
                startingTime
                    ? `Starting departure: ${formatTime(startingTime)}`
                    : 'Starting timetable is not configured.';

            return;
        }


        timetableSummary.textContent =
            returnTime
                ? `Return departure: ${formatTime(returnTime)}`
                : 'Return timetable is not configured.';
    }


    /*
    |--------------------------------------------------------------------------
    | Events
    |--------------------------------------------------------------------------
    */

    if (serviceSelect) {

        serviceSelect.addEventListener(
            'change',
            updateService
        );
    }


    if (directionSelect) {

        directionSelect.addEventListener(
            'change',
            updateTimetable
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Initial State
    |--------------------------------------------------------------------------
    */

    updateService();

})();
</script>

@endsection