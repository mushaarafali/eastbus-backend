@extends('layouts.app')

@section('title', 'Master Routes')
@section('header', 'Master Routes')

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
                Master Routes
            </h1>

            <p
                style="
                    margin:0;
                    color:#667085;
                "
            >
                Manage the fixed EastBus road routes used by bus operators.
            </p>
        </div>

        <a
            href="{{ route('admin.routes.create') }}"
            class="btn btn-primary"
        >
            + Add Master Route
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
         MASTER ROUTES TABLE
    ============================================================ --}}

    <div class="card">

        <div class="card-body">

            @if($routes->count())

                <div
                    style="
                        overflow-x:auto;
                    "
                >

                    <table
                        style="
                            width:100%;
                            border-collapse:collapse;
                            min-width:950px;
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
                                        padding:12px;
                                        border-bottom:1px solid #e1e5eb;
                                        width:70px;
                                    "
                                >
                                    #
                                </th>

                                <th
                                    style="
                                        padding:12px;
                                        border-bottom:1px solid #e1e5eb;
                                        width:130px;
                                    "
                                >
                                    Route No.
                                </th>

                                <th
                                    style="
                                        padding:12px;
                                        border-bottom:1px solid #e1e5eb;
                                    "
                                >
                                    Origin
                                </th>

                                <th
                                    style="
                                        padding:12px;
                                        border-bottom:1px solid #e1e5eb;
                                    "
                                >
                                    Destination
                                </th>

                                <th
                                    style="
                                        padding:12px;
                                        border-bottom:1px solid #e1e5eb;
                                        width:140px;
                                    "
                                >
                                    Distance
                                </th>

                                <th
                                    style="
                                        padding:12px;
                                        border-bottom:1px solid #e1e5eb;
                                        width:120px;
                                    "
                                >
                                    Duration
                                </th>

                                <th
                                    style="
                                        padding:12px;
                                        border-bottom:1px solid #e1e5eb;
                                        width:100px;
                                    "
                                >
                                    Stops
                                </th>

                                <th
                                    style="
                                        padding:12px;
                                        border-bottom:1px solid #e1e5eb;
                                        width:110px;
                                    "
                                >
                                    Status
                                </th>

                                <th
                                    style="
                                        padding:12px;
                                        border-bottom:1px solid #e1e5eb;
                                        width:180px;
                                    "
                                >
                                    Actions
                                </th>
                            </tr>
                        </thead>


                        <tbody>

                            @foreach($routes as $route)

                                <tr>

                                    <td
                                        style="
                                            padding:12px;
                                            border-bottom:1px solid #edf0f3;
                                        "
                                    >
                                        {{ $route->id }}
                                    </td>


                                    <td
                                        style="
                                            padding:12px;
                                            border-bottom:1px solid #edf0f3;
                                            font-weight:700;
                                        "
                                    >
                                        {{ $route->route_number ?: '-' }}
                                    </td>


                                    <td
                                        style="
                                            padding:12px;
                                            border-bottom:1px solid #edf0f3;
                                        "
                                    >
                                        {{ $route->origin ?: '-' }}
                                    </td>


                                    <td
                                        style="
                                            padding:12px;
                                            border-bottom:1px solid #edf0f3;
                                        "
                                    >
                                        {{ $route->destination ?: '-' }}
                                    </td>


                                    <td
                                        style="
                                            padding:12px;
                                            border-bottom:1px solid #edf0f3;
                                        "
                                    >
                                        @if($route->distance_km !== null)
                                            {{ number_format(
                                                (float) $route->distance_km,
                                                2
                                            ) }} km
                                        @else
                                            -
                                        @endif
                                    </td>


                                    <td
                                        style="
                                            padding:12px;
                                            border-bottom:1px solid #edf0f3;
                                        "
                                    >
                                        @if($route->duration_minutes)
                                            {{ $route->duration_minutes }} min
                                        @else
                                            -
                                        @endif
                                    </td>


                                    <td
                                        style="
                                            padding:12px;
                                            border-bottom:1px solid #edf0f3;
                                        "
                                    >
                                        {{ $route->stop_count ?? 0 }}
                                    </td>


                                    <td
                                        style="
                                            padding:12px;
                                            border-bottom:1px solid #edf0f3;
                                        "
                                    >
                                        @if($route->is_active)

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
                                    </td>


                                    <td
                                        style="
                                            padding:12px;
                                            border-bottom:1px solid #edf0f3;
                                        "
                                    >

                                        <div
                                            style="
                                                display:flex;
                                                gap:8px;
                                                flex-wrap:wrap;
                                            "
                                        >

                                            <a
                                                href="{{ route(
                                                    'admin.routes.edit',
                                                    $route->id
                                                ) }}"
                                                class="btn btn-sm btn-outline-primary"
                                            >
                                                Edit
                                            </a>


                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-secondary"
                                                onclick="toggleRoadway(
                                                    'roadway-{{ $route->id }}'
                                                )"
                                            >
                                                View Road Way
                                            </button>


                                            <form
                                                method="POST"
                                                action="{{ route(
                                                    'admin.routes.destroy',
                                                    $route->id
                                                ) }}"
                                                onsubmit="
                                                    return confirm(
                                                        'Delete this master route?'
                                                    );
                                                "
                                                style="display:inline;"
                                            >
                                                @csrf
                                                @method('DELETE')

                                                <button
                                                    type="submit"
                                                    class="btn btn-sm btn-outline-danger"
                                                >
                                                    Delete
                                                </button>
                                            </form>

                                        </div>

                                    </td>

                                </tr>


                                {{-- ============================================
                                     ROADWAY DETAILS ROW
                                ============================================ --}}

                                <tr
                                    id="roadway-{{ $route->id }}"
                                    style="display:none;"
                                >

                                    <td
                                        colspan="9"
                                        style="
                                            padding:0;
                                            border-bottom:1px solid #dfe4ea;
                                        "
                                    >

                                        <div
                                            style="
                                                background:#fafbfc;
                                                padding:18px;
                                            "
                                        >

                                            <div
                                                style="
                                                    display:flex;
                                                    justify-content:space-between;
                                                    gap:12px;
                                                    align-items:center;
                                                    flex-wrap:wrap;
                                                    margin-bottom:12px;
                                                "
                                            >

                                                <div>

                                                    <strong>
                                                        Route
                                                        {{ $route->route_number }}
                                                        Road Way
                                                    </strong>

                                                    <div
                                                        style="
                                                            color:#667085;
                                                            font-size:13px;
                                                            margin-top:3px;
                                                        "
                                                    >
                                                        {{ $route->origin }}
                                                        →
                                                        {{ $route->destination }}
                                                    </div>

                                                </div>

                                                <span
                                                    style="
                                                        font-size:13px;
                                                        color:#667085;
                                                    "
                                                >
                                                    {{ $route->stop_count ?? 0 }}
                                                    stops
                                                </span>

                                            </div>


                                            @if(
                                                isset($route->stops)
                                                &&
                                                $route->stops->count()
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
                                                            background:#fff;
                                                        "
                                                    >

                                                        <thead>

                                                            <tr
                                                                style="
                                                                    background:#f3f5f7;
                                                                    text-align:left;
                                                                "
                                                            >

                                                                <th
                                                                    style="
                                                                        padding:10px;
                                                                        border:1px solid #e3e7ec;
                                                                        width:80px;
                                                                    "
                                                                >
                                                                    Order
                                                                </th>

                                                                <th
                                                                    style="
                                                                        padding:10px;
                                                                        border:1px solid #e3e7ec;
                                                                    "
                                                                >
                                                                    Stop
                                                                </th>

                                                                <th
                                                                    style="
                                                                        padding:10px;
                                                                        border:1px solid #e3e7ec;
                                                                        width:140px;
                                                                    "
                                                                >
                                                                    Fare Stage
                                                                </th>

                                                                <th
                                                                    style="
                                                                        padding:10px;
                                                                        border:1px solid #e3e7ec;
                                                                        width:180px;
                                                                    "
                                                                >
                                                                    Distance
                                                                </th>

                                                            </tr>

                                                        </thead>


                                                        <tbody>

                                                            @foreach(
                                                                $route->stops
                                                                as $stop
                                                            )

                                                                @php
                                                                    $distance =
                                                                        $stop->distance_from_origin_km
                                                                        ?? $stop->distance_from_origin
                                                                        ?? null;
                                                                @endphp

                                                                <tr>

                                                                    <td
                                                                        style="
                                                                            padding:10px;
                                                                            border:1px solid #e3e7ec;
                                                                        "
                                                                    >
                                                                        {{ $stop->stop_order }}
                                                                    </td>


                                                                    <td
                                                                        style="
                                                                            padding:10px;
                                                                            border:1px solid #e3e7ec;
                                                                            font-weight:600;
                                                                        "
                                                                    >
                                                                        {{ $stop->name }}
                                                                    </td>


                                                                    <td
                                                                        style="
                                                                            padding:10px;
                                                                            border:1px solid #e3e7ec;
                                                                        "
                                                                    >
                                                                        {{ $stop->fare_stage_no ?: '-' }}
                                                                    </td>


                                                                    <td
                                                                        style="
                                                                            padding:10px;
                                                                            border:1px solid #e3e7ec;
                                                                        "
                                                                    >

                                                                        @if($distance !== null)

                                                                            {{ number_format(
                                                                                (float) $distance,
                                                                                2
                                                                            ) }}
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
                                                        padding:16px;
                                                        border:1px dashed #cfd6df;
                                                        border-radius:8px;
                                                        color:#667085;
                                                        background:#fff;
                                                    "
                                                >
                                                    No road-way stops have been
                                                    added to this route.
                                                </div>

                                            @endif

                                        </div>

                                    </td>

                                </tr>

                            @endforeach

                        </tbody>

                    </table>

                </div>


                {{-- ====================================================
                     PAGINATION
                ==================================================== --}}

                @if(method_exists($routes, 'links'))

                    <div style="margin-top:18px;">
                        {{ $routes->links() }}
                    </div>

                @endif

            @else

                {{-- ====================================================
                     EMPTY STATE
                ==================================================== --}}

                <div
                    style="
                        text-align:center;
                        padding:50px 20px;
                        color:#667085;
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
                        No Master Routes
                    </div>

                    <div style="margin-bottom:18px;">
                        Create the first EastBus master route and its
                        fixed road way.
                    </div>

                    <a
                        href="{{ route('admin.routes.create') }}"
                        class="btn btn-primary"
                    >
                        + Add Master Route
                    </a>

                </div>

            @endif

        </div>

    </div>

</div>


<script>
function toggleRoadway(id) {

    const row =
        document.getElementById(id);

    if (!row) {
        return;
    }

    row.style.display =
        row.style.display === 'none'
            ? 'table-row'
            : 'none';
}
</script>

@endsection