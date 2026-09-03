<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PassengerApiAuth
{
    public function handle(Request $request, Closure $next)
    {
        $authorization = $request->header(
            'Authorization',
            ''
        );

        if (!str_starts_with(
            $authorization,
            'Bearer '
        )) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $plainToken = trim(
            substr(
                $authorization,
                7
            )
        );

        if ($plainToken === '') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $tokenHash = hash(
            'sha256',
            $plainToken
        );

        $record = DB::table('passenger_api_tokens')
            ->where('token_hash', $tokenHash)
            ->first();

        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token.',
            ], 401);
        }

        $passenger = DB::table('users')
            ->where('id', $record->user_id)
            ->where('role', 'PASSENGER')
            ->where('status', 'ACTIVE')
            ->first();

        if (!$passenger) {
            return response()->json([
                'success' => false,
                'message' => 'Passenger account not found.',
            ], 401);
        }

        unset($passenger->password);

        $request->attributes->set(
            'passenger',
            $passenger
        );

        $request->attributes->set(
            'passenger_token_hash',
            $tokenHash
        );

        return $next($request);
    }
}