@extends('layouts.app')

@section('header', 'Trips & Schedule')

@section('content')

<div class="card">
    <h2>Create Scheduled Trip</h2>

    <p style="color:#6b7890;">
        Select the route, bus, staff, direction and start date.
        Departure and arrival times are taken automatically from the route timetable.
        Passenger fare is calculated separately using the NTC fare-stage system.
    </p>

    @if ($errors->any())
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
            <strong>Please check the following:</strong>

            <ul style="margin:8px 0 0 20px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    <form
        method="POST"
        action="{{ route('operator.trips.store') }}"
        autocomplete="off"
    >
        @csrf

        <div class="form-grid">

            <div class="form-group">
                <label for="route_id">
                    Route
                </label>

                <select
                    id="route_id"
                    name="route_id"
                    required
                >
                    <option value="">
                        -- Select Route --
                    </option>

                    @foreach ($routes as $route)
                        <option
                            value="{{ $route->id }}"
                            @selected(
                                old('route_id') == $route->id
                            )
                        >
                            {{ $route->name }}

                            @if (!is_null($route->distance_km))
                                - {{ number_format(
                                    (float) $route->distance_km,
                                    1
                                ) }} km
                            @endif
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label for="bus_id">
                    Bus
                </label>

                <select
                    id="bus_id"
                    name="bus_id"
                    required
                >
                    <option value="">
                        -- Select Bus --
                    </option>

                    @foreach ($buses as $bus)
                        <option
                            value="{{ $bus->id }}"
                            @selected(
                                old('bus_id') == $bus->id
                            )
                        >
                            {{ $bus->bus_number }}

                            @if($bus->bus_name)
                                - {{ $bus->bus_name }}
                            @endif
                        </option>
                    @endforeach
                </select>
            </div>

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

                    @foreach ($drivers as $driver)
                        <option
                            value="{{ $driver->id }}"
                            @selected(
                                old('driver_id') == $driver->id
                            )
                        >
                            {{ $driver->full_name }}
                        </option>
                    @endforeach
                </select>
            </div>

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

                    @foreach ($conductors as $conductor)
                        <option
                            value="{{ $conductor->id }}"
                            @selected(
                                old('conductor_id') == $conductor->id
                            )
                        >
                            {{ $conductor->full_name }}
                        </option>
                    @endforeach
                </select>
            </div>

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
                            ) === 'starting'
                        )
                    >
                        Starting
                    </option>

                    <option
                        value="return"
                        @selected(
                            old('trip_type') === 'return'
                        )
                    >
                        Return
                    </option>
                </select>

                <small style="color:#6b7890;">
                    The selected direction decides which saved route timetable is used.
                </small>
            </div>

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
                            ) == '1'
                        )
                    >
                        Selected Date Only
                    </option>

                    <option
                        value="7"
                        @selected(
                            old('schedule_days') == '7'
                        )
                    >
                        7 Consecutive Days
                    </option>
                </select>

                <small style="color:#6b7890;">
                    7 days creates one separate trip for each day using the same saved timetable.
                </small>
            </div>

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
                    Passenger booking uses the route booking points,
                    50 km minimum rule and automatic NTC fare calculation.
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

<div
    class="card"
    style="margin-top:16px;"
>
    <h2>Scheduled Trips</h2>

    <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>Trip</th>
                    <th>Direction</th>
                    <th>Date & Time</th>
                    <th>Route</th>
                    <th>Bus</th>
                    <th>Staff</th>
                    <th>Status</th>
                    <th>Published</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>
                @forelse ($trips as $trip)

                    <tr>
                        <td>
                            <strong>
                                {{ $trip->trip_code }}
                            </strong>
                        </td>

                        <td>
                            {{ $trip->trip_type === 'starting'
                                ? 'Starting'
                                : 'Return' }}
                        </td>

                        <td>
                            {{ \Carbon\Carbon::parse(
                                $trip->service_date
                            )->format('d M Y') }}

                            <br>

                            <small>
                                Departure:
                                {{ \Carbon\Carbon::parse(
                                    $trip->departure_time
                                )->format('h:i A') }}
                            </small>

                            @if ($trip->arrival_time)
                                <br>

                                <small>
                                    Arrival:
                                    {{ \Carbon\Carbon::parse(
                                        $trip->arrival_time
                                    )->format('h:i A') }}
                                </small>
                            @endif
                        </td>

                        <td>
                            {{ $trip->route?->name ?? '-' }}

                            @if ($trip->route?->distance_km)
                                <br>

                                <small>
                                    {{ number_format(
                                        (float) $trip->route->distance_km,
                                        1
                                    ) }} km
                                </small>
                            @endif
                        </td>

                        <td>
                            {{ $trip->bus?->bus_number ?? '-' }}

                            @if ($trip->bus?->bus_name)
                                <br>

                                <small>
                                    {{ $trip->bus->bus_name }}
                                </small>
                            @endif

                            @if ($trip->bus?->bus_type)
                                <br>

                                <small>
                                    {{ $trip->bus->bus_type }}
                                </small>
                            @endif
                        </td>

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

                        <td>
                            {{ ucfirst($trip->status) }}
                        </td>

                        <td>
                            {{ $trip->is_published
                                ? 'Yes'
                                : 'No' }}
                        </td>

                        <td style="white-space:nowrap;">

                            @if ($trip->status === 'scheduled')
                                <a
                                    href="{{ route(
                                        'operator.trips.edit',
                                        $trip->id
                                    ) }}"
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
                                action="{{ route(
                                    'operator.trips.publish',
                                    $trip->id
                                ) }}"
                                style="display:inline-block;"
                            >
                                @csrf
                                @method('PATCH')

                                <button
                                    type="submit"
                                    class="btn btn-sm"
                                >
                                    {{ $trip->is_published
                                        ? 'Unpublish'
                                        : 'Publish' }}
                                </button>
                            </form>

                        </td>
                    </tr>

                @empty

                    <tr>
                        <td
                            colspan="9"
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

@endsection