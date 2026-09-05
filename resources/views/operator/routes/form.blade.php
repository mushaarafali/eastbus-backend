@extends('layouts.app')

@section('content')

@php
    $isEdit = $route !== null;
    $rows = old('stops');

    if (!$rows) {
        $rows = $stops->map(fn ($stop) => [
            'name' => $stop->name,
            'fare_stage_no' => $stop->fare_stage_no,
            'distance_from_origin' => $stop->distance_from_origin ?? 0,
            'boarding_allowed' => $stop->boarding_allowed,
            'dropoff_allowed' => $stop->dropoff_allowed,
        ])->toArray();
    }

    if (!$rows) {
        $rows = [
            [
                'name' => '',
                'fare_stage_no' => '',
                'distance_from_origin' => 0,
                'boarding_allowed' => 1,
                'dropoff_allowed' => 1,
            ],
            [
                'name' => '',
                'fare_stage_no' => '',
                'distance_from_origin' => '',
                'boarding_allowed' => 1,
                'dropoff_allowed' => 1,
            ],
        ];
    }
@endphp

<div class="container">
    <h1>{{ $isEdit ? 'Edit Route' : 'Add Route' }}</h1>

    <p>
        Add all major towns and stops in the correct road order. The first stop becomes the route origin and the last stop becomes the destination automatically. Passenger booking is available only between approved booking stops and the journey must be at least 50 km.
    </p>

    <p style="color:#667085;">
        Fare Stage No is required only for stops where passenger boarding or drop-off is allowed. Passenger fare will be calculated automatically using the NTC fare table based on the bus service class.
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

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ $isEdit ? route('operator.routes.update', $route->id) : route('operator.routes.store') }}" autocomplete="off">
        @csrf

        @if($isEdit)
            @method('PUT')
        @endif

        <div style="max-width:360px;margin-bottom:20px;">
            <label for="duration_minutes">Approx. Duration (minutes)</label>
            <input id="duration_minutes" type="number" name="duration_minutes" class="form-control" value="{{ old('duration_minutes', $route->duration_minutes ?? '') }}" min="1">
        </div>

        <h3>Road Way Stops</h3>

        <div id="stops">
            @foreach($rows as $index => $stop)
                <div class="stop-row" style="border:1px solid #ddd;padding:14px;margin-bottom:10px;border-radius:8px;">
                    <div style="display:grid;grid-template-columns:70px 2fr 1fr 1.2fr;gap:10px;align-items:end;">
                        <div>
                            <label>Order</label>
                            <input class="form-control stop-order" value="{{ $index + 1 }}" readonly>
                        </div>

                        <div>
                            <label>Stop Name</label>
                            <input class="form-control stop-name" name="stops[{{ $index }}][name]" value="{{ $stop['name'] ?? '' }}" placeholder="Example: Batticaloa" required>
                        </div>

                        <div>
                            <label>Fare Stage No</label>
                            <input class="form-control stop-stage" type="number" name="stops[{{ $index }}][fare_stage_no]" value="{{ $stop['fare_stage_no'] ?? '' }}" min="1" max="350" placeholder="Example: 81">
                        </div>

                        <div>
                            <label>Distance from Origin (km)</label>
                            <input class="form-control stop-distance" type="number" step="0.01" min="0" name="stops[{{ $index }}][distance_from_origin]" value="{{ $stop['distance_from_origin'] ?? '' }}" placeholder="Example: 95.00" required>
                        </div>
                    </div>

                    <div style="display:flex;gap:20px;margin-top:12px;align-items:center;flex-wrap:wrap;">
                        <label>
                            <input type="hidden" name="stops[{{ $index }}][boarding_allowed]" value="0">
                            <input class="boarding-check" type="checkbox" name="stops[{{ $index }}][boarding_allowed]" value="1" {{ !empty($stop['boarding_allowed']) ? 'checked' : '' }}>
                            Boarding Allowed
                        </label>

                        <label>
                            <input type="hidden" name="stops[{{ $index }}][dropoff_allowed]" value="0">
                            <input class="dropoff-check" type="checkbox" name="stops[{{ $index }}][dropoff_allowed]" value="1" {{ !empty($stop['dropoff_allowed']) ? 'checked' : '' }}>
                            Drop-off Allowed
                        </label>

                        <button type="button" class="btn btn-sm btn-outline-danger remove-stop">Remove</button>
                    </div>

                    <small class="stage-help" style="display:block;margin-top:8px;color:#667085;">
                        Fare Stage No is required when Boarding or Drop-off is enabled.
                    </small>
                </div>
            @endforeach
        </div>

        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:14px;">
            <button type="button" id="add-stop" class="btn btn-outline-secondary">Add Stop</button>

            <button type="submit" class="btn btn-primary">
                {{ $isEdit ? 'Update Route' : 'Create Route' }}
            </button>

            @if($isEdit)
                <a href="{{ route('operator.routes.index') }}" class="btn btn-outline-secondary">Cancel</a>
            @endif
        </div>
    </form>
