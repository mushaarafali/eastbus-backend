@extends('layouts.app')

@section('title', $service ? 'Edit Fixed Schedule' : 'Add Fixed Schedule')
@section('header', $service ? 'Edit Fixed Schedule' : 'Add Fixed Schedule')

@section('content')

@php
    $isEdit = $service !== null;

    $startRows = old('starting_stops') ?: $startingStops->map(fn ($stop) => [
        'route_stop_id' => $stop->route_stop_id,
        'stop_name' => $stop->stop_name,
        'arrival_time' => $stop->arrival_time ? substr($stop->arrival_time, 0, 5) : '',
        'departure_time' => $stop->departure_time ? substr($stop->departure_time, 0, 5) : '',
        'boarding_allowed' => $stop->boarding_allowed,
        'dropoff_allowed' => $stop->dropoff_allowed,
    ])->toArray();

    $returnRows = old('return_stops') ?: $returnStops->map(fn ($stop) => [
        'route_stop_id' => $stop->route_stop_id,
        'stop_name' => $stop->stop_name,
        'arrival_time' => $stop->arrival_time ? substr($stop->arrival_time, 0, 5) : '',
        'departure_time' => $stop->departure_time ? substr($stop->departure_time, 0, 5) : '',
        'boarding_allowed' => $stop->boarding_allowed,
        'dropoff_allowed' => $stop->dropoff_allowed,
    ])->toArray();

    if (!$startRows) {
        $startRows = [
            [
                'route_stop_id' => '',
                'stop_name' => '',
                'arrival_time' => '',
                'departure_time' => '',
                'boarding_allowed' => 1,
                'dropoff_allowed' => 1,
            ],
            [
                'route_stop_id' => '',
                'stop_name' => '',
                'arrival_time' => '',
                'departure_time' => '',
                'boarding_allowed' => 1,
                'dropoff_allowed' => 1,
            ],
        ];
    }

    if (!$returnRows) {
        $returnRows = [
            [
                'route_stop_id' => '',
                'stop_name' => '',
                'arrival_time' => '',
                'departure_time' => '',
                'boarding_allowed' => 1,
                'dropoff_allowed' => 1,
            ],
            [
                'route_stop_id' => '',
                'stop_name' => '',
                'arrival_time' => '',
                'departure_time' => '',
                'boarding_allowed' => 1,
                'dropoff_allowed' => 1,
            ],
        ];
    }
@endphp

@if($errors->any())
    <div class="alert alert-danger" style="margin-bottom:16px;">
        <ul style="margin:0;padding-left:20px;">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if(session('success'))
    <div class="alert alert-success" style="margin-bottom:16px;">
        {{ session('success') }}
    </div>
@endif

