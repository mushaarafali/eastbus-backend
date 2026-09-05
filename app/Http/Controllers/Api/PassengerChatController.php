<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PassengerChatController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Passenger AI Chat
    |--------------------------------------------------------------------------
    */

    public function chat(Request $request)
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:1500'],
            'context' => ['nullable', 'array'],
        ]);

        try {
            $message = trim((string) $data['message']);
            $passengerContext = $data['context'] ?? [];

            // 1. EastBus DB first. Matching route/service data is returned
            // directly so Gemini downtime never blocks bus-information queries.
            $databaseContext = $this->buildDatabaseContext($message);

            if ($this->hasDatabaseMatch($databaseContext)) {
                return response()->json([
                    'success' => true,
                    'reply' => $this->buildDirectDatabaseReply($databaseContext),
                    'source' => 'eastbus_database',
                    'database_match' => true,
                    'matched_locations' => $databaseContext['matched_locations'] ?? [],
                ]);
            }

            // 2. Gemini is only a fallback for questions not answered by DB.
            $apiKey = config('services.gemini.key');

            if (empty($apiKey)) {
                return response()->json([
                    'success' => true,
                    'reply' => $this->buildNoDatabaseMatchReply($databaseContext),
                    'source' => 'eastbus_database_fallback',
                    'database_match' => false,
                    'matched_locations' => $databaseContext['matched_locations'] ?? [],
                ]);
            }

            $passengerContextText = $this->buildPassengerContextText($passengerContext);

            $prompt = <<<PROMPT
You are EastBus AI Assistant, the official passenger-support assistant for EastBus in Sri Lanka.

Reply only in English, Tamil, or Sinhala based on the passenger's language.
Keep answers short, clear, practical, and passenger-friendly.

Important rules:
- EastBus database did not return a matching direct service for this query.
- Never invent buses, routes, fares, schedules, booking availability, booking references, passenger details, live locations, or trip statuses.
- If the passenger asks about a route/bus that is not in EastBus data, clearly say no matching EastBus service was found.
- You may explain how to use Search Buses, booking, tickets, tracking, payments, feedback, login, or OTP features.
- Never ask for passwords, OTPs, CVV, full card numbers, API keys, or tokens.
- Use personal booking/trip details only if they appear in PASSENGER CONTEXT.

PASSENGER CONTEXT:
{$passengerContextText}

PASSENGER MESSAGE:
{$message}
PROMPT;

            $response = Http::timeout(18)
                ->retry(1, 700, throw: false)
                ->withHeaders([
                    'x-goog-api-key' => $apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post(
                    'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent',
                    [
                        'contents' => [[
                            'role' => 'user',
                            'parts' => [['text' => $prompt]],
                        ]],
                        'generationConfig' => [
                            'temperature' => 0.20,
                            'topP' => 0.85,
                            'maxOutputTokens' => 500,
                        ],
                    ]
                );

            if (!$response->successful()) {
                Log::warning('Gemini API Fallback Error', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);

                return response()->json([
                    'success' => true,
                    'reply' => $this->buildNoDatabaseMatchReply($databaseContext),
                    'source' => 'eastbus_database_fallback',
                    'database_match' => false,
                    'gemini_status' => $response->status(),
                    'matched_locations' => $databaseContext['matched_locations'] ?? [],
                ]);
            }

            $reply = data_get($response->json(), 'candidates.0.content.parts.0.text');

            if (!is_string($reply) || trim($reply) === '') {
                return response()->json([
                    'success' => true,
                    'reply' => $this->buildNoDatabaseMatchReply($databaseContext),
                    'source' => 'eastbus_database_fallback',
                    'database_match' => false,
                    'matched_locations' => $databaseContext['matched_locations'] ?? [],
                ]);
            }

            return response()->json([
                'success' => true,
                'reply' => trim($reply),
                'source' => 'gemini',
                'database_match' => false,
                'matched_locations' => $databaseContext['matched_locations'] ?? [],
                'model' => 'gemini-3.6-flash',
            ]);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning('Gemini Connection Error', ['message' => $e->getMessage()]);

            return response()->json([
                'success' => true,
                'reply' => 'I could not find a matching EastBus service in the database right now. Please use Search Buses in the EastBus Passenger App to check the latest available services.',
                'source' => 'eastbus_database_fallback',
                'database_match' => false,
            ]);

        } catch (\Throwable $e) {
            Log::error('Passenger Chatbot Error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to process your message right now.',
            ], 500);
        }
    }

    private function hasDatabaseMatch(array $context): bool
    {
        return !empty($context['online_trips'] ?? [])
            || !empty($context['daily_service_buses'] ?? [])
            || !empty($context['routes'] ?? []);
    }

    private function buildDirectDatabaseReply(array $context): string
    {
        $locations = $context['matched_locations'] ?? [];
        $origin = $locations[0] ?? 'selected origin';
        $destination = $locations[1] ?? 'selected destination';
        $onlineTrips = $context['online_trips'] ?? [];
        $dailyServices = $context['daily_service_buses'] ?? [];
        $routes = $context['routes'] ?? [];
        $lines = [];

        $serviceCount = count($onlineTrips) + count($dailyServices);

        if ($serviceCount > 0) {
            $lines[] = "I found {$serviceCount} EastBus service" . ($serviceCount === 1 ? '' : 's') . " from {$origin} to {$destination}.";
        } elseif (!empty($routes)) {
            $lines[] = "I found an EastBus route from {$origin} to {$destination}, but there is no currently published passenger service for it.";
        }

        foreach (array_slice($onlineTrips, 0, 5) as $i => $trip) {
            $name = trim((string) ($trip['company_name'] ?? $trip['bus_name'] ?? 'EastBus Service'));
            $number = trim((string) ($trip['bus_number'] ?? ''));
            $heading = ($i + 1) . '. ' . $name . ($number !== '' ? " ({$number})" : '');
            $lines[] = $heading;

            if (!empty($trip['service_date'])) {
                $lines[] = '   Date: ' . $trip['service_date'];
            }

            $boarding = '   Boarding: ' . ($trip['boarding_stop'] ?? $origin);
            if (!empty($trip['boarding_time'])) {
                $boarding .= ' - ' . $this->formatTime($trip['boarding_time']);
            }
            $lines[] = $boarding;

            $dropoff = '   Drop-off: ' . ($trip['dropoff_stop'] ?? $destination);
            if (!empty($trip['dropoff_time'])) {
                $dropoff .= ' - ' . $this->formatTime($trip['dropoff_time']);
            }
            $lines[] = $dropoff;

            if (isset($trip['fare']) && $trip['fare'] !== null) {
                $lines[] = '   Fare: Rs. ' . number_format((float) $trip['fare'], 2);
            }

            $lines[] = '   Online Booking: ' . (!empty($trip['booking_available']) ? 'Available' : 'Not Available');
        }

        if (!empty($dailyServices)) {
            if (!empty($onlineTrips)) {
                $lines[] = '';
            }
            $lines[] = 'Daily Service Bus:';

            foreach (array_slice($dailyServices, 0, 5) as $i => $service) {
                $name = trim((string) ($service['bus_name'] ?? 'Daily Service Bus'));
                $number = trim((string) ($service['bus_number'] ?? ''));
                $lines[] = ($i + 1) . '. ' . $name . ($number !== '' ? " ({$number})" : '');

                $boarding = '   Boarding: ' . ($service['boarding_stop'] ?? $origin);
                if (!empty($service['boarding_time'])) {
                    $boarding .= ' - ' . $this->formatTime($service['boarding_time']);
                }
                $lines[] = $boarding;

                $dropoff = '   Drop-off: ' . ($service['dropoff_stop'] ?? $destination);
                if (!empty($service['dropoff_time'])) {
                    $dropoff .= ' - ' . $this->formatTime($service['dropoff_time']);
                }
                $lines[] = $dropoff;

                $contacts = collect([
                    $service['contact_number_1'] ?? null,
                    $service['contact_number_2'] ?? null,
                    $service['contact_number_3'] ?? null,
                ])->filter()->unique()->values()->all();

                if (!empty($contacts)) {
                    $lines[] = '   Contact: ' . implode(' / ', $contacts);
                }

                $lines[] = '   Online Booking: Not Available';
            }
        }

        if (empty($onlineTrips) && empty($dailyServices) && !empty($routes)) {
            $lines[] = 'Please use Search Buses in the EastBus Passenger App to check future published trips.';
        }

        return implode("\n", $lines);
    }

    private function buildNoDatabaseMatchReply(array $context): string
    {
        $locations = $context['matched_locations'] ?? [];

        if (count($locations) >= 2) {
            return 'I could not find a matching EastBus service from '
                . $locations[0]
                . ' to '
                . $locations[1]
                . '. Please use Search Buses in the EastBus Passenger App to check the latest available services.';
        }

        return 'I could not find matching EastBus route or bus information for that question. Please use Search Buses in the EastBus Passenger App or ask with both origin and destination.';
    }

    private function formatTime($value): string
    {
        $text = trim((string) $value);

        if ($text === '') {
            return '';
        }

        try {
            return \Carbon\Carbon::parse($text)->format('h:i A');
        } catch (\Throwable $e) {
            return $text;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Build Database Context
    |--------------------------------------------------------------------------
    */

    private function buildDatabaseContext(
        string $message
    ): array {
        $matchedLocations =
            $this->detectLocations(
                $message
            );

        $context = [
            'matched_locations' =>
                $matchedLocations,

            'routes' =>
                [],

            'online_trips' =>
                [],

            'daily_service_buses' =>
                [],
        ];

        /*
         * For useful route matching we normally
         * need at least two locations.
         */
        if (
            count($matchedLocations) <
            2
        ) {
            return $context;
        }

        $origin =
            $matchedLocations[0];

        $destination =
            $matchedLocations[1];

        /*
        |--------------------------------------------------------------------------
        | Matching Routes
        |--------------------------------------------------------------------------
        */

        $routeMatches =
            $this->findMatchingRoutes(
                $origin,
                $destination
            );

        $context['routes'] =
            $routeMatches
                ->map(
                    fn ($route) => [
                        'route_id' =>
                            $route->id,

                        'route_name' =>
                            $route->name ?? null,

                        'origin' =>
                            $route->origin ?? null,

                        'destination' =>
                            $route->destination ?? null,

                        'distance_km' =>
                            isset(
                                $route->distance_km
                            )
                                ? (float)
                                    $route->distance_km
                                : null,
                    ]
                )
                ->values()
                ->all();

        $routeIds =
            $routeMatches
                ->pluck('id')
                ->filter()
                ->unique()
                ->values();

        /*
        |--------------------------------------------------------------------------
        | Online Trips
        |--------------------------------------------------------------------------
        */

        if ($routeIds->isNotEmpty()) {
            $context['online_trips'] =
                $this->findOnlineTrips(
                    $routeIds,
                    $origin,
                    $destination
                );
        }

        /*
        |--------------------------------------------------------------------------
        | Daily Service Buses
        |--------------------------------------------------------------------------
        */

        $context['daily_service_buses'] =
            $this->findDailyServices(
                $origin,
                $destination
            );

        return $context;
    }

    /*
    |--------------------------------------------------------------------------
    | Detect Locations From Passenger Question
    |--------------------------------------------------------------------------
    */

    private function detectLocations(
        string $message
    ): array {
        $messageLower =
            mb_strtolower(
                $message
            );

        $names =
            collect();

        /*
        |--------------------------------------------------------------------------
        | route_booking_stops
        |--------------------------------------------------------------------------
        */

        if (
            Schema::hasTable(
                'route_booking_stops'
            ) &&
            Schema::hasColumn(
                'route_booking_stops',
                'stop_name'
            )
        ) {
            $names = $names->merge(
                DB::table(
                    'route_booking_stops'
                )
                    ->whereNotNull(
                        'stop_name'
                    )
                    ->pluck(
                        'stop_name'
                    )
            );
        }

        /*
        |--------------------------------------------------------------------------
        | route_stops
        |--------------------------------------------------------------------------
        */

        if (
            Schema::hasTable(
                'route_stops'
            ) &&
            Schema::hasColumn(
                'route_stops',
                'name'
            )
        ) {
            $names = $names->merge(
                DB::table(
                    'route_stops'
                )
                    ->whereNotNull(
                        'name'
                    )
                    ->pluck(
                        'name'
                    )
            );
        }

        /*
        |--------------------------------------------------------------------------
        | fixed_service_stops
        |--------------------------------------------------------------------------
        */

        if (
            Schema::hasTable(
                'fixed_service_stops'
            ) &&
            Schema::hasColumn(
                'fixed_service_stops',
                'stop_name'
            )
        ) {
            $names = $names->merge(
                DB::table(
                    'fixed_service_stops'
                )
                    ->whereNotNull(
                        'stop_name'
                    )
                    ->pluck(
                        'stop_name'
                    )
            );
        }

        /*
         * Longest names first prevents a shorter
         * place name matching before a longer one.
         */
        $names = $names
            ->filter()
            ->map(
                fn ($name) =>
                    trim(
                        (string) $name
                    )
            )
            ->filter()
            ->unique(
                fn ($name) =>
                    mb_strtolower($name)
            )
            ->sortByDesc(
                fn ($name) =>
                    mb_strlen($name)
            )
            ->values();

        $matches = [];

        foreach ($names as $name) {
            $needle =
                mb_strtolower(
                    $name
                );

            $position =
                mb_strpos(
                    $messageLower,
                    $needle
                );

            if ($position === false) {
                continue;
            }

            $matches[] = [
                'name' =>
                    $name,

                'position' =>
                    $position,
            ];
        }

        /*
         * Preserve the order passenger typed:
         *
         * Batticaloa to Galle
         *
         * Batticaloa = origin
         * Galle      = destination
         */
        usort(
            $matches,
            fn ($a, $b) =>
                $a['position']
                <=>
                $b['position']
        );

        return collect(
            $matches
        )
            ->pluck('name')
            ->unique(
                fn ($name) =>
                    mb_strtolower($name)
            )
            ->take(2)
            ->values()
            ->all();
    }

    /*
    |--------------------------------------------------------------------------
    | Find Routes Containing Both Locations
    |--------------------------------------------------------------------------
    */

    private function findMatchingRoutes(
        string $origin,
        string $destination
    ): Collection {
        if (
            !Schema::hasTable(
                'routes'
            ) ||
            !Schema::hasTable(
                'route_stops'
            )
        ) {
            return collect();
        }

        /*
         * Find routes where both stops are
         * present somewhere in the road way.
         */

        $originRouteIds =
            DB::table(
                'route_stops'
            )
                ->whereRaw(
                    'LOWER(name) = ?',
                    [
                        mb_strtolower(
                            $origin
                        ),
                    ]
                )
                ->pluck(
                    'route_id'
                );

        $destinationRouteIds =
            DB::table(
                'route_stops'
            )
                ->whereRaw(
                    'LOWER(name) = ?',
                    [
                        mb_strtolower(
                            $destination
                        ),
                    ]
                )
                ->pluck(
                    'route_id'
                );

        $routeIds =
            $originRouteIds
                ->intersect(
                    $destinationRouteIds
                )
                ->unique()
                ->values();

        if ($routeIds->isEmpty()) {
            return collect();
        }

        return DB::table(
            'routes'
        )
            ->whereIn(
                'id',
                $routeIds
            )
            ->when(
                Schema::hasColumn(
                    'routes',
                    'is_active'
                ),
                fn ($query) =>
                    $query->where(
                        'is_active',
                        1
                    )
            )
            ->limit(10)
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | Find Published Online Trips
    |--------------------------------------------------------------------------
    */

    private function findOnlineTrips(
        Collection $routeIds,
        string $origin,
        string $destination
    ): array {
        if (
            !Schema::hasTable(
                'trips'
            )
        ) {
            return [];
        }

        $query =
            DB::table(
                'trips'
            )
                ->whereIn(
                    'trips.route_id',
                    $routeIds
                );

        /*
        |--------------------------------------------------------------------------
        | Bus
        |--------------------------------------------------------------------------
        */

        if (
            Schema::hasTable(
                'buses'
            )
        ) {
            $query->leftJoin(
                'buses',
                'buses.id',
                '=',
                'trips.bus_id'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Operator
        |--------------------------------------------------------------------------
        */

        if (
            Schema::hasTable(
                'operators'
            ) &&
            Schema::hasTable(
                'buses'
            )
        ) {
            $query->leftJoin(
                'operators',
                'operators.id',
                '=',
                'buses.operator_id'
            );
        }

        if (
            Schema::hasColumn(
                'trips',
                'is_published'
            )
        ) {
            $query->where(
                'trips.is_published',
                1
            );
        }

        if (
            Schema::hasColumn(
                'trips',
                'status'
            )
        ) {
            $query->whereIn(
                'trips.status',
                [
                    'scheduled',
                    'active',
                ]
            );
        }

        if (
            Schema::hasColumn(
                'trips',
                'service_date'
            )
        ) {
            $query->whereDate(
                'trips.service_date',
                '>=',
                now()->toDateString()
            );
        }

        $select = [
            'trips.id',
            'trips.route_id',
        ];

        foreach (
            [
                'trip_code',
                'service_date',
                'status',
                'trip_type',
                'fare',
                'is_published',
            ]
            as $column
        ) {
            if (
                Schema::hasColumn(
                    'trips',
                    $column
                )
            ) {
                $select[] =
                    "trips.$column";
            }
        }

        if (
            Schema::hasTable(
                'buses'
            )
        ) {
            foreach (
                [
                    'bus_number',
                    'bus_name',
                    'bus_type',
                ]
                as $column
            ) {
                if (
                    Schema::hasColumn(
                        'buses',
                        $column
                    )
                ) {
                    $select[] =
                        "buses.$column";
                }
            }
        }

        if (
            Schema::hasTable(
                'operators'
            ) &&
            Schema::hasColumn(
                'operators',
                'company_name'
            )
        ) {
            $select[] =
                'operators.company_name';
        }

        $trips =
            $query
                ->select(
                    $select
                )
                ->orderBy(
                    Schema::hasColumn(
                        'trips',
                        'service_date'
                    )
                        ? 'trips.service_date'
                        : 'trips.id'
                )
                ->limit(10)
                ->get();

        return $trips
            ->map(
                function ($trip) use (
                    $origin,
                    $destination
                ) {
                    $times =
                        $this->findBookingStopTimes(
                            (int) $trip->route_id,
                            $origin,
                            $destination,
                            $trip->trip_type ?? 'starting'
                        );

                    return [
                        'trip_id' =>
                            $trip->id,

                        'trip_code' =>
                            $trip->trip_code
                            ?? null,

                        'company_name' =>
                            $trip->company_name
                            ?? null,

                        'bus_name' =>
                            $trip->bus_name
                            ?? null,

                        'bus_number' =>
                            $trip->bus_number
                            ?? null,

                        'bus_type' =>
                            $trip->bus_type
                            ?? null,

                        'service_date' =>
                            $trip->service_date
                            ?? null,

                        'direction' =>
                            $trip->trip_type
                            ?? 'starting',

                        'status' =>
                            $trip->status
                            ?? null,

                        'boarding_stop' =>
                            $origin,

                        'boarding_time' =>
                            $times[
                                'boarding_time'
                            ],

                        'dropoff_stop' =>
                            $destination,

                        'dropoff_time' =>
                            $times[
                                'dropoff_time'
                            ],

                        'fare' =>
                            isset($trip->fare)
                                ? (float)
                                    $trip->fare
                                : null,

                        'booking_available' =>
                            isset(
                                $trip->is_published
                            )
                                ? (bool)
                                    $trip->is_published
                                : true,
                    ];
                }
            )
            ->values()
            ->all();
    }

    /*
    |--------------------------------------------------------------------------
    | Booking Stop Times
    |--------------------------------------------------------------------------
    */

    private function findBookingStopTimes(
        int $routeId,
        string $origin,
        string $destination,
        string $direction
    ): array {
        $result = [
            'boarding_time' =>
                null,

            'dropoff_time' =>
                null,
        ];

        if (
            !Schema::hasTable(
                'route_booking_stops'
            )
        ) {
            return $result;
        }

        $query =
            DB::table(
                'route_booking_stops'
            )
                ->where(
                    'route_id',
                    $routeId
                );

        if (
            Schema::hasColumn(
                'route_booking_stops',
                'direction'
            )
        ) {
            $query->where(
                'direction',
                $direction
            );
        }

        $rows =
            $query->get();

        $boarding =
            $rows->first(
                fn ($row) =>
                    mb_strtolower(
                        trim(
                            (string)
                            (
                                $row->stop_name
                                ?? ''
                            )
                        )
                    )
                    ===
                    mb_strtolower(
                        trim($origin)
                    )
            );

        $dropoff =
            $rows->first(
                fn ($row) =>
                    mb_strtolower(
                        trim(
                            (string)
                            (
                                $row->stop_name
                                ?? ''
                            )
                        )
                    )
                    ===
                    mb_strtolower(
                        trim($destination)
                    )
            );

        if ($boarding) {
            $result[
                'boarding_time'
            ] =
                $boarding->schedule_time
                ?? null;
        }

        if ($dropoff) {
            $result[
                'dropoff_time'
            ] =
                $dropoff->schedule_time
                ?? null;
        }

        return $result;
    }

    /*
    |--------------------------------------------------------------------------
    | Daily Service Bus
    |--------------------------------------------------------------------------
    */

    private function findDailyServices(
        string $origin,
        string $destination
    ): array {
        if (
            !Schema::hasTable(
                'fixed_services'
            ) ||
            !Schema::hasTable(
                'fixed_service_stops'
            )
        ) {
            return [];
        }

        /*
         * Find Daily Service Bus IDs that contain
         * both passenger locations.
         */

        $originIds =
            DB::table(
                'fixed_service_stops'
            )
                ->whereRaw(
                    'LOWER(stop_name) = ?',
                    [
                        mb_strtolower(
                            $origin
                        ),
                    ]
                )
                ->pluck(
                    'fixed_service_id'
                );

        $destinationIds =
            DB::table(
                'fixed_service_stops'
            )
                ->whereRaw(
                    'LOWER(stop_name) = ?',
                    [
                        mb_strtolower(
                            $destination
                        ),
                    ]
                )
                ->pluck(
                    'fixed_service_id'
                );

        $serviceIds =
            $originIds
                ->intersect(
                    $destinationIds
                )
                ->unique()
                ->values();

        if ($serviceIds->isEmpty()) {
            return [];
        }

        $serviceQuery =
            DB::table(
                'fixed_services'
            )
                ->whereIn(
                    'id',
                    $serviceIds
                );

        if (
            Schema::hasColumn(
                'fixed_services',
                'is_active'
            )
        ) {
            $serviceQuery->where(
                'is_active',
                1
            );
        }

        if (
            Schema::hasColumn(
                'fixed_services',
                'is_published'
            )
        ) {
            $serviceQuery->where(
                'is_published',
                1
            );
        }

        $services =
            $serviceQuery
                ->limit(10)
                ->get();

        return $services
            ->map(
                function ($service) use (
                    $origin,
                    $destination
                ) {
                    $stops =
                        DB::table(
                            'fixed_service_stops'
                        )
                            ->where(
                                'fixed_service_id',
                                $service->id
                            )
                            ->orderBy(
                                Schema::hasColumn(
                                    'fixed_service_stops',
                                    'stop_order'
                                )
                                    ? 'stop_order'
                                    : 'id'
                            )
                            ->get();

                    $matchingDirection =
                        $this->findDailyServiceDirection(
                            $stops,
                            $origin,
                            $destination
                        );

                    return [
                        'service_id' =>
                            $service->id,

                        'service_type' =>
                            'Daily Service Bus',

                        'bus_name' =>
                            $service->bus_name
                            ?? null,

                        'bus_number' =>
                            $service->bus_number
                            ?? null,

                        'contact_number_1' =>
                            $service->contact_number_1
                            ?? null,

                        'contact_number_2' =>
                            $service->contact_number_2
                            ?? null,

                        'contact_number_3' =>
                            $service->contact_number_3
                            ?? null,

                        'direction' =>
                            $matchingDirection[
                                'direction'
                            ],

                        'boarding_stop' =>
                            $origin,

                        'boarding_time' =>
                            $matchingDirection[
                                'boarding_time'
                            ],

                        'dropoff_stop' =>
                            $destination,

                        'dropoff_time' =>
                            $matchingDirection[
                                'dropoff_time'
                            ],

                        /*
                         * Daily Service Bus does not
                         * automatically mean online booking.
                         */
                        'online_booking_available' =>
                            false,
                    ];
                }
            )
            ->filter(
                fn ($service) =>
                    $service[
                        'direction'
                    ] !== null
            )
            ->values()
            ->all();
    }

    /*
    |--------------------------------------------------------------------------
    | Daily Service Direction
    |--------------------------------------------------------------------------
    */

    private function findDailyServiceDirection(
        Collection $stops,
        string $origin,
        string $destination
    ): array {
        foreach (
            [
                'starting',
                'return',
            ]
            as $direction
        ) {
            $directionStops =
                $stops
                    ->filter(
                        function ($stop) use (
                            $direction
                        ) {
                            if (
                                !property_exists(
                                    $stop,
                                    'direction'
                                )
                            ) {
                                return true;
                            }

                            return
                                strtolower(
                                    (string)
                                    $stop->direction
                                )
                                ===
                                $direction;
                        }
                    )
                    ->values();

            $originIndex =
                $directionStops
                    ->search(
                        fn ($stop) =>
                            mb_strtolower(
                                trim(
                                    (string)
                                    (
                                        $stop->stop_name
                                        ?? ''
                                    )
                                )
                            )
                            ===
                            mb_strtolower(
                                trim(
                                    $origin
                                )
                            )
                    );

            $destinationIndex =
                $directionStops
                    ->search(
                        fn ($stop) =>
                            mb_strtolower(
                                trim(
                                    (string)
                                    (
                                        $stop->stop_name
                                        ?? ''
                                    )
                                )
                            )
                            ===
                            mb_strtolower(
                                trim(
                                    $destination
                                )
                            )
                    );

            if (
                $originIndex === false ||
                $destinationIndex === false ||
                $originIndex >=
                $destinationIndex
            ) {
                continue;
            }

            $boarding =
                $directionStops[
                    $originIndex
                ];

            $dropoff =
                $directionStops[
                    $destinationIndex
                ];

            return [
                'direction' =>
                    $direction,

                'boarding_time' =>
                    $boarding->departure_time
                    ??
                    $boarding->arrival_time
                    ??
                    null,

                'dropoff_time' =>
                    $dropoff->arrival_time
                    ??
                    $dropoff->departure_time
                    ??
                    null,
            ];
        }

        return [
            'direction' =>
                null,

            'boarding_time' =>
                null,

            'dropoff_time' =>
                null,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Passenger Context
    |--------------------------------------------------------------------------
    */

    private function buildPassengerContextText(
        array $context
    ): string {
        if (empty($context)) {
            return
                'No personal passenger booking or tracking context was provided.';
        }

        $allowed = [
            'booking_reference',
            'booking_status',
            'payment_status',
            'trip_id',
            'trip_code',
            'trip_status',
            'bus_name',
            'bus_number',
            'origin',
            'destination',
            'service_date',
            'boarding_stop',
            'boarding_time',
            'dropoff_stop',
            'dropoff_time',
            'seat_numbers',
            'available_seats',
            'fare',
            'tracking_status',
            'recommendation_reason',
        ];

        $safeContext = [];

        foreach (
            $allowed
            as $key
        ) {
            if (
                array_key_exists(
                    $key,
                    $context
                )
            ) {
                $safeContext[
                    $key
                ] =
                    $context[$key];
            }
        }

        if (empty($safeContext)) {
            return
                'No usable personal EastBus context was provided.';
        }

        return json_encode(
            $safeContext,
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        ) ?: 'No usable personal EastBus context was provided.';
    }
}