</div>

<template id="stop-template">
    <div class="stop-row" style="border:1px solid #ddd;padding:14px;margin-bottom:10px;border-radius:8px;">
        <div style="display:grid;grid-template-columns:70px 2fr 1fr 1.2fr;gap:10px;align-items:end;">
            <div>
                <label>Order</label>
                <input class="form-control stop-order" readonly>
            </div>

            <div>
                <label>Stop Name</label>
                <input class="form-control stop-name" placeholder="Example: Batticaloa" required>
            </div>

            <div>
                <label>Fare Stage No</label>
                <input class="form-control stop-stage" type="number" min="1" max="350" placeholder="Example: 81">
            </div>

            <div>
                <label>Distance from Origin (km)</label>
                <input class="form-control stop-distance" type="number" step="0.01" min="0" placeholder="Example: 95.00" required>
            </div>
        </div>

        <div style="display:flex;gap:20px;margin-top:12px;align-items:center;flex-wrap:wrap;">
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

            <button type="button" class="btn btn-sm btn-outline-danger remove-stop">Remove</button>
        </div>

        <small class="stage-help" style="display:block;margin-top:8px;color:#667085;">
            Fare Stage No is required when Boarding or Drop-off is enabled.
        </small>
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
                ['.stop-stage', `stops[${index}][fare_stage_no]`],
                ['.stop-distance', `stops[${index}][distance_from_origin]`],
                ['.boarding-hidden', `stops[${index}][boarding_allowed]`],
                ['.boarding-check', `stops[${index}][boarding_allowed]`],
                ['.dropoff-hidden', `stops[${index}][dropoff_allowed]`],
                ['.dropoff-check', `stops[${index}][dropoff_allowed]`],
            ];

            mappings.forEach(([selector, name]) => {
                const element = row.querySelector(selector);
                if (element) {
                    element.name = name;
                }
            });

            updateStageRequirement(row);
        });
    }

    function updateStageRequirement(row) {
        const boarding = row.querySelector('.boarding-check');
        const dropoff = row.querySelector('.dropoff-check');
        const stage = row.querySelector('.stop-stage');

        if (!boarding || !dropoff || !stage) {
            return;
        }

        const isBookable = boarding.checked || dropoff.checked;

        stage.required = isBookable;

        if (isBookable) {
            stage.style.borderColor = '';
        }
    }

    addButton.addEventListener('click', function () {
        container.appendChild(template.content.cloneNode(true));
        rebuildNames();
    });

    container.addEventListener('click', function (event) {
        if (!event.target.classList.contains('remove-stop')) {
            return;
        }

        if (container.querySelectorAll('.stop-row').length <= 2) {
            alert('A route must contain at least two stops.');
            return;
        }

        event.target.closest('.stop-row').remove();
        rebuildNames();
    });

    container.addEventListener('change', function (event) {
        if (!event.target.classList.contains('boarding-check') && !event.target.classList.contains('dropoff-check')) {
            return;
        }

        updateStageRequirement(event.target.closest('.stop-row'));
    });

    rebuildNames();
})();
</script>

@endsection