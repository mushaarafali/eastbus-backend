@extends('layouts.app')

@section('content')
@php
    $isEdit = $route !== null;
    $rows = old('stops');

    if (!$rows) {
        $rows = $stops->map(fn($stop) => [
            'name' => $stop->name,
            'latitude' => $stop->latitude,
            'longitude' => $stop->longitude,
            'distance_from_origin_km' => $stop->distance_from_origin_km ?? 0,
            'boarding_allowed' => $stop->boarding_allowed,
            'dropoff_allowed' => $stop->dropoff_allowed,
        ])->toArray();
    }

    if (!$rows) {
        $rows = [
            [
                'name' => '',
                'latitude' => '',
                'longitude' => '',
                'distance_from_origin_km' => 0,
                'boarding_allowed' => 1,
                'dropoff_allowed' => 1,
            ],
            [
                'name' => '',
                'latitude' => '',
                'longitude' => '',
                'distance_from_origin_km' => '',
                'boarding_allowed' => 1,
                'dropoff_allowed' => 1,
            ],
        ];
    }
@endphp

<div class="container">
    <h1>{{ $isEdit ? 'Edit Route' : 'Add Route' }}</h1>

    <p>
        First stop becomes the route origin automatically.
        Last stop becomes the route destination automatically.
        Passenger booking is allowed only between these approved stops,
        and the journey must be at least 50 km.
    </p>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul style="margin:0;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST"
          action="{{ $isEdit ? route('operator.routes.update', $route->id) : route('operator.routes.store') }}">
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px;">
            <div>
                <label>Approx. Duration (minutes)</label>
                <input type="number" name="duration_minutes" class="form-control"
                       value="{{ old('duration_minutes', $route->duration_minutes ?? '') }}" min="1">
            </div>

            <div>
                <label>Full Route Base Fare</label>
                <input type="number" step="0.01" name="base_fare" class="form-control"
                       value="{{ old('base_fare', $route->base_fare ?? '') }}" min="0">
            </div>
        </div>

        <h3>Ordered Route Stops</h3>

        <div id="stops">
            @foreach($rows as $index => $stop)
                <div class="stop-row" style="border:1px solid #ddd;padding:14px;margin-bottom:10px;border-radius:8px;">
                    <div style="display:grid;grid-template-columns:70px 2fr 1fr 1fr 1fr;gap:10px;align-items:end;">
                        <div>
                            <label>Order</label>
                            <input class="form-control stop-order" value="{{ $index + 1 }}" readonly>
                        </div>

                        <div>
                            <label>Stop Name</label>
                            <input class="form-control" name="stops[{{ $index }}][name]"
                                   value="{{ $stop['name'] ?? '' }}" required>
                        </div>

                        <div>
                            <label>Latitude</label>
                            <input class="form-control" name="stops[{{ $index }}][latitude]"
                                   value="{{ $stop['latitude'] ?? '' }}" step="any" type="number">
                        </div>

                        <div>
                            <label>Longitude</label>
                            <input class="form-control" name="stops[{{ $index }}][longitude]"
                                   value="{{ $stop['longitude'] ?? '' }}" step="any" type="number">
                        </div>

                        <div>
                            <label>Distance from Origin (km)</label>
                            <input class="form-control" name="stops[{{ $index }}][distance_from_origin_km]"
                                   value="{{ $stop['distance_from_origin_km'] ?? '' }}" step="0.01" type="number" min="0" required>
                        </div>
                    </div>

                    <div style="display:flex;gap:20px;margin-top:12px;">
                        <label>
                            <input type="hidden" name="stops[{{ $index }}][boarding_allowed]" value="0">
                            <input type="checkbox" name="stops[{{ $index }}][boarding_allowed]" value="1"
                                   {{ !empty($stop['boarding_allowed']) ? 'checked' : '' }}>
                            Boarding Allowed
                        </label>

                        <label>
                            <input type="hidden" name="stops[{{ $index }}][dropoff_allowed]" value="0">
                            <input type="checkbox" name="stops[{{ $index }}][dropoff_allowed]" value="1"
                                   {{ !empty($stop['dropoff_allowed']) ? 'checked' : '' }}>
                            Drop-off Allowed
                        </label>

                        <button type="button" class="btn btn-sm btn-outline-danger remove-stop">
                            Remove
                        </button>
                    </div>
                </div>
            @endforeach
        </div>

        <button type="button" id="add-stop" class="btn btn-outline-secondary">
            Add Stop
        </button>

        <button type="submit" class="btn btn-primary">
            {{ $isEdit ? 'Update Route' : 'Create Route' }}
        </button>
    </form>
</div>

<template id="stop-template">
    <div class="stop-row" style="border:1px solid #ddd;padding:14px;margin-bottom:10px;border-radius:8px;">
        <div style="display:grid;grid-template-columns:70px 2fr 1fr 1fr 1fr;gap:10px;align-items:end;">
            <div>
                <label>Order</label>
                <input class="form-control stop-order" readonly>
            </div>

            <div>
                <label>Stop Name</label>
                <input class="form-control stop-name" required>
            </div>

            <div>
                <label>Latitude</label>
                <input class="form-control stop-latitude" step="any" type="number">
            </div>

            <div>
                <label>Longitude</label>
                <input class="form-control stop-longitude" step="any" type="number">
            </div>

            <div>
                <label>Distance from Origin (km)</label>
                <input class="form-control stop-distance" step="0.01" type="number" min="0" required>
            </div>
        </div>

        <div style="display:flex;gap:20px;margin-top:12px;">
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

            <button type="button" class="btn btn-sm btn-outline-danger remove-stop">
                Remove
            </button>
        </div>
    </div>
</template>

<script>
(function () {
    const container = document.getElementById('stops');
    const template = document.getElementById('stop-template');
    const addButton = document.getElementById('add-stop');

    function rebuildNames() {
        const rows = container.querySelectorAll('.stop-row');

        rows.forEach((row, index) => {
            row.querySelector('.stop-order').value = index + 1;

            const mappings = [
                ['.stop-name', `stops[${index}][name]`],
                ['.stop-latitude', `stops[${index}][latitude]`],
                ['.stop-longitude', `stops[${index}][longitude]`],
                ['.stop-distance', `stops[${index}][distance_from_origin_km]`],
                ['.boarding-hidden', `stops[${index}][boarding_allowed]`],
                ['.boarding-check', `stops[${index}][boarding_allowed]`],
                ['.dropoff-hidden', `stops[${index}][dropoff_allowed]`],
                ['.dropoff-check', `stops[${index}][dropoff_allowed]`],
            ];

            mappings.forEach(([selector, name]) => {
                const element = row.querySelector(selector);
                if (element) element.name = name;
            });
        });
    }

    addButton.addEventListener('click', function () {
        container.appendChild(template.content.cloneNode(true));
        rebuildNames();
    });

    container.addEventListener('click', function (event) {
        if (!event.target.classList.contains('remove-stop')) return;

        if (container.querySelectorAll('.stop-row').length <= 2) {
            alert('A route must contain at least two stops.');
            return;
        }

        event.target.closest('.stop-row').remove();
        rebuildNames();
    });

    rebuildNames();
})();
</script>
@endsection
