@extends('layouts.app')

@section('header', 'My Live Buses')

@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">Live Tracking</h1>
        <p class="muted">
            Monitor only your active buses and current trips.
        </p>
    </div>
</div>

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

<div class="card">
    <div class="card-header">
        <h3>Active Buses</h3>
    </div>

    <div class="card-body">

        @if(isset($activeTrips) && $activeTrips->count() > 0)

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Trip</th>
                            <th>Bus</th>
                            <th>Route</th>
                            <th>Driver</th>
                            <th>Conductor</th>
                            <th>Status</th>
                            <th>Last Update</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($activeTrips as $trip)
                            <tr>
                                <td>
                                    {{ $trip->trip_code ?? '-' }}
                                </td>

                                <td>
                                    {{ $trip->bus->bus_number ?? '-' }}
                                </td>

                                <td>
                                    {{ $trip->origin ?? '-' }}
                                    →
                                    {{ $trip->destination ?? '-' }}
                                </td>

                                <td>
                                    {{ $trip->driver->full_name ?? '-' }}
                                </td>

                                <td>
                                    {{ $trip->conductor->full_name ?? '-' }}
                                </td>

                                <td>
                                    <span class="badge badge-success">
                                        {{ strtoupper($trip->status ?? 'ACTIVE') }}
                                    </span>
                                </td>

                                <td>
                                    {{ $trip->updated_at?->format('Y-m-d H:i:s') ?? '-' }}
                                </td>

                                <td>
    <span class="badge badge-success">
        Live
    </span>
</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

        @else

            <div class="empty-state">
                <h3>No Active Trips</h3>
                <p>
                    Live tracking will appear here after a driver or conductor starts a trip.
                </p>
            </div>

        @endif

    </div>
</div>

@endsection