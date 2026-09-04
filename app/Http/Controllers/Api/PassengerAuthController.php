<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EastBusMailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PassengerAuthController extends Controller
{
    private EastBusMailService $mailService;

    public function __construct(EastBusMailService $mailService)
    {
        $this->mailService = $mailService;
    }

    /*
    |--------------------------------------------------------------------------
    | REGISTER PASSENGER
    |--------------------------------------------------------------------------
    */

    public function register(Request $request)
    {
        $data = $request->validate([
            'full_name' => [
                'required',
                'string',
                'max:150',
            ],

            'email' => [
                'required',
                'email',
                'max:150',
                'unique:users,email',
            ],

            'phone' => [
                'required',
                'string',
                'regex:/^\+94[0-9]{9}$/',
                'unique:users,phone',
            ],

            'password' => [
                'required',
                'string',
                'min:8',
            ],
        ], [
            'phone.regex' =>
                'Phone number must use Sri Lankan format: +94XXXXXXXXX',
        ]);

        $email = strtolower(
            trim($data['email'])
        );

        $otp = (string) random_int(
            100000,
            999999
        );

        $passengerId = DB::table('users')
            ->insertGetId([
                'name' =>
                    trim($data['full_name']),

                'email' =>
                    $email,

                'phone' =>
                    trim($data['phone']),

                'password' =>
                    Hash::make(
                        $data['password']
                    ),

                'role' =>
                    'PASSENGER',

                'is_active' =>
                    true,

                'status' =>
                    'PENDING',

                'email_verified_at' =>
                    null,

                'otp_code' =>
                    $otp,

                'otp_expires_at' =>
                    now()->addMinutes(10),

                'created_at' =>
                    now(),

                'updated_at' =>
                    now(),
            ]);

        try {
            $this->mailService->otp(
                $email,
                trim($data['full_name']),
                $otp,
                'Email Verification'
            );
        } catch (\Throwable $e) {
            DB::table('users')
                ->where(
                    'id',
                    $passengerId
                )
                ->delete();

            return response()->json([
                'success' => false,
                'message' =>
                    'Unable to send verification email. Please try again.',
            ], 500);
        }

        return response()->json([
            'success' => true,

            'message' =>
                'Registration successful. A verification OTP has been sent to your email.',

            'passenger_id' =>
                $passengerId,

            'email' =>
                $email,
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | VERIFY REGISTRATION OTP
    |--------------------------------------------------------------------------
    */

    public function verifyOtp(Request $request)
    {
        $data = $request->validate([
            'email' => [
                'required',
                'email',
            ],

            'otp' => [
                'required',
                'string',
                'size:6',
            ],
        ]);

        $email = strtolower(
            trim($data['email'])
        );

        $user = DB::table('users')
            ->where(
                'email',
                $email
            )
            ->where(
                'role',
                'PASSENGER'
            )
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Passenger account not found.',
            ], 404);
        }

        if (
            $user->status === 'ACTIVE' &&
            $user->email_verified_at !== null
        ) {
            return response()->json([
                'success' => true,
                'message' =>
                    'Email is already verified. You can login.',
            ]);
        }

        if (
            empty($user->otp_code) ||
            $user->otp_code !== $data['otp']
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Invalid verification OTP.',
            ], 422);
        }

        if (
            empty($user->otp_expires_at) ||
            now()->greaterThan(
                $user->otp_expires_at
            )
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'OTP has expired. Please request a new OTP.',
            ], 422);
        }

        DB::table('users')
            ->where(
                'id',
                $user->id
            )
            ->update([
                'status' =>
                    'ACTIVE',

                'is_active' =>
                    true,

                'email_verified_at' =>
                    now(),

                'otp_code' =>
                    null,

                'otp_expires_at' =>
                    null,

                'updated_at' =>
                    now(),
            ]);

        /*
         * Reload user after verification.
         */
        $verifiedUser = DB::table('users')
            ->where(
                'id',
                $user->id
            )
            ->first();

        /*
         * Send Welcome Email.
         *
         * Welcome email failure must NOT undo
         * successful account verification.
         */
        if ($verifiedUser) {
            try {
                $this->mailService->welcome(
                    $verifiedUser
                );
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json([
            'success' => true,
            'message' =>
                'Email verified successfully. You can now login.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | RESEND REGISTRATION OTP
    |--------------------------------------------------------------------------
    */

    public function resendOtp(Request $request)
    {
        $data = $request->validate([
            'email' => [
                'required',
                'email',
            ],
        ]);

        $email = strtolower(
            trim($data['email'])
        );

        $user = DB::table('users')
            ->where(
                'email',
                $email
            )
            ->where(
                'role',
                'PASSENGER'
            )
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Passenger account not found.',
            ], 404);
        }

        if (
            $user->status === 'ACTIVE' &&
            $user->email_verified_at !== null
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'This account is already verified.',
            ], 422);
        }

        $otp = (string) random_int(
            100000,
            999999
        );

        DB::table('users')
            ->where(
                'id',
                $user->id
            )
            ->update([
                'otp_code' =>
                    $otp,

                'otp_expires_at' =>
                    now()->addMinutes(10),

                'updated_at' =>
                    now(),
            ]);

        try {
            $this->mailService->otp(
                $user->email,
                $user->name,
                $otp,
                'Email Verification'
            );
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Unable to send OTP email. Please try again.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' =>
                'A new verification OTP has been sent to your email.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | LOGIN
    |--------------------------------------------------------------------------
    */

    public function login(Request $request)
    {
        $data = $request->validate([
            'login' => [
                'required',
                'string',
            ],

            'password' => [
                'required',
                'string',
            ],
        ]);

        $login = trim(
            $data['login']
        );

        $user = DB::table('users')
            ->where(
                'role',
                'PASSENGER'
            )
            ->where(function ($query) use ($login) {
                $query
                    ->where(
                        'email',
                        strtolower($login)
                    )
                    ->orWhere(
                        'phone',
                        $login
                    );
            })
            ->first();

        /*
         * Account not found.
         */
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Invalid email/phone or password.',
            ], 401);
        }

        /*
         * Wrong Password.
         */
        if (
            !Hash::check(
                $data['password'],
                $user->password
            )
        ) {
            $this->handleFailedLogin(
                $request,
                $user
            );

            return response()->json([
                'success' => false,
                'message' =>
                    'Invalid email/phone or password.',
            ], 401);
        }

        /*
         * Correct login.
         * Clear failed-login counter.
         */
        $this->clearFailedLogin(
            $request,
            $user
        );

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Your passenger account has been deactivated.',
            ], 403);
        }

        if (
            $user->status !== 'ACTIVE' ||
            $user->email_verified_at === null
        ) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Please verify your email before login.',

                'requires_verification' =>
                    true,

                'email' =>
                    $user->email,
            ], 403);
        }

        $plainToken = Str::random(64);

        $tokenHash = hash(
            'sha256',
            $plainToken
        );

        DB::table(
            'passenger_api_tokens'
        )->insert([
            'user_id' =>
                $user->id,

            'token_hash' =>
                $tokenHash,

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);

        return response()->json([
            'success' => true,

            'message' =>
                'Login successful.',

            'token' =>
                $plainToken,

            'passenger' => [
                'id' =>
                    $user->id,

                'full_name' =>
                    $user->name,

                'email' =>
                    $user->email,

                'phone' =>
                    $user->phone,

                'role' =>
                    $user->role,
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | FORGOT PASSWORD
    |--------------------------------------------------------------------------
    */

    public function forgotPassword(Request $request)
    {
        $data = $request->validate([
            'email' => [
                'required',
                'email',
            ],
        ]);

        $email = strtolower(
            trim($data['email'])
        );

        $user = DB::table('users')
            ->where(
                'email',
                $email
            )
            ->where(
                'role',
                'PASSENGER'
            )
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' =>
                    'No passenger account was found with this email.',
            ], 404);
        }

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' =>
                    'This passenger account is currently inactive.',
            ], 403);
        }

        $otp = (string) random_int(
            100000,
            999999
        );

        DB::table('users')
            ->where(
                'id',
                $user->id
            )
            ->update([
                'password_reset_otp' =>
                    $otp,

                'password_reset_expires_at' =>
                    now()->addMinutes(10),

                'updated_at' =>
                    now(),
            ]);

        try {
            $this->mailService->otp(
                $user->email,
                $user->name,
                $otp,
                'Password Reset'
            );
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Unable to send password reset email. Please try again.',
            ], 500);
        }

        return response()->json([
            'success' => true,

            'message' =>
                'A password reset OTP has been sent to your email.',

            'email' =>
                $user->email,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | VERIFY PASSWORD RESET OTP
    |--------------------------------------------------------------------------
    */

    public function verifyResetOtp(Request $request)
    {
        $data = $request->validate([
            'email' => [
                'required',
                'email',
            ],

            'otp' => [
                'required',
                'string',
                'size:6',
            ],
        ]);

        $email = strtolower(
            trim($data['email'])
        );

        $user = DB::table('users')
            ->where(
                'email',
                $email
            )
            ->where(
                'role',
                'PASSENGER'
            )
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Passenger account not found.',
            ], 404);
        }

        if (
            empty(
                $user->password_reset_otp
            ) ||
            $user->password_reset_otp
                !== $data['otp']
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Invalid password reset OTP.',
            ], 422);
        }

        if (
            empty(
                $user->password_reset_expires_at
            ) ||
            now()->greaterThan(
                $user->password_reset_expires_at
            )
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Password reset OTP has expired.',
            ], 422);
        }

        return response()->json([
            'success' => true,

            'message' =>
                'OTP verified successfully.',

            'email' =>
                $user->email,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | RESET PASSWORD
    |--------------------------------------------------------------------------
    */

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'email' => [
                'required',
                'email',
            ],

            'otp' => [
                'required',
                'string',
                'size:6',
            ],

            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],
        ]);

        $email = strtolower(
            trim($data['email'])
        );

        $user = DB::table('users')
            ->where(
                'email',
                $email
            )
            ->where(
                'role',
                'PASSENGER'
            )
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Passenger account not found.',
            ], 404);
        }

        if (
            empty(
                $user->password_reset_otp
            ) ||
            $user->password_reset_otp
                !== $data['otp']
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Invalid password reset OTP.',
            ], 422);
        }

        if (
            empty(
                $user->password_reset_expires_at
            ) ||
            now()->greaterThan(
                $user->password_reset_expires_at
            )
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Password reset OTP has expired.',
            ], 422);
        }

        DB::transaction(
            function () use (
                $user,
                $data
            ) {
                DB::table('users')
                    ->where(
                        'id',
                        $user->id
                    )
                    ->update([
                        'password' =>
                            Hash::make(
                                $data['password']
                            ),

                        'password_reset_otp' =>
                            null,

                        'password_reset_expires_at' =>
                            null,

                        'updated_at' =>
                            now(),
                    ]);

                /*
                 * Logout all existing sessions.
                 */
                DB::table(
                    'passenger_api_tokens'
                )
                    ->where(
                        'user_id',
                        $user->id
                    )
                    ->delete();
            }
        );

        /*
         * Reload user for mail.
         */
        $updatedUser = DB::table('users')
            ->where(
                'id',
                $user->id
            )
            ->first();

        if ($updatedUser) {
            try {
                $this->mailService
                    ->passwordChanged(
                        $updatedUser
                    );
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json([
            'success' => true,
            'message' =>
                'Password reset successfully. Please login using your new password.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CURRENT PASSENGER
    |--------------------------------------------------------------------------
    */

    public function me(Request $request)
    {
        $passenger = $request
            ->attributes
            ->get(
                'passenger'
            );

        return response()->json([
            'success' => true,
            'passenger' =>
                $passenger,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | LOGOUT
    |--------------------------------------------------------------------------
    */

    public function logout(Request $request)
    {
        $tokenHash = $request
            ->attributes
            ->get(
                'passenger_token_hash'
            );

        if ($tokenHash) {
            DB::table(
                'passenger_api_tokens'
            )
                ->where(
                    'token_hash',
                    $tokenHash
                )
                ->delete();
        }

        return response()->json([
            'success' => true,
            'message' =>
                'Logged out successfully.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | FAILED LOGIN SECURITY
    |--------------------------------------------------------------------------
    */

    private function handleFailedLogin(
        Request $request,
        object $user
    ): void {
        $ip = (string) (
            $request->ip()
            ?? 'Unknown'
        );

        $key =
            'eastbus_failed_login_' .
            $user->id .
            '_' .
            sha1($ip);

        $attempts = (int) Cache::get(
            $key,
            0
        );

        $attempts++;

        Cache::put(
            $key,
            $attempts,
            now()->addMinutes(30)
        );

        /*
         * Send alert on the 3rd failed attempt.
         */
        if ($attempts === 3) {
            $device = (string) (
                $request->userAgent()
                ?? 'Unknown'
            );

            try {
                $this->mailService
                    ->securityAlert(
                        $user,
                        $ip,
                        $device
                    );
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CLEAR FAILED LOGIN COUNTER
    |--------------------------------------------------------------------------
    */

    private function clearFailedLogin(
        Request $request,
        object $user
    ): void {
        $ip = (string) (
            $request->ip()
            ?? 'Unknown'
        );

        $key =
            'eastbus_failed_login_' .
            $user->id .
            '_' .
            sha1($ip);

        Cache::forget($key);
    }
}