@extends('layouts.app')

@section('header', 'Live Tracking Monitor')

@section('content')

    <h1 class="page-title">Live Tracking</h1>

    <p class="muted">
        Active trips and the most recent GPS point received by the system.
    </p>

    <div class="grid two">

        <div class="card">
            <div id="map" class="map"></div>
        </div>

        <div class="card">

            <table>
                <thead>
                    <tr>
                        <th>Bus</th>
                        <th>Route</th>
                        <th>Last Update</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach($trips as $trip)
                        <tr>
                            <td>
                                {{ $trip->bus?->bus_number ?? '-' }}
                            </td>

                            <td>
                                {{ $trip->route?->name ?? '-' }}
                            </td>

                            <td>
                                {{ $trip->liveLocation?->recorded_at?->diffForHumans() ?? 'No GPS yet' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

        </div>

    </div>

@endsection


@push('head')

    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    >

@endpush


@push('scripts')

    <script
        src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    ></script>

    @php
        $trackingPoints = [];

        foreach ($trips as $trip) {
            if ($trip->liveLocation) {
                $trackingPoints[] = [
                    'lat' => (float) $trip->liveLocation->latitude,
                    'lng' => (float) $trip->liveLocation->longitude,
                    'bus' => $trip->bus?->bus_number ?? 'Unknown Bus',
                    'route' => $trip->route?->name ?? 'Unknown Route',
                ];
            }
        }
    @endphp

    <script>
        const map = L.map('map').setView(
            [7.8731, 80.7718],
            7
        );

        L.tileLayer(
            'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            {
                maxZoom: 19,
                attribution: '© OpenStreetMap'
            }
        ).addTo(map);

        const points = @json($trackingPoints);

        points.forEach(function (point) {

            L.marker([
                point.lat,
                point.lng
            ])
            .addTo(map)
            .bindPopup(
                '<b>' + point.bus + '</b><br>' + point.route
            );

        });

        if (points.length > 0) {

            const bounds = points.map(function (point) {
                return [
                    point.lat,
                    point.lng
                ];
            });

            map.fitBounds(bounds, {
                padding: [30, 30]
            });
        }
    </script>

@endpush