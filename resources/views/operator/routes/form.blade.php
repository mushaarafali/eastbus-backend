@extends('layouts.app')

@section('title', $route ? 'Edit Route' : 'Add Route')
@section('header', $route ? 'Edit Route' : 'Add Route')

@section('content')

@php
    $isEdit = $route !== null;

    /*
    |--------------------------------------------------------------------------
    | Road Way Rows
    |--------------------------------------------------------------------------
    */

    $roadRows = old('stops');

    if (!$roadRows) {
        $roadRows = $stops->map(fn ($stop) => [
            'name' => $stop->name,
            'fare_stage_no' => $stop->fare_stage_no,
            'distance_from_origin' => $stop->distance_from_origin ?? 0,
        ])->toArray();
    }

    if (!$roadRows) {
        $roadRows = [
            [
                'name' => '',
                'fare_stage_no' => '',
                'distance_from_origin' => 0,
            ],
            [
                'name' => '',
                'fare_stage_no' => '',
                'distance_from_origin' => '',
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Starting Booking Points
    |--------------------------------------------------------------------------
    */

    $startingRows = old('starting_booking_stops');

    if (!$startingRows) {
        $startingRows = $startingBookingStops->map(function ($stop) {
            return [
                'road_stop_index' =>
                    max(0, ((int) $stop->road_stop_order) - 1),

                'schedule_time' =>
                    $stop->schedule_time
                        ? substr($stop->schedule_time, 0, 5)
                        : '',
            ];
        })->toArray();
    }

    if (!$startingRows) {
        $startingRows = [
            [
                'road_stop_index' => 0,
                'schedule_time' => '',
            ],
            [
                'road_stop_index' =>
                    max(0, count($roadRows) - 1),
                'schedule_time' => '',
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
        $returnRows = $returnBookingStops->map(function ($stop) {
            return [
                'road_stop_index' =>
                    max(0, ((int) $stop->road_stop_order) - 1),

                'schedule_time' =>
                    $stop->schedule_time
                        ? substr($stop->schedule_time, 0, 5)
                        : '',
            ];
        })->toArray();
    }

    if (!$returnRows) {
        $returnRows = [
            [
                'road_stop_index' =>
                    max(0, count($roadRows) - 1),
                'schedule_time' => '',
            ],
            [
                'road_stop_index' => 0,
                'schedule_time' => '',
            ],
        ];
    }
@endphp

<div class="container">

    <h1>
        {{ $isEdit ? 'Edit Route' : 'Add Route' }}
    </h1>

    <p style="color:#667085;">
        Add the complete road way first. Then select the passenger
        booking points for the Starting and Return schedules.
    </p>

    <p style="color:#667085;">
        Fare is calculated automatically using the NTC Fare Stage No.
        Online booking is allowed only for journeys of at least 50 km.
    </p>

    {{-- Validation Errors --}}
    @if($errors->any())
        <div class="alert alert-danger">
            <ul style="margin:0;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Success Message --}}
    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <form
        method="POST"
        action="{{ $isEdit
            ? route('operator.routes.update', $route->id)
            : route('operator.routes.store') }}"
        autocomplete="off"
    >
        @csrf

        @if($isEdit)
            @method('PUT')
        @endif

        {{-- ============================================================
             ROUTE DETAILS
        ============================================================ --}}

        <div class="card">
            <div class="card-body">

                <h3>Route Details</h3>

                <div
                    style="
                        display:grid;
                        grid-template-columns:1fr 1fr;
                        gap:14px;
                        max-width:800px;
                    "
                >
                    <div>
                        <label>
                            Route Number
                        </label>

                        <input
                            type="text"
                            name="route_number"
                            class="form-control"
                            value="{{ old(
                                'route_number',
                                $route->route_number ?? ''
                            ) }}"
                            placeholder="Example: 48"
                        >
                    </div>

                    <div>
                        <label>
                            Approx. Duration (minutes)
                        </label>

                        <input
                            type="number"
                            name="duration_minutes"
                            class="form-control"
                            value="{{ old(
                                'duration_minutes',
                                $route->duration_minutes ?? ''
                            ) }}"
                            min="1"
                            placeholder="Example: 180"
                        >
                    </div>
                </div>

                <small
                    style="
                        display:block;
                        margin-top:10px;
                        color:#667085;
                    "
                >
                    Origin, destination and total route distance are
                    calculated automatically from the Road Way.
                </small>

            </div>
        </div>

        {{-- ============================================================
             FULL ROAD WAY
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
                        margin-bottom:14px;
                    "
                >
                    <div>
                        <h3 style="margin:0;">
                            Road Way
                        </h3>

                        <small style="color:#667085;">
                            Add every road-way stop in the correct order.
                        </small>
                    </div>

                    <button
                        type="button"
                        id="add-road-stop"
                        class="btn btn-outline-secondary"
                    >
                        + Add Stop
                    </button>
                </div>

                <div id="road-stops">

                    @foreach($roadRows as $index => $stop)

                        <div
                            class="road-stop-row"
                            style="
                                border:1px solid #e1e5eb;
                                border-radius:10px;
                                padding:14px;
                                margin-bottom:10px;
                            "
                        >
                            <div
                                style="
                                    display:grid;
                                    grid-template-columns:
                                        70px
                                        minmax(180px,2fr)
                                        minmax(120px,1fr)
                                        minmax(160px,1.2fr)
                                        auto;
                                    gap:10px;
                                    align-items:end;
                                "
                            >
                                <div>
                                    <label>
                                        Order
                                    </label>

                                    <input
                                        class="form-control road-order"
                                        value="{{ $index + 1 }}"
                                        readonly
                                    >
                                </div>

                                <div>
                                    <label>
                                        Road Way Stop
                                    </label>

                                    <input
                                        class="form-control road-name"
                                        name="stops[{{ $index }}][name]"
                                        value="{{ $stop['name'] ?? '' }}"
                                        placeholder="Example: Batticaloa"
                                        required
                                    >
                                </div>

                                <div>
                                    <label>
                                        Fare Stage No
                                    </label>

                                    <input
                                        class="form-control road-stage"
                                        type="number"
                                        name="stops[{{ $index }}][fare_stage_no]"
                                        value="{{ $stop['fare_stage_no'] ?? '' }}"
                                        min="1"
                                        max="350"
                                        placeholder="81"
                                        required
                                    >
                                </div>

                                <div>
                                    <label>
                                        Distance from Origin (km)
                                    </label>

                                    <input
                                        class="form-control road-distance"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        name="stops[{{ $index }}][distance_from_origin]"
                                        value="{{ $stop['distance_from_origin'] ?? '' }}"
                                        placeholder="95.00"
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
                                            remove-road-stop
                                        "
                                    >
                                        Remove
                                    </button>
                                </div>

                            </div>
                        </div>

                    @endforeach

                </div>

                <small style="color:#667085;">
                    Latitude and Longitude are not required.
                    The first stop automatically becomes the origin
                    and the last stop becomes the destination.
                </small>

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
                        margin-bottom:14px;
                    "
                >
                    <div>
                        <h3 style="margin:0;">
                            Starting Booking Points
                        </h3>

                        <small style="color:#667085;">
                            Select only the stops where passengers
                            can book for the starting journey.
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
                            class="booking-row starting-booking-row"
                            style="
                                display:grid;
                                grid-template-columns:
                                    70px
                                    minmax(220px,2fr)
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
                                    class="
                                        form-control
                                        booking-order
                                    "
                                    value="{{ $index + 1 }}"
                                    readonly
                                >
                            </div>

                            <div>
                                <label>
                                    Booking Stop
                                </label>

                                <select
                                    class="
                                        form-control
                                        booking-road-index
                                    "
                                    name="
                                        starting_booking_stops[
                                            {{ $index }}
                                        ][road_stop_index]
                                    "
                                    data-selected="{{ $point['road_stop_index'] ?? '' }}"
                                    required
                                >
                                    <option value="">
                                        Select Road Way Stop
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label>
                                    Starting Time
                                </label>

                                <input
                                    type="time"
                                    class="
                                        form-control
                                        booking-time
                                    "
                                    name="
                                        starting_booking_stops[
                                            {{ $index }}
                                        ][schedule_time]
                                    "
                                    value="{{ $point['schedule_time'] ?? '' }}"
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
                        margin-bottom:14px;
                    "
                >
                    <div>
                        <h3 style="margin:0;">
                            Return Booking Points
                        </h3>

                        <small style="color:#667085;">
                            Return booking points must follow the
                            Road Way in reverse order.
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
                            class="booking-row return-booking-row"
                            style="
                                display:grid;
                                grid-template-columns:
                                    70px
                                    minmax(220px,2fr)
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
                                    class="
                                        form-control
                                        booking-order
                                    "
                                    value="{{ $index + 1 }}"
                                    readonly
                                >
                            </div>

                            <div>
                                <label>
                                    Booking Stop
                                </label>

                                <select
                                    class="
                                        form-control
                                        booking-road-index
                                    "
                                    name="
                                        return_booking_stops[
                                            {{ $index }}
                                        ][road_stop_index]
                                    "
                                    data-selected="{{ $point['road_stop_index'] ?? '' }}"
                                    required
                                >
                                    <option value="">
                                        Select Road Way Stop
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label>
                                    Return Time
                                </label>

                                <input
                                    type="time"
                                    class="
                                        form-control
                                        booking-time
                                    "
                                    name="
                                        return_booking_stops[
                                            {{ $index }}
                                        ][schedule_time]
                                    "
                                    value="{{ $point['schedule_time'] ?? '' }}"
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
                margin-top:18px;
                margin-bottom:30px;
            "
        >
            <button
                type="submit"
                class="btn btn-primary"
            >
                {{ $isEdit
                    ? 'Update Route'
                    : 'Create Route' }}
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
     ROAD WAY TEMPLATE
================================================================ --}}

