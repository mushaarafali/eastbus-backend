@extends('layouts.app')

@section('title', $route ? 'Edit Master Route' : 'Add Master Route')
@section('header', $route ? 'Edit Master Route' : 'Add Master Route')

@section('content')

@php
    $isEdit = $route !== null;

    $roadRows = old('stops');

    if (!$roadRows) {
        $roadRows = collect($stops ?? [])->map(function ($stop) {
            return [
                'id' => $stop->id ?? null,
                'name' => $stop->name ?? '',
                'fare_stage_no' => $stop->fare_stage_no ?? '',
                'distance_from_origin_km' =>
                    $stop->distance_from_origin_km
                    ?? $stop->distance_from_origin
                    ?? '',
            ];
        })->toArray();
    }

    if (!$roadRows) {
        $roadRows = [
            [
                'id' => null,
                'name' => '',
                'fare_stage_no' => '',
                'distance_from_origin_km' => 0,
            ],
            [
                'id' => null,
                'name' => '',
                'fare_stage_no' => '',
                'distance_from_origin_km' => '',
            ],
        ];
    }
@endphp


<div class="container">

    {{-- ============================================================
         PAGE HEADER
    ============================================================ --}}

    <div style="margin-bottom:20px;">

        <h1 style="margin:0 0 6px;">
            {{ $isEdit ? 'Edit Master Route' : 'Add Master Route' }}
        </h1>

        <p
            style="
                margin:0;
                color:#667085;
                max-width:900px;
            "
        >
            Create the official fixed road way for this route.
            Bus Operators will select this route and can only choose booking
            points from the road way configured here.
        </p>

    </div>


    {{-- ============================================================
         VALIDATION ERRORS
    ============================================================ --}}

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


    {{-- ============================================================
         SUCCESS MESSAGE
    ============================================================ --}}

    @if(session('success'))

        <div class="alert alert-success">
            {{ session('success') }}
        </div>

    @endif


    {{-- ============================================================
         FORM
    ============================================================ --}}

    <form
        method="POST"
        action="{{
            $isEdit
                ? route('admin.routes.update', $route->id)
                : route('admin.routes.store')
        }}"
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

                <h3 style="margin-top:0;">
                    Route Details
                </h3>

                <div
                    style="
                        display:grid;
                        grid-template-columns:
                            repeat(auto-fit, minmax(220px, 1fr));
                        gap:16px;
                        max-width:900px;
                    "
                >

                    {{-- ROUTE NUMBER --}}

                    <div>

                        <label>
                            Route Number
                            <span style="color:#dc2626;">*</span>
                        </label>

                        <input
                            type="text"
                            name="route_number"
                            class="form-control"
                            value="{{ old(
                                'route_number',
                                $route->route_number ?? ''
                            ) }}"
                            placeholder="Example: 76, 04/86, 41/48, 76/3"
                            maxlength="50"
                            required
                        >

                    </div>


                    {{-- DURATION --}}

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
                            placeholder="Example: 350"
                        >

                    </div>

                </div>


                @if($isEdit)

                    <div
                        style="
                            margin-top:16px;
                            padding:12px 14px;
                            background:#f8fafc;
                            border:1px solid #e5e9ef;
                            border-radius:8px;
                            color:#475467;
                            font-size:13px;
                        "
                    >
                        Current route:
                        <strong>
                            {{ $route->origin }}
                            →
                            {{ $route->destination }}
                        </strong>

                        @if($route->distance_km !== null)
                            •
                            {{ number_format(
                                (float) $route->distance_km,
                                2
                            ) }}
                            km
                        @endif
                    </div>

                @endif

            </div>

        </div>


        {{-- ============================================================
             FIXED ROAD WAY
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
                            Fixed Road Way
                        </h3>

                        <small style="color:#667085;">
                            Add every official road-way stop in the correct order.
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

                            @if(!empty($stop['id']))

                                <input
                                    type="hidden"
                                    class="road-id"
                                    name="stops[{{ $index }}][id]"
                                    value="{{ $stop['id'] }}"
                                >

                            @else

                                <input
                                    type="hidden"
                                    class="road-id"
                                    name="stops[{{ $index }}][id]"
                                    value=""
                                >

                            @endif


                            <div
                                style="
                                    display:grid;
                                    grid-template-columns:
                                        70px
                                        minmax(220px,2fr)
                                        minmax(130px,1fr)
                                        minmax(170px,1fr)
                                        auto;
                                    gap:10px;
                                    align-items:end;
                                "
                            >

                                {{-- ORDER --}}

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


                                {{-- STOP NAME --}}

                                <div>

                                    <label>
                                        Road Way Stop
                                        <span style="color:#dc2626;">*</span>
                                    </label>

                                    <input
                                        type="text"
                                        class="form-control road-name"
                                        name="stops[{{ $index }}][name]"
                                        value="{{ $stop['name'] ?? '' }}"
                                        placeholder="Example: Kalmunai"
                                        maxlength="150"
                                        required
                                    >

                                </div>


                                {{-- FARE STAGE --}}

                                <div>

                                    <label>
                                        Fare Stage No
                                    </label>

                                    <input
                                        type="number"
                                        class="form-control road-stage"
                                        name="stops[{{ $index }}][fare_stage_no]"
                                        value="{{ $stop['fare_stage_no'] ?? '' }}"
                                        min="1"
                                        max="350"
                                        placeholder="102"
                                    >

                                </div>


                                {{-- DISTANCE --}}

                                <div>

                                    <label>
                                        Distance from Origin (km)
                                        <span style="color:#dc2626;">*</span>
                                    </label>

                                    <input
                                        type="number"
                                        class="form-control road-distance"
                                        name="stops[{{ $index }}][distance_from_origin_km]"
                                        value="{{ $stop['distance_from_origin_km'] ?? '' }}"
                                        step="0.01"
                                        min="0"
                                        placeholder="46.00"
                                        required
                                    >

                                </div>


                                {{-- REMOVE --}}

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


                <div
                    style="
                        margin-top:12px;
                        padding:12px 14px;
                        background:#f8fafc;
                        border-radius:8px;
                        color:#667085;
                        font-size:13px;
                    "
                >

                    <strong>Rules:</strong>

                    <div style="margin-top:5px;">
                        First road-way stop must start at
                        <strong>0 km</strong>.
                    </div>

                    <div>
                        Distance must increase in road-way order.
                    </div>

                    <div>
                        The first stop automatically becomes the route origin,
                        and the last stop becomes the destination.
                    </div>

                    <div>
                        Fare Stage No is optional for a normal road-way stop,
                        but required later if that stop is used for fare-based
                        booking.
                    </div>

                </div>

            </div>

        </div>


        {{-- ============================================================
             ROAD WAY PREVIEW
        ============================================================ --}}

        <div
            class="card"
            style="margin-top:18px;"
        >

            <div class="card-body">

                <h3 style="margin-top:0;">
                    Route Preview
                </h3>


                <div
                    id="route-preview-empty"
                    style="
                        padding:20px;
                        text-align:center;
                        color:#667085;
                        border:1px dashed #d5dae1;
                        border-radius:8px;
                    "
                >
                    Enter road-way stops to preview the route.
                </div>


                <div
                    id="route-preview"
                    style="
                        display:none;
                        overflow-x:auto;
                    "
                >

                    <table
                        style="
                            width:100%;
                            border-collapse:collapse;
                            min-width:700px;
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
                                    Stop
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


                        <tbody id="route-preview-body">
                        </tbody>

                    </table>

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
                    ? 'Update Master Route'
                    : 'Create Master Route'
                }}
            </button>


            <a
                href="{{ route('admin.routes.index') }}"
                class="btn btn-outline-secondary"
            >
                Cancel
            </a>

        </div>

    </form>

