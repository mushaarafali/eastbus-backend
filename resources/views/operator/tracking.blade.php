@extends('layouts.app')

@section('header', 'My Live Buses')

@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">Live Tracking</h1>
        <p class="muted">Monitor your active buses and view their current location in real time.</p>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
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
                            @php
                                $routeOrigin = $trip->route->origin ?? '-';
                                $routeDestination = $trip->route->destination ?? '-';

                                if (($trip->trip_type ?? 'starting') === 'return') {
                                    $displayOrigin = $routeDestination;
                                    $displayDestination = $routeOrigin;
                                } else {
                                    $displayOrigin = $routeOrigin;
                                    $displayDestination = $routeDestination;
                                }
                            @endphp

                            <tr>
                                <td>{{ $trip->trip_code ?? '-' }}</td>
                                <td>{{ $trip->bus->bus_number ?? '-' }}</td>
                                <td>{{ $displayOrigin }} → {{ $displayDestination }}</td>
                                <td>{{ $trip->driver->full_name ?? '-' }}</td>
                                <td>{{ $trip->conductor->full_name ?? '-' }}</td>
                                <td>
                                    <span class="badge badge-success">
                                        {{ strtoupper($trip->status ?? 'ACTIVE') }}
                                    </span>
                                </td>
                                <td>
                                    <span id="table-last-update-{{ $trip->id }}">
                                        {{ $trip->updated_at?->format('Y-m-d H:i:s') ?? '-' }}
                                    </span>
                                </td>
                                <td>
                                    <button
                                        type="button"
                                        class="btn btn-primary btn-sm live-track-btn"
                                        data-trip-id="{{ $trip->id }}"
                                        data-trip-code="{{ $trip->trip_code }}"
                                        data-bus="{{ $trip->bus->bus_number ?? '-' }}"
                                        data-origin="{{ $displayOrigin }}"
                                        data-destination="{{ $displayDestination }}"
                                    >
                                        View Live
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="empty-state">
                <h3>No Active Trips</h3>
                <p>Live tracking will appear here after a driver or conductor starts a trip.</p>
            </div>
        @endif
    </div>
</div>

@if(isset($activeTrips) && $activeTrips->count() > 0)
    <div id="live-tracking-panel" class="card" style="display:none; margin-top:18px;">
        <div class="card-header tracking-header">
            <div>
                <h3 id="tracking-title">Live Bus Tracking</h3>
                <p id="tracking-route" class="muted" style="margin:4px 0 0;"></p>
            </div>

            <div class="tracking-live-badge">
                <span class="tracking-live-dot"></span>
                <span id="tracking-live-text">LIVE</span>
            </div>
        </div>

        <div class="card-body">
            <div id="tracking-message" class="tracking-message">
                Select an active bus to view its live location.
            </div>

            <div id="tracking-content" style="display:none;">
                <div id="operator-live-map"></div>

                <div class="tracking-stats">
                    <div class="tracking-stat">
                        <span class="tracking-label">GPS Status</span>
                        <strong id="tracking-status">LIVE</strong>
                    </div>

                    <div class="tracking-stat">
                        <span class="tracking-label">Latitude</span>
                        <strong id="tracking-latitude">-</strong>
                    </div>

                    <div class="tracking-stat">
                        <span class="tracking-label">Longitude</span>
                        <strong id="tracking-longitude">-</strong>
                    </div>

                    <div class="tracking-stat">
                        <span class="tracking-label">Speed</span>
                        <strong id="tracking-speed">0.0 km/h</strong>
                    </div>

                    <div class="tracking-stat">
                        <span class="tracking-label">Heading</span>
                        <strong id="tracking-heading">-</strong>
                    </div>

                    <div class="tracking-stat">
                        <span class="tracking-label">Last Updated</span>
                        <strong id="tracking-updated">-</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif

