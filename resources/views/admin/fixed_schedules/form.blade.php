@extends('layouts.app')

@section('title', $service ? 'Edit Fixed Schedule' : 'Add Fixed Schedule')
@section('header', $service ? 'Edit Fixed Schedule' : 'Add Fixed Schedule')

@section('content')
@php
    $isEdit = $service !== null;

    $startRows = old('starting_stops') ?: $startingStops->map(fn($s)=>[
        'stop_name'=>$s->stop_name,
        'arrival_time'=>$s->arrival_time ? substr($s->arrival_time,0,5) : '',
        'departure_time'=>$s->departure_time ? substr($s->departure_time,0,5) : '',
    ])->toArray();

    $returnRows = old('return_stops') ?: $returnStops->map(fn($s)=>[
        'stop_name'=>$s->stop_name,
        'arrival_time'=>$s->arrival_time ? substr($s->arrival_time,0,5) : '',
        'departure_time'=>$s->departure_time ? substr($s->departure_time,0,5) : '',
    ])->toArray();

    if(!$startRows) $startRows=[['stop_name'=>'','arrival_time'=>'','departure_time'=>''],['stop_name'=>'','arrival_time'=>'','departure_time'=>'']];
    if(!$returnRows) $returnRows=[['stop_name'=>'','arrival_time'=>'','departure_time'=>''],['stop_name'=>'','arrival_time'=>'','departure_time'=>'']];
@endphp

<form method="POST" action="{{ $isEdit ? route('admin.fixed-schedules.update',$service->id) : route('admin.fixed-schedules.store') }}">
    @csrf
    @if($isEdit) @method('PUT') @endif

    <div class="card"><div class="card-body">
        <h3>Bus Details</h3>
        <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;">
            <div><label>Bus Name *</label><input class="form-control" name="bus_name" value="{{ old('bus_name',$service->bus_name ?? '') }}" required></div>
            <div><label>Bus Number</label><input class="form-control" name="bus_number" value="{{ old('bus_number',$service->bus_number ?? '') }}"></div>
            <div><label>Contact Number 1</label><input class="form-control" name="contact_number_1" value="{{ old('contact_number_1',$service->contact_number_1 ?? '') }}"></div>
            <div><label>Contact Number 2</label><input class="form-control" name="contact_number_2" value="{{ old('contact_number_2',$service->contact_number_2 ?? '') }}"></div>
            <div><label>Contact Number 3</label><input class="form-control" name="contact_number_3" value="{{ old('contact_number_3',$service->contact_number_3 ?? '') }}"></div>
        </div>
        <div style="margin-top:12px;display:flex;gap:18px;">
            <label><input type="checkbox" name="is_published" value="1" {{ old('is_published',$service->is_published ?? true) ? 'checked' : '' }}> Published</label>
            <label><input type="checkbox" name="is_active" value="1" {{ old('is_active',$service->is_active ?? true) ? 'checked' : '' }}> Active</label>
        </div>
    </div></div>

    <div class="card" style="margin-top:16px;"><div class="card-body">
        <div style="display:flex;justify-content:space-between;"><h3>Starting Schedule</h3><button type="button" class="btn btn-sm" onclick="addStop('starting')">+ Add Stop</button></div>
        <div id="starting-container">
            @foreach($startRows as $i=>$s)
                <div class="schedule-row" style="display:grid;grid-template-columns:50px 2fr 1fr 1fr 80px;gap:8px;margin-bottom:8px;align-items:end;">
                    <input class="form-control order-field" value="{{ $i+1 }}" readonly>
                    <input class="form-control stop-name" name="starting_stops[{{ $i }}][stop_name]" value="{{ $s['stop_name'] }}" placeholder="Stop Name" required>
                    <input class="form-control arrival-time" type="time" name="starting_stops[{{ $i }}][arrival_time]" value="{{ $s['arrival_time'] }}">
                    <input class="form-control departure-time" type="time" name="starting_stops[{{ $i }}][departure_time]" value="{{ $s['departure_time'] }}">
                    <button type="button" class="btn btn-sm btn-outline-danger remove-row">Remove</button>
                </div>
            @endforeach
        </div>
    </div></div>

    <div class="card" style="margin-top:16px;"><div class="card-body">
        <div style="display:flex;justify-content:space-between;"><h3>Return Schedule</h3><button type="button" class="btn btn-sm" onclick="addStop('return')">+ Add Stop</button></div>
        <div id="return-container">
            @foreach($returnRows as $i=>$s)
                <div class="schedule-row" style="display:grid;grid-template-columns:50px 2fr 1fr 1fr 80px;gap:8px;margin-bottom:8px;align-items:end;">
                    <input class="form-control order-field" value="{{ $i+1 }}" readonly>
                    <input class="form-control stop-name" name="return_stops[{{ $i }}][stop_name]" value="{{ $s['stop_name'] }}" placeholder="Stop Name" required>
                    <input class="form-control arrival-time" type="time" name="return_stops[{{ $i }}][arrival_time]" value="{{ $s['arrival_time'] }}">
                    <input class="form-control departure-time" type="time" name="return_stops[{{ $i }}][departure_time]" value="{{ $s['departure_time'] }}">
                    <button type="button" class="btn btn-sm btn-outline-danger remove-row">Remove</button>
                </div>
            @endforeach
        </div>
    </div></div>

    <button class="btn btn-primary" type="submit" style="margin-top:16px;">{{ $isEdit ? 'Update Fixed Schedule' : 'Create Fixed Schedule' }}</button>
</form>

<script>
function rebuild(direction){
    const c=document.getElementById(direction+'-container');
    [...c.querySelectorAll('.schedule-row')].forEach((r,i)=>{
        r.querySelector('.order-field').value=i+1;
        r.querySelector('.stop-name').name=`${direction}_stops[${i}][stop_name]`;
        r.querySelector('.arrival-time').name=`${direction}_stops[${i}][arrival_time]`;
        r.querySelector('.departure-time').name=`${direction}_stops[${i}][departure_time]`;
    });
}
function addStop(direction){
    const c=document.getElementById(direction+'-container');
    const r=document.createElement('div');
    r.className='schedule-row';
    r.style.cssText='display:grid;grid-template-columns:50px 2fr 1fr 1fr 80px;gap:8px;margin-bottom:8px;align-items:end;';
    r.innerHTML=`<input class="form-control order-field" readonly><input class="form-control stop-name" placeholder="Stop Name" required><input class="form-control arrival-time" type="time"><input class="form-control departure-time" type="time"><button type="button" class="btn btn-sm btn-outline-danger remove-row">Remove</button>`;
    c.appendChild(r); rebuild(direction);
}
document.addEventListener('click',e=>{
    if(!e.target.classList.contains('remove-row')) return;
    const c=e.target.closest('[id$="-container"]');
    if(c.querySelectorAll('.schedule-row').length<=2){alert('Each direction needs at least two stops.');return;}
    const direction=c.id.replace('-container','');
    e.target.closest('.schedule-row').remove(); rebuild(direction);
});
</script>
@endsection