<template id="road-stop-template">

    <div
        class="road-stop-row"
        style="
            border:1px solid #e1e5eb;
            border-radius:10px;
            padding:14px;
            margin-bottom:10px;
        "
    >
        <div
            style="
                display:grid;
                grid-template-columns:
                    70px
                    minmax(180px,2fr)
                    minmax(120px,1fr)
                    minmax(160px,1.2fr)
                    auto;
                gap:10px;
                align-items:end;
            "
        >
            <div>
                <label>
                    Order
                </label>

                <input
                    class="form-control road-order"
                    readonly
                >
            </div>

            <div>
                <label>
                    Road Way Stop
                </label>

                <input
                    class="form-control road-name"
                    placeholder="Example: Batticaloa"
                    required
                >
            </div>

            <div>
                <label>
                    Fare Stage No
                </label>

                <input
                    class="form-control road-stage"
                    type="number"
                    min="1"
                    max="350"
                    placeholder="81"
                    required
                >
            </div>

            <div>
                <label>
                    Distance from Origin (km)
                </label>

                <input
                    class="form-control road-distance"
                    type="number"
                    step="0.01"
                    min="0"
                    placeholder="95.00"
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
                        remove-road-stop
                    "
                >
                    Remove
                </button>
            </div>
        </div>
    </div>

