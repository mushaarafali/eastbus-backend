@extends('layouts.app')

@section('title', 'Fixed Bus Schedules')
@section('header', 'Fixed Bus Schedules')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
    <div>
        <h2>Fixed Bus Schedules</h2>
        <p>Information-only timetables managed by Admin.</p>
    </div>
    <a class="btn btn-primary" href="{{ route('admin.fixed-schedules.create') }}">+ Add Fixed Schedule</a>
</div>

@forelse($services as $service)
    <div class="card" style="margin-bottom:16px;">
        <div class="card-body">
            <h3>{{ $service->bus_name }} @if($service->bus_number) • {{ $service->bus_number }} @endif</h3>
            <div>{{ $service->origin }} ↔ {{ $service->destination }}</div>
            <div style="margin-top:6px;">
                Contacts:
                {{ collect([$service->contact_number_1,$service->contact_number_2,$service->contact_number_3])->filter()->join(' | ') ?: 'Not provided' }}
            </div>
            <div style="margin-top:10px;">
                <span class="badge bg-warning text-dark">TIMETABLE ONLY</span>
                <span class="badge {{ $service->is_published ? 'bg-success' : 'bg-secondary' }}">{{ $service->is_published ? 'Published' : 'Unpublished' }}</span>
                <span class="badge {{ $service->is_active ? 'bg-primary' : 'bg-danger' }}">{{ $service->is_active ? 'Active' : 'Inactive' }}</span>
            </div>

            <hr>

            <strong>Starting</strong>
            <div style="margin:8px 0;">
                @foreach($service->starting_stops as $stop)
                    <span class="badge bg-light text-dark">
                        {{ $stop->stop_order }}. {{ $stop->stop_name }}
                        @if($stop->arrival_time || $stop->departure_time)
                            • {{ $stop->arrival_time ? substr($stop->arrival_time,0,5) : '' }}
                            @if($stop->arrival_time && $stop->departure_time)-@endif
                            {{ $stop->departure_time ? substr($stop->departure_time,0,5) : '' }}
                        @endif
                    </span>
                @endforeach
            </div>

            <strong>Return</strong>
            <div style="margin:8px 0 12px;">
                @foreach($service->return_stops as $stop)
                    <span class="badge bg-light text-dark">
                        {{ $stop->stop_order }}. {{ $stop->stop_name }}
                        @if($stop->arrival_time || $stop->departure_time)
                            • {{ $stop->arrival_time ? substr($stop->arrival_time,0,5) : '' }}
                            @if($stop->arrival_time && $stop->departure_time)-@endif
                            {{ $stop->departure_time ? substr($stop->departure_time,0,5) : '' }}
                        @endif
                    </span>
                @endforeach
            </div>

            <div style="display:flex;gap:8px;">
                <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.fixed-schedules.edit',$service->id) }}">Edit</a>

                <form method="POST" action="{{ route('admin.fixed-schedules.publish',$service->id) }}">
                    @csrf @method('PATCH')
                    <button class="btn btn-sm">{{ $service->is_published ? 'Unpublish' : 'Publish' }}</button>
                </form>

                <form method="POST" action="{{ route('admin.fixed-schedules.active',$service->id) }}">
                    @csrf @method('PATCH')
                    <button class="btn btn-sm">{{ $service->is_active ? 'Disable' : 'Enable' }}</button>
                </form>
            </div>
        </div>
    </div>
@empty
    <div class="card"><div class="card-body">No fixed schedules added.</div></div>
@endforelse
@endsection
