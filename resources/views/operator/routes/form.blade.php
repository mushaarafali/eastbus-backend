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
            'booking_allowed' => !empty($stop->boarding_allowed) || !empty($stop->dropoff_allowed),
            'starting_time' => $stop->starting_time ?? '',
            'return_time' => $stop->return_time ?? '',
        ])->toArray();
    }

    if (!$rows) {
        $rows = [
            [
                'name' => '',
                'fare_stage_no' => '',
                'distance_from_origin' => 0,
                'booking_allowed' => 1,
                'starting_time' => '',
                'return_time' => '',
            ],
            [
                'name' => '',
                'fare_stage_no' => '',
                'distance_from_origin' => '',
                'booking_allowed' => 1,
                'starting_time' => '',
                'return_time' => '',
            ],
        ];
    }
@endphp

<div class="container">
    <h1>{{ $isEdit ? 'Edit Route' : 'Add Route' }}</h1>

    <p>
        Add all road-way stops in the correct order. The first stop becomes the route origin
        and the last stop becomes the destination automatically.
    </p>

    <p style="color:#667085;">
        Every stop must have a Fare Stage No. Only selected stops can be enabled for passenger booking.
        Passenger fare is calculated automatically using the NTC fare-stage difference and bus type.
        Online booking is allowed only for journeys of at least 50 km.
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
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <form
        method="POST"
        action="{{ $isEdit ? route('operator.routes.update', $route->id) : route('operator.routes.store') }}"
        autocomplete="off"
    >
        @csrf

        @if($isEdit)
            @method('PUT')
        @endif

        <div style="max-width:360px;margin-bottom:20px;">
            <label for="duration_minutes">
                Approx. Duration (minutes)
            </label>

            <input
                id="duration_minutes"
                type="number"
                name="duration_minutes"
                class="form-control"
                value="{{ old('duration_minutes', $route->duration_minutes ?? '') }}"
                min="1"
            >
        </div>

        <h3>Road Way Stops</h3>

        <div id="stops">
            @foreach($rows as $index => $stop)
                <div
                    class="stop-row"
                    style="border:1px solid #ddd;padding:14px;margin-bottom:10px;border-radius:8px;"
                >
                    <div
                        style="
                            display:grid;
                            grid-template-columns:70px 2fr 1fr 1.2fr;
                            gap:10px;
                            align-items:end;
                        "
                    >
                        <div>
                            <label>Order</label>

                            <input
                                class="form-control stop-order"
                                value="{{ $index + 1 }}"
                                readonly
                            >
                        </div>

                        <div>
                            <label>Road Way Stop</label>

                            <input
                                class="form-control stop-name"
                                name="stops[{{ $index }}][name]"
                                value="{{ $stop['name'] ?? '' }}"
                                placeholder="Example: Batticaloa"
                                required
                            >
                        </div>

                        <div>
                            <label>Fare Stage No</label>

                            <input
                                class="form-control stop-stage"
                                type="number"
                                name="stops[{{ $index }}][fare_stage_no]"
                                value="{{ $stop['fare_stage_no'] ?? '' }}"
                                min="1"
                                max="350"
                                placeholder="Example: 81"
                                required
                            >
                        </div>

                        <div>
                            <label>Distance from Origin (km)</label>

                            <input
                                class="form-control stop-distance"
                                type="number"
                                step="0.01"
                                min="0"
                                name="stops[{{ $index }}][distance_from_origin]"
                                value="{{ $stop['distance_from_origin'] ?? '' }}"
                                placeholder="Example: 95.00"
                                required
                            >
                        </div>
                    </div>

                    <div
                        style="
                            display:grid;
                            grid-template-columns:1.2fr 1fr 1fr auto;
                            gap:12px;
                            margin-top:14px;
                            align-items:end;
                        "
                    >
                        <div>
                            <label style="display:block;margin-bottom:7px;">
                                Passenger Booking
                            </label>

                            <label
                                style="
                                    display:flex;
                                    gap:8px;
                                    align-items:center;
                                "
                            >
                                <input
                                    type="hidden"
                                    name="stops[{{ $index }}][booking_allowed]"
                                    value="0"
                                >

                                <input
                                    class="booking-check"
                                    type="checkbox"
                                    name="stops[{{ $index }}][booking_allowed]"
                                    value="1"
                                    {{ !empty($stop['booking_allowed']) ? 'checked' : '' }}
                                >

                                Booking Allowed
                            </label>
                        </div>

                        <div>
                            <label>Starting Time</label>

                            <input
                                class="form-control starting-time"
                                type="time"
                                name="stops[{{ $index }}][starting_time]"
                                value="{{ $stop['starting_time'] ?? '' }}"
                                {{ !empty($stop['booking_allowed']) ? '' : 'disabled' }}
                            >
                        </div>

                        <div>
                            <label>Return Time</label>

                            <input
                                class="form-control return-time"
                                type="time"
                                name="stops[{{ $index }}][return_time]"
                                value="{{ $stop['return_time'] ?? '' }}"
                                {{ !empty($stop['booking_allowed']) ? '' : 'disabled' }}
                            >
                        </div>

                        <div>
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-danger remove-stop"
                            >
                                Remove
                            </button>
                        </div>
                    </div>

                    <small
                        style="
                            display:block;
                            margin-top:8px;
                            color:#667085;
                        "
                    >
                        Fare Stage No is used for automatic NTC fare calculation.
                        Starting and Return times are used only for passenger-bookable stops.
                    </small>
                </div>
            @endforeach
        </div>

        <div
            style="
                display:flex;
                gap:10px;
                flex-wrap:wrap;
                margin-top:14px;
            "
        >
            <button
                type="button"
                id="add-stop"
                class="btn btn-outline-secondary"
            >
                Add Stop
            </button>

            <button
                type="submit"
                class="btn btn-primary"
            >
                {{ $isEdit ? 'Update Route' : 'Create Route' }}
            </button>

            @if($isEdit)
                <a
                    href="{{ route('operator.routes.index') }}"
                    class="btn btn-outline-secondary"
                >
                    Cancel
                </a>
            @endif
        </div>
    </form>
