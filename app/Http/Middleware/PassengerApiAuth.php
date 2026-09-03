<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class PassengerApiAuth
{
    /**
     * Authenticate Passenger API requests.
     */
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        /*
        |--------------------------------------------------------------------------
        | Get Bearer Token
        |--------------------------------------------------------------------------
        */

        $plainToken = $request->bearerToken();

        if (!$plainToken) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Please login.',
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | Hash Token
        |--------------------------------------------------------------------------
        |
        | PassengerAuthController stores SHA-256 hash in
        | passenger_api_tokens.token_hash.
        |
        */

        $tokenHash = hash(
            'sha256',
            $plainToken
        );

        /*
        |--------------------------------------------------------------------------
        | Find Passenger Token
        |--------------------------------------------------------------------------
        */

        $apiToken = DB::table('passenger_api_tokens')
            ->where('token_hash', $tokenHash)
            ->first();

        if (!$apiToken) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired login session.',
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | Find Passenger Account
        |--------------------------------------------------------------------------
        */

        $passenger = DB::table('users')
            ->where('id', $apiToken->user_id)
            ->where('role', 'PASSENGER')
            ->first();

        if (!$passenger) {
            return response()->json([
                'success' => false,
                'message' => 'Passenger account not found.',
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | Check Account Status
        |--------------------------------------------------------------------------
        */

        if (($passenger->status ?? '') !== 'ACTIVE') {
            return response()->json([
                'success' => false,
                'message' => 'Passenger account is not active.',
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | Check Active Flag
        |--------------------------------------------------------------------------
        */

        if (
            isset($passenger->is_active) &&
            !$passenger->is_active
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Passenger account has been disabled.',
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | Attach Passenger To Request
        |--------------------------------------------------------------------------
        |
        | PassengerAuthController::me() already expects:
        |
        | $request->attributes->get('passenger')
        |
        */

        $request->attributes->set(
            'passenger',
            $passenger
        );

        $request->attributes->set(
            'passenger_id',
            $passenger->id
        );

        $request->attributes->set(
            'passenger_token_hash',
            $tokenHash
        );

        /*
        |--------------------------------------------------------------------------
        | Continue Request
        |--------------------------------------------------------------------------
        */

        return $next($request);
    }
}