</div>


{{-- ================================================================
     ROAD STOP TEMPLATE
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

        <input
            type="hidden"
            class="road-id"
            value=""
        >


        <div
            style="
                display:grid;
                grid-template-columns:
                    70px
                    minmax(220px,2fr)
                    minmax(130px,1fr)
                    minmax(170px,1fr)
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
                    <span style="color:#dc2626;">*</span>
                </label>

                <input
                    type="text"
                    class="form-control road-name"
                    placeholder="Example: Kalmunai"
                    maxlength="150"
                    required
                >

            </div>


            <div>

                <label>
                    Fare Stage No
                </label>

                <input
                    type="number"
                    class="form-control road-stage"
                    min="1"
                    max="350"
                    placeholder="102"
                >

            </div>


            <div>

                <label>
                    Distance from Origin (km)
                    <span style="color:#dc2626;">*</span>
                </label>

                <input
                    type="number"
                    class="form-control road-distance"
                    step="0.01"
                    min="0"
                    placeholder="46.00"
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


<script>
(function () {

    const roadContainer =
        document.getElementById(
            'road-stops'
        );

    const roadTemplate =
        document.getElementById(
            'road-stop-template'
        );

    const addRoadButton =
        document.getElementById(
            'add-road-stop'
        );

    const preview =
        document.getElementById(
            'route-preview'
        );

    const previewEmpty =
        document.getElementById(
            'route-preview-empty'
        );

    const previewBody =
        document.getElementById(
            'route-preview-body'
        );


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
    | Current Road Way Data
    |--------------------------------------------------------------------------
    */

    function roadWayData() {

        return [
            ...roadContainer.querySelectorAll(
                '.road-stop-row'
            )
        ].map(
            function (row, index) {

                return {
                    order: index + 1,

                    id:
                        row.querySelector(
                            '.road-id'
                        ).value,

                    name:
                        row.querySelector(
                            '.road-name'
                        ).value.trim(),

                    stage:
                        row.querySelector(
                            '.road-stage'
                        ).value.trim(),

                    distance:
                        row.querySelector(
                            '.road-distance'
                        ).value.trim(),
                };
            }
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Rebuild Input Names
    |--------------------------------------------------------------------------
    */

    function rebuildRoadWay() {

        const rows =
            roadContainer.querySelectorAll(
                '.road-stop-row'
            );


        rows.forEach(
            function (row, index) {

                row.querySelector(
                    '.road-order'
                ).value =
                    index + 1;


                row.querySelector(
                    '.road-id'
                ).name =
                    `stops[${index}][id]`;


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
                    `stops[${index}][distance_from_origin_km]`;
            }
        );


        /*
         * First stop should always begin at 0 km.
         */
        const firstRow =
            rows[0];

        if (firstRow) {

            const distanceInput =
                firstRow.querySelector(
                    '.road-distance'
                );

            if (
                distanceInput.value === ''
            ) {
                distanceInput.value =
                    '0';
            }
        }


        renderPreview();
    }


    /*
    |--------------------------------------------------------------------------
    | Preview
    |--------------------------------------------------------------------------
    */

    function renderPreview() {

        const stops =
            roadWayData();


        previewBody.innerHTML =
            '';


        const validStops =
            stops.filter(
                stop => stop.name !== ''
            );


        if (!validStops.length) {

            preview.style.display =
                'none';

            previewEmpty.style.display =
                'block';

            return;
        }


        validStops.forEach(
            function (stop) {

                previewBody.insertAdjacentHTML(
                    'beforeend',
                    `
                        <tr>

                            <td
                                style="
                                    padding:10px;
                                    border-bottom:1px solid #edf0f3;
                                "
                            >
                                ${escapeHtml(stop.order)}
                            </td>

                            <td
                                style="
                                    padding:10px;
                                    border-bottom:1px solid #edf0f3;
                                    font-weight:600;
                                "
                            >
                                ${escapeHtml(stop.name)}
                            </td>

                            <td
                                style="
                                    padding:10px;
                                    border-bottom:1px solid #edf0f3;
                                "
                            >
                                ${
                                    stop.stage
                                        ? escapeHtml(stop.stage)
                                        : '-'
                                }
                            </td>

                            <td
                                style="
                                    padding:10px;
                                    border-bottom:1px solid #edf0f3;
                                "
                            >
                                ${
                                    stop.distance !== ''
                                        ? escapeHtml(stop.distance) + ' km'
                                        : '-'
                                }
                            </td>

                        </tr>
                    `
                );
            }
        );


        previewEmpty.style.display =
            'none';

        preview.style.display =
            'block';
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
    | Remove Road Way Stop
    |--------------------------------------------------------------------------
    */

    roadContainer.addEventListener(
        'click',
        function (event) {

            const button =
                event.target.closest(
                    '.remove-road-stop'
                );


            if (!button) {
                return;
            }


            const count =
                roadContainer.querySelectorAll(
                    '.road-stop-row'
                ).length;


            if (count <= 2) {

                alert(
                    'A master route must contain at least two road-way stops.'
                );

                return;
            }


            button
                .closest(
                    '.road-stop-row'
                )
                .remove();


            rebuildRoadWay();
        }
    );


    /*
    |--------------------------------------------------------------------------
    | Live Preview
    |--------------------------------------------------------------------------
    */

    roadContainer.addEventListener(
        'input',
        function () {

            renderPreview();
        }
    );


    /*
    |--------------------------------------------------------------------------
    | Initial Build
    |--------------------------------------------------------------------------
    */

    rebuildRoadWay();

})();
</script>

@endsection