</div>

<template id="stop-template">
    <div
        class="stop-row"
        style="border:1px solid #ddd;padding:14px;margin-bottom:10px;border-radius:8px;"
    >
        <div
            style="
                display:grid;
                grid-template-columns:70px 2fr 1fr 1.2fr;
                gap:10px;
                align-items:end;
            "
        >
            <div>
                <label>Order</label>

                <input
                    class="form-control stop-order"
                    readonly
                >
            </div>

            <div>
                <label>Road Way Stop</label>

                <input
                    class="form-control stop-name"
                    placeholder="Example: Batticaloa"
                    required
                >
            </div>

            <div>
                <label>Fare Stage No</label>

                <input
                    class="form-control stop-stage"
                    type="number"
                    min="1"
                    max="350"
                    placeholder="Example: 81"
                    required
                >
            </div>

            <div>
                <label>Distance from Origin (km)</label>

                <input
                    class="form-control stop-distance"
                    type="number"
                    step="0.01"
                    min="0"
                    placeholder="Example: 95.00"
                    required
                >
            </div>
        </div>

        <div
            style="
                display:grid;
                grid-template-columns:1.2fr 1fr 1fr auto;
                gap:12px;
                margin-top:14px;
                align-items:end;
            "
        >
            <div>
                <label style="display:block;margin-bottom:7px;">
                    Passenger Booking
                </label>

                <label
                    style="
                        display:flex;
                        gap:8px;
                        align-items:center;
                    "
                >
                    <input
                        class="booking-hidden"
                        type="hidden"
                        value="0"
                    >

                    <input
                        class="booking-check"
                        type="checkbox"
                        value="1"
                    >

                    Booking Allowed
                </label>
            </div>

            <div>
                <label>Starting Time</label>

                <input
                    class="form-control starting-time"
                    type="time"
                    disabled
                >
            </div>

            <div>
                <label>Return Time</label>

                <input
                    class="form-control return-time"
                    type="time"
                    disabled
                >
            </div>

            <div>
                <button
                    type="button"
                    class="btn btn-sm btn-outline-danger remove-stop"
                >
                    Remove
                </button>
            </div>
        </div>

        <small
            style="
                display:block;
                margin-top:8px;
                color:#667085;
            "
        >
            Fare Stage No is used for automatic NTC fare calculation.
            Starting and Return times are used only for passenger-bookable stops.
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
                ['.booking-hidden', `stops[${index}][booking_allowed]`],
                ['.booking-check', `stops[${index}][booking_allowed]`],
                ['.starting-time', `stops[${index}][starting_time]`],
                ['.return-time', `stops[${index}][return_time]`],
            ];

            mappings.forEach(([selector, name]) => {
                const element = row.querySelector(selector);

                if (element) {
                    element.name = name;
                }
            });

            updateBookingState(row);
        });
    }

    function updateBookingState(row) {
        const booking = row.querySelector('.booking-check');
        const startingTime = row.querySelector('.starting-time');
        const returnTime = row.querySelector('.return-time');

        if (!booking) {
            return;
        }

        const enabled = booking.checked;

        if (startingTime) {
            startingTime.disabled = !enabled;

            if (!enabled) {
                startingTime.value = '';
            }
        }

        if (returnTime) {
            returnTime.disabled = !enabled;

            if (!enabled) {
                returnTime.value = '';
            }
        }
    }

    addButton.addEventListener('click', function () {
        container.appendChild(
            template.content.cloneNode(true)
        );

        rebuildNames();
    });

    container.addEventListener('click', function (event) {
        if (!event.target.classList.contains('remove-stop')) {
            return;
        }

        if (
            container.querySelectorAll('.stop-row').length <= 2
        ) {
            alert(
                'A route must contain at least two stops.'
            );

            return;
        }

        event.target.closest('.stop-row').remove();

        rebuildNames();
    });

    container.addEventListener('change', function (event) {
        if (
            !event.target.classList.contains('booking-check')
        ) {
            return;
        }

        updateBookingState(
            event.target.closest('.stop-row')
        );
    });

    rebuildNames();
})();
</script>

@endsection