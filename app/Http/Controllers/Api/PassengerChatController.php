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
    private const TIMEZONE = 'Asia/Colombo';

    public function chat(Request $request)
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:1500'],
            'language' => ['nullable', 'string', 'in:en,ta,si'],
            'context' => ['nullable', 'array'],
        ]);

        $message = trim((string) $data['message']);
        $language = $this->resolveLanguage($message, $data['language'] ?? null);
        $passengerContext = $data['context'] ?? [];

        try {
            $matchedLocations = $this->detectLocations($message);

            if (count($matchedLocations) < 2) {
                $normalized = $this->normalizeLocationsWithGemini($message, $language);

                if (count($normalized) >= 2) {
                    $matchedLocations = $normalized;
                }
            }

            $databaseContext = $this->buildDatabaseContext($message, $matchedLocations);

            if ($this->hasDatabaseMatch($databaseContext)) {
                return response()->json([
                    'success' => true,
                    'reply' => $this->buildDirectDatabaseReply($databaseContext, $language),
                    'language' => $language,
                    'source' => 'eastbus_database',
                    'database_match' => true,
                    'matched_locations' => $databaseContext['matched_locations'] ?? [],
                ]);
            }

            if ($this->geminiApiKey() === '') {
                return response()->json([
                    'success' => true,
                    'reply' => $this->buildNoDatabaseMatchReply($databaseContext, $language),
                    'language' => $language,
                    'source' => 'eastbus_database_fallback',
                    'database_match' => false,
                    'matched_locations' => $databaseContext['matched_locations'] ?? [],
                ]);
            }

            $reply = $this->askGemini(
                $message,
                $language,
                $databaseContext,
                $passengerContext
            );

            if ($reply === null) {
                return response()->json([
                    'success' => true,
                    'reply' => $this->buildNoDatabaseMatchReply($databaseContext, $language),
                    'language' => $language,
                    'source' => 'eastbus_database_fallback',
                    'database_match' => false,
                    'matched_locations' => $databaseContext['matched_locations'] ?? [],
                ]);
            }

            return response()->json([
                'success' => true,
                'reply' => $reply,
                'language' => $language,
                'source' => 'gemini',
                'database_match' => false,
                'matched_locations' => $databaseContext['matched_locations'] ?? [],
                'model' => $this->geminiModel(),
            ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning('Passenger Chat Gemini Connection Error', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => true,
                'reply' => $this->genericTemporaryReply($language),
                'language' => $language,
                'source' => 'eastbus_database_fallback',
                'database_match' => false,
            ]);
        } catch (\Throwable $e) {
            Log::error('Passenger Chatbot Error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $this->errorMessage($language),
            ], 500);
        }
    }

    private function resolveLanguage(string $message, ?string $requestedLanguage): string
    {
        if (in_array($requestedLanguage, ['ta', 'si'], true)) {
            return $requestedLanguage;
        }

        if (preg_match('/[\x{0B80}-\x{0BFF}]/u', $message)) {
            return 'ta';
        }

        if (preg_match('/[\x{0D80}-\x{0DFF}]/u', $message)) {
            return 'si';
        }

        return 'en';
    }

    private function languageInstruction(string $language): string
    {
        return match ($language) {
            'ta' => 'Reply completely in natural and simple Tamil. Bus names, route numbers, town names, times, booking references and EastBus technical terms may remain in English.',
            'si' => 'Reply completely in natural and simple Sinhala. Bus names, route numbers, town names, times, booking references and EastBus technical terms may remain in English.',
            default => 'Reply in clear and simple English.',
        };
    }

    private function geminiApiKey(): string
    {
        return trim((string) config('services.gemini.key', ''));
    }

    private function geminiModel(): string
    {
        return trim((string) config('services.gemini.model', 'gemini-2.5-flash'));
    }

    private function geminiUrl(): string
    {
        return 'https://generativelanguage.googleapis.com/v1beta/models/' .
            rawurlencode($this->geminiModel()) .
            ':generateContent';
    }

    private function askGemini(
        string $message,
        string $language,
        array $databaseContext,
        array $passengerContext
    ): ?string {
        $apiKey = $this->geminiApiKey();

        if ($apiKey === '') {
            return null;
        }

        $locationText = empty($databaseContext['matched_locations'] ?? [])
            ? 'No confirmed origin/destination were matched.'
            : json_encode(
                $databaseContext['matched_locations'],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );

        $passengerContextText = $this->buildPassengerContextText($passengerContext);
        $languageInstruction = $this->languageInstruction($language);

        $prompt = <<<PROMPT
You are EastBus AI Assistant, the official passenger-support assistant for EastBus.lk.

LANGUAGE:
{$languageInstruction}

RULES:
1. Never invent a bus, route, fare, schedule, seat availability, booking, payment, trip status or GPS location.
2. EastBus database information is the source of truth for bus services and operational information.
3. If live or service information cannot be confirmed, clearly say so.
4. You may explain Passenger App features such as registration, email OTP verification, login, bus search, trip details, seat selection, passenger details, demonstration payment, QR e-ticket, tracking, notifications, feedback and EastBus AI Assistant.
5. Live tracking is only for an eligible passenger with a valid paid booking while the trip is active.
6. Never request passwords, OTP codes, CVV numbers, full card numbers, API keys or authentication tokens.
7. Answer in the requested language even when database values are English.
8. Keep the answer practical and reasonably short.
9. Do not invent prices, departure times or arrival times.
10. Do not claim that a bus service exists unless EastBus data confirms it.

MATCHED EASTBUS LOCATIONS:
{$locationText}

SAFE PASSENGER CONTEXT:
{$passengerContextText}

PASSENGER MESSAGE:
{$message}
PROMPT;

        $response = Http::timeout(20)
            ->retry(1, 700, throw: false)
            ->withHeaders([
                'x-goog-api-key' => $apiKey,
                'Content-Type' => 'application/json',
            ])
            ->post($this->geminiUrl(), [
                'contents' => [[
                    'role' => 'user',
                    'parts' => [['text' => $prompt]],
                ]],
                'generationConfig' => [
                    'temperature' => 0.20,
                    'topP' => 0.85,
                    'maxOutputTokens' => 600,
                ],
            ]);

        if (!$response->successful()) {
            Log::warning('Passenger Chat Gemini API Error', [
                'model' => $this->geminiModel(),
                'status' => $response->status(),
                'response' => mb_substr($response->body(), 0, 2000),
            ]);

            return null;
        }

        $reply = data_get($response->json(), 'candidates.0.content.parts.0.text');

        return is_string($reply) && trim($reply) !== ''
            ? trim($reply)
            : null;
    }

    private function detectLocations(string $message): array
    {
        $messageLower = mb_strtolower($message);
        $matches = [];

        foreach ($this->allKnownLocationNames() as $name) {
            $position = mb_strpos($messageLower, mb_strtolower($name));

            if ($position !== false) {
                $matches[] = [
                    'name' => $name,
                    'position' => $position,
                ];
            }
        }

        foreach ($this->locationAliases() as $alias => $canonical) {
            $position = mb_strpos($messageLower, mb_strtolower($alias));

            if ($position === false) {
                continue;
            }

            $canonicalName = $this->canonicalLocationName($canonical);

            if ($canonicalName !== null) {
                $matches[] = [
                    'name' => $canonicalName,
                    'position' => $position,
                ];
            }
        }

        usort($matches, fn ($a, $b) => $a['position'] <=> $b['position']);

        return collect($matches)
            ->pluck('name')
            ->unique(fn ($name) => mb_strtolower($name))
            ->take(2)
            ->values()
            ->all();
    }

    private function allKnownLocationNames(): Collection
    {
        $names = collect();

        if (Schema::hasTable('route_stops')) {
            $names = $names->merge(
                DB::table('route_stops')
                    ->whereNotNull('name')
                    ->pluck('name')
            );
        }

        if (Schema::hasTable('fixed_service_stops') &&
            Schema::hasColumn('fixed_service_stops', 'stop_name')) {
            $names = $names->merge(
                DB::table('fixed_service_stops')
                    ->whereNotNull('stop_name')
                    ->pluck('stop_name')
            );
        }

        if (Schema::hasTable('locations')) {
            $names = $names->merge(
                DB::table('locations')
                    ->whereNotNull('name')
                    ->pluck('name')
            );
        }

        return $names
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique(fn ($name) => mb_strtolower($name))
            ->sortByDesc(fn ($name) => mb_strlen($name))
            ->values();
    }

    private function locationAliases(): array
    {
        return [
            'கல்முனை' => 'Kalmunai',
            'கல்முனையில்' => 'Kalmunai',
            'கல்முனையிலிருந்து' => 'Kalmunai',
            'அக்கரைப்பற்று' => 'Akkaraipattu',
            'அக்கரைப்பற்றில்' => 'Akkaraipattu',
            'அக்கரைப்பற்றிலிருந்து' => 'Akkaraipattu',
            'மட்டக்களப்பு' => 'Batticaloa',
            'மட்டக்களப்பில்' => 'Batticaloa',
            'மட்டக்களப்பிலிருந்து' => 'Batticaloa',
            'திருகோணமலை' => 'Trincomalee',
            'திருகோணமலையில்' => 'Trincomalee',
            'திருகோணமலையிலிருந்து' => 'Trincomalee',
            'கொழும்பு' => 'Colombo',
            'கொழும்பில்' => 'Colombo',
            'கொழும்பிலிருந்து' => 'Colombo',
            'கண்டி' => 'Kandy',
            'கண்டியில்' => 'Kandy',
            'காத்தான்குடி' => 'Kattankudy',
            'காத்தான்குடியில்' => 'Kattankudy',
            'பொத்துவில்' => 'Pottuvil',
            'பொத்துவிலில்' => 'Pottuvil',
            'அம்பாறை' => 'Ampara',
            'அம்பாரை' => 'Ampara',
            'ஏறாவூர்' => 'Eravur',
            'செங்கலடி' => 'Chenkalady',
            'கலுவாஞ்சிக்குடி' => 'Kaluwanchikudy',
            'ஆரையம்பதி' => 'Arayampathy',
            'யாழ்ப்பாணம்' => 'Jaffna',
            'காலி' => 'Galle',

            'කල්මුණේ' => 'Kalmunai',
            'අක්කරපත්තුව' => 'Akkaraipattu',
            'මඩකලපුව' => 'Batticaloa',
            'ත්‍රිකුණාමලය' => 'Trincomalee',
            'කොළඹ' => 'Colombo',
            'මහනුවර' => 'Kandy',
            'අම්පාර' => 'Ampara',
            'යාපනය' => 'Jaffna',
            'ගාල්ල' => 'Galle',
            'පොතුවිල්' => 'Pottuvil',
        ];
    }

    private function normalizeLocationsWithGemini(string $message, string $language): array
    {
        $apiKey = $this->geminiApiKey();

        if ($apiKey === '') {
            return [];
        }

        $knownLocations = $this->allKnownLocationNames()->take(400)->all();

        if (empty($knownLocations)) {
            return [];
        }

        try {
            $knownLocationJson = json_encode(
                $knownLocations,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );

            $prompt = <<<PROMPT
Extract the passenger's origin and destination from this Sri Lankan bus message.

The message may be English, Tamil, Sinhala or transliterated text.

Convert place names only to canonical English EastBus location names from the provided list.

Rules:
- Do not invent a place.
- Understand Tamil and Sinhala grammar such as "from", "to", "இலிருந்து", "வரை", "සිට", "දක්වා".
- Understand informal mixed-language messages.
- Return ONLY JSON.
- No markdown.

Format:
{"origin":"Location or null","destination":"Location or null"}

KNOWN EASTBUS LOCATIONS:
{$knownLocationJson}

Selected language:
{$language}

Passenger message:
{$message}
PROMPT;

            $response = Http::timeout(15)
                ->retry(1, 500, throw: false)
                ->withHeaders([
                    'x-goog-api-key' => $apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($this->geminiUrl(), [
                    'contents' => [[
                        'role' => 'user',
                        'parts' => [['text' => $prompt]],
                    ]],
                    'generationConfig' => [
                        'temperature' => 0,
                        'maxOutputTokens' => 120,
                        'responseMimeType' => 'application/json',
                    ],
                ]);

            if (!$response->successful()) {
                Log::warning('Gemini Location Normalization Error', [
                    'status' => $response->status(),
                    'response' => mb_substr($response->body(), 0, 1500),
                ]);

                return [];
            }

            $text = data_get($response->json(), 'candidates.0.content.parts.0.text');

            if (!is_string($text) || trim($text) === '') {
                return [];
            }

            $json = $this->extractJson($text);

            if (!$json) {
                return [];
            }

            $locations = [];

            foreach (['origin', 'destination'] as $key) {
                $candidate = trim((string) ($json[$key] ?? ''));

                if ($candidate === '' || strtolower($candidate) === 'null') {
                    continue;
                }

                $canonical = $this->canonicalLocationName($candidate);

                if ($canonical !== null) {
                    $locations[] = $canonical;
                }
            }

            return collect($locations)
                ->unique(fn ($name) => mb_strtolower($name))
                ->take(2)
                ->values()
                ->all();
        } catch (\Throwable $e) {
            Log::warning('Gemini Location Normalization Failed', [
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function extractJson(string $text): ?array
    {
        $text = trim($text);
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);
        $text = trim($text);

        $decoded = json_decode($text, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{.*\}/s', $text, $match)) {
            $decoded = json_decode($match[0], true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function canonicalLocationName(string $candidate): ?string
    {
        $candidate = trim($candidate);

        if ($candidate === '') {
            return null;
        }

        $candidateLower = mb_strtolower($candidate);

        foreach ($this->allKnownLocationNames() as $name) {
            if (mb_strtolower($name) === $candidateLower) {
                return $name;
            }
        }

        foreach ($this->allKnownLocationNames() as $name) {
            $nameLower = mb_strtolower($name);

            if (str_contains($nameLower, $candidateLower) ||
                str_contains($candidateLower, $nameLower)) {
                return $name;
            }
        }

        return null;
    }

    private function buildDatabaseContext(string $message, array $matchedLocations = []): array
    {
        if (count($matchedLocations) < 2) {
            $matchedLocations = $this->detectLocations($message);
        }

        $context = [
            'matched_locations' => $matchedLocations,
            'routes' => [],
            'online_trips' => [],
            'daily_service_buses' => [],
        ];

        if (count($matchedLocations) < 2) {
            return $context;
        }

        [$origin, $destination] = [$matchedLocations[0], $matchedLocations[1]];

        $routeMatches = $this->findMatchingRoutes($origin, $destination);

        $context['routes'] = $routeMatches
            ->map(fn ($route) => [
                'route_id' => (int) $route->id,
                'route_number' => $route->route_number ?? null,
                'route_name' => $route->name ?? null,
                'origin' => $route->origin ?? null,
                'destination' => $route->destination ?? null,
                'distance_km' => isset($route->distance_km)
                    ? (float) $route->distance_km
                    : null,
            ])
            ->values()
            ->all();

        $routeIds = $routeMatches->pluck('id')->filter()->unique()->values();

        if ($routeIds->isNotEmpty()) {
            $context['online_trips'] = $this->findOnlineTrips(
                $routeIds,
                $origin,
                $destination
            );
        }

        $context['daily_service_buses'] = $this->findDailyServices(
            $origin,
            $destination
        );

        return $context;
    }

    private function hasDatabaseMatch(array $context): bool
    {
        return !empty($context['online_trips'] ?? []) ||
            !empty($context['daily_service_buses'] ?? []) ||
            !empty($context['routes'] ?? []);
    }

    private function findMatchingRoutes(string $origin, string $destination): Collection
    {
        if (!Schema::hasTable('routes') || !Schema::hasTable('route_stops')) {
            return collect();
        }

        $originRouteIds = DB::table('route_stops')
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($origin))])
            ->pluck('route_id');

        $destinationRouteIds = DB::table('route_stops')
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($destination))])
            ->pluck('route_id');

        $routeIds = $originRouteIds
            ->intersect($destinationRouteIds)
            ->unique()
            ->values();

        if ($routeIds->isEmpty()) {
            return collect();
        }

        return DB::table('routes')
            ->whereIn('id', $routeIds)
            ->when(
                Schema::hasColumn('routes', 'is_active'),
                fn ($query) => $query->where('is_active', true)
            )
            ->orderBy('route_number')
            ->limit(20)
            ->get();
    }

    private function findOnlineTrips(
        Collection $routeIds,
        string $origin,
        string $destination
    ): array {
        if (!Schema::hasTable('trips') ||
            !Schema::hasTable('routes') ||
            !Schema::hasTable('buses')) {
            return [];
        }

        $query = DB::table('trips')
            ->join('routes', 'routes.id', '=', 'trips.route_id')
            ->join('buses', 'buses.id', '=', 'trips.bus_id')
            ->leftJoin('fixed_services', 'fixed_services.id', '=', 'trips.fixed_service_id')
            ->leftJoin('operators', 'operators.id', '=', 'trips.operator_id')
            ->whereIn('trips.route_id', $routeIds);

        if (Schema::hasColumn('trips', 'is_published')) {
            $query->where('trips.is_published', true);
        }

        if (Schema::hasColumn('trips', 'status')) {
            $query->whereIn('trips.status', ['scheduled', 'active', 'on_trip']);
        }

        if (Schema::hasColumn('trips', 'service_date')) {
            $query->whereDate(
                'trips.service_date',
                '>=',
                Carbon::now(self::TIMEZONE)->toDateString()
            );
        }

        $trips = $query
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
                'trips.booking_closed_at',
                'routes.route_number',
                'routes.origin',
                'routes.destination',
                'buses.bus_number',
                'buses.bus_name',
                'buses.bus_type',
                'fixed_services.service_name',
                'fixed_services.is_active as service_active',
                'fixed_services.is_published as service_published',
                'operators.company_name'
            )
            ->orderBy('trips.service_date')
            ->orderBy('trips.departure_time')
            ->limit(20)
            ->get();

        return $trips
            ->map(function ($trip) use ($origin, $destination) {
                if ($trip->fixed_service_id) {
                    if ($trip->service_active !== null && !(bool) $trip->service_active) {
                        return null;
                    }

                    if ($trip->service_published !== null && !(bool) $trip->service_published) {
                        return null;
                    }

                    $times = $this->findServiceStopTimes(
                        (int) $trip->fixed_service_id,
                        $origin,
                        $destination,
                        $trip->trip_type ?? 'starting'
                    );

                    if (!$times['valid']) {
                        return null;
                    }
                } else {
                    $times = $this->findRouteStopTimesFallback(
                        (int) $trip->route_id,
                        $origin,
                        $destination,
                        $trip->departure_time,
                        $trip->arrival_time,
                        $trip->trip_type ?? 'starting'
                    );
                }

                return [
                    'trip_id' => (int) $trip->id,
                    'fixed_service_id' => $trip->fixed_service_id
                        ? (int) $trip->fixed_service_id
                        : null,
                    'route_id' => (int) $trip->route_id,
                    'route_number' => $trip->route_number,
                    'trip_code' => $trip->trip_code,
                    'service_name' => $trip->service_name,
                    'company_name' => $trip->company_name,
                    'bus_name' => $trip->bus_name,
                    'bus_number' => $trip->bus_number,
                    'bus_type' => $trip->bus_type,
                    'service_date' => $trip->service_date,
                    'direction' => $trip->trip_type ?? 'starting',
                    'status' => $trip->status,
                    'boarding_stop' => $origin,
                    'boarding_time' => $times['boarding_time'],
                    'dropoff_stop' => $destination,
                    'dropoff_time' => $times['dropoff_time'],
                    'fare' => $trip->fare !== null ? (float) $trip->fare : null,
                    'booking_available' => $this->tripBookingAvailable($trip),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function tripBookingAvailable($trip): bool
    {
        if (strtolower((string) ($trip->status ?? '')) !== 'scheduled') {
            return false;
        }

        if (!(bool) ($trip->is_published ?? false)) {
            return false;
        }

        if (!empty($trip->booking_closed_at)) {
            return false;
        }

        if (empty($trip->service_date) || empty($trip->departure_time)) {
            return false;
        }

        try {
            $departure = Carbon::parse(
                Carbon::parse($trip->service_date, self::TIMEZONE)->toDateString() .
                ' ' .
                $trip->departure_time,
                self::TIMEZONE
            );

            return Carbon::now(self::TIMEZONE)->lt(
                $departure->copy()->subHour()
            );
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function findServiceStopTimes(
        int $fixedServiceId,
        string $origin,
        string $destination,
        string $direction
    ): array {
        $rows = $this->fixedServiceStops($fixedServiceId, $direction);

        return $this->extractStopJourney($rows, $origin, $destination);
    }

    private function fixedServiceStops(int $fixedServiceId, string $direction): Collection
    {
        if (!Schema::hasTable('fixed_service_stops')) {
            return collect();
        }

        $query = DB::table('fixed_service_stops as fss')
            ->leftJoin('route_stops as rs', 'rs.id', '=', 'fss.route_stop_id')
            ->where('fss.fixed_service_id', $fixedServiceId)
            ->where('fss.direction', $direction)
            ->orderBy('fss.stop_order');

        $stopNameExpression = Schema::hasColumn('fixed_service_stops', 'stop_name')
            ? 'COALESCE(fss.stop_name, rs.name) as name'
            : 'rs.name as name';

        return $query
            ->select(
                'fss.stop_order',
                'fss.arrival_time',
                'fss.departure_time',
                'fss.boarding_allowed',
                'fss.dropoff_allowed',
                DB::raw($stopNameExpression)
            )
            ->get()
            ->filter(fn ($row) => trim((string) ($row->name ?? '')) !== '')
            ->values();
    }

    private function extractStopJourney(
        Collection $rows,
        string $origin,
        string $destination
    ): array {
        $empty = [
            'valid' => false,
            'boarding_time' => null,
            'dropoff_time' => null,
        ];

        if ($rows->isEmpty()) {
            return $empty;
        }

        $originIndex = $rows->search(
            fn ($row) => $this->sameStopName((string) $row->name, $origin)
        );

        $destinationIndex = $rows->search(
            fn ($row) => $this->sameStopName((string) $row->name, $destination)
        );

        if ($originIndex === false ||
            $destinationIndex === false ||
            $originIndex >= $destinationIndex) {
            return $empty;
        }

        $boarding = $rows[$originIndex];
        $dropoff = $rows[$destinationIndex];

        if (isset($boarding->boarding_allowed) && !(bool) $boarding->boarding_allowed) {
            return $empty;
        }

        if (isset($dropoff->dropoff_allowed) && !(bool) $dropoff->dropoff_allowed) {
            return $empty;
        }

        return [
            'valid' => true,
            'boarding_time' => $boarding->departure_time ?? $boarding->arrival_time,
            'dropoff_time' => $dropoff->arrival_time ?? $dropoff->departure_time,
        ];
    }

    private function findRouteStopTimesFallback(
        int $routeId,
        string $origin,
        string $destination,
        $departureTime,
        $arrivalTime,
        string $direction
    ): array {
        if (!Schema::hasTable('route_stops')) {
            return [
                'valid' => true,
                'boarding_time' => $departureTime,
                'dropoff_time' => $arrivalTime,
            ];
        }

        $stops = DB::table('route_stops')
            ->where('route_id', $routeId)
            ->orderBy('stop_order')
            ->get(['name', 'stop_order'])
            ->values();

        if (strtolower($direction) === 'return') {
            $stops = $stops->reverse()->values();
        }

        $originIndex = $stops->search(
            fn ($row) => $this->sameStopName((string) $row->name, $origin)
        );

        $destinationIndex = $stops->search(
            fn ($row) => $this->sameStopName((string) $row->name, $destination)
        );

        if ($originIndex === false ||
            $destinationIndex === false ||
            $originIndex >= $destinationIndex) {
            return [
                'valid' => false,
                'boarding_time' => null,
                'dropoff_time' => null,
            ];
        }

        return [
            'valid' => true,
            'boarding_time' => $departureTime,
            'dropoff_time' => $arrivalTime,
        ];
    }

    private function findDailyServices(string $origin, string $destination): array
    {
        if (!Schema::hasTable('fixed_services') ||
            !Schema::hasTable('fixed_service_stops')) {
            return [];
        }

        $query = DB::table('fixed_services as fs')
            ->leftJoin('routes as r', 'r.id', '=', 'fs.route_id')
            ->leftJoin('buses as b', 'b.id', '=', 'fs.bus_id')
            ->leftJoin('operators as o', 'o.id', '=', 'fs.operator_id')
            ->where('fs.is_active', true)
            ->where('fs.is_published', true);

        $services = $query
            ->select(
                'fs.id',
                'fs.route_id',
                'fs.bus_id',
                'fs.service_name',
                'fs.starting_time',
                'fs.return_time',
                'fs.bus_name as fixed_bus_name',
                'fs.bus_number as fixed_bus_number',
                'fs.origin as fixed_origin',
                'fs.destination as fixed_destination',
                'r.route_number',
                'r.origin as route_origin',
                'r.destination as route_destination',
                'b.bus_number',
                'b.bus_name',
                'b.bus_type',
                'o.company_name'
            )
            ->orderBy('fs.id')
            ->limit(50)
            ->get();

        return $services
            ->map(function ($service) use ($origin, $destination) {
                $match = $this->findFixedServiceDirection(
                    (int) $service->id,
                    $origin,
                    $destination
                );

                if ($match['direction'] === null) {
                    return null;
                }

                return [
                    'service_id' => (int) $service->id,
                    'service_type' => 'Daily Service Bus',
                    'service_name' => $service->service_name,
                    'route_number' => $service->route_number,
                    'company_name' => $service->company_name,
                    'bus_name' => $service->bus_name ?? $service->fixed_bus_name,
                    'bus_number' => $service->bus_number ?? $service->fixed_bus_number,
                    'bus_type' => $service->bus_type,
                    'origin' => $service->route_origin ?? $service->fixed_origin,
                    'destination' => $service->route_destination ?? $service->fixed_destination,
                    'direction' => $match['direction'],
                    'boarding_stop' => $origin,
                    'boarding_time' => $match['boarding_time'],
                    'dropoff_stop' => $destination,
                    'dropoff_time' => $match['dropoff_time'],
                    'online_booking_available' => false,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function findFixedServiceDirection(
        int $fixedServiceId,
        string $origin,
        string $destination
    ): array {
        foreach (['starting', 'return'] as $direction) {
            $stops = $this->fixedServiceStops($fixedServiceId, $direction);
            $journey = $this->extractStopJourney($stops, $origin, $destination);

            if (!$journey['valid']) {
                continue;
            }

            return [
                'direction' => $direction,
                'boarding_time' => $journey['boarding_time'],
                'dropoff_time' => $journey['dropoff_time'],
            ];
        }

        return [
            'direction' => null,
            'boarding_time' => null,
            'dropoff_time' => null,
        ];
    }

    private function sameStopName(string $a, string $b): bool
    {
        return mb_strtolower(trim($a)) === mb_strtolower(trim($b));
    }

    private function buildDirectDatabaseReply(array $context, string $language): string
    {
        $locations = $context['matched_locations'] ?? [];

        $origin = $locations[0] ?? 'Origin';
        $destination = $locations[1] ?? 'Destination';

        $onlineTrips = $context['online_trips'] ?? [];
        $dailyServices = $context['daily_service_buses'] ?? [];
        $routes = $context['routes'] ?? [];

        $serviceCount = count($onlineTrips) + count($dailyServices);
        $lines = [];

        if ($serviceCount > 0) {
            $lines[] = match ($language) {
                'ta' => "{$origin} இலிருந்து {$destination} வரை {$serviceCount} EastBus சேவைகள் கிடைக்கின்றன.",
                'si' => "{$origin} සිට {$destination} දක්වා EastBus සේවා {$serviceCount}ක් හමු විය.",
                default => "I found {$serviceCount} EastBus service" .
                    ($serviceCount === 1 ? '' : 's') .
                    " from {$origin} to {$destination}.",
            };
        } elseif (!empty($routes)) {
            $lines[] = match ($language) {
                'ta' => "{$origin} இலிருந்து {$destination} வரை EastBus route உள்ளது. ஆனால் தற்போது published passenger service இல்லை.",
                'si' => "{$origin} සිට {$destination} දක්වා EastBus මාර්ගයක් ඇත. නමුත් දැනට published passenger service එකක් නොමැත.",
                default => "An EastBus route exists from {$origin} to {$destination}, but there is no currently published passenger service.",
            };
        }

        foreach (array_slice($onlineTrips, 0, 5) as $index => $trip) {
            $name = trim((string) (
                $trip['company_name'] ??
                $trip['bus_name'] ??
                $trip['service_name'] ??
                'EastBus Service'
            ));

            $number = trim((string) ($trip['bus_number'] ?? ''));

            $lines[] = ($index + 1) . '. ' . $name .
                ($number !== '' ? " ({$number})" : '');

            if (!empty($trip['service_date'])) {
                $lines[] = $this->label($language, 'date') .
                    ': ' . $trip['service_date'];
            }

            $boarding = $this->label($language, 'boarding') .
                ': ' . ($trip['boarding_stop'] ?? $origin);

            if (!empty($trip['boarding_time'])) {
                $boarding .= ' - ' . $this->formatTime($trip['boarding_time']);
            }

            $lines[] = $boarding;

            $dropoff = $this->label($language, 'dropoff') .
                ': ' . ($trip['dropoff_stop'] ?? $destination);

            if (!empty($trip['dropoff_time'])) {
                $dropoff .= ' - ' . $this->formatTime($trip['dropoff_time']);
            }

            $lines[] = $dropoff;

            if (isset($trip['fare']) && $trip['fare'] !== null) {
                $lines[] = $this->label($language, 'fare') .
                    ': Rs. ' . number_format((float) $trip['fare'], 2);
            }

            $lines[] = $this->label($language, 'booking') . ': ' .
                (!empty($trip['booking_available'])
                    ? $this->label($language, 'available')
                    : $this->label($language, 'not_available'));
        }

        if (!empty($dailyServices)) {
            if (!empty($onlineTrips)) {
                $lines[] = '';
            }

            $lines[] = $this->label($language, 'daily_service') . ':';

            foreach (array_slice($dailyServices, 0, 5) as $index => $service) {
                $name = trim((string) (
                    $service['bus_name'] ??
                    $service['service_name'] ??
                    'Daily Service Bus'
                ));

                $number = trim((string) ($service['bus_number'] ?? ''));

                $lines[] = ($index + 1) . '. ' . $name .
                    ($number !== '' ? " ({$number})" : '');

                $boarding = $this->label($language, 'boarding') .
                    ': ' . ($service['boarding_stop'] ?? $origin);

                if (!empty($service['boarding_time'])) {
                    $boarding .= ' - ' . $this->formatTime($service['boarding_time']);
                }

                $lines[] = $boarding;

                $dropoff = $this->label($language, 'dropoff') .
                    ': ' . ($service['dropoff_stop'] ?? $destination);

                if (!empty($service['dropoff_time'])) {
                    $dropoff .= ' - ' . $this->formatTime($service['dropoff_time']);
                }

                $lines[] = $dropoff;

                $lines[] = $this->label($language, 'booking') .
                    ': ' . $this->label($language, 'not_available');
            }
        }

        return implode("\n", $lines);
    }

    private function label(string $language, string $key): string
    {
        $labels = [
            'en' => [
                'date' => 'Date',
                'boarding' => 'Boarding',
                'dropoff' => 'Drop-off',
                'fare' => 'Fare',
                'booking' => 'Online Booking',
                'available' => 'Available',
                'not_available' => 'Not Available',
                'daily_service' => 'Timetable Only Service',
            ],
            'ta' => [
                'date' => 'தேதி',
                'boarding' => 'ஏறும் இடம்',
                'dropoff' => 'இறங்கும் இடம்',
                'fare' => 'கட்டணம்',
                'booking' => 'Online Booking',
                'available' => 'கிடைக்கும்',
                'not_available' => 'கிடைக்காது',
                'daily_service' => 'நேர அட்டவணை சேவை',
            ],
            'si' => [
                'date' => 'දිනය',
                'boarding' => 'නැගීම',
                'dropoff' => 'බැසීම',
                'fare' => 'ගාස්තුව',
                'booking' => 'Online Booking',
                'available' => 'ලබා ගත හැක',
                'not_available' => 'ලබා ගත නොහැක',
                'daily_service' => 'කාලසටහන් සේවාව',
            ],
        ];

        return $labels[$language][$key] ??
            $labels['en'][$key] ??
            $key;
    }

    private function buildNoDatabaseMatchReply(array $context, string $language): string
    {
        $locations = $context['matched_locations'] ?? [];

        if (count($locations) >= 2) {
            return match ($language) {
                'ta' => "{$locations[0]} இலிருந்து {$locations[1]} வரை matching EastBus service தற்போது கிடைக்கவில்லை. Passenger App-ல் Search Buses மூலம் மீண்டும் சரிபார்க்கவும்.",
                'si' => "{$locations[0]} සිට {$locations[1]} දක්වා ගැළපෙන EastBus සේවාවක් දැනට හමු නොවීය. Passenger App හි Search Buses භාවිතයෙන් නැවත පරීක්ෂා කරන්න.",
                default => "I could not find a matching EastBus service from {$locations[0]} to {$locations[1]}. Please use Search Buses to check the latest services.",
            };
        }

        return match ($language) {
            'ta' => 'இந்த கேள்விக்கான EastBus route அல்லது bus தகவலை உறுதிப்படுத்த முடியவில்லை. From மற்றும் To இடங்களை சேர்த்து கேட்கவும் அல்லது Search Buses பயன்படுத்தவும்.',
            'si' => 'මෙම ප්‍රශ්නය සඳහා EastBus මාර්ග හෝ බස් තොරතුරු තහවුරු කළ නොහැකි විය. From සහ To ස්ථාන සඳහන් කරන්න හෝ Search Buses භාවිතා කරන්න.',
            default => 'I could not confirm EastBus route or bus information for that question. Please include both origin and destination or use Search Buses.',
        };
    }

    private function genericTemporaryReply(string $language): string
    {
        return match ($language) {
            'ta' => 'தற்போது EastBus AI service-ஐ அணுக முடியவில்லை. Bus சேவைகளை பார்க்க Passenger App-ல் Search Buses பயன்படுத்தவும்.',
            'si' => 'දැනට EastBus AI සේවාවට සම්බන්ධ විය නොහැක. බස් සේවා බැලීමට Passenger App හි Search Buses භාවිතා කරන්න.',
            default => 'The EastBus AI service is temporarily unavailable. Please use Search Buses to check bus services.',
        };
    }

    private function errorMessage(string $language): string
    {
        return match ($language) {
            'ta' => 'தற்போது உங்கள் கேள்வியை செயல்படுத்த முடியவில்லை.',
            'si' => 'දැනට ඔබගේ ප්‍රශ්නය සකස් කළ නොහැක.',
            default => 'Unable to process your message right now.',
        };
    }

    private function buildPassengerContextText(array $context): string
    {
        if (empty($context)) {
            return 'No personal passenger context was provided.';
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

        $safe = [];

        foreach ($allowed as $key) {
            if (array_key_exists($key, $context)) {
                $safe[$key] = $context[$key];
            }
        }

        if (empty($safe)) {
            return 'No usable personal EastBus context was provided.';
        }

        return json_encode(
            $safe,
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        ) ?: 'No usable personal EastBus context was provided.';
    }

    private function formatTime($value): string
    {
        $text = trim((string) $value);

        if ($text === '') {
            return '';
        }

        try {
            return Carbon::parse($text, self::TIMEZONE)->format('h:i A');
        } catch (\Throwable $e) {
            return $text;
        }
    }
}