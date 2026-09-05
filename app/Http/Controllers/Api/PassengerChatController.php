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
            'message' => [
                'required',
                'string',
                'max:1500',
            ],

            'context' => [
                'nullable',
                'array',
            ],
        ]);

        $apiKey = config(
            'services.gemini.key'
        );

        if (empty($apiKey)) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Gemini API key is not configured.',
            ], 500);
        }

        try {
            $message = trim(
                (string) $data['message']
            );

            $passengerContext =
                $data['context'] ?? [];

            /*
            |--------------------------------------------------------------------------
            | Flutter / Passenger Context
            |--------------------------------------------------------------------------
            */

            $passengerContextText =
                $this->buildPassengerContextText(
                    $passengerContext
                );

            /*
            |--------------------------------------------------------------------------
            | EastBus Database Context
            |--------------------------------------------------------------------------
            |
            | Search:
            |
            | - Routes
            | - Road Way Stops
            | - Booking Stops
            | - Online Trips
            | - Buses
            | - Daily Service Buses
            |
            */

            $databaseContext =
                $this->buildDatabaseContext(
                    $message
                );

            $databaseContextText =
                json_encode(
                    $databaseContext,
                    JSON_PRETTY_PRINT |
                    JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES
                );

            if (!$databaseContextText) {
                $databaseContextText =
                    'No matching EastBus database data was found.';
            }

            /*
            |--------------------------------------------------------------------------
            | Gemini Prompt
            |--------------------------------------------------------------------------
            */

            $prompt = <<<PROMPT
You are EastBus AI Assistant.

You are the official passenger-support AI assistant for EastBus,
a smart transportation platform for private long-distance buses
in Sri Lanka.

============================================================
LANGUAGE
============================================================

Support only:

1. English
2. Tamil
3. Sinhala

Rules:

- English question → reply in English.
- Tamil question → reply in Tamil.
- Sinhala question → reply in Sinhala.
- Tamil + English mixed → reply mainly in Tamil with simple English transport terms.
- Sinhala + English mixed → reply mainly in Sinhala with simple English transport terms.
- Keep answers short, practical, clear and passenger-friendly.

============================================================
MOST IMPORTANT DATABASE RULE
============================================================

The EASTBUS DATABASE CONTEXT below is the primary source for
routes, buses, schedules, booking availability and service information.

STRICT RULES:

1. Never invent a bus.
2. Never invent a route.
3. Never invent a timetable.
4. Never invent a fare.
5. Never invent booking availability.
6. Never invent contact numbers.
7. Never invent a departure or arrival time.
8. Never invent a Daily Service Bus.
9. Never claim a direct service exists unless the database context shows it.
10. Never modify database values.

If matching EastBus database information exists:
- answer using that information first;
- clearly mention matching buses or routes;
- show useful boarding/drop-off times when available;
- tell the passenger whether online booking is available;
- mention Daily Service Bus services separately when relevant.

If multiple buses match:
- provide the best matching options in a short numbered list.

If no direct service is found:
- clearly say no matching direct EastBus service was found;
- if the database contains a route or service that partially matches,
  explain it carefully without pretending it is direct.

Do NOT tell the passenger that you cannot access the database
when EASTBUS DATABASE CONTEXT contains matching records.

============================================================
ONLINE BOOKING
============================================================

Online booking may be available only when:

- the trip is published;
- booking is open;
- the selected stops are approved booking stops;
- the journey satisfies EastBus booking rules.

Maximum seats per booking: 6.

Do not ask the passenger to send:
- password
- OTP
- CVV
- full card number
- API keys
- authentication tokens

============================================================
DAILY SERVICE BUS
============================================================

EastBus may contain Daily Service Bus information.

A Daily Service Bus may provide:

- bus name
- bus number
- road way
- Starting daily service
- Return daily service
- boarding/drop-off stops
- arrival/departure times
- contact numbers

Daily Service Bus information does NOT automatically mean
online seat booking is available.

State this clearly when necessary.

============================================================
ONLINE TRIPS
============================================================

When an online trip is present, useful information may include:

- company
- bus number
- bus type
- route
- service date
- boarding stop
- boarding time
- drop-off stop
- drop-off time
- fare
- booking availability

Only display values present in the database context.

============================================================
PERSONAL PASSENGER DATA
============================================================

Use personal booking/trip information only when it exists in
PASSENGER CONTEXT.

Never expose information belonging to another passenger.

============================================================
LIVE TRACKING
============================================================

Never claim a bus is currently at a particular location unless
live tracking information is explicitly included in PASSENGER CONTEXT.

============================================================
RESPONSE STYLE
============================================================

Prefer responses such as:

"I found 2 EastBus services from Batticaloa to Galle."

Then list only the useful details.

Do not give long explanations unless asked.

============================================================
EASTBUS DATABASE CONTEXT
============================================================

{$databaseContextText}

============================================================
PASSENGER CONTEXT
============================================================

{$passengerContextText}

============================================================
PASSENGER MESSAGE
============================================================

{$message}
PROMPT;

            /*
            |--------------------------------------------------------------------------
            | Gemini Request
            |--------------------------------------------------------------------------
            */

            $response = Http::timeout(30)
                ->retry(
                    1,
                    500
                )
                ->withHeaders([
                    'x-goog-api-key' =>
                        $apiKey,

                    'Content-Type' =>
                        'application/json',
                ])
                ->post(
                    'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent',
                    [
                        'contents' => [
                            [
                                'role' => 'user',

                                'parts' => [
                                    [
                                        'text' =>
                                            $prompt,
                                    ],
                                ],
                            ],
                        ],

                        'generationConfig' => [
                            'temperature' =>
                                0.20,

                            'topP' =>
                                0.85,

                            'maxOutputTokens' =>
                                900,
                        ],
                    ]
                );

            /*
            |--------------------------------------------------------------------------
            | Gemini Error
            |--------------------------------------------------------------------------
            */

            if (!$response->successful()) {
                Log::error(
                    'Gemini API Error',
                    [
                        'status' =>
                            $response->status(),

                        'response' =>
                            $response->body(),
                    ]
                );

                return response()->json([
                    'success' => false,

                    'message' =>
                        'EastBus AI Assistant is temporarily unavailable. Please try again.',
                ], 502);
            }

            /*
            |--------------------------------------------------------------------------
            | Gemini Response
            |--------------------------------------------------------------------------
            */

            $responseData =
                $response->json();

            $reply = data_get(
                $responseData,
                'candidates.0.content.parts.0.text'
            );

            if (
                !is_string($reply) ||
                trim($reply) === ''
            ) {
                Log::warning(
                    'Gemini Empty Response',
                    [
                        'response' =>
                            $responseData,
                    ]
                );

                return response()->json([
                    'success' => false,

                    'message' =>
                        'EastBus AI Assistant did not return a response. Please try again.',
                ], 502);
            }

            /*
            |--------------------------------------------------------------------------
            | Success
            |--------------------------------------------------------------------------
            */

            return response()->json([
                'success' =>
                    true,

                'reply' =>
                    trim($reply),

                'database_match' =>
                    !empty(
                        $databaseContext[
                            'matched_locations'
                        ]
                    ),

                'matched_locations' =>
                    $databaseContext[
                        'matched_locations'
                    ] ?? [],

                'model' =>
                    'gemini-3.6-flash',
            ]);

        } catch (
            \Illuminate\Http\Client\ConnectionException $e
        ) {
            Log::error(
                'Gemini Connection Error',
                [
                    'message' =>
                        $e->getMessage(),
                ]
            );

            return response()->json([
                'success' => false,

                'message' =>
                    'Unable to connect to EastBus AI Assistant. Please try again.',
            ], 503);

        } catch (\Throwable $e) {
            Log::error(
                'Passenger Chatbot Error',
                [
                    'message' =>
                        $e->getMessage(),

                    'file' =>
                        $e->getFile(),

                    'line' =>
                        $e->getLine(),

                    'trace' =>
                        $e->getTraceAsString(),
                ]
            );

            return response()->json([
                'success' => false,

                'message' =>
                    'Unable to process your message right now.',
            ], 500);
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