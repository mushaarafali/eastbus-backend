<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PassengerProfileController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Update Passenger Profile
    |--------------------------------------------------------------------------
    */

    public function update(Request $request)
    {
        $passenger = $request->attributes->get('passenger');

        abort_unless(
            $passenger,
            401,
            'Passenger authentication required.'
        );

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'regex:/^\+94[0-9]{9}$/'],
        ], [
            'name.required' => 'Full name is required.',
            'phone.required' => 'Phone number is required.',
            'phone.regex' => 'Phone number must use Sri Lankan international format +94XXXXXXXXX.',
        ]);

        $name = trim($data['name']);
        $phone = trim($data['phone']);

        /*
        |--------------------------------------------------------------------------
        | Check Phone Number
        |--------------------------------------------------------------------------
        |
        | The same phone number cannot be used by another account.
        |
        */

        $phoneExists = DB::table('users')
            ->where('phone', $phone)
            ->where('id', '!=', $passenger->id)
            ->exists();

        if ($phoneExists) {
            return response()->json([
                'success' => false,
                'message' => 'This phone number is already registered with another account.',
                'errors' => [
                    'phone' => [
                        'This phone number is already registered with another account.',
                    ],
                ],
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Update Profile
        |--------------------------------------------------------------------------
        |
        | Email cannot be changed from the passenger profile.
        |
        */

        DB::table('users')
            ->where('id', $passenger->id)
            ->where('role', 'PASSENGER')
            ->update([
                'name' => $name,
                'phone' => $phone,
                'updated_at' => now(),
            ]);

        /*
        |--------------------------------------------------------------------------
        | Reload Updated Passenger
        |--------------------------------------------------------------------------
        */

        $updatedPassenger = DB::table('users')
            ->where('id', $passenger->id)
            ->where('role', 'PASSENGER')
            ->select([
                'id',
                'name',
                'email',
                'phone',
                'role',
                'status',
                'is_active',
                'email_verified_at',
            ])
            ->first();

        if (!$updatedPassenger) {
            return response()->json([
                'success' => false,
                'message' => 'Passenger account not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',

            'user' => [
                'id' => (int) $updatedPassenger->id,
                'full_name' => $updatedPassenger->name,
                'name' => $updatedPassenger->name,
                'email' => $updatedPassenger->email,
                'phone' => $updatedPassenger->phone,
                'role' => $updatedPassenger->role,
                'status' => $updatedPassenger->status,
                'is_active' => (bool) $updatedPassenger->is_active,
                'email_verified' => $updatedPassenger->email_verified_at !== null,
            ],

            'email_change_allowed' => false,
        ]);
    }
}