<form method="POST" action="{{ $isEdit ? route('admin.fixed-schedules.update', $service->id) : route('admin.fixed-schedules.store') }}">
    @csrf

    @if($isEdit)
        @method('PUT')
    @endif

    <div class="card">
        <div class="card-body">
            <h3>Bus Details</h3>

            <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;">
                <div>
                    <label>Bus Name *</label>
                    <input class="form-control" name="bus_name" value="{{ old('bus_name', $service->bus_name ?? '') }}" required>
                </div>

                <div>
                    <label>Bus Number</label>
                    <input class="form-control" name="bus_number" value="{{ old('bus_number', $service->bus_number ?? '') }}">
                </div>

                <div>
                    <label>Contact Number 1</label>
                    <input class="form-control" name="contact_number_1" value="{{ old('contact_number_1', $service->contact_number_1 ?? '') }}">
                </div>

                <div>
                    <label>Contact Number 2</label>
                    <input class="form-control" name="contact_number_2" value="{{ old('contact_number_2', $service->contact_number_2 ?? '') }}">
                </div>

                <div>
                    <label>Contact Number 3</label>
                    <input class="form-control" name="contact_number_3" value="{{ old('contact_number_3', $service->contact_number_3 ?? '') }}">
                </div>
            </div>

            <div style="margin-top:14px;display:flex;gap:20px;flex-wrap:wrap;">
                <label>
                    <input type="checkbox" name="is_published" value="1" {{ old('is_published', $service->is_published ?? true) ? 'checked' : '' }}>
                    Published
                </label>

                <label>
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $service->is_active ?? true) ? 'checked' : '' }}>
                    Active
                </label>
            </div>
        </div>
    </div>

    <div class="card" style="margin-top:16px;">
        <div class="card-body">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;">
                <div>
                    <h3 style="margin-bottom:4px;">Starting Schedule</h3>
                    <small style="color:#667085;">Select road-way stops, enter timetable times and choose which stops allow passenger booking.</small>
                </div>

                <button type="button" class="btn btn-sm" onclick="addStop('starting')">+ Add Stop</button>
            </div>

            <div id="starting-container" style="margin-top:16px;">
                @foreach($startRows as $i => $stop)
                    <div class="schedule-row" style="border:1px solid #e1e6ef;border-radius:10px;padding:12px;margin-bottom:10px;">
                        <div style="display:grid;grid-template-columns:60px 2fr 1fr 1fr 90px;gap:8px;align-items:end;">
                            <div>
                                <label>Order</label>
                                <input class="form-control order-field" value="{{ $i + 1 }}" readonly>
                            </div>

                            <div>
                                <label>Road Way Stop *</label>

                                <select class="form-control route-stop-select" name="starting_stops[{{ $i }}][route_stop_id]" required>
                                    <option value="">-- Select Stop --</option>

                                    @foreach($routeStops as $routeStop)
                                        <option value="{{ $routeStop->id }}" data-name="{{ $routeStop->name }}" @selected(($stop['route_stop_id'] ?? '') == $routeStop->id)>
                                            {{ $routeStop->name }}
                                            @if(!empty($routeStop->fare_stage_no))
                                                — Stage {{ $routeStop->fare_stage_no }}
                                            @endif
                                        </option>
                                    @endforeach
                                </select>

                                <input type="hidden" class="stop-name" name="starting_stops[{{ $i }}][stop_name]" value="{{ $stop['stop_name'] ?? '' }}">
                            </div>

                            <div>
                                <label>Arrival</label>
                                <input class="form-control arrival-time" type="time" name="starting_stops[{{ $i }}][arrival_time]" value="{{ $stop['arrival_time'] ?? '' }}">
                            </div>

                            <div>
                                <label>Departure</label>
                                <input class="form-control departure-time" type="time" name="starting_stops[{{ $i }}][departure_time]" value="{{ $stop['departure_time'] ?? '' }}">
                            </div>

                            <div>
                                <button type="button" class="btn btn-sm btn-outline-danger remove-row">Remove</button>
                            </div>
                        </div>

                        <div style="display:flex;gap:20px;flex-wrap:wrap;margin-top:12px;">
                            <label>
                                <input class="boarding-hidden" type="hidden" name="starting_stops[{{ $i }}][boarding_allowed]" value="0">
                                <input class="boarding-check" type="checkbox" name="starting_stops[{{ $i }}][boarding_allowed]" value="1" {{ !empty($stop['boarding_allowed']) ? 'checked' : '' }}>
                                Boarding Allowed
                            </label>

                            <label>
                                <input class="dropoff-hidden" type="hidden" name="starting_stops[{{ $i }}][dropoff_allowed]" value="0">
                                <input class="dropoff-check" type="checkbox" name="starting_stops[{{ $i }}][dropoff_allowed]" value="1" {{ !empty($stop['dropoff_allowed']) ? 'checked' : '' }}>
                                Drop-off Allowed
                            </label>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="card" style="margin-top:16px;">
        <div class="card-body">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;">
                <div>
                    <h3 style="margin-bottom:4px;">Return Schedule</h3>
                    <small style="color:#667085;">Return must start from the Starting Schedule destination and finish at its origin.</small>
                </div>

                <button type="button" class="btn btn-sm" onclick="addStop('return')">+ Add Stop</button>
            </div>

            <div id="return-container" style="margin-top:16px;">
                @foreach($returnRows as $i => $stop)
                    <div class="schedule-row" style="border:1px solid #e1e6ef;border-radius:10px;padding:12px;margin-bottom:10px;">
                        <div style="display:grid;grid-template-columns:60px 2fr 1fr 1fr 90px;gap:8px;align-items:end;">
                            <div>
                                <label>Order</label>
                                <input class="form-control order-field" value="{{ $i + 1 }}" readonly>
                            </div>

                            <div>
                                <label>Road Way Stop *</label>

                                <select class="form-control route-stop-select" name="return_stops[{{ $i }}][route_stop_id]" required>
                                    <option value="">-- Select Stop --</option>

                                    @foreach($routeStops as $routeStop)
                                        <option value="{{ $routeStop->id }}" data-name="{{ $routeStop->name }}" @selected(($stop['route_stop_id'] ?? '') == $routeStop->id)>
                                            {{ $routeStop->name }}
                                            @if(!empty($routeStop->fare_stage_no))
                                                — Stage {{ $routeStop->fare_stage_no }}
                                            @endif
                                        </option>
                                    @endforeach
                                </select>

                                <input type="hidden" class="stop-name" name="return_stops[{{ $i }}][stop_name]" value="{{ $stop['stop_name'] ?? '' }}">
                            </div>

                            <div>
                                <label>Arrival</label>
                                <input class="form-control arrival-time" type="time" name="return_stops[{{ $i }}][arrival_time]" value="{{ $stop['arrival_time'] ?? '' }}">
                            </div>

                            <div>
                                <label>Departure</label>
                                <input class="form-control departure-time" type="time" name="return_stops[{{ $i }}][departure_time]" value="{{ $stop['departure_time'] ?? '' }}">
                            </div>

                            <div>
                                <button type="button" class="btn btn-sm btn-outline-danger remove-row">Remove</button>
                            </div>
                        </div>

                        <div style="display:flex;gap:20px;flex-wrap:wrap;margin-top:12px;">
                            <label>
                                <input class="boarding-hidden" type="hidden" name="return_stops[{{ $i }}][boarding_allowed]" value="0">
                                <input class="boarding-check" type="checkbox" name="return_stops[{{ $i }}][boarding_allowed]" value="1" {{ !empty($stop['boarding_allowed']) ? 'checked' : '' }}>
                                Boarding Allowed
                            </label>

                            <label>
                                <input class="dropoff-hidden" type="hidden" name="return_stops[{{ $i }}][dropoff_allowed]" value="0">
                                <input class="dropoff-check" type="checkbox" name="return_stops[{{ $i }}][dropoff_allowed]" value="1" {{ !empty($stop['dropoff_allowed']) ? 'checked' : '' }}>
                                Drop-off Allowed
                            </label>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:16px;">
        <button class="btn btn-primary" type="submit">
            {{ $isEdit ? 'Update Fixed Schedule' : 'Create Fixed Schedule' }}
        </button>

        <a href="{{ route('admin.fixed-schedules.index') }}" class="btn btn-outline-secondary">
            Cancel
        </a>
    </div>
