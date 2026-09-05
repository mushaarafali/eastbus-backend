<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminFixedScheduleController extends Controller
{
    public function index()
    {
        $services = DB::table('fixed_services')
            ->orderByDesc('id')
            ->get();

        foreach ($services as $service) {
            $service->starting_stops = DB::table('fixed_service_stops')
                ->where('fixed_service_id', $service->id)
                ->where('direction', 'starting')
                ->orderBy('stop_order')
                ->get();

            $service->return_stops = DB::table('fixed_service_stops')
                ->where('fixed_service_id', $service->id)
                ->where('direction', 'return')
                ->orderBy('stop_order')
                ->get();
        }

        return view('admin.fixed_schedules.index', compact('services'));
    }

    public function create()
    {
        $routeStops = DB::table('route_stops')
            ->orderBy('name')
            ->get();

        return view('admin.fixed_schedules.form', [
            'service' => null,
            'startingStops' => collect(),
            'returnStops' => collect(),
            'routeStops' => $routeStops,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        DB::transaction(function () use ($data, $request) {
            $first = $data['starting_stops'][0]['stop_name'];
            $last = $data['starting_stops'][count($data['starting_stops']) - 1]['stop_name'];

            $id = DB::table('fixed_services')->insertGetId([
                'bus_name' => $data['bus_name'],
                'bus_number' => $data['bus_number'] ?: null,
                'origin' => $first,
                'destination' => $last,
                'contact_number_1' => $data['contact_number_1'] ?: null,
                'contact_number_2' => $data['contact_number_2'] ?: null,
                'contact_number_3' => $data['contact_number_3'] ?: null,
                'is_published' => $request->boolean('is_published'),
                'is_active' => $request->boolean('is_active'),
                'created_by_admin_id' => $request->user()?->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->saveStops($id, 'starting', $data['starting_stops']);
            $this->saveStops($id, 'return', $data['return_stops']);
        });

        return redirect()
            ->route('admin.fixed-schedules.index')
            ->with('success', 'Fixed bus schedule created successfully.');
    }

    public function edit(int $id)
    {
        $service = DB::table('fixed_services')
            ->where('id', $id)
            ->first();

        abort_unless($service, 404);

        $startingStops = DB::table('fixed_service_stops')
            ->where('fixed_service_id', $id)
            ->where('direction', 'starting')
            ->orderBy('stop_order')
            ->get();

        $returnStops = DB::table('fixed_service_stops')
            ->where('fixed_service_id', $id)
            ->where('direction', 'return')
            ->orderBy('stop_order')
            ->get();

        $routeStops = DB::table('route_stops')
            ->orderBy('name')
            ->get();

        return view('admin.fixed_schedules.form', compact(
            'service',
            'startingStops',
            'returnStops',
            'routeStops'
        ));
    }

    public function update(Request $request, int $id)
    {
        abort_unless(
            DB::table('fixed_services')
                ->where('id', $id)
                ->exists(),
            404
        );

        $data = $this->validateData($request);

        DB::transaction(function () use ($id, $data, $request) {
            $first = $data['starting_stops'][0]['stop_name'];
            $last = $data['starting_stops'][count($data['starting_stops']) - 1]['stop_name'];

            DB::table('fixed_services')
                ->where('id', $id)
                ->update([
                    'bus_name' => $data['bus_name'],
                    'bus_number' => $data['bus_number'] ?: null,
                    'origin' => $first,
                    'destination' => $last,
                    'contact_number_1' => $data['contact_number_1'] ?: null,
                    'contact_number_2' => $data['contact_number_2'] ?: null,
                    'contact_number_3' => $data['contact_number_3'] ?: null,
                    'is_published' => $request->boolean('is_published'),
                    'is_active' => $request->boolean('is_active'),
                    'updated_at' => now(),
                ]);

            DB::table('fixed_service_stops')
                ->where('fixed_service_id', $id)
                ->delete();

            $this->saveStops($id, 'starting', $data['starting_stops']);
            $this->saveStops($id, 'return', $data['return_stops']);
        });

        return back()->with('success', 'Fixed bus schedule updated successfully.');
    }

    public function togglePublish(int $id)
    {
        $service = DB::table('fixed_services')
            ->where('id', $id)
            ->first();

        abort_unless($service, 404);

        DB::table('fixed_services')
            ->where('id', $id)
            ->update([
                'is_published' => !$service->is_published,
                'updated_at' => now(),
            ]);

        return back()->with('success', 'Publish status updated.');
    }

    public function toggleActive(int $id)
    {
        $service = DB::table('fixed_services')
            ->where('id', $id)
            ->first();

        abort_unless($service, 404);

        DB::table('fixed_services')
            ->where('id', $id)
            ->update([
                'is_active' => !$service->is_active,
                'updated_at' => now(),
            ]);

        return back()->with('success', 'Active status updated.');
    }

    public function destroy(int $id)
    {
        DB::transaction(function () use ($id) {
            DB::table('fixed_service_stops')
                ->where('fixed_service_id', $id)
                ->delete();

            DB::table('fixed_services')
                ->where('id', $id)
                ->delete();
        });

        return redirect()
            ->route('admin.fixed-schedules.index')
            ->with('success', 'Fixed schedule deleted.');
    }

    private function validateData(Request $request): array
    {
        $data = $request->validate([
            'bus_name' => ['required', 'string', 'max:150'],
            'bus_number' => ['nullable', 'string', 'max:50'],
            'contact_number_1' => ['nullable', 'string', 'max:30'],
            'contact_number_2' => ['nullable', 'string', 'max:30'],
            'contact_number_3' => ['nullable', 'string', 'max:30'],

            'starting_stops' => ['required', 'array', 'min:2'],
            'starting_stops.*.route_stop_id' => ['nullable', 'integer'],
            'starting_stops.*.stop_name' => ['required', 'string', 'max:150'],
            'starting_stops.*.arrival_time' => ['nullable', 'date_format:H:i'],
            'starting_stops.*.departure_time' => ['nullable', 'date_format:H:i'],
            'starting_stops.*.boarding_allowed' => ['nullable', 'boolean'],
            'starting_stops.*.dropoff_allowed' => ['nullable', 'boolean'],

            'return_stops' => ['required', 'array', 'min:2'],
            'return_stops.*.route_stop_id' => ['nullable', 'integer'],
            'return_stops.*.stop_name' => ['required', 'string', 'max:150'],
            'return_stops.*.arrival_time' => ['nullable', 'date_format:H:i'],
            'return_stops.*.departure_time' => ['nullable', 'date_format:H:i'],
            'return_stops.*.boarding_allowed' => ['nullable', 'boolean'],
            'return_stops.*.dropoff_allowed' => ['nullable', 'boolean'],
        ]);

        $data['starting_stops'] = $this->cleanStops($data['starting_stops']);
        $data['return_stops'] = $this->cleanStops($data['return_stops']);

        $startFirst = mb_strtolower($data['starting_stops'][0]['stop_name']);
        $startLast = mb_strtolower($data['starting_stops'][count($data['starting_stops']) - 1]['stop_name']);
        $returnFirst = mb_strtolower($data['return_stops'][0]['stop_name']);
        $returnLast = mb_strtolower($data['return_stops'][count($data['return_stops']) - 1]['stop_name']);

        if ($returnFirst !== $startLast || $returnLast !== $startFirst) {
            throw ValidationException::withMessages([
                'return_stops' => 'Return schedule must start at the starting destination and end at the starting origin.',
            ]);
        }

        $this->validateDirectionStops(
            $data['starting_stops'],
            'starting_stops'
        );

        $this->validateDirectionStops(
            $data['return_stops'],
            'return_stops'
        );

        return $data;
    }

    private function cleanStops(array $stops): array
    {
        return collect($stops)
            ->map(function ($stop) {
                return [
                    'route_stop_id' => !empty($stop['route_stop_id'])
                        ? (int) $stop['route_stop_id']
                        : null,
                    'stop_name' => trim((string) $stop['stop_name']),
                    'arrival_time' => !empty($stop['arrival_time'])
                        ? $stop['arrival_time']
                        : null,
                    'departure_time' => !empty($stop['departure_time'])
                        ? $stop['departure_time']
                        : null,
                    'boarding_allowed' => !empty($stop['boarding_allowed']),
                    'dropoff_allowed' => !empty($stop['dropoff_allowed']),
                ];
            })
            ->values()
            ->all();
    }

    private function validateDirectionStops(array $stops, string $field): void
    {
        foreach ($stops as $index => $stop) {
            if (!empty($stop['route_stop_id'])) {
                $routeStop = DB::table('route_stops')
                    ->where('id', $stop['route_stop_id'])
                    ->first();

                if (!$routeStop) {
                    throw ValidationException::withMessages([
                        "$field.$index.route_stop_id" => 'Selected route stop does not exist.',
                    ]);
                }

                if (mb_strtolower(trim($routeStop->name)) !== mb_strtolower(trim($stop['stop_name']))) {
                    throw ValidationException::withMessages([
                        "$field.$index.stop_name" => 'Selected route stop and stop name do not match.',
                    ]);
                }
            }

            if (!$stop['arrival_time'] && !$stop['departure_time']) {
                throw ValidationException::withMessages([
                    "$field.$index.arrival_time" => 'Each timetable stop must contain an arrival or departure time.',
                ]);
            }

            if (
                ($stop['boarding_allowed'] || $stop['dropoff_allowed']) &&
                empty($stop['route_stop_id'])
            ) {
                throw ValidationException::withMessages([
                    "$field.$index.route_stop_id" => 'A route stop must be selected for a bookable stop.',
                ]);
            }

            if (!empty($stop['route_stop_id'])) {
                $routeStop = DB::table('route_stops')
                    ->where('id', $stop['route_stop_id'])
                    ->first();

                if (
                    ($stop['boarding_allowed'] || $stop['dropoff_allowed']) &&
                    empty($routeStop->fare_stage_no)
                ) {
                    throw ValidationException::withMessages([
                        "$field.$index.route_stop_id" => 'Bookable stop must have an NTC Fare Stage No.',
                    ]);
                }
            }
        }

        $names = collect($stops)
            ->pluck('stop_name')
            ->map(fn ($name) => mb_strtolower(trim($name)));

        if ($names->unique()->count() !== $names->count()) {
            throw ValidationException::withMessages([
                $field => 'The same stop cannot appear more than once in the same direction.',
            ]);
        }
    }

    private function saveStops(
        int $serviceId,
        string $direction,
        array $stops
    ): void {
        foreach ($stops as $index => $stop) {
            DB::table('fixed_service_stops')->insert([
                'fixed_service_id' => $serviceId,
                'route_stop_id' => $stop['route_stop_id'],
                'direction' => $direction,
                'stop_name' => $stop['stop_name'],
                'stop_order' => $index + 1,
                'arrival_time' => $stop['arrival_time'],
                'departure_time' => $stop['departure_time'],
                'boarding_allowed' => $stop['boarding_allowed'],
                'dropoff_allowed' => $stop['dropoff_allowed'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}