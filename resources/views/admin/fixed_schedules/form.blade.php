@extends('layouts.app')

@section('title', $service ? 'Edit Daily Service Bus' : 'Add Daily Service Bus')
@section('header', $service ? 'Edit Daily Service Bus' : 'Add Daily Service Bus')

@section('content')

@php
    $isEdit = $service !== null;

    $selectedRouteId = old(
        'route_id',
        $service->route_id ?? ''
    );

    $startRows = old('starting_stops');

    if (!$startRows) {
        $startRows = $startingStops
            ->map(function ($stop) {
                return [
                    'route_stop_id' =>
                        $stop->route_stop_id
                        ?? $stop->id
                        ?? '',

                    'arrival_time' =>
                        !empty($stop->arrival_time)
                            ? substr($stop->arrival_time, 0, 5)
                            : '',

                    'departure_time' =>
                        !empty($stop->departure_time)
                            ? substr($stop->departure_time, 0, 5)
                            : '',
                ];
            })
            ->toArray();
    }

    $returnRows = old('return_stops');

    if (!$returnRows) {
        $returnRows = $returnStops
            ->map(function ($stop) {
                return [
                    'route_stop_id' =>
                        $stop->route_stop_id
                        ?? $stop->id
                        ?? '',

                    'arrival_time' =>
                        !empty($stop->arrival_time)
                            ? substr($stop->arrival_time, 0, 5)
                            : '',

                    'departure_time' =>
                        !empty($stop->departure_time)
                            ? substr($stop->departure_time, 0, 5)
                            : '',
                ];
            })
            ->toArray();
    }

    if (!$startRows) {
        $startRows = [
            [
                'route_stop_id' => '',
                'arrival_time' => '',
                'departure_time' => '',
            ],
            [
                'route_stop_id' => '',
                'arrival_time' => '',
                'departure_time' => '',
            ],
        ];
    }

    if (!$returnRows) {
        $returnRows = [
            [
                'route_stop_id' => '',
                'arrival_time' => '',
                'departure_time' => '',
            ],
            [
                'route_stop_id' => '',
                'arrival_time' => '',
                'departure_time' => '',
            ],
        ];
    }
@endphp


@if($errors->any())
    <div class="flash error" style="margin-bottom:16px;">
        <strong>Please check the following:</strong>

        <ul style="margin:8px 0 0 20px;">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif


@if(session('success'))
    <div class="flash success" style="margin-bottom:16px;">
        {{ session('success') }}
    </div>
@endif


<form
    method="POST"
    action="{{ $isEdit
        ? route('admin.fixed-schedules.update', $service->id)
        : route('admin.fixed-schedules.store') }}"
    autocomplete="off"