</template>

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
                minmax(220px,2fr)
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
                Booking Stop
            </label>

            <select
                class="
                    form-control
                    booking-road-index
                "
                required
            >
                <option value="">
                    Select Road Way Stop
                </option>
            </select>
        </div>

        <div>
            <label class="booking-time-label">
                Time
            </label>

            <input
                type="time"
                class="
                    form-control
                    booking-time
                "
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

    const roadContainer =
        document.getElementById(
            'road-stops'
        );

    const startingContainer =
        document.getElementById(
            'starting-booking-stops'
        );

    const returnContainer =
        document.getElementById(
            'return-booking-stops'
        );

    const roadTemplate =
        document.getElementById(
            'road-stop-template'
        );

    const bookingTemplate =
        document.getElementById(
            'booking-stop-template'
        );

    const addRoadButton =
        document.getElementById(
            'add-road-stop'
        );

    const addStartingButton =
        document.getElementById(
            'add-starting-stop'
        );

    const addReturnButton =
        document.getElementById(
            'add-return-stop'
        );

    /*
    |--------------------------------------------------------------------------
    | Road Way Data
    |--------------------------------------------------------------------------
    */

    function roadWayStops() {

        return [
            ...roadContainer.querySelectorAll(
                '.road-stop-row'
            )
        ].map(
            (row, index) => {

                const name =
                    row.querySelector(
                        '.road-name'
                    ).value.trim();

                const stage =
                    row.querySelector(
                        '.road-stage'
                    ).value.trim();

                const distance =
                    row.querySelector(
                        '.road-distance'
                    ).value.trim();

                return {
                    index,
                    name,
                    stage,
                    distance,
                };
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Rebuild Road Way Names
    |--------------------------------------------------------------------------
    */

    function rebuildRoadWay() {

        const rows =
            roadContainer.querySelectorAll(
                '.road-stop-row'
            );

        rows.forEach(
            (row, index) => {

                row.querySelector(
                    '.road-order'
                ).value =
                    index + 1;

                row.querySelector(
                    '.road-name'
                ).name =
                    `stops[${index}][name]`;

                row.querySelector(
                    '.road-stage'
                ).name =
                    `stops[${index}][fare_stage_no]`;

                row.querySelector(
                    '.road-distance'
                ).name =
                    `stops[${index}][distance_from_origin]`;
            }
        );

        refreshBookingSelects();
    }

    /*
    |--------------------------------------------------------------------------
    | Refresh Booking Dropdowns
    |--------------------------------------------------------------------------
    */

    function refreshBookingSelects() {

        const roadStops =
            roadWayStops();

        document
            .querySelectorAll(
                '.booking-road-index'
            )
            .forEach(
                (select) => {

                    const previous =
                        select.value !== ''
                            ? select.value
                            : (
                                select.dataset.selected ??
                                ''
                            );

                    select.innerHTML =
                        '<option value="">Select Road Way Stop</option>';

                    roadStops.forEach(
                        (stop) => {

                            if (!stop.name) {
                                return;
                            }

                            const option =
                                document.createElement(
                                    'option'
                                );

                            option.value =
                                stop.index;

                            option.textContent =
                                `${stop.index + 1}. ${stop.name}` +
                                (
                                    stop.stage
                                        ? ` • Stage ${stop.stage}`
                                        : ''
                                ) +
                                (
                                    stop.distance
                                        ? ` • ${stop.distance} km`
                                        : ''
                                );

                            if (
                                String(stop.index) ===
                                String(previous)
                            ) {
                                option.selected = true;
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
    | Rebuild Booking Point Names
    |--------------------------------------------------------------------------
    */

    function rebuildBookingRows(
        container,
        direction
    ) {

        const rows =
            container.querySelectorAll(
                '.booking-row'
            );

        rows.forEach(
            (row, index) => {

                row.querySelector(
                    '.booking-order'
                ).value =
                    index + 1;

                row.querySelector(
                    '.booking-road-index'
                ).name =
                    `${direction}_booking_stops[${index}][road_stop_index]`;

                row.querySelector(
                    '.booking-time'
                ).name =
                    `${direction}_booking_stops[${index}][schedule_time]`;

                const label =
                    row.querySelector(
                        '.booking-time-label'
                    );

                if (label) {
                    label.textContent =
                        direction === 'starting'
                            ? 'Starting Time'
                            : 'Return Time';
                }
            }
        );

        refreshBookingSelects();
    }

    /*
    |--------------------------------------------------------------------------
    | Add Road Way Stop
    |--------------------------------------------------------------------------
    */

    addRoadButton.addEventListener(
        'click',
        function () {

            roadContainer.appendChild(
                roadTemplate.content
                    .cloneNode(true)
            );

            rebuildRoadWay();
        }
    );

    /*
    |--------------------------------------------------------------------------
    | Add Starting Booking Point
    |--------------------------------------------------------------------------
    */

    addStartingButton.addEventListener(
        'click',
        function () {

            const fragment =
                bookingTemplate.content
                    .cloneNode(true);

            const row =
                fragment.querySelector(
                    '.booking-row'
                );

            row.classList.add(
                'starting-booking-row'
            );

            startingContainer.appendChild(
                fragment
            );

            rebuildBookingRows(
                startingContainer,
                'starting'
            );
        }
    );

    /*
    |--------------------------------------------------------------------------
    | Add Return Booking Point
    |--------------------------------------------------------------------------
    */

    addReturnButton.addEventListener(
        'click',
        function () {

            const fragment =
                bookingTemplate.content
                    .cloneNode(true);

            const row =
                fragment.querySelector(
                    '.booking-row'
                );

            row.classList.add(
                'return-booking-row'
            );

            returnContainer.appendChild(
                fragment
            );

            rebuildBookingRows(
                returnContainer,
                'return'
            );
        }
    );

    /*
    |--------------------------------------------------------------------------
    | Remove Road Way Stop
    |--------------------------------------------------------------------------
    */

    roadContainer.addEventListener(
        'click',
        function (event) {

            if (
                !event.target.classList.contains(
                    'remove-road-stop'
                )
            ) {
                return;
            }

            const count =
                roadContainer.querySelectorAll(
                    '.road-stop-row'
                ).length;

            if (count <= 2) {
                alert(
                    'A route must contain at least two road-way stops.'
                );

                return;
            }

            event.target
                .closest(
                    '.road-stop-row'
                )
                .remove();

            rebuildRoadWay();

            rebuildBookingRows(
                startingContainer,
                'starting'
            );

            rebuildBookingRows(
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

    function removeBookingPoint(
        event,
        container,
        direction
    ) {

        if (
            !event.target.classList.contains(
                'remove-booking-stop'
            )
        ) {
            return;
        }

        const count =
            container.querySelectorAll(
                '.booking-row'
            ).length;

        if (count <= 2) {
            alert(
                'Each direction must contain at least two booking points.'
            );

            return;
        }

        event.target
            .closest(
                '.booking-row'
            )
            .remove();

        rebuildBookingRows(
            container,
            direction
        );
    }

    startingContainer.addEventListener(
        'click',
        function (event) {

            removeBookingPoint(
                event,
                startingContainer,
                'starting'
            );
        }
    );

    returnContainer.addEventListener(
        'click',
        function (event) {

            removeBookingPoint(
                event,
                returnContainer,
                'return'
            );
        }
    );

    /*
    |--------------------------------------------------------------------------
    | Update Booking Dropdowns When Road Way Changes
    |--------------------------------------------------------------------------
    */

    roadContainer.addEventListener(
        'input',
        function () {
            refreshBookingSelects();
        }
    );

    /*
    |--------------------------------------------------------------------------
    | Preserve Selected Booking Point
    |--------------------------------------------------------------------------
    */

    document.addEventListener(
        'change',
        function (event) {

            if (
                event.target.classList.contains(
                    'booking-road-index'
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

    rebuildRoadWay();

    rebuildBookingRows(
        startingContainer,
        'starting'
    );

    rebuildBookingRows(
        returnContainer,
        'return'
    );

})();
</script>

@endsection