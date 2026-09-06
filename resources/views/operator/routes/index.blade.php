@extends('layouts.app')

@section('title', 'Bus Route Services')
@section('header', 'Bus Route Services')

@section('content')

<div class="container">

    {{-- ============================================================
         PAGE HEADER
    ============================================================ --}}

    <div
        style="
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:16px;
            flex-wrap:wrap;
            margin-bottom:20px;
        "
    >
        <div>

            <h1 style="margin:0 0 6px;">
                Bus Route Services
            </h1>

            <p
                style="
                    margin:0;
                    color:#667085;
                    max-width:850px;
                "
            >
                Manage your buses on existing System Admin master routes.
                Road ways are fixed by the System Administrator.
                You can manage booking points and service times for each bus.
            </p>

        </div>

        <a
            href="{{ route('operator.routes.create') }}"
            class="btn btn-primary"
        >
            + Add Bus Service
        </a>

    </div>


    {{-- ============================================================
         SUCCESS
    ============================================================ --}}

    @if(session('success'))

        <div class="alert alert-success">
            {{ session('success') }}
        </div>

    @endif


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
         SERVICES
    ============================================================ --}}

    @forelse($services as $service)

        <div
            class="card"
            style="margin-bottom:18px;"
        >

            <div class="card-body">

                {{-- ====================================================
                     SERVICE HEADER
                ==================================================== --}}

                <div
                    style="
                        display:flex;
                        justify-content:space-between;
                        gap:16px;
                        align-items:flex-start;
                        flex-wrap:wrap;
                    "
                >

                    <div>

                        <h3
                            style="
                                margin:0 0 8px;
                            "
                        >
                            {{ $service->origin }}
                            →
                            {{ $service->destination }}
                        </h3>


                        <div
                            style="
                                display:flex;
                                flex-wrap:wrap;
                                gap:16px;
                                color:#475467;
                                font-size:14px;
                            "
                        >

                            <div>
                                <strong>
                                    Route:
                                </strong>

                                {{ $service->route_number ?: '-' }}
                            </div>


                            <div>
                                <strong>
                                    Bus:
                                </strong>

                                {{ $service->bus_number ?: '-' }}
                            </div>


                            @if(!empty($service->service_name))

                                <div>
                                    <strong>
                                        Service:
                                    </strong>

                                    {{ $service->service_name }}
                                </div>

                            @endif


                            @if($service->distance_km !== null)

                                <div>
                                    <strong>
                                        Distance:
                                    </strong>

                                    {{ number_format(
                                        (float) $service->distance_km,
                                        1
                                    ) }}
                                    km
                                </div>

                            @endif

                        </div>


                        <div
                            style="
                                display:flex;
                                gap:8px;
                                flex-wrap:wrap;
                                margin-top:12px;
                            "
                        >

                            @if($service->is_active)

                                <span
                                    style="
                                        display:inline-block;
                                        padding:5px 10px;
                                        border-radius:20px;
                                        background:#e8f7ee;
                                        color:#157347;
                                        font-size:12px;
                                        font-weight:700;
                                    "
                                >
                                    Active
                                </span>

                            @else

                                <span
                                    style="
                                        display:inline-block;
                                        padding:5px 10px;
                                        border-radius:20px;
                                        background:#f1f3f5;
                                        color:#667085;
                                        font-size:12px;
                                        font-weight:700;
                                    "
                                >
                                    Inactive
                                </span>

                            @endif


                            @if($service->is_published)

                                <span
                                    style="
                                        display:inline-block;
                                        padding:5px 10px;
                                        border-radius:20px;
                                        background:#eef4ff;
                                        color:#175cd3;
                                        font-size:12px;
                                        font-weight:700;
                                    "
                                >
                                    Published
                                </span>

                            @else

                                <span
                                    style="
                                        display:inline-block;
                                        padding:5px 10px;
                                        border-radius:20px;
                                        background:#fff6ed;
                                        color:#b54708;
                                        font-size:12px;
                                        font-weight:700;
                                    "
                                >
                                    Not Published
                                </span>

                            @endif

                        </div>

                    </div>


                    {{-- =================================================
                         ACTIONS
                    ================================================= --}}

                    <div
                        style="
                            display:flex;
                            gap:8px;
                            flex-wrap:wrap;
                        "
                    >

                        <a
                            href="{{ route(
                                'operator.routes.edit',
                                $service->id
                            ) }}"
                            class="btn btn-outline-primary"
                        >
                            Edit Service
                        </a>


                        <form
                            method="POST"
                            action="{{ route(
                                'operator.routes.destroy',
                                $service->id
                            ) }}"
                            onsubmit="
                                return confirm(
                                    'Remove this bus route service?'
                                );
                            "
                        >

                            @csrf
                            @method('DELETE')

                            <button
                                type="submit"
                                class="btn btn-outline-danger"
                            >
                                Delete Service
                            </button>

                        </form>

                    </div>

                </div>


                <hr>


                {{-- ====================================================
                     MASTER ROUTE INFO
                ==================================================== --}}

                <div
                    style="
                        margin-bottom:20px;
                        padding:14px;
                        background:#f8fafc;
                        border:1px solid #e5e9ef;
                        border-radius:10px;
                    "
                >

                    <div
                        style="
                            font-weight:700;
                            margin-bottom:4px;
                        "
                    >
                        Master Route
                    </div>

                    <div
                        style="
                            color:#667085;
                            font-size:13px;
                        "
                    >
                        Route
                        {{ $service->route_number ?: '-' }}
                        •
                        {{ $service->origin }}
                        →
                        {{ $service->destination }}

                        @if($service->distance_km !== null)
                            •
                            {{ number_format(
                                (float) $service->distance_km,
                                1
                            ) }}
                            km
                        @endif
                    </div>

                    <div
                        style="
                            margin-top:6px;
                            color:#98a2b3;
                            font-size:12px;
                        "
                    >
                        Road way is controlled by the System Administrator.
                    </div>

                </div>


                {{-- ====================================================
                     STARTING BOOKING POINTS
                ==================================================== --}}

                <div style="margin-bottom:22px;">

                    <h4 style="margin-bottom:10px;">
                        Starting Booking Points
                    </h4>


                    @if(
                        !empty($service->starting_booking_stops)
                        &&
                        count($service->starting_booking_stops)
                    )

                        <div
                            style="
                                overflow-x:auto;
                            "
                        >

                            <table
                                style="
                                    width:100%;
                                    border-collapse:collapse;
                                    min-width:650px;
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
                                                border-bottom:1px solid #e4e7ec;
                                                width:80px;
                                            "
                                        >
                                            Order
                                        </th>

                                        <th
                                            style="
                                                padding:10px;
                                                border-bottom:1px solid #e4e7ec;
                                            "
                                        >
                                            Booking Point
                                        </th>

                                        <th
                                            style="
                                                padding:10px;
                                                border-bottom:1px solid #e4e7ec;
                                                width:150px;
                                            "
                                        >
                                            Time
                                        </th>

                                        <th
                                            style="
                                                padding:10px;
                                                border-bottom:1px solid #e4e7ec;
                                                width:140px;
                                            "
                                        >
                                            Fare Stage
                                        </th>

                                    </tr>

                                </thead>


                                <tbody>

                                    @foreach(
                                        $service->starting_booking_stops
                                        as $stop
                                    )

                                        @php
                                            $time =
                                                $stop->departure_time
                                                ?? $stop->arrival_time
                                                ?? null;
                                        @endphp

                                        <tr>

                                            <td
                                                style="
                                                    padding:10px;
                                                    border-bottom:1px solid #f0f2f5;
                                                "
                                            >
                                                {{ $stop->stop_order }}
                                            </td>


                                            <td
                                                style="
                                                    padding:10px;
                                                    border-bottom:1px solid #f0f2f5;
                                                    font-weight:600;
                                                "
                                            >
                                                {{ $stop->stop_name }}
                                            </td>


                                            <td
                                                style="
                                                    padding:10px;
                                                    border-bottom:1px solid #f0f2f5;
                                                "
                                            >

                                                @if($time)

                                                    {{
                                                        \Carbon\Carbon::parse(
                                                            $time
                                                        )->format('h:i A')
                                                    }}

                                                @else
                                                    -
                                                @endif

                                            </td>


                                            <td
                                                style="
                                                    padding:10px;
                                                    border-bottom:1px solid #f0f2f5;
                                                "
                                            >
                                                {{ $stop->fare_stage_no ?: '-' }}
                                            </td>

                                        </tr>

                                    @endforeach

                                </tbody>

                            </table>

                        </div>

                    @else

                        <div
                            style="
                                color:#98a2b3;
                                padding:12px 0;
                            "
                        >
                            No starting booking points configured.
                        </div>

                    @endif

                </div>


                {{-- ====================================================
                     RETURN BOOKING POINTS
                ==================================================== --}}

                <div>

                    <h4 style="margin-bottom:10px;">
                        Return Booking Points
                    </h4>


                    @if(
                        !empty($service->return_booking_stops)
                        &&
                        count($service->return_booking_stops)
                    )

                        <div
                            style="
                                overflow-x:auto;
                            "
                        >

                            <table
                                style="
                                    width:100%;
                                    border-collapse:collapse;
                                    min-width:650px;
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
                                                border-bottom:1px solid #e4e7ec;
                                                width:80px;
                                            "
                                        >
                                            Order
                                        </th>

                                        <th
                                            style="
                                                padding:10px;
                                                border-bottom:1px solid #e4e7ec;
                                            "
                                        >
                                            Booking Point
                                        </th>

                                        <th
                                            style="
                                                padding:10px;
                                                border-bottom:1px solid #e4e7ec;
                                                width:150px;
                                            "
                                        >
                                            Time
                                        </th>

                                        <th
                                            style="
                                                padding:10px;
                                                border-bottom:1px solid #e4e7ec;
                                                width:140px;
                                            "
                                        >
                                            Fare Stage
                                        </th>

                                    </tr>

                                </thead>


                                <tbody>

                                    @foreach(
                                        $service->return_booking_stops
                                        as $stop
                                    )

                                        @php
                                            $time =
                                                $stop->departure_time
                                                ?? $stop->arrival_time
                                                ?? null;
                                        @endphp

                                        <tr>

                                            <td
                                                style="
                                                    padding:10px;
                                                    border-bottom:1px solid #f0f2f5;
                                                "
                                            >
                                                {{ $stop->stop_order }}
                                            </td>


                                            <td
                                                style="
                                                    padding:10px;
                                                    border-bottom:1px solid #f0f2f5;
                                                    font-weight:600;
                                                "
                                            >
                                                {{ $stop->stop_name }}
                                            </td>


                                            <td
                                                style="
                                                    padding:10px;
                                                    border-bottom:1px solid #f0f2f5;
                                                "
                                            >

                                                @if($time)

                                                    {{
                                                        \Carbon\Carbon::parse(
                                                            $time
                                                        )->format('h:i A')
                                                    }}

                                                @else
                                                    -
                                                @endif

                                            </td>


                                            <td
                                                style="
                                                    padding:10px;
                                                    border-bottom:1px solid #f0f2f5;
                                                "
                                            >
                                                {{ $stop->fare_stage_no ?: '-' }}
                                            </td>

                                        </tr>

                                    @endforeach

                                </tbody>

                            </table>

                        </div>

                    @else

                        <div
                            style="
                                color:#98a2b3;
                                padding:12px 0;
                            "
                        >
                            No return booking points configured.
                        </div>

                    @endif

                </div>

            </div>

        </div>

    @empty

        {{-- ============================================================
             EMPTY STATE
        ============================================================ --}}

        <div
            class="card"
        >

            <div
                class="card-body"
                style="
                    text-align:center;
                    padding:50px 20px;
                "
            >

                <div
                    style="
                        font-size:18px;
                        font-weight:700;
                        color:#344054;
                        margin-bottom:8px;
                    "
                >
                    No Bus Route Services
                </div>


                <div
                    style="
                        color:#667085;
                        margin-bottom:18px;
                    "
                >
                    Select one of the available master routes and configure
                    the booking points and service times for your bus.
                </div>


                <a
                    href="{{ route('operator.routes.create') }}"
                    class="btn btn-primary"
                >
                    + Add Bus Service
                </a>

            </div>

        </div>

    @endforelse

</div>

@endsection