>
    @csrf

    @if($isEdit)
        @method('PUT')
    @endif


    {{-- ============================================================ --}}
    {{-- Bus / Route Details                                          --}}
    {{-- ============================================================ --}}

    <div class="card">
        <div class="card-body">

            <div style="margin-bottom:18px;">
                <h2 style="margin-bottom:5px;">
                    {{ $isEdit ? 'Edit Daily Service Bus' : 'Add Daily Service Bus' }}
                </h2>

                <p style="margin:0;color:#667085;">
                    Create an information-only daily bus timetable.
                    Select a Master Route and use its road-way stops.
                </p>
            </div>


            <div
                style="
                    display:grid;
                    grid-template-columns:repeat(2, minmax(0, 1fr));
                    gap:14px;
                "
            >

                {{-- Bus Name --}}

                <div>
                    <label for="bus_name">
                        Bus Name *
                    </label>

                    <input
                        id="bus_name"
                        class="form-control"
                        type="text"
                        name="bus_name"
                        maxlength="150"
                        value="{{ old(
                            'bus_name',
                            $service->bus_name ?? ''
                        ) }}"
                        placeholder="Example: HEMA EXPRESS"
                        required
                    >
                </div>


                {{-- Bus Number --}}

                <div>
                    <label for="bus_number">
                        Bus Number
                    </label>

                    <input
                        id="bus_number"
                        class="form-control"
                        type="text"
                        name="bus_number"
                        maxlength="100"
                        value="{{ old(
                            'bus_number',
                            $service->bus_number ?? ''
                        ) }}"
                        placeholder="Example: NB-1234"
                    >
                </div>


                {{-- Contact Number --}}

                <div>
                    <label for="contact_number">
                        Contact Number *
                    </label>

                    <input
                        id="contact_number"
                        class="form-control"
                        type="text"
                        name="contact_number"
                        maxlength="30"
                        value="{{ old(
                            'contact_number',
                            $service->contact_number_1 ?? ''
                        ) }}"
                        placeholder="Example: 0771234567"
                        required
                    >
                </div>


                {{-- Master Route --}}

                <div>
                    <label for="route_id">
                        Master Route *
                    </label>

                    <select
                        id="route_id"
                        name="route_id"
                        class="form-control"
                        required
                    >
                        <option value="">
                            -- Select Master Route --
                        </option>

                        @foreach($routes as $route)
                            <option
                                value="{{ $route->id }}"
                                @selected(
                                    (string) $selectedRouteId
                                    ===
                                    (string) $route->id
                                )
                            >
                                Route {{ $route->route_number }}
                                -
                                {{ $route->origin }}
                                →
                                {{ $route->destination }}
                            </option>
                        @endforeach
                    </select>

                    <small style="color:#667085;">
                        Stop dropdowns are loaded from this Master Route.
                    </small>
                </div>

            </div>


            <div
                id="selected-route-info"
                style="
                    margin-top:16px;
                    padding:12px 14px;
                    background:#f7f9fd;
                    border:1px solid #e2e8f0;
                    border-radius:10px;
                    color:#667085;
                    display:none;
                "
            >
            </div>


            <div
                style="
                    margin-top:16px;
                    display:flex;
                    gap:20px;
                    flex-wrap:wrap;
                "
            >
                <label>
                    <input
                        type="checkbox"
                        name="is_published"
                        value="1"
                        {{
                            old(
                                'is_published',
                                $service->is_published ?? true
                            )
                                ? 'checked'
                                : ''
                        }}
                    >

                    Published
                </label>


                <label>
                    <input
                        type="checkbox"
                        name="is_active"
                        value="1"
                        {{
                            old(
                                'is_active',
                                $service->is_active ?? true
                            )
                                ? 'checked'
                                : ''
                        }}
                    >

                    Active
                </label>
            </div>

        </div>
    </div>


    {{-- ============================================================ --}}
    {{-- Starting Daily Service                                       --}}
    {{-- ============================================================ --}}

    <div class="card" style="margin-top:16px;">
        <div class="card-body">

            <div
                style="
                    display:flex;
                    justify-content:space-between;
                    align-items:center;
                    gap:10px;
                "
            >
                <div>
                    <h3 style="margin-bottom:4px;">
                        Starting Daily Service
                    </h3>

                    <small style="color:#667085;">
                        Select road-way stops in forward Master Route order.
                    </small>
                </div>

                <button
                    type="button"
                    class="btn btn-sm"
                    onclick="addStop('starting')"
                >
                    + Add Stop
                </button>
            </div>


            <div
                id="starting-container"
                style="margin-top:16px;"
            >
                @foreach($startRows as $i => $stop)

                    <div
                        class="schedule-row"
                        style="
                            border:1px solid #e1e6ef;
                            border-radius:10px;
                            padding:12px;
                            margin-bottom:10px;
                        "
                    >

                        <div
                            style="
                                display:grid;
                                grid-template-columns:60px 2fr 1fr 1fr 90px;
                                gap:8px;
                                align-items:end;
                            "
                        >

                            <div>
                                <label>Order</label>

                                <input
                                    class="form-control order-field"
                                    value="{{ $i + 1 }}"
                                    readonly
                                >
                            </div>


                            <div>
                                <label>
                                    Road Way Stop *
                                </label>

                                <select
                                    class="form-control route-stop-select"
                                    name="starting_stops[{{ $i }}][route_stop_id]"
                                    data-selected="{{ $stop['route_stop_id'] ?? '' }}"
                                    required
                                >
                                    <option value="">
                                        -- Select Master Route First --
                                    </option>

                                    @foreach($routeStops as $routeStop)
                                        <option
                                            value="{{ $routeStop->id }}"
                                            @selected(
                                                (string)
                                                ($stop['route_stop_id'] ?? '')
                                                ===
                                                (string) $routeStop->id
                                            )
                                        >
                                            {{ $routeStop->name }}

                                            @if(isset($routeStop->distance_from_origin_km))
                                                -
                                                {{ number_format(
                                                    $routeStop->distance_from_origin_km,
                                                    2
                                                ) }}
                                                km
                                            @endif

                                            @if(!empty($routeStop->fare_stage_no))
                                                - Stage
                                                {{ $routeStop->fare_stage_no }}
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>


                            <div>
                                <label>
                                    Arrival Time
                                </label>

                                <input
                                    class="form-control arrival-time"
                                    type="time"
                                    name="starting_stops[{{ $i }}][arrival_time]"
                                    value="{{ $stop['arrival_time'] ?? '' }}"
                                >
                            </div>


                            <div>
                                <label>
                                    Departure Time
                                </label>

                                <input
                                    class="form-control departure-time"
                                    type="time"
                                    name="starting_stops[{{ $i }}][departure_time]"
                                    value="{{ $stop['departure_time'] ?? '' }}"
                                >
                            </div>


                            <div>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-danger remove-row"
                                >
                                    Remove
                                </button>
                            </div>

                        </div>

                    </div>

                @endforeach
            </div>

        </div>
    </div>


    {{-- ============================================================ --}}
    {{-- Return Daily Service                                         --}}
    {{-- ============================================================ --}}

    <div class="card" style="margin-top:16px;">
        <div class="card-body">

            <div
                style="
                    display:flex;
                    justify-content:space-between;
                    align-items:center;
                    gap:10px;
                "
            >
                <div>
                    <h3 style="margin-bottom:4px;">
                        Return Daily Service
                    </h3>

                    <small style="color:#667085;">
                        Select road-way stops in reverse Master Route order.
                    </small>
                </div>

                <button
                    type="button"
                    class="btn btn-sm"
                    onclick="addStop('return')"
                >
                    + Add Stop
                </button>
            </div>


            <div
                id="return-container"
                style="margin-top:16px;"
            >
                @foreach($returnRows as $i => $stop)

                    <div
                        class="schedule-row"
                        style="
                            border:1px solid #e1e6ef;
                            border-radius:10px;
                            padding:12px;
                            margin-bottom:10px;
                        "
                    >

                        <div
                            style="
                                display:grid;
                                grid-template-columns:60px 2fr 1fr 1fr 90px;
                                gap:8px;
                                align-items:end;
                            "
                        >

                            <div>
                                <label>Order</label>

                                <input
                                    class="form-control order-field"
                                    value="{{ $i + 1 }}"
                                    readonly
                                >
                            </div>


                            <div>
                                <label>
                                    Road Way Stop *
                                </label>

                                <select
                                    class="form-control route-stop-select"
                                    name="return_stops[{{ $i }}][route_stop_id]"
                                    data-selected="{{ $stop['route_stop_id'] ?? '' }}"
                                    required
                                >
                                    <option value="">
                                        -- Select Master Route First --
                                    </option>

                                    @foreach($routeStops as $routeStop)
                                        <option
                                            value="{{ $routeStop->id }}"
                                            @selected(
                                                (string)
                                                ($stop['route_stop_id'] ?? '')
                                                ===
                                                (string) $routeStop->id
                                            )
                                        >
                                            {{ $routeStop->name }}

                                            @if(isset($routeStop->distance_from_origin_km))
                                                -
                                                {{ number_format(
                                                    $routeStop->distance_from_origin_km,
                                                    2
                                                ) }}
                                                km
                                            @endif

                                            @if(!empty($routeStop->fare_stage_no))
                                                - Stage
                                                {{ $routeStop->fare_stage_no }}
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>


                            <div>
                                <label>
                                    Arrival Time
                                </label>

                                <input
                                    class="form-control arrival-time"
                                    type="time"
                                    name="return_stops[{{ $i }}][arrival_time]"
                                    value="{{ $stop['arrival_time'] ?? '' }}"
                                >
                            </div>


                            <div>
                                <label>
                                    Departure Time
                                </label>

                                <input
                                    class="form-control departure-time"
                                    type="time"
                                    name="return_stops[{{ $i }}][departure_time]"
                                    value="{{ $stop['departure_time'] ?? '' }}"
                                >
                            </div>


                            <div>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-danger remove-row"
                                >
                                    Remove
                                </button>
                            </div>

                        </div>

                    </div>

                @endforeach
            </div>

        </div>
    </div>


    {{-- ============================================================ --}}
    {{-- Buttons                                                      --}}
    {{-- ============================================================ --}}

    <div
        style="
            display:flex;
            gap:10px;
            flex-wrap:wrap;
            margin-top:16px;
        "
    >
        <button
            class="btn btn-primary"
            type="submit"
        >
            {{ $isEdit
                ? 'Update Daily Service'
                : 'Create Daily Service' }}
        </button>

        <a
            href="{{ route('admin.fixed-schedules.index') }}"
            class="btn"
        >
            Cancel
        </a>
    </div>

