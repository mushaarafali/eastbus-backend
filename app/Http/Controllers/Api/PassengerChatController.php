<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
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

        try {
            $message = trim(
                (string) $data['message']
            );

            $passengerContext =
                $data['context'] ?? [];

            /*
             * 1. EastBus database first.
             */
            $databaseContext =
                $this->buildDatabaseContext(
                    $message
                );

            if (
                $this->hasDatabaseMatch(
                    $databaseContext
                )
            ) {
                return response()->json([
                    'success' => true,

                    'reply' =>
                        $this->buildDirectDatabaseReply(
                            $databaseContext
                        ),

                    'source' =>
                        'eastbus_database',

                    'database_match' =>
                        true,

                    'matched_locations' =>
                        $databaseContext[
                            'matched_locations'
                        ] ?? [],
                ]);
            }

            /*
             * 2. Gemini only as fallback.
             */
            $apiKey =
                config(
                    'services.gemini.key'
                );

            if (empty($apiKey)) {
                return response()->json([
                    'success' => true,

                    'reply' =>
                        $this->buildNoDatabaseMatchReply(
                            $databaseContext
                        ),

                    'source' =>
                        'eastbus_database_fallback',

                    'database_match' =>
                        false,

                    'matched_locations' =>
                        $databaseContext[
                            'matched_locations'
                        ] ?? [],
                ]);
            }

            $passengerContextText =
                $this->buildPassengerContextText(
                    $passengerContext
                );

            $prompt = <<<PROMPT
You are EastBus AI Assistant, the official passenger-support assistant for EastBus in Sri Lanka.

Reply only in English, Tamil, or Sinhala based on the passenger's language.
Keep answers short, clear, practical, and passenger-friendly.

Important rules:
- EastBus database did not return a matching direct service for this query.
- Never invent buses, routes, fares, schedules, booking availability, booking references, passenger details, live locations, or trip statuses.
- If the passenger asks about a route or bus that is not in EastBus data, clearly say no matching EastBus service was found.
- You may explain how to use Search Buses, booking, tickets, tracking, payments, feedback, login, or OTP features.
- Never ask for passwords, OTPs, CVV, full card numbers, API keys, or tokens.
- Use personal booking or trip details only if they appear in PASSENGER CONTEXT.

PASSENGER CONTEXT:
{$passengerContextText}

PASSENGER MESSAGE:
{$message}
PROMPT;

            $response =
                Http::timeout(18)
                    ->retry(
                        1,
                        700,
                        throw: false
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
                            'contents' => [[
                                'role' => 'user',

                                'parts' => [[
                                    'text' => $prompt,
                                ]],
                            ]],

                            'generationConfig' => [
                                'temperature' => 0.20,
                                'topP' => 0.85,
                                'maxOutputTokens' => 500,
                            ],
                        ]
                    );

            if (!$response->successful()) {
                Log::warning(
                    'Gemini API Fallback Error',
                    [
                        'status' =>
                            $response->status(),

                        'response' =>
                            $response->body(),
                    ]
                );

                return response()->json([
                    'success' => true,

                    'reply' =>
                        $this->buildNoDatabaseMatchReply(
                            $databaseContext
                        ),

                    'source' =>
                        'eastbus_database_fallback',

                    'database_match' =>
                        false,

                    'gemini_status' =>
                        $response->status(),

                    'matched_locations' =>
                        $databaseContext[
                            'matched_locations'
                        ] ?? [],
                ]);
            }

            $reply =
                data_get(
                    $response->json(),
                    'candidates.0.content.parts.0.text'
                );

            if (
                !is_string($reply)
                ||
                trim($reply) === ''
            ) {
                return response()->json([
                    'success' => true,

                    'reply' =>
                        $this->buildNoDatabaseMatchReply(
                            $databaseContext
                        ),

                    'source' =>
                        'eastbus_database_fallback',

                    'database_match' =>
                        false,

                    'matched_locations' =>
                        $databaseContext[
                            'matched_locations'
                        ] ?? [],
                ]);
            }

            return response()->json([
                'success' => true,

                'reply' =>
                    trim($reply),

                'source' =>
                    'gemini',

                'database_match' =>
                    false,

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
            Log::warning(
                'Gemini Connection Error',
                [
                    'message' =>
                        $e->getMessage(),
                ]
            );

            return response()->json([
                'success' => true,

                'reply' =>
                    'I could not find a matching EastBus service in the database right now. Please use Search Buses in the EastBus Passenger App to check the latest available services.',

                'source' =>
                    'eastbus_database_fallback',

                'database_match' =>
                    false,
            ]);

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
    | Database Match
    |--------------------------------------------------------------------------
    */

    private function hasDatabaseMatch(
        array $context
    ): bool {
        return
            !empty(
                $context[
                    'online_trips'
                ] ?? []
            )
            ||
            !empty(
                $context[
                    'daily_service_buses'
                ] ?? []
            )
            ||
            !empty(
                $context[
                    'routes'
                ] ?? []
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Direct Database Reply
    |--------------------------------------------------------------------------
    */

    private function buildDirectDatabaseReply(
        array $context
    ): string {
        $locations =
            $context[
                'matched_locations'
            ] ?? [];

        $origin =
            $locations[0]
            ??
            'selected origin';

        $destination =
            $locations[1]
            ??
            'selected destination';

        $onlineTrips =
            $context[
                'online_trips'
            ] ?? [];

        $dailyServices =
            $context[
                'daily_service_buses'
            ] ?? [];

        $routes =
            $context[
                'routes'
            ] ?? [];

        $lines = [];

        $serviceCount =
            count($onlineTrips)
            +
            count($dailyServices);

        if ($serviceCount > 0) {
            $lines[] =
                "I found {$serviceCount} EastBus service"
                .
                (
                    $serviceCount === 1
                        ? ''
                        : 's'
                )
                .
                " from {$origin} to {$destination}.";

        } elseif (!empty($routes)) {
            $lines[] =
                "I found an EastBus route from {$origin} to {$destination}, but there is no currently published passenger service for it.";
        }

        /*
         * Online Trips
         */
        foreach (
            array_slice(
                $onlineTrips,
                0,
                5
            )
            as $i => $trip
        ) {
            $name =
                trim(
                    (string)
                    (
                        $trip[
                            'company_name'
                        ]
                        ??
                        $trip[
                            'bus_name'
                        ]
                        ??
                        'EastBus Service'
                    )
                );

            $number =
                trim(
                    (string)
                    (
                        $trip[
                            'bus_number'
                        ]
                        ??
                        ''
                    )
                );

            $heading =
                ($i + 1)
                .
                '. '
                .
                $name
                .
                (
                    $number !== ''
                        ? " ({$number})"
                        : ''
                );

            $lines[] =
                $heading;

            if (
                !empty(
                    $trip[
                        'service_date'
                    ]
                )
            ) {
                $lines[] =
                    '   Date: '
                    .
                    $trip[
                        'service_date'
                    ];
            }

            $boarding =
                '   Boarding: '
                .
                (
                    $trip[
                        'boarding_stop'
                    ]
                    ??
                    $origin
                );

            if (
                !empty(
                    $trip[
                        'boarding_time'
                    ]
                )
            ) {
                $boarding .=
                    ' - '
                    .
                    $this->formatTime(
                        $trip[
                            'boarding_time'
                        ]
                    );
            }

            $lines[] =
                $boarding;

            $dropoff =
                '   Drop-off: '
                .
                (
                    $trip[
                        'dropoff_stop'
                    ]
                    ??
                    $destination
                );

            if (
                !empty(
                    $trip[
                        'dropoff_time'
                    ]
                )
            ) {
                $dropoff .=
                    ' - '
                    .
                    $this->formatTime(
                        $trip[
                            'dropoff_time'
                        ]
                    );
            }

            $lines[] =
                $dropoff;

            if (
                isset(
                    $trip['fare']
                )
                &&
                $trip['fare']
                !==
                null
            ) {
                $lines[] =
                    '   Fare: Rs. '
                    .
                    number_format(
                        (float)
                        $trip['fare'],
                        2
                    );
            }

            $lines[] =
                '   Online Booking: '
                .
                (
                    !empty(
                        $trip[
                            'booking_available'
                        ]
                    )
                        ? 'Available'
                        : 'Not Available'
                );
        }

        /*
         * Daily Service Buses
         */
        if (!empty($dailyServices)) {
            if (!empty($onlineTrips)) {
                $lines[] = '';
            }

            $lines[] =
                'Daily Service Bus:';

            foreach (
                array_slice(
                    $dailyServices,
                    0,
                    5
                )
                as $i => $service
            ) {
                $name =
                    trim(
                        (string)
                        (
                            $service[
                                'bus_name'
                            ]
                            ??
                            'Daily Service Bus'
                        )
                    );

                $number =
                    trim(
                        (string)
                        (
                            $service[
                                'bus_number'
                            ]
                            ??
                            ''
                        )
                    );

                $lines[] =
                    ($i + 1)
                    .
                    '. '
                    .
                    $name
                    .
                    (
                        $number !== ''
                            ? " ({$number})"
                            : ''
                    );

                $boarding =
                    '   Boarding: '
                    .
                    (
                        $service[
                            'boarding_stop'
                        ]
                        ??
                        $origin
                    );

                if (
                    !empty(
                        $service[
                            'boarding_time'
                        ]
                    )
                ) {
                    $boarding .=
                        ' - '
                        .
                        $this->formatTime(
                            $service[
                                'boarding_time'
                            ]
                        );
                }

                $lines[] =
                    $boarding;

                $dropoff =
                    '   Drop-off: '
                    .
                    (
                        $service[
                            'dropoff_stop'
                        ]
                        ??
                        $destination
                    );

                if (
                    !empty(
                        $service[
                            'dropoff_time'
                        ]
                    )
                ) {
                    $dropoff .=
                        ' - '
                        .
                        $this->formatTime(
                            $service[
                                'dropoff_time'
                            ]
                        );
                }

                $lines[] =
                    $dropoff;

                $lines[] =
                    '   Online Booking: Not Available';
            }
        }

        if (
            empty($onlineTrips)
            &&
            empty($dailyServices)
            &&
            !empty($routes)
        ) {
            $lines[] =
                'Please use Search Buses in the EastBus Passenger App to check future published trips.';
        }

        return implode(
            "\n",
            $lines
        );
    }

    /*
    |--------------------------------------------------------------------------
    | No Database Match Reply
    |--------------------------------------------------------------------------
    */

    private function buildNoDatabaseMatchReply(
        array $context
    ): string {
        $locations =
            $context[
                'matched_locations'
            ] ?? [];

        if (
            count($locations)
            >=
            2
        ) {
            return
                'I could not find a matching EastBus service from '
                .
                $locations[0]
                .
                ' to '
                .
                $locations[1]
                .
                '. Please use Search Buses in the EastBus Passenger App to check the latest available services.';
        }

        return
            'I could not find matching EastBus route or bus information for that question. Please use Search Buses in the EastBus Passenger App or ask with both origin and destination.';
    }

    /*
    |--------------------------------------------------------------------------
    | Format Time
    |--------------------------------------------------------------------------
    */

    private function formatTime(
        $value
    ): string {
        $text =
            trim(
                (string)
                $value
            );

        if ($text === '') {
            return '';
        }

        try {
            return Carbon::parse(
                $text
            )->format(
                'h:i A'
            );

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

        if (
            count(
                $matchedLocations
            )
            <
            2
        ) {
            return $context;
        }

        $origin =
            $matchedLocations[0];

        $destination =
            $matchedLocations[1];

        /*
         * Matching Master Routes.
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

                        'route_number' =>
                            $route->route_number
                            ??
                            null,

                        'route_name' =>
                            $route->name
                            ??
                            null,

                        'origin' =>
                            $route->origin
                            ??
                            null,

                        'destination' =>
                            $route->destination
                            ??
                            null,

                        'distance_km' =>
                            isset(
                                $route->distance_km
                            )
                                ? (float)
                                    $route
                                        ->distance_km
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
         * Online trips.
         */
        if (
            $routeIds
                ->isNotEmpty()
        ) {
            $context[
                'online_trips'
            ] =
                $this->findOnlineTrips(
                    $routeIds,
                    $origin,
                    $destination
                );
        }

        /*
         * Daily services.
         */
        $context[
            'daily_service_buses'
        ] =
            $this->findDailyServices(
                $origin,
                $destination
            );

        return $context;
    }

    /*
    |--------------------------------------------------------------------------
    | Detect Locations
    |--------------------------------------------------------------------------
    |
    | Location names now come from route_stops only.
    |
    | stop_name duplication is no longer needed in fixed_service_stops.
    |
    */

    private function detectLocations(
        string $message
    ): array {
        $messageLower =
            mb_strtolower(
                $message
            );

        if (
            !Schema::hasTable(
                'route_stops'
            )
        ) {
            return [];
        }

        $names =
            DB::table(
                'route_stops'
            )
                ->whereNotNull(
                    'name'
                )
                ->pluck(
                    'name'
                )
                ->filter()
                ->map(
                    fn ($name) =>
                        trim(
                            (string)
                            $name
                        )
                )
                ->filter()
                ->unique(
                    fn ($name) =>
                        mb_strtolower(
                            $name
                        )
                )
                ->sortByDesc(
                    fn ($name) =>
                        mb_strlen(
                            $name
                        )
                )
                ->values();

        $matches = [];

        foreach (
            $names
            as
            $name
        ) {
            $needle =
                mb_strtolower(
                    $name
                );

            $position =
                mb_strpos(
                    $messageLower,
                    $needle
                );

            if (
                $position
                ===
                false
            ) {
                continue;
            }

            $matches[] = [
                'name' =>
                    $name,

                'position' =>
                    $position,
            ];
        }

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
                    mb_strtolower(
                        $name
                    )
            )
            ->take(2)
            ->values()
            ->all();
    }

    /*
    |--------------------------------------------------------------------------
    | Find Matching Master Routes
    |--------------------------------------------------------------------------
    */

    private function findMatchingRoutes(
        string $origin,
        string $destination
    ): Collection {
        if (
            !Schema::hasTable(
                'routes'
            )
            ||
            !Schema::hasTable(
                'route_stops'
            )
        ) {
            return collect();
        }

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

        if (
            $routeIds->isEmpty()
        ) {
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
                        true
                    )
            )
            ->orderBy(
                'route_number'
            )
            ->limit(20)
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | Find Published Online Trips
    |--------------------------------------------------------------------------
    |
    | Each trip now uses its exact fixed_service_id.
    |
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
                ->join(
                    'routes',
                    'routes.id',
                    '=',
                    'trips.route_id'
                )
                ->join(
                    'buses',
                    'buses.id',
                    '=',
                    'trips.bus_id'
                )
                ->leftJoin(
                    'fixed_services',
                    'fixed_services.id',
                    '=',
                    'trips.fixed_service_id'
                )
                ->leftJoin(
                    'operators',
                    'operators.id',
                    '=',
                    'trips.operator_id'
                )
                ->whereIn(
                    'trips.route_id',
                    $routeIds
                )
                ->whereNotNull(
                    'trips.fixed_service_id'
                );

        if (
            Schema::hasColumn(
                'trips',
                'is_published'
            )
        ) {
            $query->where(
                'trips.is_published',
                true
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

        $trips =
            $query
                ->select(
                    'trips.id',
                    'trips.route_id',
                    'trips.fixed_service_id',
                    'trips.bus_id',
                    'trips.trip_code',
                    'trips.service_date',
                    'trips.departure_time',
                    'trips.arrival_time',
                    'trips.status',
                    'trips.trip_type',
                    'trips.fare',
                    'trips.is_published',

                    'routes.route_number',
                    'routes.origin',
                    'routes.destination',

                    'buses.bus_number',
                    'buses.bus_name',
                    'buses.bus_type',

                    'fixed_services.service_name',
                    'fixed_services.is_active as service_active',

                    'operators.company_name'
                )
                ->orderBy(
                    'trips.service_date'
                )
                ->orderBy(
                    'trips.departure_time'
                )
                ->limit(20)
                ->get();

        return $trips
            ->map(
                function (
                    $trip
                ) use (
                    $origin,
                    $destination
                ) {
                    if (
                        !(bool)
                        $trip->service_active
                    ) {
                        return null;
                    }

                    $times =
                        $this->findServiceStopTimes(
                            (int)
                            $trip->fixed_service_id,

                            $origin,

                            $destination,

                            $trip->trip_type
                            ??
                            'starting'
                        );

                    if (
                        !$times[
                            'valid'
                        ]
                    ) {
                        return null;
                    }

                    return [
                        'trip_id' =>
                            $trip->id,

                        'fixed_service_id' =>
                            $trip
                                ->fixed_service_id,

                        'route_id' =>
                            $trip->route_id,

                        'route_number' =>
                            $trip->route_number,

                        'trip_code' =>
                            $trip->trip_code
                            ??
                            null,

                        'service_name' =>
                            $trip->service_name
                            ??
                            null,

                        'company_name' =>
                            $trip->company_name
                            ??
                            null,

                        'bus_name' =>
                            $trip->bus_name
                            ??
                            null,

                        'bus_number' =>
                            $trip->bus_number
                            ??
                            null,

                        'bus_type' =>
                            $trip->bus_type
                            ??
                            null,

                        'service_date' =>
                            $trip->service_date
                            ??
                            null,

                        'direction' =>
                            $trip->trip_type
                            ??
                            'starting',

                        'status' =>
                            $trip->status
                            ??
                            null,

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
                            isset(
                                $trip->fare
                            )
                                ? (float)
                                    $trip->fare
                                : null,

                        'booking_available' =>
                            (bool)
                            (
                                $trip
                                    ->is_published
                                ??
                                false
                            ),
                    ];
                }
            )
            ->filter()
            ->values()
            ->all();
    }

    /*
    |--------------------------------------------------------------------------
    | Fixed Service Stop Times
    |--------------------------------------------------------------------------
    |
    | Uses the current Fixed Service Stop structure.
    |
    */

    private function findServiceStopTimes(
        int $fixedServiceId,
        string $origin,
        string $destination,
        string $direction
    ): array {
        $result = [
            'valid' =>
                false,

            'boarding_time' =>
                null,

            'dropoff_time' =>
                null,
        ];

        if (
            !Schema::hasTable(
                'fixed_service_stops'
            )
            ||
            !Schema::hasTable(
                'route_stops'
            )
        ) {
            return $result;
        }

        $rows =
            DB::table(
                'fixed_service_stops as fss'
            )
                ->join(
                    'route_stops as rs',
                    'rs.id',
                    '=',
                    'fss.route_stop_id'
                )
                ->where(
                    'fss.fixed_service_id',
                    $fixedServiceId
                )
                ->where(
                    'fss.direction',
                    $direction
                )
                ->orderBy(
                    'fss.stop_order'
                )
                ->select(
                    'fss.id',
                    'fss.route_stop_id',
                    'fss.stop_order',
                    'fss.arrival_time',
                    'fss.departure_time',
                    'fss.boarding_allowed',
                    'fss.dropoff_allowed',
                    'rs.name'
                )
                ->get()
                ->values();

        $originIndex =
            $rows->search(
                fn ($row) =>
                    $this->sameStopName(
                        (string)
                        $row->name,

                        $origin
                    )
            );

        $destinationIndex =
            $rows->search(
                fn ($row) =>
                    $this->sameStopName(
                        (string)
                        $row->name,

                        $destination
                    )
            );

        if (
            $originIndex === false
            ||
            $destinationIndex === false
            ||
            $originIndex >=
            $destinationIndex
        ) {
            return $result;
        }

        $boarding =
            $rows[
                $originIndex
            ];

        $dropoff =
            $rows[
                $destinationIndex
            ];

        if (
            !(bool)
            $boarding->boarding_allowed
            ||
            !(bool)
            $dropoff->dropoff_allowed
        ) {
            return $result;
        }

        return [
            'valid' =>
                true,

            'boarding_time' =>
                $boarding
                    ->departure_time
                ??
                $boarding
                    ->arrival_time,

            'dropoff_time' =>
                $dropoff
                    ->arrival_time
                ??
                $dropoff
                    ->departure_time,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Daily Service Buses
    |--------------------------------------------------------------------------
    |
    | fixed_services already represents operator/bus-specific services.
    |
    */

    private function findDailyServices(
        string $origin,
        string $destination
    ): array {
        if (
            !Schema::hasTable(
                'fixed_services'
            )
            ||
            !Schema::hasTable(
                'fixed_service_stops'
            )
            ||
            !Schema::hasTable(
                'route_stops'
            )
        ) {
            return [];
        }

        $services =
            DB::table(
                'fixed_services as fs'
            )
                ->join(
                    'routes as r',
                    'r.id',
                    '=',
                    'fs.route_id'
                )
                ->join(
                    'buses as b',
                    'b.id',
                    '=',
                    'fs.bus_id'
                )
                ->leftJoin(
                    'operators as o',
                    'o.id',
                    '=',
                    'fs.operator_id'
                )
                ->where(
                    'fs.is_active',
                    true
                )
                ->where(
                    'fs.is_published',
                    true
                )
                ->where(
                    'r.is_active',
                    true
                )
                ->where(
                    'b.is_active',
                    true
                )
                ->select(
                    'fs.id',
                    'fs.route_id',
                    'fs.bus_id',
                    'fs.service_name',
                    'fs.starting_time',
                    'fs.return_time',

                    'r.route_number',
                    'r.origin',
                    'r.destination',

                    'b.bus_number',
                    'b.bus_name',
                    'b.bus_type',

                    'o.company_name'
                )
                ->orderBy(
                    'r.route_number'
                )
                ->limit(30)
                ->get();

        return $services
            ->map(
                function (
                    $service
                ) use (
                    $origin,
                    $destination
                ) {
                    $matchingDirection =
                        $this->findFixedServiceDirection(
                            (int)
                            $service->id,

                            $origin,

                            $destination
                        );

                    if (
                        $matchingDirection[
                            'direction'
                        ]
                        ===
                        null
                    ) {
                        return null;
                    }

                    return [
                        'service_id' =>
                            $service->id,

                        'service_type' =>
                            'Daily Service Bus',

                        'service_name' =>
                            $service
                                ->service_name,

                        'route_number' =>
                            $service
                                ->route_number,

                        'company_name' =>
                            $service
                                ->company_name,

                        'bus_name' =>
                            $service
                                ->bus_name,

                        'bus_number' =>
                            $service
                                ->bus_number,

                        'bus_type' =>
                            $service
                                ->bus_type,

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

                        'online_booking_available' =>
                            false,
                    ];
                }
            )
            ->filter()
            ->values()
            ->all();
    }

    /*
    |--------------------------------------------------------------------------
    | Fixed Service Direction
    |--------------------------------------------------------------------------
    */

    private function findFixedServiceDirection(
        int $fixedServiceId,
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
            $stops =
                DB::table(
                    'fixed_service_stops as fss'
                )
                    ->join(
                        'route_stops as rs',
                        'rs.id',
                        '=',
                        'fss.route_stop_id'
                    )
                    ->where(
                        'fss.fixed_service_id',
                        $fixedServiceId
                    )
                    ->where(
                        'fss.direction',
                        $direction
                    )
                    ->orderBy(
                        'fss.stop_order'
                    )
                    ->select(
                        'fss.stop_order',
                        'fss.arrival_time',
                        'fss.departure_time',
                        'fss.boarding_allowed',
                        'fss.dropoff_allowed',
                        'rs.name'
                    )
                    ->get()
                    ->values();

            $originIndex =
                $stops->search(
                    fn ($stop) =>
                        $this->sameStopName(
                            (string)
                            $stop->name,

                            $origin
                        )
                );

            $destinationIndex =
                $stops->search(
                    fn ($stop) =>
                        $this->sameStopName(
                            (string)
                            $stop->name,

                            $destination
                        )
                );

            if (
                $originIndex === false
                ||
                $destinationIndex === false
                ||
                $originIndex >=
                $destinationIndex
            ) {
                continue;
            }

            $boarding =
                $stops[
                    $originIndex
                ];

            $dropoff =
                $stops[
                    $destinationIndex
                ];

            if (
                !(bool)
                $boarding
                    ->boarding_allowed
                ||
                !(bool)
                $dropoff
                    ->dropoff_allowed
            ) {
                continue;
            }

            return [
                'direction' =>
                    $direction,

                'boarding_time' =>
                    $boarding
                        ->departure_time
                    ??
                    $boarding
                        ->arrival_time,

                'dropoff_time' =>
                    $dropoff
                        ->arrival_time
                    ??
                    $dropoff
                        ->departure_time,
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
    | Stop Name Helper
    |--------------------------------------------------------------------------
    */

    private function sameStopName(
        string $a,
        string $b
    ): bool {
        return
            mb_strtolower(
                trim($a)
            )
            ===
            mb_strtolower(
                trim($b)
            );
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
            'fixed_service_id',
            'service_name',
            'route_number',
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

        if (
            empty(
                $safeContext
            )
        ) {
            return
                'No usable personal EastBus context was provided.';
        }

        return
            json_encode(
                $safeContext,

                JSON_PRETTY_PRINT
                |
                JSON_UNESCAPED_UNICODE
                |
                JSON_UNESCAPED_SLASHES
            )
            ?:
            'No usable personal EastBus context was provided.';
    }
}