</form>

<template id="schedule-row-template">
    <div class="schedule-row" style="border:1px solid #e1e6ef;border-radius:10px;padding:12px;margin-bottom:10px;">
        <div style="display:grid;grid-template-columns:60px 2fr 1fr 1fr 90px;gap:8px;align-items:end;">
            <div>
                <label>Order</label>
                <input class="form-control order-field" readonly>
            </div>

            <div>
                <label>Road Way Stop *</label>

                <select class="form-control route-stop-select" required>
                    <option value="">-- Select Stop --</option>

                    @foreach($routeStops as $routeStop)
                        <option value="{{ $routeStop->id }}" data-name="{{ $routeStop->name }}">
                            {{ $routeStop->name }}
                            @if(!empty($routeStop->fare_stage_no))
                                — Stage {{ $routeStop->fare_stage_no }}
                            @endif
                        </option>
                    @endforeach
                </select>

                <input type="hidden" class="stop-name">
            </div>

            <div>
                <label>Arrival</label>
                <input class="form-control arrival-time" type="time">
            </div>

            <div>
                <label>Departure</label>
                <input class="form-control departure-time" type="time">
            </div>

            <div>
                <button type="button" class="btn btn-sm btn-outline-danger remove-row">Remove</button>
            </div>
        </div>

        <div style="display:flex;gap:20px;flex-wrap:wrap;margin-top:12px;">
            <label>
                <input class="boarding-hidden" type="hidden" value="0">
                <input class="boarding-check" type="checkbox" value="1" checked>
                Boarding Allowed
            </label>

            <label>
                <input class="dropoff-hidden" type="hidden" value="0">
                <input class="dropoff-check" type="checkbox" value="1" checked>
                Drop-off Allowed
            </label>
        </div>
    </div>
</template>

<script>
(function () {
    const template = document.getElementById('schedule-row-template');

    function rebuild(direction) {
        const container = document.getElementById(direction + '-container');

        container.querySelectorAll('.schedule-row').forEach((row, index) => {
            row.querySelector('.order-field').value = index + 1;
            row.querySelector('.route-stop-select').name = `${direction}_stops[${index}][route_stop_id]`;
            row.querySelector('.stop-name').name = `${direction}_stops[${index}][stop_name]`;
            row.querySelector('.arrival-time').name = `${direction}_stops[${index}][arrival_time]`;
            row.querySelector('.departure-time').name = `${direction}_stops[${index}][departure_time]`;
            row.querySelector('.boarding-hidden').name = `${direction}_stops[${index}][boarding_allowed]`;
            row.querySelector('.boarding-check').name = `${direction}_stops[${index}][boarding_allowed]`;
            row.querySelector('.dropoff-hidden').name = `${direction}_stops[${index}][dropoff_allowed]`;
            row.querySelector('.dropoff-check').name = `${direction}_stops[${index}][dropoff_allowed]`;

            syncStopName(row);
        });
    }

    function syncStopName(row) {
        const select = row.querySelector('.route-stop-select');
        const nameInput = row.querySelector('.stop-name');

        if (!select || !nameInput) {
            return;
        }

        const option = select.options[select.selectedIndex];

        if (option && option.value) {
            nameInput.value = option.dataset.name || option.textContent.trim();
        }
    }

    window.addStop = function (direction) {
        const container = document.getElementById(direction + '-container');
        container.appendChild(template.content.cloneNode(true));
        rebuild(direction);
    };

    document.addEventListener('change', function (event) {
        if (!event.target.classList.contains('route-stop-select')) {
            return;
        }

        syncStopName(event.target.closest('.schedule-row'));
    });

    document.addEventListener('click', function (event) {
        if (!event.target.classList.contains('remove-row')) {
            return;
        }

        const row = event.target.closest('.schedule-row');
        const container = row.closest('[id$="-container"]');

        if (container.querySelectorAll('.schedule-row').length <= 2) {
            alert('Each direction needs at least two stops.');
            return;
        }

        const direction = container.id.replace('-container', '');

        row.remove();
        rebuild(direction);
    });

    rebuild('starting');
    rebuild('return');
})();
</script>

@endsection