</form>


{{-- ================================================================ --}}
{{-- Dynamic Stop Row Template                                        --}}
{{-- ================================================================ --}}

<template id="schedule-row-template">

    <div
        class="schedule-row"
        style="
            border:1px solid #e1e6ef;
            border-radius:10px;
            padding:12px;
            margin-bottom:10px;
        "
    >

        <div
            style="
                display:grid;
                grid-template-columns:60px 2fr 1fr 1fr 90px;
                gap:8px;
                align-items:end;
            "
        >

            <div>
                <label>Order</label>

                <input
                    class="form-control order-field"
                    readonly
                >
            </div>


            <div>
                <label>
                    Road Way Stop *
                </label>

                <select
                    class="form-control route-stop-select"
                    required
                >
                    <option value="">
                        -- Select Master Route First --
                    </option>
                </select>
            </div>


            <div>
                <label>
                    Arrival Time
                </label>

                <input
                    class="form-control arrival-time"
                    type="time"
                >
            </div>


            <div>
                <label>
                    Departure Time
                </label>

                <input
                    class="form-control departure-time"
                    type="time"
                >
            </div>


            <div>
                <button
                    type="button"
                    class="btn btn-sm btn-outline-danger remove-row"
                >
                    Remove
                </button>
            </div>

        </div>

    </div>