<style>
    #operator-live-map {
        width: 100%;
        height: 430px;
        border-radius: 14px;
        overflow: hidden;
        border: 1px solid #dfe6f1;
        background: #eef2f7;
    }

    .tracking-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
    }

    .tracking-live-badge {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        background: #e8f6ec;
        color: #188038;
        border-radius: 999px;
        padding: 7px 12px;
        font-size: 12px;
        font-weight: 800;
    }

    .tracking-live-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #22a447;
    }

    .tracking-message {
        min-height: 120px;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        color: #64748b;
        font-weight: 600;
    }

    .tracking-stats {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        gap: 10px;
        margin-top: 14px;
    }

    .tracking-stat {
        padding: 12px;
        border: 1px solid #e3e8f0;
        border-radius: 12px;
        background: #ffffff;
    }

    .tracking-label {
        display: block;
        color: #6b7890;
        font-size: 11px;
        margin-bottom: 5px;
    }

    .tracking-stat strong {
        font-size: 13px;
        color: #17233f;
        word-break: break-word;
    }

    .eastbus-live-marker {
        width: 52px;
        height: 52px;
        border-radius: 50%;
        background: #173f8a;
        border: 4px solid #ffffff;
        box-shadow: 0 4px 12px rgba(0, 0, 0, .25);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        font-size: 24px;
    }

    .tracking-stale {
        background: #fff4e5;
        color: #b45309;
    }

    .tracking-offline {
        background: #fdecec;
        color: #b42318;
    }

    @media (max-width: 1100px) {
        .tracking-stats {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    @media (max-width: 700px) {
        #operator-live-map {
            height: 330px;
        }

        .tracking-stats {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .tracking-header {
            align-items: flex-start;
            flex-direction: column;
        }
    }
</style>

<link
    rel="stylesheet"
    href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    integrity="sha256-p4NxAoJBhIINfQ3yn5pP+zMZ9pNlyuD+4jMZ7Yx0nGQ="
    crossorigin=""
>

<script
    src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
    crossorigin=""
></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const firebaseDatabaseUrl = @json(
        rtrim(
            (string) env(
                'FIREBASE_DATABASE_URL',
                'https://eastbus-smart-transport-default-rtdb.asia-southeast1.firebasedatabase.app'
            ),
            '/'
        )
    );

    const panel = document.getElementById('live-tracking-panel');
    const content = document.getElementById('tracking-content');
    const message = document.getElementById('tracking-message');

    const title = document.getElementById('tracking-title');
    const route = document.getElementById('tracking-route');

    const statusText = document.getElementById('tracking-status');
    const latitudeText = document.getElementById('tracking-latitude');
    const longitudeText = document.getElementById('tracking-longitude');
    const speedText = document.getElementById('tracking-speed');
    const headingText = document.getElementById('tracking-heading');
    const updatedText = document.getElementById('tracking-updated');

    const liveBadge = document.querySelector('.tracking-live-badge');
    const liveText = document.getElementById('tracking-live-text');

    let map = null;
    let marker = null;
    let selectedTripCode = null;
    let selectedTripId = null;
    let pollTimer = null;
    let firstLocationLoaded = false;

    function toNumber(value) {
        if (value === null || value === undefined || value === '') {
            return null;
        }

        const number = Number(value);
        return Number.isFinite(number) ? number : null;
    }

    function validCoordinates(latitude, longitude) {
        return latitude !== null &&
            longitude !== null &&
            latitude >= -90 &&
            latitude <= 90 &&
            longitude >= -180 &&
            longitude <= 180;
    }

    function toMilliseconds(value) {
        if (value === null || value === undefined || value === '') {
            return null;
        }

        if (typeof value === 'number') {
            return value < 100000000000 ? value * 1000 : value;
        }

        const numeric = Number(value);

        if (Number.isFinite(numeric)) {
            return numeric < 100000000000 ? numeric * 1000 : numeric;
        }

        const parsed = Date.parse(String(value));
        return Number.isNaN(parsed) ? null : parsed;
    }

    function formatUpdatedAt(timestamp) {
        if (!timestamp) {
            return '-';
        }

        const date = new Date(timestamp);

        if (Number.isNaN(date.getTime())) {
            return '-';
        }

        return date.toLocaleString();
    }

    function setLiveState(timestamp) {
        if (!timestamp) {
            liveText.textContent = 'LIVE';
            statusText.textContent = 'LIVE';
            liveBadge.className = 'tracking-live-badge';
            return;
        }

        const age = Date.now() - timestamp;

        if (age >= 120000) {
            liveText.textContent = 'STALE';
            statusText.textContent = 'STALE';
            liveBadge.className = 'tracking-live-badge tracking-stale';
        } else {
            liveText.textContent = 'LIVE';
            statusText.textContent = 'LIVE';
            liveBadge.className = 'tracking-live-badge';
        }
    }

    function createBusIcon(heading) {
        const safeHeading = Number.isFinite(heading) ? heading : 0;

        return L.divIcon({
            className: '',
            iconSize: [56, 56],
            iconAnchor: [28, 28],
            html: `
                <div class="eastbus-live-marker" style="transform:rotate(${safeHeading}deg)">
                    <span style="transform:rotate(${-safeHeading}deg)">🚌</span>
                </div>
            `
        });
    }

    function ensureMap(latitude, longitude, heading) {
        if (!map) {
            map = L.map('operator-live-map', {
                zoomControl: true,
                preferCanvas: true,
                fadeAnimation: true,
                markerZoomAnimation: true
            }).setView([latitude, longitude], 15.5);

            L.tileLayer(
                'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png',
                {
                    subdomains: 'abcd',
                    maxZoom: 20,
                    detectRetina: true,
                    updateWhenIdle: false,
                    keepBuffer: 4,
                    crossOrigin: true,
                    attribution:
                        '&copy; OpenStreetMap contributors &copy; CARTO'
                }
            ).addTo(map);
        }

        if (!marker) {
            marker = L.marker(
                [latitude, longitude],
                {
                    icon: createBusIcon(heading)
                }
            ).addTo(map);
        } else {
            marker.setLatLng([latitude, longitude]);
            marker.setIcon(createBusIcon(heading));
        }

        if (!firstLocationLoaded) {
            map.setView([latitude, longitude], 15.5);
            firstLocationLoaded = true;
        } else {
            map.panTo([latitude, longitude], {
                animate: true,
                duration: 0.7
            });
        }

        setTimeout(() => {
            map.invalidateSize();
        }, 100);
    }

    function showWaiting(text) {
        message.textContent = text;
        message.style.display = 'flex';
        content.style.display = 'none';
    }

    function showTracking() {
        message.style.display = 'none';
        content.style.display = 'block';

        setTimeout(() => {
            if (map) {
                map.invalidateSize();
            }
        }, 100);
    }

    function clearCurrentTracking() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }

        selectedTripCode = null;
        selectedTripId = null;
        firstLocationLoaded = false;

        if (marker && map) {
            map.removeLayer(marker);
        }

        marker = null;
    }

    async function loadFirebaseLocation() {
        if (!selectedTripCode) {
            return;
        }

        const codeAtRequestTime = selectedTripCode;

        try {
            const response = await fetch(
                `${firebaseDatabaseUrl}/live_trips/${encodeURIComponent(codeAtRequestTime)}.json`,
                {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json'
                    },
                    cache: 'no-store'
                }
            );

            if (!response.ok) {
                throw new Error(`Firebase HTTP ${response.status}`);
            }

            const data = await response.json();

            if (codeAtRequestTime !== selectedTripCode) {
                return;
            }

            if (!data || typeof data !== 'object') {
                showWaiting('Waiting for the first live GPS update from the Trip Management App.');
                return;
            }

            const firebaseStatus = String(data.status || '').trim().toUpperCase();

            if (
                firebaseStatus &&
                !['ON_TRIP', 'ACTIVE', 'STARTED', 'IN_PROGRESS'].includes(firebaseStatus)
            ) {
                liveText.textContent = firebaseStatus;
                statusText.textContent = firebaseStatus;
                liveBadge.className = 'tracking-live-badge tracking-offline';
                showWaiting('This trip is no longer sharing an active live location.');
                return;
            }

            const latitude = toNumber(data.latitude ?? data.lat);
            const longitude = toNumber(data.longitude ?? data.lng ?? data.lon);

            if (!validCoordinates(latitude, longitude)) {
                showWaiting('Trip is active, but live GPS coordinates are not available yet.');
                return;
            }

            const speed = Math.max(
                0,
                toNumber(data.speed_kmh ?? data.speed) ?? 0
            );

            const heading = toNumber(data.heading ?? data.bearing) ?? 0;

            const timestamp = toMilliseconds(
                data.location_updated_at ??
                data.recorded_at ??
                data.updated_at ??
                data.timestamp
            );

            showTracking();
            ensureMap(latitude, longitude, heading);

            latitudeText.textContent = latitude.toFixed(6);
            longitudeText.textContent = longitude.toFixed(6);
            speedText.textContent = `${speed.toFixed(1)} km/h`;
            headingText.textContent = `${heading.toFixed(0)}°`;
            updatedText.textContent = formatUpdatedAt(timestamp);

            setLiveState(timestamp);

            if (selectedTripId) {
                const tableLastUpdate = document.getElementById(
                    `table-last-update-${selectedTripId}`
                );

                if (tableLastUpdate && timestamp) {
                    tableLastUpdate.textContent = formatUpdatedAt(timestamp);
                }
            }
        } catch (error) {
            console.error('EastBus operator live tracking error:', error);

            liveText.textContent = 'ERROR';
            statusText.textContent = 'ERROR';
            liveBadge.className = 'tracking-live-badge tracking-offline';

            showWaiting(
                'Unable to read the live bus location. Check the Firebase database connection and rules.'
            );
        }
    }

    function selectTrip(button) {
        clearCurrentTracking();

        selectedTripId = button.dataset.tripId;
        selectedTripCode = (button.dataset.tripCode || '').trim();

        const bus = button.dataset.bus || '-';
        const origin = button.dataset.origin || '-';
        const destination = button.dataset.destination || '-';

        title.textContent = `${bus} • ${selectedTripCode || 'Active Trip'}`;
        route.textContent = `${origin} → ${destination}`;

        panel.style.display = 'block';

        latitudeText.textContent = '-';
        longitudeText.textContent = '-';
        speedText.textContent = '0.0 km/h';
        headingText.textContent = '-';
        updatedText.textContent = '-';

        liveText.textContent = 'CONNECTING';
        statusText.textContent = 'CONNECTING';
        liveBadge.className = 'tracking-live-badge';

        showWaiting('Connecting to the live bus GPS...');

        panel.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });

        if (!selectedTripCode) {
            showWaiting('This active trip does not have a valid trip code.');
            return;
        }

        loadFirebaseLocation();

        pollTimer = setInterval(
            loadFirebaseLocation,
            5000
        );
    }

    document.querySelectorAll('.live-track-btn').forEach((button) => {
        button.addEventListener('click', function () {
            selectTrip(this);
        });
    });

    const firstButton = document.querySelector('.live-track-btn');

    if (firstButton) {
        selectTrip(firstButton);
    }

    window.addEventListener('beforeunload', function () {
        if (pollTimer) {
            clearInterval(pollTimer);
        }
    });
});
</script>

@endsection
