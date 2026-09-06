@extends('layouts.app')

@section('title', 'Edit Scheduled Trip')
@section('header', 'Edit Scheduled Trip')

@section('content')

<div class="card">

    <h2>
        Edit Trip
    </h2>

    <p
        class="muted"
        style="margin-bottom:18px;"
    >
        Update this scheduled trip only.
        Changes will not affect the other trips in the 7-day schedule.
        Departure and arrival times are loaded automatically from the
        configured Bus Route Service timetable.
    </p>


    {{-- ============================================================
         SESSION ERROR
    ============================================================ --}}

    @if(session('error'))

        <div class="flash error">
            {{ session('error') }}
        </div>

    @endif


    {{-- ============================================================
         SESSION SUCCESS
    ============================================================ --}}

    @if(session('success'))

        <div class="flash success">
            {{ session('success') }}
        </div>

    @endif


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
         TRIP SUMMARY
    ============================================================ --}}

    <div
        style="
            margin-bottom:18px;
            padding:14px;
            background:#f7f9fd;
            border:1px solid #e2e8f0;
            border-radius:10px;
        "
    >

        <div>
            <strong>
                Trip Code:
            </strong>

            {{ $trip->trip_code }}
        </div>


        <div>
            <strong>
                Master Route:
            </strong>

            @if($trip->route)

                Route
                {{ $trip->route->route_number ?: '-' }}

                —

                {{ $trip->route->origin }}

                →

                {{ $trip->route->destination }}

            @else
                -
            @endif
        </div>


        @if($trip->route?->distance_km)

            <div>
                <strong>
                    Route Distance:
                </strong>

                {{ number_format(
                    (float) $trip->route->distance_km,
                    1
                ) }}
                km
            </div>

        @endif


        <div>
            <strong>
                Bus:
            </strong>

            {{ $trip->bus?->bus_number ?? '-' }}

            @if($trip->bus?->bus_name)
                — {{ $trip->bus->bus_name }}
            @endif

            @if($trip->bus?->bus_type)
                — {{ $trip->bus->bus_type }}
            @endif
        </div>


        <div>
            <strong>
                Direction:
            </strong>

            {{
                $trip->trip_type === 'starting'
                    ? 'Starting'
                    : 'Return'
            }}
        </div>


        <div>
            <strong>
                Current Departure:
            </strong>

            @if($trip->departure_time)

                {{
                    \Carbon\Carbon::parse(
                        $trip->departure_time
                    )->format('h:i A')
                }}

            @else
                -
            @endif
        </div>


        <div>
            <strong>
                Current Arrival:
            </strong>

            @if($trip->arrival_time)

                {{
                    \Carbon\Carbon::parse(
                        $trip->arrival_time
                    )->format('h:i A')
                }}

            @else
                -
            @endif
        </div>


        <div>
            <strong>
                Status:
            </strong>

            {{ ucfirst($trip->status) }}
        </div>


        <div>
            <strong>
                Published:
            </strong>

            {{ $trip->is_published ? 'Yes' : 'No' }}
        </div>

    </div>


    {{-- ============================================================
         EDIT FORM
    ============================================================ --}}

    <form
        method="POST"
        action="{{ route(
            'operator.trips.update',
            $trip->id
        ) }}"
        autocomplete="off"
    >

        @csrf
        @method('PATCH')


        <div class="form-grid">


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
                                old(
                                    'driver_id',
                                    $trip->driver_id
                                )
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
                                old(
                                    'conductor_id',
                                    $trip->conductor_id
                                )
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
                 SERVICE DATE
            ==================================================== --}}

            <div class="form-group">

                <label for="service_date">
                    Date
                </label>

                <input
                    id="service_date"
                    type="date"
                    name="service_date"
                    min="{{ now()->toDateString() }}"
                    value="{{ old(
                        'service_date',
                        \Carbon\Carbon::parse(
                            $trip->service_date
                        )->format('Y-m-d')
                    ) }}"
                    required
                >

                <small style="color:#6b7890;">
                    If the date changes, this trip will still use the
                    timetable configured for the same Bus Route Service.
                </small>

            </div>

        </div>


        {{-- ============================================================
             FIXED SERVICE TIMETABLE INFO
        ============================================================ --}}

        <div
            style="
                margin-top:16px;
                padding:12px 14px;
                background:#f7f9fd;
                border:1px solid #e2e8f0;
                border-radius:10px;
                color:#667085;
            "
        >

            <strong>
                Timetable:
            </strong>

            Departure and arrival times cannot be edited from this page.

            <br><br>

            This trip automatically uses the

            <strong>
                {{
                    $trip->trip_type === 'starting'
                        ? 'Starting'
                        : 'Return'
                }}
            </strong>

            timetable configured under the bus's

            <strong>
                Bus Route Service
            </strong>.

            <br><br>

            If the service timetable needs to change, update it from:

            <strong>
                Bus Route Services
            </strong>

            instead of editing the individual trip.


            <div
                style="
                    margin-top:10px;
                    padding-top:10px;
                    border-top:1px solid #e2e8f0;
                "
            >

                <strong>
                    Fare:
                </strong>

                Passenger fare is not edited here.
                It is calculated during booking using the configured
                NTC Fare Stage system.

            </div>

        </div>


        {{-- ============================================================
             ACTIONS
        ============================================================ --}}

        <div
            style="
                display:flex;
                gap:10px;
                flex-wrap:wrap;
                margin-top:18px;
            "
        >

            <button
                type="submit"
                class="btn btn-primary"
            >
                Save Changes
            </button>


            <a
                href="{{ route('operator.trips') }}"
                class="btn"
            >
                Cancel
            </a>

        </div>

    </form>

</div>

@endsection