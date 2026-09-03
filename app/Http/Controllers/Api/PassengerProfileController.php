<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PassengerProfileController extends Controller
{
    public function update(Request $request)
    {
        $passenger = $request->attributes->get('passenger');
        abort_unless($passenger, 401, 'Passenger authentication required.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'regex:/^\+94[0-9]{9}$/'],
        ], [
            'phone.regex' =>
                'Phone number must use Sri Lankan international format +94XXXXXXXXX.',
        ]);

        DB::table('users')
            ->where('id', $passenger->id)
            ->update([
                'name' => trim($data['name']),
                'phone' => trim($data['phone']),
                'updated_at' => now(),
            ]);

        $columns = ['id', 'name', 'phone', 'email'];
        if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'email_verified_at')) {
            $columns[] = 'email_verified_at';
        }

        return response()->json([
            'success' => true,
            'message' => 'Profile updated.',
            'user' => DB::table('users')
                ->where('id', $passenger->id)
                ->select($columns)
                ->first(),
            'email_change_allowed' => false,
        ]);
    }
}
