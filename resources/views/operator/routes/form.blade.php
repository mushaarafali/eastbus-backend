@extends('layouts.app')

@section('title', $service ? 'Edit Bus Service' : 'Add Bus Service')
@section('header', $service ? 'Edit Bus Service' : 'Add Bus Service')

@section('content')

@php
    $isEdit = $service !== null;

    /*
    |--------------------------------------------------------------------------
    | Selected Bus
    |--------------------------------------------------------------------------
    */

    $selectedBusId = old(
        'bus_id',
        $service->bus_id ?? ''
    );

    /*
    |--------------------------------------------------------------------------
    | Selected Master Route
    |--------------------------------------------------------------------------
    */

    $selectedRouteId = old(
        'route_id',
        $service->route_id ?? ''
    );

    /*
    |--------------------------------------------------------------------------
    | Starting Booking Points
    |--------------------------------------------------------------------------
    */

    $startingRows = old('starting_booking_stops');

    if (!$startingRows) {
        $startingRows = $startingBookingStops
            ->map(function ($stop) {
                return [
                    'route_stop_id' => $stop->route_stop_id,

                    'time' =>
                        $stop->departure_time
                        ? substr($stop->departure_time, 0, 5)
                        : (
                            $stop->arrival_time
                            ? substr($stop->arrival_time, 0, 5)
                            : ''
                        ),
                ];
            })
            ->toArray();
    }

    if (!$startingRows) {
        $startingRows = [
            [
                'route_stop_id' => '',
                'time' => '',
            ],
            [
                'route_stop_id' => '',
                'time' => '',
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Return Booking Points
    |--------------------------------------------------------------------------
    */

    $returnRows = old('return_booking_stops');

    if (!$returnRows) {
        $returnRows = $returnBookingStops
            ->map(function ($stop) {
                return [
                    'route_stop_id' => $stop->route_stop_id,

                    'time' =>
                        $stop->departure_time
                        ? substr($stop->departure_time, 0, 5)
                        : (
                            $stop->arrival_time
                            ? substr($stop->arrival_time, 0, 5)
                            : ''
                        ),
                ];
            })
            ->toArray();
    }

    if (!$returnRows) {
        $returnRows = [
            [
                'route_stop_id' => '',
                'time' => '',
            ],
            [
                'route_stop_id' => '',
                'time' => '',
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Existing Master Roadway
    |--------------------------------------------------------------------------
    */

    $initialRouteStops = collect($routeStops ?? [])
        ->map(function ($stop) {
            return [
                'id' => $stop->id,
                'name' => $stop->name,
                'stop_order' => (int) $stop->stop_order,
                'fare_stage_no' => $stop->fare_stage_no,

                'distance_from_origin_km' =>
                    $stop->distance_from_origin_km
                    ?? $stop->distance_from_origin
                    ?? 0,
            ];
        })
        ->values()
        ->toArray();
@endphp


<div class="container">

    {{-- ================================================================
         PAGE INTRODUCTION
    ================================================================ --}}

    <div style="margin-bottom:20px;">

        <h1 style="margin-bottom:6px;">
            {{ $isEdit ? 'Edit Bus Service' : 'Add Bus Service' }}
        </h1>

        <p
            style="
                color:#667085;
                margin:0;
                max-width:900px;
            "
        >
            Select your bus and an existing System Admin master route.
            The fixed road way will load automatically.
            You only need to select passenger booking points and assign
            the relevant times for this bus.
        </p>

    </div>


    {{-- ================================================================
         VALIDATION ERRORS
    ================================================================ --}}

    @if($errors->any())

        <div class="alert alert-danger">

            <ul style="margin:0;">

                @foreach($errors->all() as $error)

                    <li>
                        {{ $error }}
                    </li>

                @endforeach

            </ul>

        </div>

    @endif


    {{-- ================================================================
         SUCCESS MESSAGE
    ================================================================ --}}

    @if(session('success'))

        <div class="alert alert-success">
            {{ session('success') }}
        </div>

    @endif


    {{-- ================================================================
         FORM
    ================================================================ --}}

    <form
        method="POST"
        action="{{
            $isEdit
                ? route('operator.routes.update', $service->id)
                : route('operator.routes.store')
        }}"
        autocomplete="off"
    >

        @csrf

        @if($isEdit)
            @method('PUT')
        @endif


        {{-- ============================================================
             BUS + MASTER ROUTE
        ============================================================ --}}

        <div class="card">

            <div class="card-body">

                <h3 style="margin-top:0;">
                    Bus Service Details
                </h3>

                <div
                    style="
                        display:grid;
                        grid-template-columns:
                            repeat(auto-fit, minmax(240px, 1fr));
                        gap:16px;
                    "
                >

                    {{-- BUS --}}

                    <div>

                        <label>
                            Bus
                            <span style="color:#dc2626;">*</span>
                        </label>

                        <select
                            name="bus_id"
                            id="bus-select"
                            class="form-control"
                            required
                        >

                            <option value="">
                                Select Bus
                            </option>

                            @foreach($buses as $bus)

                                <option
                                    value="{{ $bus->id }}"
                                    @selected(
                                        (string) $selectedBusId
                                        ===
                                        (string) $bus->id
                                    )
                                >
                                    {{ $bus->bus_number }}

                                    @if(!empty($bus->bus_type))
                                        — {{ $bus->bus_type }}
                                    @endif
                                </option>

                            @endforeach

                        </select>

                    </div>


                    {{-- MASTER ROUTE --}}

                    <div>

                        <label>
                            Master Route
                            <span style="color:#dc2626;">*</span>
                        </label>

                        <select
                            name="route_id"
                            id="route-select"
                            class="form-control"
                            required
                        >

                            <option value="">
                                Select Route
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

                                    {{ $route->route_number ?: 'No Route No.' }}

                                    —

                                    {{ $route->origin }}

                                    →

                                    {{ $route->destination }}

                                </option>

                            @endforeach

                        </select>

                    </div>


                    {{-- SERVICE NAME --}}

                    <div>

                        <label>
                            Service Name
                        </label>

                        <input
                            type="text"
                            name="service_name"
                            class="form-control"
                            value="{{ old(
                                'service_name',
                                $service->service_name ?? ''
                            ) }}"
                            placeholder="Optional"
                            maxlength="150"
                        >

                    </div>

                </div>


                <div
                    style="
                        display:flex;
                        gap:24px;
                        flex-wrap:wrap;
                        margin-top:18px;
                    "
                >

                    <label
                        style="
                            display:flex;
                            align-items:center;
                            gap:8px;
                        "
                    >

                        <input
                            type="checkbox"
                            name="is_active"
                            value="1"
                            @checked(
                                old(
                                    'is_active',
                                    $service->is_active ?? true
                                )
                            )
                        >

                        Active Service

                    </label>


                    <label
                        style="
                            display:flex;
                            align-items:center;
                            gap:8px;
                        "
                    >

                        <input
                            type="checkbox"
                            name="is_published"
                            value="1"
                            @checked(
                                old(
                                    'is_published',
                                    $service->is_published ?? false
                                )
                            )
                        >

                        Publish Service

                    </label>

                </div>

            </div>

        </div>


        {{-- ============================================================
             FIXED MASTER ROADWAY
        ============================================================ --}}

        <div
            class="card"
            style="margin-top:18px;"
        >

            <div class="card-body">

                <div
                    style="
                        display:flex;
                        justify-content:space-between;
                        align-items:flex-start;
                        gap:16px;
                        flex-wrap:wrap;
                        margin-bottom:14px;
                    "
                >

                    <div>

                        <h3 style="margin:0 0 4px;">
                            Fixed Road Way
                        </h3>

                        <small style="color:#667085;">
                            This road way is managed by the System Administrator
                            and cannot be changed by the Bus Operator.
                        </small>

                    </div>


                    <div
                        id="route-summary"
                        style="
                            display:none;
                            padding:8px 12px;
                            background:#f5f7fa;
                            border-radius:8px;
                            font-size:13px;
                        "
                    ></div>

                </div>


                {{-- NO ROUTE SELECTED --}}

                <div
                    id="roadway-empty"
                    style="
                        padding:24px;
                        text-align:center;
                        border:1px dashed #cfd6df;
                        border-radius:10px;
                        color:#667085;
                    "
                >

                    Select a master route to view its fixed road way.

                </div>


                {{-- LOADING --}}

                <div
                    id="roadway-loading"
                    style="
                        display:none;
                        padding:24px;
                        text-align:center;
                        color:#667085;
                    "
                >

                    Loading road way...

                </div>


                {{-- ROADWAY TABLE --}}

                <div
                    id="roadway-wrapper"
                    style="
                        display:none;
                        overflow-x:auto;
                    "
                >

                    <table
                        style="
                            width:100%;
                            border-collapse:collapse;
                        "
                    >

                        <thead>

                            <tr
                                style="
                                    background:#f7f8fa;
                                    text-align:left;
                                "
                            >

                                <th
                                    style="
                                        padding:10px;
                                        border-bottom:1px solid #e1e5eb;
                                        width:80px;
                                    "
                                >
                                    Order
                                </th>

                                <th
                                    style="
                                        padding:10px;
                                        border-bottom:1px solid #e1e5eb;
                                    "
                                >
                                    Road Way Stop
                                </th>

                                <th
                                    style="
                                        padding:10px;
                                        border-bottom:1px solid #e1e5eb;
                                        width:140px;
                                    "
                                >
                                    Fare Stage
                                </th>

                                <th
                                    style="
                                        padding:10px;
                                        border-bottom:1px solid #e1e5eb;
                                        width:180px;
                                    "
                                >
                                    Distance
                                </th>

                            </tr>

                        </thead>


                        <tbody id="roadway-body">
                        </tbody>

                    </table>

                </div>

            </div>

        </div>


        {{-- ============================================================
             STARTING BOOKING POINTS
        ============================================================ --}}

        <div
            class="card"
            style="margin-top:18px;"
        >

            <div class="card-body">

                <div
                    style="
                        display:flex;
                        justify-content:space-between;
                        align-items:center;
                        gap:12px;
                        flex-wrap:wrap;
                        margin-bottom:14px;
                    "
                >

                    <div>

                        <h3 style="margin:0;">
                            Starting Booking Points
                        </h3>

                        <small style="color:#667085;">
                            Select booking points from the fixed road way
                            in the starting direction.
                        </small>

                    </div>


                    <button
                        type="button"
                        id="add-starting-stop"
                        class="btn btn-outline-secondary"
                    >
                        + Add Booking Point
                    </button>

                </div>


                <div id="starting-booking-stops">

                    @foreach($startingRows as $index => $point)

                        <div
                            class="booking-row"
                            style="
                                display:grid;
                                grid-template-columns:
                                    70px
                                    minmax(240px,2fr)
                                    minmax(150px,1fr)
                                    auto;
                                gap:10px;
                                align-items:end;
                                border:1px solid #e1e5eb;
                                border-radius:10px;
                                padding:14px;
                                margin-bottom:10px;
                            "
                        >

                            <div>

                                <label>
                                    Order
                                </label>

                                <input
                                    class="form-control booking-order"
                                    value="{{ $index + 1 }}"
                                    readonly
                                >

                            </div>


                            <div>

                                <label>
                                    Booking Point
                                </label>

                                <select
                                    class="form-control booking-stop-select"
                                    name="starting_booking_stops[{{ $index }}][route_stop_id]"
                                    data-selected="{{ $point['route_stop_id'] ?? '' }}"
                                    required
                                >

                                    <option value="">
                                        Select Booking Point
                                    </option>

                                </select>

                            </div>


                            <div>

                                <label>
                                    Time
                                </label>

                                <input
                                    type="time"
                                    class="form-control booking-time"
                                    name="starting_booking_stops[{{ $index }}][departure_time]"
                                    value="{{ $point['time'] ?? '' }}"
                                    required
                                >

                            </div>


                            <div>

                                <button
                                    type="button"
                                    class="
                                        btn
                                        btn-sm
                                        btn-outline-danger
                                        remove-booking-stop
                                    "
                                >
                                    Remove
                                </button>

                            </div>

                        </div>

                    @endforeach

                </div>

            </div>

        </div>


        {{-- ============================================================
             RETURN BOOKING POINTS
        ============================================================ --}}

        <div
            class="card"
            style="margin-top:18px;"
        >

            <div class="card-body">

                <div
                    style="
                        display:flex;
                        justify-content:space-between;
                        align-items:center;
                        gap:12px;
                        flex-wrap:wrap;
                        margin-bottom:14px;
                    "
                >

                    <div>

                        <h3 style="margin:0;">
                            Return Booking Points
                        </h3>

                        <small style="color:#667085;">
                            Select booking points in reverse road-way order
                            for the return journey.
                        </small>

                    </div>


                    <button
                        type="button"
                        id="add-return-stop"
                        class="btn btn-outline-secondary"
                    >
                        + Add Booking Point
                    </button>

                </div>


                <div id="return-booking-stops">

                    @foreach($returnRows as $index => $point)

                        <div
                            class="booking-row"
                            style="
                                display:grid;
                                grid-template-columns:
                                    70px
                                    minmax(240px,2fr)
                                    minmax(150px,1fr)
                                    auto;
                                gap:10px;
                                align-items:end;
                                border:1px solid #e1e5eb;
                                border-radius:10px;
                                padding:14px;
                                margin-bottom:10px;
                            "
                        >

                            <div>

                                <label>
                                    Order
                                </label>

                                <input
                                    class="form-control booking-order"
                                    value="{{ $index + 1 }}"
                                    readonly
                                >

                            </div>


                            <div>

                                <label>
                                    Booking Point
                                </label>

                                <select
                                    class="form-control booking-stop-select"
                                    name="return_booking_stops[{{ $index }}][route_stop_id]"
                                    data-selected="{{ $point['route_stop_id'] ?? '' }}"
                                    required
                                >

                                    <option value="">
                                        Select Booking Point
                                    </option>

                                </select>

                            </div>


                            <div>

                                <label>
                                    Time
                                </label>

                                <input
                                    type="time"
                                    class="form-control booking-time"
                                    name="return_booking_stops[{{ $index }}][departure_time]"
                                    value="{{ $point['time'] ?? '' }}"
                                    required
                                >

                            </div>


                            <div>

                                <button
                                    type="button"
                                    class="
                                        btn
                                        btn-sm
                                        btn-outline-danger
                                        remove-booking-stop
                                    "
                                >
                                    Remove
                                </button>

                            </div>

                        </div>

                    @endforeach

                </div>

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
                margin:18px 0 30px;
            "
        >

            <button
                type="submit"
                class="btn btn-primary"
            >
                {{ $isEdit
                    ? 'Update Bus Service'
                    : 'Create Bus Service'
                }}
            </button>


            <a
                href="{{ route('operator.routes.index') }}"
                class="btn btn-outline-secondary"
            >
                Cancel
            </a>

        </div>

    </form>

</div>


{{-- ================================================================
     BOOKING POINT TEMPLATE
================================================================ --}}

<template id="booking-stop-template">

    <div
        class="booking-row"
        style="
            display:grid;
            grid-template-columns:
                70px
                minmax(240px,2fr)
                minmax(150px,1fr)
                auto;
            gap:10px;
            align-items:end;
            border:1px solid #e1e5eb;
            border-radius:10px;
            padding:14px;
            margin-bottom:10px;
        "
    >

        <div>

            <label>
                Order
            </label>

            <input
                class="form-control booking-order"
                readonly
            >

        </div>


        <div>

            <label>
                Booking Point
            </label>

            <select
                class="form-control booking-stop-select"
                required
            >

                <option value="">
                    Select Booking Point
                </option>

            </select>

        </div>


        <div>

            <label>
                Time
            </label>

            <input
                type="time"
                class="form-control booking-time"
                required
            >

        </div>


        <div>

            <button
                type="button"
                class="
                    btn
                    btn-sm
                    btn-outline-danger
                    remove-booking-stop
                "
            >
                Remove
            </button>

        </div>

    </div>

</template>


<script>
(function () {

    /*
    |--------------------------------------------------------------------------
    | Elements
    |--------------------------------------------------------------------------
    */

    const routeSelect =
        document.getElementById(
            'route-select'
        );

    const roadwayEmpty =
        document.getElementById(
            'roadway-empty'
        );

    const roadwayLoading =
        document.getElementById(
            'roadway-loading'
        );

    const roadwayWrapper =
        document.getElementById(
            'roadway-wrapper'
        );

    const roadwayBody =
        document.getElementById(
            'roadway-body'
        );

    const routeSummary =
        document.getElementById(
            'route-summary'
        );

    const startingContainer =
        document.getElementById(
            'starting-booking-stops'
        );

    const returnContainer =
        document.getElementById(
            'return-booking-stops'
        );

    const addStartingButton =
        document.getElementById(
            'add-starting-stop'
        );

    const addReturnButton =
        document.getElementById(
            'add-return-stop'
        );

    const bookingTemplate =
        document.getElementById(
            'booking-stop-template'
        );


    /*
    |--------------------------------------------------------------------------
    | Initial Roadway From Blade
    |--------------------------------------------------------------------------
    */

    let routeStops =
        @json($initialRouteStops);


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
    | Render Fixed Roadway
    |--------------------------------------------------------------------------
    */

    function renderRoadway() {

        roadwayBody.innerHTML = '';

        if (!routeStops.length) {

            roadwayEmpty.style.display =
                'block';

            roadwayWrapper.style.display =
                'none';

            refreshBookingDropdowns();

            return;
        }


        routeStops.forEach(
            function (stop) {

                const distance =
                    stop.distance_from_origin_km
                    ?? 0;

                const fareStage =
                    stop.fare_stage_no
                    ?? '-';


                roadwayBody.insertAdjacentHTML(
                    'beforeend',
                    `
                        <tr>

                            <td
                                style="
                                    padding:10px;
                                    border-bottom:
                                        1px solid #edf0f3;
                                "
                            >
                                ${escapeHtml(stop.stop_order)}
                            </td>

                            <td
                                style="
                                    padding:10px;
                                    border-bottom:
                                        1px solid #edf0f3;
                                    font-weight:600;
                                "
                            >
                                ${escapeHtml(stop.name)}
                            </td>

                            <td
                                style="
                                    padding:10px;
                                    border-bottom:
                                        1px solid #edf0f3;
                                "
                            >
                                ${escapeHtml(fareStage)}
                            </td>

                            <td
                                style="
                                    padding:10px;
                                    border-bottom:
                                        1px solid #edf0f3;
                                "
                            >
                                ${escapeHtml(distance)} km
                            </td>

                        </tr>
                    `
                );
            }
        );


        roadwayEmpty.style.display =
            'none';

        roadwayWrapper.style.display =
            'block';


        refreshBookingDropdowns();
    }


    /*
    |--------------------------------------------------------------------------
    | Booking Point Dropdown
    |--------------------------------------------------------------------------
    */

    function refreshBookingDropdowns() {

        document
            .querySelectorAll(
                '.booking-stop-select'
            )
            .forEach(
                function (select) {

                    const selectedValue =
                        select.value
                        ||
                        select.dataset.selected
                        ||
                        '';


                    select.innerHTML =
                        '<option value="">Select Booking Point</option>';


                    routeStops.forEach(
                        function (stop) {

                            const option =
                                document.createElement(
                                    'option'
                                );


                            option.value =
                                stop.id;


                            let label =
                                `${stop.stop_order}. ${stop.name}`;


                            if (
                                stop.distance_from_origin_km
                                !== null
                                &&
                                stop.distance_from_origin_km
                                !== undefined
                            ) {
                                label +=
                                    ` • ${stop.distance_from_origin_km} km`;
                            }


                            option.textContent =
                                label;


                            if (
                                String(stop.id)
                                ===
                                String(selectedValue)
                            ) {
                                option.selected =
                                    true;
                            }


                            select.appendChild(
                                option
                            );
                        }
                    );


                    select.dataset.selected =
                        select.value;
                }
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Load Master Route
    |--------------------------------------------------------------------------
    */

    async function loadRoute(
        routeId
    ) {

        if (!routeId) {

            routeStops = [];

            routeSummary.style.display =
                'none';

            renderRoadway();

            return;
        }


        roadwayEmpty.style.display =
            'none';

        roadwayWrapper.style.display =
            'none';

        roadwayLoading.style.display =
            'block';


        try {

            const response =
                await fetch(
                    `/operator/routes/master/${routeId}/stops`,
                    {
                        headers: {
                            'Accept':
                                'application/json',
                        },
                    }
                );


            if (!response.ok) {

                throw new Error(
                    'Unable to load route.'
                );
            }


            const data =
                await response.json();


            routeStops =
                data.stops
                ?? [];


            if (data.route) {

                const routeNumber =
                    data.route.route_number
                    ?? '-';


                const origin =
                    data.route.origin
                    ?? '-';


                const destination =
                    data.route.destination
                    ?? '-';


                const distance =
                    data.route.distance_km
                    ?? '-';


                routeSummary.textContent =
                    `Route ${routeNumber} • ` +
                    `${origin} → ${destination} • ` +
                    `${distance} km`;


                routeSummary.style.display =
                    'block';
            }


            renderRoadway();

        } catch (error) {

            console.error(error);


            routeStops = [];

            renderRoadway();


            alert(
                'Unable to load the selected master route.'
            );

        } finally {

            roadwayLoading.style.display =
                'none';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Route Change
    |--------------------------------------------------------------------------
    */

    routeSelect.addEventListener(
        'change',
        function () {

            /*
             * When route changes, old booking
             * point selections must not be kept.
             */

            document
                .querySelectorAll(
                    '.booking-stop-select'
                )
                .forEach(
                    function (select) {

                        select.value = '';

                        select.dataset.selected =
                            '';
                    }
                );


            loadRoute(
                this.value
            );
        }
    );


    /*
    |--------------------------------------------------------------------------
    | Rebuild Booking Row Names
    |--------------------------------------------------------------------------
    */

    function rebuildRows(
        container,
        direction
    ) {

        const rows =
            container.querySelectorAll(
                '.booking-row'
            );


        rows.forEach(
            function (row, index) {

                row.querySelector(
                    '.booking-order'
                ).value =
                    index + 1;


                row.querySelector(
                    '.booking-stop-select'
                ).name =
                    `${direction}_booking_stops[${index}][route_stop_id]`;


                row.querySelector(
                    '.booking-time'
                ).name =
                    `${direction}_booking_stops[${index}][departure_time]`;
            }
        );


        refreshBookingDropdowns();
    }


    /*
    |--------------------------------------------------------------------------
    | Add Booking Row
    |--------------------------------------------------------------------------
    */

    function addBookingRow(
        container,
        direction
    ) {

        const fragment =
            bookingTemplate
                .content
                .cloneNode(true);


        container.appendChild(
            fragment
        );


        rebuildRows(
            container,
            direction
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Add Starting
    |--------------------------------------------------------------------------
    */

    addStartingButton.addEventListener(
        'click',
        function () {

            addBookingRow(
                startingContainer,
                'starting'
            );
        }
    );


    /*
    |--------------------------------------------------------------------------
    | Add Return
    |--------------------------------------------------------------------------
    */

    addReturnButton.addEventListener(
        'click',
        function () {

            addBookingRow(
                returnContainer,
                'return'
            );
        }
    );


    /*
    |--------------------------------------------------------------------------
    | Remove Booking Point
    |--------------------------------------------------------------------------
    */

    function handleRemove(
        event,
        container,
        direction
    ) {

        const button =
            event.target.closest(
                '.remove-booking-stop'
            );


        if (!button) {
            return;
        }


        const rows =
            container.querySelectorAll(
                '.booking-row'
            );


        if (rows.length <= 2) {

            alert(
                'Each direction must contain at least two booking points.'
            );

            return;
        }


        button
            .closest(
                '.booking-row'
            )
            .remove();


        rebuildRows(
            container,
            direction
        );
    }


    startingContainer.addEventListener(
        'click',
        function (event) {

            handleRemove(
                event,
                startingContainer,
                'starting'
            );
        }
    );


    returnContainer.addEventListener(
        'click',
        function (event) {

            handleRemove(
                event,
                returnContainer,
                'return'
            );
        }
    );


    /*
    |--------------------------------------------------------------------------
    | Preserve Dropdown Selection
    |--------------------------------------------------------------------------
    */

    document.addEventListener(
        'change',
        function (event) {

            if (
                event.target.classList.contains(
                    'booking-stop-select'
                )
            ) {

                event.target.dataset.selected =
                    event.target.value;
            }
        }
    );


    /*
    |--------------------------------------------------------------------------
    | Initial Build
    |--------------------------------------------------------------------------
    */

    rebuildRows(
        startingContainer,
        'starting'
    );


    rebuildRows(
        returnContainer,
        'return'
    );


    /*
     * Edit page already receives routeStops
     * from controller.
     */
    if (
        routeStops.length > 0
    ) {

        renderRoadway();

    } else if (
        routeSelect.value
    ) {

        loadRoute(
            routeSelect.value
        );

    } else {

        renderRoadway();
    }

})();
</script>

@endsection