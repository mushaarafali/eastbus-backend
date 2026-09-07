@extends('layouts.app')

@section('title', 'Fixed Bus Schedules')
@section('header', 'Fixed Bus Schedules')

@section('content')

<div
    style="
        display:flex;
        justify-content:space-between;
        align-items:center;
        gap:16px;
        margin-bottom:20px;
        flex-wrap:wrap;
    "
>
    <div>
        <h2 style="margin-bottom:6px;">
            Fixed Bus Schedules
        </h2>

        <p
            style="
                margin:0;
                color:#667085;
            "
        >
            Manage daily bus services linked to
            System Admin Master Routes.
        </p>
    </div>

    <a
        class="btn btn-primary"
        href="{{ route('admin.fixed-schedules.create') }}"
    >
        + Add Fixed Schedule
    </a>
</div>


@forelse($services as $service)

    <div
        class="card"
        style="margin-bottom:16px;"
    >
        <div class="card-body">

            {{-- ======================================================== --}}
            {{-- Service Header                                           --}}
            {{-- ======================================================== --}}

            <div
                style="
                    display:flex;
                    justify-content:space-between;
                    align-items:flex-start;
                    gap:16px;
                    flex-wrap:wrap;
                "
            >

                <div>
                    <h3 style="margin-bottom:6px;">

                        {{
                            $service->service_name
                            ??
                            $service->linked_bus_name
                            ??
                            'Daily Bus Service'
                        }}

                        @if(!empty($service->linked_bus_number))
                            <span style="font-weight:500;">
                                • {{ $service->linked_bus_number }}
                            </span>
                        @endif

                    </h3>


                    <div
                        style="
                            color:#475467;
                            margin-bottom:5px;
                        "
                    >
                        <strong>Operator:</strong>

                        {{
                            $service->operator_name
                            ??
                            'Not linked'
                        }}
                    </div>


                    <div
                        style="
                            color:#475467;
                            margin-bottom:5px;
                        "
                    >
                        <strong>Master Route:</strong>

                        @if(!empty($service->route_number))
                            Route {{ $service->route_number }}
                            —
                        @endif

                        {{
                            $service->route_origin
                            ??
                            '-'
                        }}

                        →

                        {{
                            $service->route_destination
                            ??
                            '-'
                        }}
                    </div>


                    @if(!empty($service->route_distance_km))
                        <div
                            style="
                                color:#667085;
                                font-size:13px;
                            "
                        >
                            Distance:
                            {{
                                number_format(
                                    (float)
                                    $service->route_distance_km,
                                    2
                                )
                            }}
                            km
                        </div>
                    @endif
                </div>


                {{-- ==================================================== --}}
                {{-- Status                                               --}}
                {{-- ==================================================== --}}

                <div
                    style="
                        display:flex;
                        gap:6px;
                        flex-wrap:wrap;
                    "
                >
                    <span
                        class="badge
                        {{
                            $service->is_published
                                ? 'bg-success'
                                : 'bg-secondary'
                        }}"
                    >
                        {{
                            $service->is_published
                                ? 'Published'
                                : 'Unpublished'
                        }}
                    </span>

                    <span
                        class="badge
                        {{
                            $service->is_active
                                ? 'bg-primary'
                                : 'bg-danger'
                        }}"
                    >
                        {{
                            $service->is_active
                                ? 'Active'
                                : 'Inactive'
                        }}
                    </span>
                </div>

            </div>


            <hr>


            {{-- ======================================================== --}}
            {{-- Starting Service                                         --}}
            {{-- ======================================================== --}}

            <div style="margin-bottom:18px;">

                <strong>
                    Starting Daily Service
                </strong>

                @if(
                    isset($service->starting_stops)
                    &&
                    $service->starting_stops->count()
                )

                    <div
                        style="
                            margin-top:10px;
                            display:flex;
                            gap:7px;
                            flex-wrap:wrap;
                        "
                    >

                        @foreach(
                            $service->starting_stops
                            as
                            $stop
                        )

                            <span
                                class="badge bg-light text-dark"
                                style="
                                    padding:8px 10px;
                                    border:1px solid #e2e8f0;
                                "
                            >

                                {{ $stop->stop_order }}.

                                {{
                                    $stop->stop_name
                                    ??
                                    $stop->route_stop_name
                                    ??
                                    'Unknown Stop'
                                }}


                                @if(
                                    $stop->arrival_time
                                    ||
                                    $stop->departure_time
                                )

                                    <span style="margin-left:4px;">
                                        •

                                        @if($stop->arrival_time)
                                            {{
                                                substr(
                                                    $stop->arrival_time,
                                                    0,
                                                    5
                                                )
                                            }}
                                        @endif

                                        @if(
                                            $stop->arrival_time
                                            &&
                                            $stop->departure_time
                                        )
                                            -
                                        @endif

                                        @if($stop->departure_time)
                                            {{
                                                substr(
                                                    $stop->departure_time,
                                                    0,
                                                    5
                                                )
                                            }}
                                        @endif

                                    </span>

                                @endif


                                @if(
                                    isset(
                                        $stop
                                            ->distance_from_origin_km
                                    )
                                )

                                    <span
                                        style="
                                            margin-left:4px;
                                            color:#667085;
                                        "
                                    >
                                        (
                                        {{
                                            number_format(
                                                (float)
                                                $stop
                                                    ->distance_from_origin_km,
                                                2
                                            )
                                        }}
                                        km
                                        )
                                    </span>

                                @endif

                            </span>

                        @endforeach

                    </div>

                @else

                    <div
                        style="
                            margin-top:8px;
                            color:#98a2b3;
                        "
                    >
                        No starting booking points configured.
                    </div>

                @endif

            </div>


            {{-- ======================================================== --}}
            {{-- Return Service                                           --}}
            {{-- ======================================================== --}}

            <div style="margin-bottom:18px;">

                <strong>
                    Return Daily Service
                </strong>

                @if(
                    isset($service->return_stops)
                    &&
                    $service->return_stops->count()
                )

                    <div
                        style="
                            margin-top:10px;
                            display:flex;
                            gap:7px;
                            flex-wrap:wrap;
                        "
                    >

                        @foreach(
                            $service->return_stops
                            as
                            $stop
                        )

                            <span
                                class="badge bg-light text-dark"
                                style="
                                    padding:8px 10px;
                                    border:1px solid #e2e8f0;
                                "
                            >

                                {{ $stop->stop_order }}.

                                {{
                                    $stop->stop_name
                                    ??
                                    $stop->route_stop_name
                                    ??
                                    'Unknown Stop'
                                }}


                                @if(
                                    $stop->arrival_time
                                    ||
                                    $stop->departure_time
                                )

                                    <span style="margin-left:4px;">
                                        •

                                        @if($stop->arrival_time)
                                            {{
                                                substr(
                                                    $stop->arrival_time,
                                                    0,
                                                    5
                                                )
                                            }}
                                        @endif

                                        @if(
                                            $stop->arrival_time
                                            &&
                                            $stop->departure_time
                                        )
                                            -
                                        @endif

                                        @if($stop->departure_time)
                                            {{
                                                substr(
                                                    $stop->departure_time,
                                                    0,
                                                    5
                                                )
                                            }}
                                        @endif

                                    </span>

                                @endif


                                @if(
                                    isset(
                                        $stop
                                            ->distance_from_origin_km
                                    )
                                )

                                    <span
                                        style="
                                            margin-left:4px;
                                            color:#667085;
                                        "
                                    >
                                        (
                                        {{
                                            number_format(
                                                (float)
                                                $stop
                                                    ->distance_from_origin_km,
                                                2
                                            )
                                        }}
                                        km
                                        )
                                    </span>

                                @endif

                            </span>

                        @endforeach

                    </div>

                @else

                    <div
                        style="
                            margin-top:8px;
                            color:#98a2b3;
                        "
                    >
                        No return booking points configured.
                    </div>

                @endif

            </div>


            {{-- ======================================================== --}}
            {{-- Actions                                                  --}}
            {{-- ======================================================== --}}

            <div
                style="
                    display:flex;
                    gap:8px;
                    flex-wrap:wrap;
                    align-items:center;
                "
            >

                <a
                    class="
                        btn
                        btn-sm
                        btn-outline-primary
                    "
                    href="{{
                        route(
                            'admin.fixed-schedules.edit',
                            $service->id
                        )
                    }}"
                >
                    Edit
                </a>


                <form
                    method="POST"
                    action="{{
                        route(
                            'admin.fixed-schedules.publish',
                            $service->id
                        )
                    }}"
                >
                    @csrf
                    @method('PATCH')

                    <button
                        type="submit"
                        class="btn btn-sm"
                    >
                        {{
                            $service->is_published
                                ? 'Unpublish'
                                : 'Publish'
                        }}
                    </button>
                </form>


                <form
                    method="POST"
                    action="{{
                        route(
                            'admin.fixed-schedules.active',
                            $service->id
                        )
                    }}"
                >
                    @csrf
                    @method('PATCH')

                    <button
                        type="submit"
                        class="btn btn-sm"
                    >
                        {{
                            $service->is_active
                                ? 'Disable'
                                : 'Enable'
                        }}
                    </button>
                </form>


                <form
                    method="POST"
                    action="{{
                        route(
                            'admin.fixed-schedules.destroy',
                            $service->id
                        )
                    }}"
                    onsubmit="
                        return confirm(
                            'Delete this daily bus service?'
                        );
                    "
                >
                    @csrf
                    @method('DELETE')

                    <button
                        type="submit"
                        class="
                            btn
                            btn-sm
                            btn-outline-danger
                        "
                    >
                        Delete
                    </button>
                </form>

            </div>

        </div>
    </div>

@empty

    <div class="card">
        <div class="card-body">

            <strong>
                No fixed bus services found.
            </strong>

            <div
                style="
                    margin-top:6px;
                    color:#667085;
                "
            >
                Add a daily bus service by selecting
                an operator, bus and Master Route.
            </div>

        </div>
    </div>

@endforelse

@endsection