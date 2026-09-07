<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FixedScheduleController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Search Fixed Bus Schedules
    |--------------------------------------------------------------------------
    |
    | Passenger can search using ANY valid fixed stop.
    |
    | Example HEMA EXPRESS:
    |
    | Pottuvil
    | → Akkaraipattu
    | → Kalmunai
    | → Kaluwanchikudy
    | → Kattankudy
    | → Batticaloa Hospital
    | → Batticaloa Bus Stand
    |
    | Valid searches:
    |
    | Pottuvil → Batticaloa
    | Akkaraipattu → Kalmunai
    | Kalmunai → Kattankudy
    | Kattankudy → Batticaloa Bus Stand
    |
    | Return direction is checked separately.
    |
    */

    public function search(Request $request)
    {
        $data = $request->validate([
            'origin' => [
                'required',
                'string',
                'max:150',
            ],
            'destination' => [
                'required',
                'string',
                'max:150',
                'different:origin',
            ],
        ]);

        $origin = trim($data['origin']);
        $destination = trim($data['destination']);

        $services = DB::table('fixed_services')
            ->where('is_active', true)
            ->where('is_published', true)
            ->orderBy('bus_name')
            ->get();

        $results = [];

        foreach ($services as $service) {
            foreach (['starting', 'return'] as $direction) {

                $stops = DB::table('fixed_service_stops')
                    ->where(
                        'fixed_service_id',
                        $service->id
                    )
                    ->where(
                        'direction',
                        $direction
                    )
                    ->orderBy('stop_order')
                    ->get();

                if ($stops->count() < 2) {
                    continue;
                }

                /*
                 * Find passenger's requested boarding stop.
                 */
                $boarding = $this->findStop(
                    $stops,
                    $origin
                );

                /*
                 * Find passenger's requested destination stop.
                 */
                $dropoff = $this->findStop(
                    $stops,
                    $destination
                );

                /*
                 * Both stops must exist in the same direction.
                 */
                if (!$boarding || !$dropoff) {
                    continue;
                }

                /*
                 * Boarding must come BEFORE drop-off.
                 */
                if (
                    (int) $boarding->stop_order >=
                    (int) $dropoff->stop_order
                ) {
                    continue;
                }

                $departureTime =
                    $boarding->departure_time
                    ?? $boarding->arrival_time;

                $arrivalTime =
                    $dropoff->arrival_time
                    ?? $dropoff->departure_time;

                /*
                 * Prevent duplicate result for same bus/direction/stops.
                 */
                $resultKey =
                    $service->id
                    . '-'
                    . $direction
                    . '-'
                    . $boarding->id
                    . '-'
                    . $dropoff->id;

                if (isset($results[$resultKey])) {
                    continue;
                }

                $results[$resultKey] = [
                    'type' => 'fixed_schedule',

                    /*
                     * Important:
                     * Fixed timetable services are information-only.
                     */
                    'bookable' => false,

                    'fixed_service_id' => $service->id,

                    'bus_name' =>
                        $service->bus_name,

                    'bus_number' =>
                        $service->bus_number,

                    'direction' =>
                        $direction,

                    /*
                     * Passenger-requested locations.
                     */
                    'requested_origin' =>
                        $origin,

                    'requested_destination' =>
                        $destination,

                    /*
                     * Actual matching fixed stops.
                     *
                     * Flutter should display these.
                     */
                    'origin' =>
                        $boarding->stop_name,

                    'destination' =>
                        $dropoff->stop_name,

                    'boarding_stop' =>
                        $boarding->stop_name,

                    'dropoff_stop' =>
                        $dropoff->stop_name,

                    'boarding_stop_order' =>
                        (int) $boarding->stop_order,

                    'dropoff_stop_order' =>
                        (int) $dropoff->stop_order,

                    'departure_time' =>
                        $departureTime,

                    'arrival_time' =>
                        $arrivalTime,

                    'contact_numbers' =>
                        array_values(
                            array_filter([
                                $service->contact_number_1,
                                $service->contact_number_2,
                                $service->contact_number_3,
                            ])
                        ),

                    'label' =>
                        'Daily Service',

                    'message' =>
                        'Online seat booking is not available for this service.',
                ];
            }
        }

        return response()->json([
            'success' => true,

            'search' => [
                'origin' => $origin,
                'destination' => $destination,
            ],

            'count' => count($results),

            'results' => array_values(
                $results
            ),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Fixed Schedule Details
    |--------------------------------------------------------------------------
    */

    public function show(int $id)
    {
        $service = DB::table('fixed_services')
            ->where('id', $id)
            ->where('is_active', true)
            ->where('is_published', true)
            ->first();

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Fixed bus schedule not found.',
            ], 404);
        }

        $startingStops =
            DB::table('fixed_service_stops')
                ->where(
                    'fixed_service_id',
                    $service->id
                )
                ->where(
                    'direction',
                    'starting'
                )
                ->orderBy('stop_order')
                ->get();

        $returnStops =
            DB::table('fixed_service_stops')
                ->where(
                    'fixed_service_id',
                    $service->id
                )
                ->where(
                    'direction',
                    'return'
                )
                ->orderBy('stop_order')
                ->get();

        return response()->json([
            'success' => true,

            'service' => [
                'id' =>
                    $service->id,

                'type' =>
                    'fixed_schedule',

                'bookable' =>
                    false,

                'bus_name' =>
                    $service->bus_name,

                'bus_number' =>
                    $service->bus_number,

                'origin' =>
                    $service->origin,

                'destination' =>
                    $service->destination,

                'contact_numbers' =>
                    array_values(
                        array_filter([
                            $service->contact_number_1,
                            $service->contact_number_2,
                            $service->contact_number_3,
                        ])
                    ),

                'is_published' =>
                    (bool) $service->is_published,

                'is_active' =>
                    (bool) $service->is_active,

                'starting_stops' =>
                    $startingStops,

                'return_stops' =>
                    $returnStops,

                'label' =>
                    'Daily Service',

                'message' =>
                    'Online seat booking is not available for this service.',
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Find Matching Stop
    |--------------------------------------------------------------------------
    */

    private function findStop(
        $stops,
        string $search
    ) {
        /*
         * 1. Try exact normalized match first.
         */
        foreach ($stops as $stop) {
            if (
                $this->same(
                    $stop->stop_name,
                    $search
                )
            ) {
                return $stop;
            }
        }

        /*
         * 2. Try sensible partial match.
         *
         * Example:
         *
         * User:
         * Batticaloa
         *
         * DB:
         * Batticaloa Bus Stand
         *
         * This should match.
         */
        foreach ($stops as $stop) {
            if (
                $this->similar(
                    $stop->stop_name,
                    $search
                )
            ) {
                return $stop;
            }
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Exact Normalized Match
    |--------------------------------------------------------------------------
    */

    private function same(
        string $first,
        string $second
    ): bool {
        return $this->normalize($first)
            ===
            $this->normalize($second);
    }

    /*
    |--------------------------------------------------------------------------
    | Partial Stop Match
    |--------------------------------------------------------------------------
    */

    private function similar(
        string $first,
        string $second
    ): bool {
        $a = $this->normalize($first);
        $b = $this->normalize($second);

        if ($a === '' || $b === '') {
            return false;
        }

        /*
         * Avoid matching extremely short words.
         *
         * Example:
         * "Ka" should not match "Kalmunai".
         */
        if (
            mb_strlen($a) < 4 ||
            mb_strlen($b) < 4
        ) {
            return false;
        }

        /*
         * Example:
         *
         * batticaloa
         * batticaloabusstand
         */
        return str_contains($a, $b)
            || str_contains($b, $a);
    }

    /*
    |--------------------------------------------------------------------------
    | Normalize Stop Name
    |--------------------------------------------------------------------------
    |
    | Examples:
    |
    | "Batticaloa Bus Stand"
    | "BATTICALOA BUS STAND"
    | "Batticaloa-Bus-Stand"
    |
    | become:
    |
    | batticaloabusstand
    |
    */

    private function normalize(
        string $value
    ): string {
        $value = trim(
            mb_strtolower($value)
        );

        /*
         * Remove punctuation and spaces.
         */
        $value = preg_replace(
            '/[^\p{L}\p{N}]+/u',
            '',
            $value
        );

        return $value ?? '';
    }
}