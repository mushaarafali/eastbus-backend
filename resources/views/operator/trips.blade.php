@extends('layouts.app')

@section('header', 'Trips & Schedule')

@section('content')

<div class="card">
    <h2>Create Scheduled Trip</h2>

    @if ($errors->any())
        <div
            style="
                margin-bottom: 16px;
                padding: 12px 14px;
                background: #fff1f1;
                border: 1px solid #f3b6b6;
                border-radius: 10px;
                color: #9b1c1c;
            "
        >
            <strong>Please check the following:</strong>

            <ul style="margin: 8px 0 0 20px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
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
                            @selected(old('route_id') == $route->id)
                        >
                            {{ $route->name }}

                            @if (!is_null($route->distance_km))
                                - {{ number_format($route->distance_km, 1) }} km
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
                            @selected(old('bus_id') == $bus->id)
                        >
                            {{ $bus->bus_number }}
                            -
                            {{ $bus->bus_name }}
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
                            @selected(old('driver_id') == $driver->id)
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
                            @selected(old('conductor_id') == $conductor->id)
                        >
                            {{ $conductor->full_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label for="trip_type">
                    Trip Type
                </label>

                <select
                    id="trip_type"
                    name="trip_type"
                    required
                >
                    <option
                        value="starting"
                        @selected(old('trip_type', 'starting') === 'starting')
                    >
                        Starting Turn
                    </option>

                    <option
                        value="return"
                        @selected(old('trip_type') === 'return')
                    >
                        Return Trip
                    </option>
                </select>
            </div>

            <div class="form-group">
                <label for="service_date">
                    Date
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
                <label for="departure_time">
                    Departure
                </label>

                <input
                    id="departure_time"
                    type="time"
                    name="departure_time"
                    value="{{ old('departure_time') }}"
                    required
                >
            </div>

            <div class="form-group">
                <label for="arrival_time">
                    Arrival
                </label>

                <input
                    id="arrival_time"
                    type="time"
                    name="arrival_time"
                    value="{{ old('arrival_time') }}"
                >
            </div>

            <div class="form-group">
                <label for="fare">
                    Full Route Fare
                </label>

                <input
                    id="fare"
                    type="number"
                    step="0.01"
                    min="0"
                    name="fare"
                    value="{{ old('fare') }}"
                    placeholder="Example: 850.00"
                    required
                >
            </div>

            <div class="form-group">
                <label
                    style="
                        display: flex;
                        align-items: center;
                        gap: 8px;
                        cursor: pointer;
                    "
                >
                    <input
                        type="checkbox"
                        name="is_published"
                        value="1"
                        @checked(old('is_published'))
                    >

                    Publish for passenger booking
                </label>

                <small style="color: #6b7890;">
                    Only eligible long-distance routes can be published for online booking.
                </small>
            </div>

        </div>

        <div style="margin-top: 18px;">
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
    style="margin-top: 16px;"
>
    <h2>Scheduled Trips</h2>

    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>Trip</th>
                    <th>Type</th>
                    <th>Date</th>
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
                            {{ ucfirst($trip->trip_type) }}
                        </td>

                        <td>
                            {{ \Carbon\Carbon::parse($trip->service_date)->format('d M Y') }}

                            <br>

                            <small>
                                {{ \Carbon\Carbon::parse($trip->departure_time)->format('h:i A') }}
                            </small>
                        </td>

                        <td>
                            {{ $trip->route?->name ?? '-' }}

                            @if ($trip->route?->distance_km)
                                <br>

                                <small>
                                    {{ number_format($trip->route->distance_km, 1) }} km
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
                            {{ $trip->is_published ? 'Yes' : 'No' }}
                        </td>

                        <td>
                            <form
                                method="POST"
                                action="{{ route('operator.trips.publish', $trip->id) }}"
                                style="display: inline-block;"
                            >
                                @csrf
                                @method('PATCH')

                                <button
                                    type="submit"
                                    class="btn btn-sm"
                                >
                                    {{ $trip->is_published
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
                            colspan="9"
                            style="
                                text-align: center;
                                padding: 24px;
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