@extends('layouts.app')

@section('header', 'Trips & Schedule')

@section('content')

<h1 class="page-title">Trips & Schedule</h1>

<div class="card">

    <table>
        <thead>
            <tr>
                <th>Trip</th>
                <th>Operator</th>
                <th>Route</th>
                <th>Bus</th>
                <th>Date / Time</th>
                <th>Staff</th>
                <th>Status</th>
            </tr>
        </thead>

        <tbody>
            @forelse($trips as $t)
                <tr>
                    <td>
                        {{ $t->trip_code }}
                    </td>

                    <td>
                        {{ $t->operator?->company_name ?? '-' }}
                    </td>

                    <td>
                        {{ $t->route?->name ?? '-' }}
                    </td>

                    <td>
                        {{ $t->bus?->bus_number ?? '-' }}
                    </td>

                    <td>
                        {{ $t->service_date?->format('d M Y') ?? '-' }}
                        <br>
                        {{ $t->departure_time ?? '-' }}
                    </td>

                    <td>
                        {{ $t->driver?->full_name ?? '-' }}

                        @if($t->conductor?->full_name)
                            <br>
                            {{ $t->conductor->full_name }}
                        @endif
                    </td>

                    <td>
                        <span class="badge info">
                            {{ ucfirst($t->status ?? 'unknown') }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align:center; padding:25px;">
                        No trips found.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if($trips->hasPages())
        <div class="trips-pagination">
            {{ $trips->links() }}
        </div>
    @endif

</div>

<style>
    .trips-pagination {
        margin-top: 20px;
        padding: 10px 0;
    }

    .trips-pagination nav {
        width: 100%;
    }

    /*
     * Laravel pagination uses SVG icons for
     * Previous and Next buttons.
     */
    .trips-pagination svg {
        width: 18px !important;
        height: 18px !important;
        max-width: 18px !important;
        max-height: 18px !important;
        display: inline-block !important;
        vertical-align: middle;
    }

    .trips-pagination a,
    .trips-pagination span {
        font-size: 14px;
        text-decoration: none;
    }

    .trips-pagination a svg,
    .trips-pagination span svg {
        width: 18px !important;
        height: 18px !important;
    }
</style>

@endsection