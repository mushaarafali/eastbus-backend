@extends('layouts.app')

@section('content')

<div class="container">

    <div
        style="
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:16px;
            margin-bottom:20px;
        "
    >
        <div>
            <h1>Routes & Stops</h1>

            <p style="margin:0;color:#667085;">
                Manage full road ways, Starting booking points and Return booking points.
            </p>
        </div>

        <a
            href="{{ route('operator.routes.create') }}"
            class="btn btn-primary"
        >
            Add Route
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul style="margin:0;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @forelse($routes as $route)

        <div
            class="card"
            style="margin-bottom:18px;"
        >
            <div class="card-body">

                {{-- =====================================================
                     ROUTE HEADER
                ====================================================== --}}

                <div
                    style="
                        display:flex;
                        justify-content:space-between;
                        gap:15px;
                        align-items:flex-start;
                        flex-wrap:wrap;
                    "
                >
                    <div>
                        <h3 style="margin-bottom:8px;">
                            {{ $route->origin }}
                            →
                            {{ $route->destination }}
                        </h3>

                        @if(isset($route->route_number) && $route->route_number)
                            <div>
                                <strong>Route Number:</strong>
                                {{ $route->route_number }}
                            </div>
                        @endif

                        <div>
                            <strong>Distance:</strong>
                            {{ number_format((float) $route->distance_km, 1) }} km
                        </div>

                        @if($route->duration_minutes)
                            <div>
                                <strong>Approx. Duration:</strong>
                                {{ $route->duration_minutes }} minutes
                            </div>
                        @endif
                    </div>

                    <div
                        style="
                            display:flex;
                            gap:8px;
                            flex-wrap:wrap;
                        "
                    >
                        <a
                            href="{{ route('operator.routes.edit', $route->id) }}"
                            class="btn btn-outline-primary"
                        >
                            Edit Route
                        </a>

                        <form
                            method="POST"
                            action="{{ route('operator.routes.destroy', $route->id) }}"
                            onsubmit="return confirm('Delete this route?');"
                        >
                            @csrf
                            @method('DELETE')

                            <button
                                type="submit"
                                class="btn btn-outline-danger"
                            >
                                Delete
                            </button>
                        </form>
                    </div>
                </div>

                <hr>

                {{-- =====================================================
                     FULL ROAD WAY
                ====================================================== --}}

                <div style="margin-bottom:20px;">

                    <h4 style="margin-bottom:10px;">
                        Road Way
                    </h4>

                    @if(!empty($route->stops) && count($route->stops))

                        <div
                            style="
                                display:flex;
                                flex-wrap:wrap;
                                gap:8px;
                                align-items:center;
                            "
                        >
                            @foreach($route->stops as $stop)

                                <span
                                    style="
                                        display:inline-flex;
                                        align-items:center;
                                        gap:6px;
                                        padding:7px 10px;
                                        background:#f2f4f7;
                                        border:1px solid #e4e7ec;
                                        border-radius:8px;
                                        font-size:13px;
                                    "
                                >
                                    <strong>
                                        {{ $stop->stop_order }}.
                                    </strong>

                                    {{ $stop->name }}

                                    @if(isset($stop->fare_stage_no) && $stop->fare_stage_no)
                                        <span style="color:#667085;">
                                            • Stage {{ $stop->fare_stage_no }}
                                        </span>
                                    @endif

                                    @if(isset($stop->distance_from_origin))
                                        <span style="color:#667085;">
                                            • {{ number_format((float) $stop->distance_from_origin, 1) }} km
                                        </span>
                                    @endif
                                </span>

                                @if(!$loop->last)
                                    <span
                                        style="
                                            color:#98a2b3;
                                            font-weight:700;
                                        "
                                    >
                                        →
                                    </span>
                                @endif

                            @endforeach
                        </div>

                    @else

                        <div style="color:#98a2b3;">
                            No road-way stops added.
                        </div>

                    @endif

                </div>

                {{-- =====================================================
                     STARTING BOOKING POINTS
                ====================================================== --}}

                <div style="margin-bottom:20px;">

                    <h4 style="margin-bottom:10px;">
                        Starting Booking Points
                    </h4>

                    @if(
                        !empty($route->starting_booking_stops)
                        && count($route->starting_booking_stops)
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
                                    min-width:600px;
                                "
                            >
                                <thead>
                                    <tr>
                                        <th style="text-align:left;padding:8px;border-bottom:1px solid #e4e7ec;">
                                            Order
                                        </th>

                                        <th style="text-align:left;padding:8px;border-bottom:1px solid #e4e7ec;">
                                            Stop
                                        </th>

                                        <th style="text-align:left;padding:8px;border-bottom:1px solid #e4e7ec;">
                                            Time
                                        </th>

                                        <th style="text-align:left;padding:8px;border-bottom:1px solid #e4e7ec;">
                                            Fare Stage
                                        </th>

                                        <th style="text-align:left;padding:8px;border-bottom:1px solid #e4e7ec;">
                                            Distance
                                        </th>
                                    </tr>
                                </thead>

                                <tbody>
                                    @foreach($route->starting_booking_stops as $stop)
                                        <tr>
                                            <td style="padding:8px;border-bottom:1px solid #f0f2f5;">
                                                {{ $stop->stop_order }}
                                            </td>

                                            <td style="padding:8px;border-bottom:1px solid #f0f2f5;">
                                                {{ $stop->stop_name }}
                                            </td>

                                            <td style="padding:8px;border-bottom:1px solid #f0f2f5;">
                                                {{ \Carbon\Carbon::parse($stop->schedule_time)->format('h:i A') }}
                                            </td>

                                            <td style="padding:8px;border-bottom:1px solid #f0f2f5;">
                                                {{ $stop->fare_stage_no }}
                                            </td>

                                            <td style="padding:8px;border-bottom:1px solid #f0f2f5;">
                                                {{ number_format((float) $stop->distance_from_origin, 1) }} km
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                    @else

                        <div style="color:#98a2b3;">
                            No Starting booking points configured.
                        </div>

                    @endif

                </div>

                {{-- =====================================================
                     RETURN BOOKING POINTS
                ====================================================== --}}

                <div>

                    <h4 style="margin-bottom:10px;">
                        Return Booking Points
                    </h4>

                    @if(
                        !empty($route->return_booking_stops)
                        && count($route->return_booking_stops)
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
                                    min-width:600px;
                                "
                            >
                                <thead>
                                    <tr>
                                        <th style="text-align:left;padding:8px;border-bottom:1px solid #e4e7ec;">
                                            Order
                                        </th>

                                        <th style="text-align:left;padding:8px;border-bottom:1px solid #e4e7ec;">
                                            Stop
                                        </th>

                                        <th style="text-align:left;padding:8px;border-bottom:1px solid #e4e7ec;">
                                            Time
                                        </th>

                                        <th style="text-align:left;padding:8px;border-bottom:1px solid #e4e7ec;">
                                            Fare Stage
                                        </th>

                                        <th style="text-align:left;padding:8px;border-bottom:1px solid #e4e7ec;">
                                            Distance
                                        </th>
                                    </tr>
                                </thead>

                                <tbody>
                                    @foreach($route->return_booking_stops as $stop)
                                        <tr>
                                            <td style="padding:8px;border-bottom:1px solid #f0f2f5;">
                                                {{ $stop->stop_order }}
                                            </td>

                                            <td style="padding:8px;border-bottom:1px solid #f0f2f5;">
                                                {{ $stop->stop_name }}
                                            </td>

                                            <td style="padding:8px;border-bottom:1px solid #f0f2f5;">
                                                {{ \Carbon\Carbon::parse($stop->schedule_time)->format('h:i A') }}
                                            </td>

                                            <td style="padding:8px;border-bottom:1px solid #f0f2f5;">
                                                {{ $stop->fare_stage_no }}
                                            </td>

                                            <td style="padding:8px;border-bottom:1px solid #f0f2f5;">
                                                {{ number_format((float) $stop->distance_from_origin, 1) }} km
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                    @else

                        <div style="color:#98a2b3;">
                            No Return booking points configured.
                        </div>

                    @endif

                </div>

            </div>
        </div>

    @empty

        <div class="alert alert-info">
            No routes have been created yet.
        </div>

    @endforelse

</div>

@endsection