</template>


<script>
(function () {

    const routeSelect =
        document.getElementById('route_id');

    const routeInfo =
        document.getElementById('selected-route-info');

    const template =
        document.getElementById('schedule-row-template');

    let masterRouteStops = [];


    /*
    |--------------------------------------------------------------------------
    | Escape HTML
    |--------------------------------------------------------------------------
    */

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }


    /*
    |--------------------------------------------------------------------------
    | Build Stop Dropdown
    |--------------------------------------------------------------------------
    */

    function buildStopOptions(selectedId = '') {
        let html =
            '<option value="">-- Select Stop --</option>';

        masterRouteStops.forEach(stop => {

            const selected =
                String(stop.id) === String(selectedId)
                    ? ' selected'
                    : '';

            const km =
                stop.distance_from_origin_km !== null
                &&
                stop.distance_from_origin_km !== undefined
                    ? ` - ${Number(
                        stop.distance_from_origin_km
                    ).toFixed(2)} km`
                    : '';

            const stage =
                stop.fare_stage_no
                    ? ` - Stage ${stop.fare_stage_no}`
                    : '';

            html += `
                <option
                    value="${escapeHtml(stop.id)}"
                    ${selected}
                >
                    ${escapeHtml(stop.name)}
                    ${escapeHtml(km)}
                    ${escapeHtml(stage)}
                </option>
            `;
        });

        return html;
    }


    /*
    |--------------------------------------------------------------------------
    | Populate Stop Dropdowns
    |--------------------------------------------------------------------------
    */

    function populateAllStopDropdowns() {
        document
            .querySelectorAll('.route-stop-select')
            .forEach(select => {

                const selectedId =
                    select.dataset.selected
                    ||
                    select.value
                    ||
                    '';

                select.innerHTML =
                    buildStopOptions(selectedId);

                select.dataset.selected = '';
            });
    }


    /*
    |--------------------------------------------------------------------------
    | Clear Dropdowns
    |--------------------------------------------------------------------------
    */

    function clearStopDropdowns() {
        masterRouteStops = [];

        document
            .querySelectorAll('.route-stop-select')
            .forEach(select => {
                select.innerHTML =
                    '<option value="">-- Select Master Route First --</option>';
            });

        routeInfo.style.display = 'none';
        routeInfo.innerHTML = '';
    }


    /*
    |--------------------------------------------------------------------------
    | Load Route Stops
    |--------------------------------------------------------------------------
    */

    async function loadRouteStops(routeId) {
        if (!routeId) {
            clearStopDropdowns();
            return;
        }

        document
            .querySelectorAll('.route-stop-select')
            .forEach(select => {
                select.innerHTML =
                    '<option value="">Loading stops...</option>';
            });

        try {
            const response =
                await fetch(
                    `/admin/fixed-schedules/routes/${routeId}/stops`,
                    {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    }
                );

            if (!response.ok) {
                throw new Error(
                    'Unable to load Master Route stops.'
                );
            }

            const data =
                await response.json();

            masterRouteStops =
                Array.isArray(data.stops)
                    ? data.stops
                    : [];

            populateAllStopDropdowns();

            if (data.route) {
                routeInfo.innerHTML = `
                    <strong>
                        Route ${escapeHtml(
                            data.route.route_number
                        )}
                    </strong>

                    &nbsp; — &nbsp;

                    ${escapeHtml(
                        data.route.origin
                    )}

                    →

                    ${escapeHtml(
                        data.route.destination
                    )}

                    &nbsp; | &nbsp;

                    Distance:
                    ${escapeHtml(
                        data.route.distance_km ?? '-'
                    )} km

                    &nbsp; | &nbsp;

                    Road-way Stops:
                    ${masterRouteStops.length}
                `;

                routeInfo.style.display =
                    'block';
            }

        } catch (error) {
            console.error(error);

            masterRouteStops = [];

            document
                .querySelectorAll('.route-stop-select')
                .forEach(select => {
                    select.innerHTML =
                        '<option value="">Unable to load stops</option>';
                });

            routeInfo.innerHTML =
                'Unable to load stops for the selected Master Route.';

            routeInfo.style.display =
                'block';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Rebuild Dynamic Input Names
    |--------------------------------------------------------------------------
    */

    function rebuild(direction) {
        const container =
            document.getElementById(
                direction + '-container'
            );

        container
            .querySelectorAll('.schedule-row')
            .forEach((row, index) => {

                row.querySelector(
                    '.order-field'
                ).value =
                    index + 1;

                row.querySelector(
                    '.route-stop-select'
                ).name =
                    `${direction}_stops[${index}][route_stop_id]`;

                row.querySelector(
                    '.arrival-time'
                ).name =
                    `${direction}_stops[${index}][arrival_time]`;

                row.querySelector(
                    '.departure-time'
                ).name =
                    `${direction}_stops[${index}][departure_time]`;
            });
    }


    /*
    |--------------------------------------------------------------------------
    | Add Stop
    |--------------------------------------------------------------------------
    */

    window.addStop =
        function (direction) {

            const container =
                document.getElementById(
                    direction + '-container'
                );

            const fragment =
                template.content.cloneNode(true);

            container.appendChild(fragment);

            const rows =
                container.querySelectorAll(
                    '.schedule-row'
                );

            const newRow =
                rows[rows.length - 1];

            const select =
                newRow.querySelector(
                    '.route-stop-select'
                );

            if (masterRouteStops.length > 0) {
                select.innerHTML =
                    buildStopOptions();
            }

            rebuild(direction);
        };


    /*
    |--------------------------------------------------------------------------
    | Remove Stop
    |--------------------------------------------------------------------------
    */

    document.addEventListener(
        'click',
        function (event) {

            if (
                !event.target.classList
                    .contains('remove-row')
            ) {
                return;
            }

            const row =
                event.target.closest(
                    '.schedule-row'
                );

            const container =
                row.closest(
                    '[id$="-container"]'
                );

            if (
                container
                    .querySelectorAll(
                        '.schedule-row'
                    )
                    .length
                <= 2
            ) {
                alert(
                    'Each direction needs at least two timetable stops.'
                );

                return;
            }

            const direction =
                container.id.replace(
                    '-container',
                    ''
                );

            row.remove();

            rebuild(direction);
        }
    );


    /*
    |--------------------------------------------------------------------------
    | Route Change
    |--------------------------------------------------------------------------
    */

    routeSelect.addEventListener(
        'change',
        function () {

            document
                .querySelectorAll('.route-stop-select')
                .forEach(select => {
                    select.dataset.selected = '';
                });

            loadRouteStops(
                this.value
            );
        }
    );


    /*
    |--------------------------------------------------------------------------
    | Initial Load
    |--------------------------------------------------------------------------
    */

    rebuild('starting');
    rebuild('return');

    if (routeSelect.value) {
        loadRouteStops(
            routeSelect.value
        );
    } else {
        clearStopDropdowns();
    }

})();
</script>

@endsection