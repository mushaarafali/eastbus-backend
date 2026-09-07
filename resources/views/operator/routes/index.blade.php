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
         SUCCESS MESSAGE
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
         BUS ROUTE SERVICES
    ============================================================ --}}

    @forelse($services as $service)

        @php
            /*
            |--------------------------------------------------------------------------
            | Safe Service Values
            |--------------------------------------------------------------------------
            |
            | Supports both old and new controller aliases.
            |
            */

            $serviceId =
                $service->fixed_service_id
                ?? $service->id
                ?? null;

            $routeOrigin =
                $service->route_origin
                ?? $service->origin
                ?? '-';

            $routeDestination =
                $service->route_destination
                ?? $service->destination
                ?? '-';

            $routeNumber =
                $service->route_number
                ?? '-';

            $routeName =
                $service->route_name
                ?? null;

            $busNumber =
                $service->bus_number
                ?? '-';

            $busName =
                $service->bus_name
                ?? null;

            $serviceName =
                $service->service_name
                ?? null;

            $distanceKm =
                $service->route_distance_km
                ?? $service->distance_km
                ?? null;

            $durationMinutes =
                $service->route_duration_minutes
                ?? $service->duration_minutes
                ?? null;

            $isActive =
                (bool) (
                    $service->is_active
                    ?? false
                );

            $isPublished =
                (bool) (
                    $service->is_published
                    ?? false
                );

            $startingStops =
                $service->starting_booking_stops
                ?? $service->starting_stops
                ?? [];

            $returnStops =
                $service->return_booking_stops
                ?? $service->return_stops
                ?? [];
        @endphp


        <div
            class="card"
            style="
                margin-bottom:18px;
            "
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
                            {{ $routeOrigin }}
                            ↔
                            {{ $routeDestination }}
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

                                {{ $routeNumber }}
                            </div>


                            @if(!empty($routeName))
                                <div>
                                    <strong>
                                        Route Name:
                                    </strong>

                                    {{ $routeName }}
                                </div>
                            @endif


                            <div>
                                <strong>
                                    Bus:
                                </strong>

                                {{ $busNumber }}

                                @if(!empty($busName))
                                    - {{ $busName }}
                                @endif
                            </div>


                            @if(!empty($serviceName))
                                <div>
                                    <strong>
                                        Service:
                                    </strong>

                                    {{ $serviceName }}
                                </div>
                            @endif


                            @if($distanceKm !== null)
                                <div>
                                    <strong>
                                        Distance:
                                    </strong>

                                    {{
                                        number_format(
                                            (float) $distanceKm,
                                            1
                                        )
                                    }}
                                    km
                                </div>
                            @endif


                            @if($durationMinutes !== null)
                                <div>
                                    <strong>
                                        Duration:
                                    </strong>

                                    {{ (int) $durationMinutes }}
                                    min
                                </div>
                            @endif

                        </div>


                        {{-- ============================================
                             STATUS
                        ============================================ --}}

                        <div
                            style="
                                display:flex;
                                gap:8px;
                                flex-wrap:wrap;
                                margin-top:12px;
                            "
                        >

                            @if($isActive)
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


                            @if($isPublished)
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

                        @if($serviceId)

                            <a
                                href="{{ route(
                                    'operator.routes.edit',
                                    $serviceId
                                ) }}"
                                class="btn btn-outline-primary"
                            >
                                Edit Service
                            </a>


                            <form
                                method="POST"
                                action="{{ route(
                                    'operator.routes.destroy',
                                    $serviceId
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

                        @endif

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
                        Route {{ $routeNumber }}

                        • {{ $routeOrigin }}

                        ↔ {{ $routeDestination }}

                        @if($distanceKm !== null)
                            •
                            {{
                                number_format(
                                    (float) $distanceKm,
                                    1
                                )
                            }}
                            km
                        @endif

                        @if($durationMinutes !== null)
                            •
                            {{ (int) $durationMinutes }}
                            min
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
                        The same Master Route is used for both Starting and Return services.
                    </div>

                </div>


                {{-- ====================================================
                     STARTING BOOKING POINTS
                ==================================================== --}}

                <div
                    style="
                        margin-bottom:22px;
                    "
                >

                    <div
                        style="
                            display:flex;
                            justify-content:space-between;
                            align-items:center;
                            gap:10px;
                            flex-wrap:wrap;
                            margin-bottom:10px;
                        "
                    >
                        <h4 style="margin:0;">
                            Starting Booking Points
                        </h4>

                        <span
                            style="
                                color:#667085;
                                font-size:13px;
                            "
                        >
                            {{ $routeOrigin }}
                            →
                            {{ $routeDestination }}
                        </span>
                    </div>


                    @if(count($startingStops))

                        <div style="overflow-x:auto;">

                            <table
                                style="
                                    width:100%;
                                    border-collapse:collapse;
                                    min-width:760px;
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
                                                width:140px;
                                            "
                                        >
                                            Arrival
                                        </th>

                                        <th
                                            style="
                                                padding:10px;
                                                border-bottom:1px solid #e4e7ec;
                                                width:140px;
                                            "
                                        >
                                            Departure
                                        </th>

                                        <th
                                            style="
                                                padding:10px;
                                                border-bottom:1px solid #e4e7ec;
                                                width:120px;
                                            "
                                        >
                                            Fare Stage
                                        </th>

                                        <th
                                            style="
                                                padding:10px;
                                                border-bottom:1px solid #e4e7ec;
                                                width:120px;
                                            "
                                        >
                                            Distance
                                        </th>
                                    </tr>
                                </thead>


                                <tbody>

                                    @foreach($startingStops as $stop)

                                        @php
                                            $stopName =
                                                $stop->route_stop_name
                                                ?? $stop->stop_name
                                                ?? $stop->name
                                                ?? '-';

                                            $stopOrder =
                                                $stop->stop_order
                                                ?? '-';

                                            $arrivalTime =
                                                $stop->arrival_time
                                                ?? null;

                                            $departureTime =
                                                $stop->departure_time
                                                ?? null;

                                            $fareStage =
                                                $stop->fare_stage_no
                                                ?? null;

                                            $stopDistance =
                                                $stop->distance_from_origin_km
                                                ?? $stop->distance_from_origin
                                                ?? null;
                                        @endphp


                                        <tr>

                                            <td
                                                style="
                                                    padding:10px;
                                                    border-bottom:1px solid #f0f2f5;
                                                "
                                            >
                                                {{ $stopOrder }}
                                            </td>


                                            <td
                                                style="
                                                    padding:10px;
                                                    border-bottom:1px solid #f0f2f5;
                                                    font-weight:600;
                                                "
                                            >
                                                {{ $stopName }}
                                            </td>


                                            <td
                                                style="
                                                    padding:10px;
                                                    border-bottom:1px solid #f0f2f5;
                                                "
                                            >
                                                @if($arrivalTime)
                                                    {{
                                                        \Carbon\Carbon::parse(
                                                            $arrivalTime
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
                                                @if($departureTime)
                                                    {{
                                                        \Carbon\Carbon::parse(
                                                            $departureTime
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
                                                {{ $fareStage ?? '-' }}
                                            </td>


                                            <td
                                                style="
                                                    padding:10px;
                                                    border-bottom:1px solid #f0f2f5;
                                                "
                                            >
                                                @if($stopDistance !== null)
                                                    {{
                                                        number_format(
                                                            (float) $stopDistance,
                                                            1
                                                        )
                                                    }}
                                                    km
                                                @else
                                                    -
                                                @endif
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

                    <div
                        style="
                            display:flex;
                            justify-content:space-between;
                            align-items:center;
                            gap:10px;
                            flex-wrap:wrap;
                            margin-bottom:10px;
                        "
                    >
                        <h4 style="margin:0;">
                            Return Booking Points
                        </h4>

                        <span
                            style="
                                color:#667085;
                                font-size:13px;
                            "
                        >
                            {{ $routeDestination }}
                            →
                            {{ $routeOrigin }}
                        </span>
                    </div>


                    @if(count($returnStops))

                        <div style="overflow-x:auto;">

                            <table
                                style="
                                    width:100%;
                                    border-collapse:collapse;
                                    min-width:760px;
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
                                                width:140px;
                                            "
                                        >
                                            Arrival
                                        </th>

                                        <th
                                            style="
                                                padding:10px;
                                                border-bottom:1px solid #e4e7ec;
                                                width:140px;
                                            "
                                        >
                                            Departure
                                        </th>

                                        <th
                                            style="
                                                padding:10px;
                                                border-bottom:1px solid #e4e7ec;
                                                width:120px;
                                            "
                                        >
                                            Fare Stage
                                        </th>

                                        <th
                                            style="
                                                padding:10px;
                                                border-bottom:1px solid #e4e7ec;
                                                width:120px;
                                            "
                                        >
                                            Distance
                                        </th>
                                    </tr>
                                </thead>


                                <tbody>

                                    @foreach($returnStops as $stop)

                                        @php
                                            $stopName =
                                                $stop->route_stop_name
                                                ?? $stop->stop_name
                                                ?? $stop->name
                                                ?? '-';

                                            $stopOrder =
                                                $stop->stop_order
                                                ?? '-';

                                            $arrivalTime =
                                                $stop->arrival_time
                                                ?? null;

                                            $departureTime =
                                                $stop->departure_time
                                                ?? null;

                                            $fareStage =
                                                $stop->fare_stage_no
                                                ?? null;

                                            $stopDistance =
                                                $stop->distance_from_origin_km
                                                ?? $stop->distance_from_origin
                                                ?? null;
                                        @endphp


                                        <tr>

                                            <td
                                                style="
                                                    padding:10px;
                                                    border-bottom:1px solid #f0f2f5;
                                                "
                                            >
                                                {{ $stopOrder }}
                                            </td>


                                            <td
                                                style="
                                                    padding:10px;
                                                    border-bottom:1px solid #f0f2f5;
                                                    font-weight:600;
                                                "
                                            >
                                                {{ $stopName }}
                                            </td>


                                            <td
                                                style="
                                                    padding:10px;
                                                    border-bottom:1px solid #f0f2f5;
                                                "
                                            >
                                                @if($arrivalTime)
                                                    {{
                                                        \Carbon\Carbon::parse(
                                                            $arrivalTime
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
                                                @if($departureTime)
                                                    {{
                                                        \Carbon\Carbon::parse(
                                                            $departureTime
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
                                                {{ $fareStage ?? '-' }}
                                            </td>


                                            <td
                                                style="
                                                    padding:10px;
                                                    border-bottom:1px solid #f0f2f5;
                                                "
                                            >
                                                @if($stopDistance !== null)
                                                    {{
                                                        number_format(
                                                            (float) $stopDistance,
                                                            1
                                                        )
                                                    }}
                                                    km
                                                @else
                                                    -
                                                @endif
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

        <div class="card">

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
                    Select one of the available Master Routes and configure
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