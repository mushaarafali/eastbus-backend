@extends('layouts.app')

@section('content')
<div class="container">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
        <div>
            <h1>Routes & Stops</h1>
            <p>Manage long-route stops in travelling order.</p>
        </div>
        <a href="{{ route('operator.routes.create') }}" class="btn btn-primary">Add Route</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @foreach($routes as $route)
        <div class="card" style="margin-bottom:18px;">
            <div class="card-body">
                <div style="display:flex;justify-content:space-between;gap:15px;align-items:flex-start;">
                    <div>
                        <h3>{{ $route->origin }} → {{ $route->destination }}</h3>
                        <div>Distance: {{ number_format((float) $route->distance_km, 1) }} km</div>
                        <div>Base Fare: Rs. {{ number_format((float) $route->base_fare, 2) }}</div>
                    </div>

                    <a href="{{ route('operator.routes.edit', $route->id) }}" class="btn btn-outline-primary">
                        Edit Route
                    </a>
                </div>

                <hr>

                <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
                    @foreach($route->stops as $stop)
                        <span class="badge bg-secondary">
                            {{ $stop->stop_order }}. {{ $stop->name }}
                            @if(isset($stop->distance_from_origin_km))
                                ({{ number_format((float) $stop->distance_from_origin_km, 1) }} km)
                            @endif
                        </span>
                        @if(!$loop->last)
                            <span>→</span>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach

    @if($routes->isEmpty())
        <div class="alert alert-info">No routes have been created yet.</div>
    @endif
</div>
@endsection
