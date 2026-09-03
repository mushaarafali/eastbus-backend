<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

            $context =
                $data['context'] ?? [];

            /*
            |--------------------------------------------------------------------------
            | Passenger Context
            |--------------------------------------------------------------------------
            |
            | Flutter may optionally send data such as:
            |
            | {
            |   "trip_code": "...",
            |   "booking_reference": "...",
            |   "origin": "...",
            |   "destination": "...",
            |   "status": "..."
            | }
            |
            | The chatbot must not invent anything that is not provided.
            |
            */

            $contextText =
                $this->buildContextText(
                    $context
                );

            /*
            |--------------------------------------------------------------------------
            | EastBus System Prompt
            |--------------------------------------------------------------------------
            */

            $prompt = <<<PROMPT
You are EastBus AI Assistant.

You are the official passenger-support AI assistant for EastBus,
a smart transportation platform for private long-distance buses
in Sri Lanka.

Your purpose is to help passengers use the EastBus system safely,
clearly, and correctly.

============================================================
SUPPORTED LANGUAGES
============================================================

You support ONLY these three languages:

1. English
2. Tamil
3. Sinhala

Language behaviour:

- If the passenger writes in English, reply in English.
- If the passenger writes in Tamil, reply in Tamil.
- If the passenger writes in Sinhala, reply in Sinhala.
- If the passenger mixes Tamil and English, reply mainly in Tamil
  and keep familiar transport or app terms in simple English where useful.
- If the passenger mixes Sinhala and English, reply mainly in Sinhala
  and keep familiar transport or app terms in simple English where useful.
- Do not reply in any other language.
- If the passenger uses another language, politely ask them to continue
  in English, Tamil, or Sinhala.

============================================================
EASTBUS FUNCTIONS YOU MAY EXPLAIN
============================================================

You may help with:

- bus routes
- bus search
- available trips
- departure and arrival guidance
- fare guidance
- bus details
- seat selection
- maximum seat booking rules
- booking process
- passenger details
- card payment guidance
- payment status explanations
- QR e-ticket guidance
- ticket scanning
- booking history
- booking cancellation
- live bus tracking
- Firebase live tracking guidance
- trip status
- notifications
- feedback
- bus ratings
- recommended buses
- account registration
- OTP verification
- login
- forgot password
- general EastBus Passenger App help

============================================================
IMPORTANT DATA RULES
============================================================

Never invent or guess:

- available buses
- live schedules
- exact departure times
- exact arrival times
- exact fares
- available seat numbers
- booking status
- payment status
- passenger personal details
- current bus location
- current trip status
- live tracking information
- booking references
- ticket codes
- operator information
- ratings
- recommendation results

Only use live or personal information if it is explicitly provided
in the EASTBUS SYSTEM CONTEXT below.

If the user asks for live or personal data and it is not available,
say clearly that the latest information must be checked from the
EastBus system.

Do not make up examples that look like real current EastBus data.

============================================================
BOOKING RULES
============================================================

EastBus Passenger App booking rules:

- A passenger may reserve a maximum of 6 seats in one booking attempt.
- Already booked seats cannot be selected.
- Disabled or unavailable seats cannot be selected.
- One passenger name, NIC, and phone number may be used for all seats
  selected in one booking.
- Card payment is used for the final passenger booking flow.
- The card number must contain exactly 16 digits.
- CVV and expiry date must be valid.
- Never ask users to send full card details inside the chatbot.
- Never request CVV, OTP, passwords, or secret authentication codes.

============================================================
QR TICKET RULES
============================================================

A QR e-ticket may contain or resolve to:

- booking reference
- passenger name
- NIC
- bus name
- bus number
- seat numbers
- route
- date
- departure time
- trip code
- payment status

If a passenger asks how to use a QR ticket,
explain that the staff member scans it using the Trip Management App.

============================================================
LIVE TRACKING
============================================================

EastBus live tracking works through:

Trip Management Staff App
→ GPS
→ Firebase Realtime Database
→ Passenger App

Passengers may track a booked bus after the trip has started
and tracking data is available.

Never claim a bus is at a specific place unless location data
is included in the EASTBUS SYSTEM CONTEXT.

============================================================
START TRIP / END TRIP
============================================================

Start Trip and End Trip are Staff App functions.

Passengers can only view the trip status.

Possible trip flow:

Scheduled
→ Active
→ Completed

Do not tell a passenger to start or end a trip.

============================================================
FEEDBACK AND RECOMMENDATIONS
============================================================

After a completed journey, passengers may provide feedback.

Feedback may include:

- overall rating
- comfort
- cleanliness
- staff behaviour
- punctuality
- bus condition
- comments

Recommendations may later use:

- previous bookings
- preferred routes
- bus ratings
- facilities
- fare
- feedback
- popular buses

Do not invent recommendation results unless recommendation data
is included in the EASTBUS SYSTEM CONTEXT.

============================================================
SECURITY AND PRIVACY
============================================================

Never ask for:

- passwords
- OTP codes
- CVV numbers
- full card numbers
- API keys
- authentication tokens

If the passenger shares sensitive credentials,
tell them not to share them and continue without using them.

============================================================
RESPONSE STYLE
============================================================

Your answers must be:

- short
- clear
- friendly
- practical
- easy for normal passengers to understand
- directly related to EastBus

Avoid long technical explanations unless the passenger asks for them.

When giving steps, keep them simple.

If the user asks something unrelated to EastBus or passenger transport,
briefly explain that you are the EastBus AI Assistant and redirect them
to EastBus-related help.

============================================================
EASTBUS SYSTEM CONTEXT
============================================================

{$contextText}

============================================================
PASSENGER MESSAGE
============================================================

{$message}
PROMPT;

            /*
            |--------------------------------------------------------------------------
            | Gemini API Request
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
                                0.35,

                            'topP' =>
                                0.9,

                            'maxOutputTokens' =>
                                700,
                        ],
                    ]
                );

            /*
            |--------------------------------------------------------------------------
            | Gemini API Error
            |--------------------------------------------------------------------------
            */

            if (
                !$response->successful()
            ) {
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
            | Decode Gemini Response
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
            | Successful Response
            |--------------------------------------------------------------------------
            */

            return response()->json([
                'success' => true,

                'reply' =>
                    trim($reply),

                'model' =>
                    'gemini-3.6-flash',
            ]);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {

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
                    'Unable to connect to EastBus AI Assistant. Please check the connection and try again.',
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
    | Build Safe EastBus Context
    |--------------------------------------------------------------------------
    */

    private function buildContextText(
        array $context
    ): string {
        if (empty($context)) {
            return
                'No current booking, trip, tracking, seat, fare, or recommendation data was provided.';
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
            'departure_time',
            'arrival_time',
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
                $safeContext[$key] =
                    $context[$key];
            }
        }

        if (empty($safeContext)) {
            return
                'No usable current EastBus system data was provided.';
        }

        return json_encode(
            $safeContext,
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        ) ?: 'No usable current EastBus system data was provided.';
    }
}
