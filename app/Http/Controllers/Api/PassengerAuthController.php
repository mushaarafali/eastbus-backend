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
    private const OTP_EXPIRY_MINUTES = 10;
    private const FAILED_LOGIN_EXPIRY_MINUTES = 30;
    private const SECURITY_ALERT_ATTEMPT = 3;

    public function __construct(
        private EastBusMailService $mailService
    ) {
    }

    /*
    |--------------------------------------------------------------------------
    | Register Passenger
    |--------------------------------------------------------------------------
    */

    public function register(Request $request)
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['required', 'string', 'regex:/^\+94[0-9]{9}$/'],
            'password' => ['required', 'string', 'min:8'],
        ], [
            'phone.regex' => 'Phone number must use Sri Lankan format: +94XXXXXXXXX',
        ]);

        $fullName = trim($data['full_name']);
        $email = strtolower(trim($data['email']));
        $phone = trim($data['phone']);

        if (
            DB::table('users')
                ->whereRaw('LOWER(email) = ?', [$email])
                ->exists()
        ) {
            return response()->json([
                'success' => false,
                'message' => 'This email address is already registered.',
                'errors' => [
                    'email' => ['This email address is already registered.'],
                ],
            ], 422);
        }

        if (
            DB::table('users')
                ->where('phone', $phone)
                ->exists()
        ) {
            return response()->json([
                'success' => false,
                'message' => 'This phone number is already registered.',
                'errors' => [
                    'phone' => ['This phone number is already registered.'],
                ],
            ], 422);
        }

        $otp = $this->generateOtp();

        $passengerId = DB::table('users')->insertGetId([
            'name' => $fullName,
            'email' => $email,
            'phone' => $phone,
            'password' => Hash::make($data['password']),
            'role' => 'PASSENGER',
            'is_active' => true,
            'status' => 'PENDING',
            'email_verified_at' => null,
            'otp_code' => $otp,
            'otp_expires_at' => now()->addMinutes(self::OTP_EXPIRY_MINUTES),
            'password_reset_otp' => null,
            'password_reset_expires_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $this->mailService->otp(
                $email,
                $fullName,
                $otp,
                'Email Verification'
            );
        } catch (\Throwable $e) {
            report($e);

            DB::table('users')
                ->where('id', $passengerId)
                ->delete();

            return response()->json([
                'success' => false,
                'message' => 'Unable to send verification email. Please try again.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Registration successful. A verification OTP has been sent to your email.',
            'passenger_id' => $passengerId,
            'email' => $email,
            'requires_verification' => true,
            'otp_expires_in_minutes' => self::OTP_EXPIRY_MINUTES,
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | Verify Registration OTP
    |--------------------------------------------------------------------------
    */

    public function verifyOtp(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'otp' => ['required', 'digits:6'],
        ]);

        $email = strtolower(trim($data['email']));
        $otp = trim((string) $data['otp']);

        $user = $this->findPassengerByEmail($email);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Passenger account not found.',
            ], 404);
        }

        if (
            strtoupper((string) $user->status) === 'ACTIVE' &&
            $user->email_verified_at !== null
        ) {
            return response()->json([
                'success' => true,
                'message' => 'Email is already verified. You can login.',
                'already_verified' => true,
            ]);
        }

        if (
            empty($user->otp_code) ||
            !hash_equals(
                (string) $user->otp_code,
                $otp
            )
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid verification OTP.',
            ], 422);
        }

        if (
            empty($user->otp_expires_at) ||
            now()->greaterThan($user->otp_expires_at)
        ) {
            return response()->json([
                'success' => false,
                'message' => 'OTP has expired. Please request a new OTP.',
                'otp_expired' => true,
            ], 422);
        }

        DB::table('users')
            ->where('id', $user->id)
            ->update([
                'status' => 'ACTIVE',
                'is_active' => true,
                'email_verified_at' => now(),
                'otp_code' => null,
                'otp_expires_at' => null,
                'updated_at' => now(),
            ]);

        $verifiedUser = DB::table('users')
            ->where('id', $user->id)
            ->first();

        if ($verifiedUser) {
            try {
                $this->mailService->welcome($verifiedUser);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully. You can now login.',
            'already_verified' => false,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Resend Registration OTP
    |--------------------------------------------------------------------------
    */

    public function resendOtp(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = strtolower(trim($data['email']));
        $user = $this->findPassengerByEmail($email);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Passenger account not found.',
            ], 404);
        }

        if (
            strtoupper((string) $user->status) === 'ACTIVE' &&
            $user->email_verified_at !== null
        ) {
            return response()->json([
                'success' => false,
                'message' => 'This account is already verified.',
            ], 422);
        }

        $otp = $this->generateOtp();

        DB::table('users')
            ->where('id', $user->id)
            ->update([
                'otp_code' => $otp,
                'otp_expires_at' => now()->addMinutes(self::OTP_EXPIRY_MINUTES),
                'updated_at' => now(),
            ]);

        try {
            $this->mailService->otp(
                $user->email,
                $user->name,
                $otp,
                'Email Verification'
            );
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to send OTP email. Please try again.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'A new verification OTP has been sent to your email.',
            'email' => $user->email,
            'otp_expires_in_minutes' => self::OTP_EXPIRY_MINUTES,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Login
    |--------------------------------------------------------------------------
    */

    public function login(Request $request)
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:150'],
            'password' => ['required', 'string'],
        ]);

        $login = trim($data['login']);

        $user = DB::table('users')
            ->where('role', 'PASSENGER')
            ->where(function ($query) use ($login) {
                $query
                    ->whereRaw('LOWER(email) = ?', [
                        strtolower($login),
                    ])
                    ->orWhere('phone', $login);
            })
            ->first();

        if (
            !$user ||
            !Hash::check($data['password'], $user->password)
        ) {
            if ($user) {
                $this->handleFailedLogin(
                    $request,
                    $user
                );
            }

            return response()->json([
                'success' => false,
                'message' => 'Invalid email/phone or password.',
            ], 401);
        }

        $this->clearFailedLogin(
            $request,
            $user
        );

        if (!(bool) $user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Your passenger account has been deactivated.',
            ], 403);
        }

        if (
            strtoupper((string) $user->status) !== 'ACTIVE' ||
            $user->email_verified_at === null
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Please verify your email before login.',
                'requires_verification' => true,
                'email' => $user->email,
            ], 403);
        }

        $plainToken = Str::random(64);
        $tokenHash = hash('sha256', $plainToken);

        DB::table('passenger_api_tokens')->insert([
            'user_id' => $user->id,
            'token_hash' => $tokenHash,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'token' => $plainToken,

            'passenger' => [
                'id' => (int) $user->id,
                'full_name' => $user->name,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'email_verified' =>
                    $user->email_verified_at !== null,
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Forgot Password
    |--------------------------------------------------------------------------
    */

    public function forgotPassword(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = strtolower(trim($data['email']));
        $user = $this->findPassengerByEmail($email);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No passenger account was found with this email.',
            ], 404);
        }

        if (!(bool) $user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'This passenger account is currently inactive.',
            ], 403);
        }

        if (
            strtoupper((string) $user->status) !== 'ACTIVE' ||
            $user->email_verified_at === null
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Please verify your email before resetting your password.',
                'requires_verification' => true,
                'email' => $user->email,
            ], 403);
        }

        $otp = $this->generateOtp();

        DB::table('users')
            ->where('id', $user->id)
            ->update([
                'password_reset_otp' => $otp,
                'password_reset_expires_at' =>
                    now()->addMinutes(self::OTP_EXPIRY_MINUTES),
                'updated_at' => now(),
            ]);

        try {
            $this->mailService->otp(
                $user->email,
                $user->name,
                $otp,
                'Password Reset'
            );
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to send password reset email. Please try again.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'A password reset OTP has been sent to your email.',
            'email' => $user->email,
            'otp_expires_in_minutes' => self::OTP_EXPIRY_MINUTES,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Verify Password Reset OTP
    |--------------------------------------------------------------------------
    */

    public function verifyResetOtp(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'otp' => ['required', 'digits:6'],
        ]);

        $email = strtolower(trim($data['email']));
        $otp = trim((string) $data['otp']);

        $user = $this->findPassengerByEmail($email);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Passenger account not found.',
            ], 404);
        }

        $validation = $this->validateResetOtp(
            $user,
            $otp
        );

        if ($validation !== null) {
            return $validation;
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP verified successfully.',
            'email' => $user->email,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Reset Password
    |--------------------------------------------------------------------------
    */

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'otp' => ['required', 'digits:6'],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],
        ]);

        $email = strtolower(trim($data['email']));
        $otp = trim((string) $data['otp']);

        $user = $this->findPassengerByEmail($email);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Passenger account not found.',
            ], 404);
        }

        if (!(bool) $user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'This passenger account is currently inactive.',
            ], 403);
        }

        $validation = $this->validateResetOtp(
            $user,
            $otp
        );

        if ($validation !== null) {
            return $validation;
        }

        DB::transaction(function () use ($user, $data) {
            DB::table('users')
                ->where('id', $user->id)
                ->update([
                    'password' => Hash::make(
                        $data['password']
                    ),
                    'password_reset_otp' => null,
                    'password_reset_expires_at' => null,
                    'updated_at' => now(),
                ]);

            /*
             * Logout every existing passenger API session.
             */
            DB::table('passenger_api_tokens')
                ->where('user_id', $user->id)
                ->delete();
        });

        $updatedUser = DB::table('users')
            ->where('id', $user->id)
            ->first();

        if ($updatedUser) {
            try {
                $this->mailService->passwordChanged(
                    $updatedUser
                );
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully. Please login using your new password.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Current Passenger
    |--------------------------------------------------------------------------
    */

    public function me(Request $request)
    {
        $passenger = $request->attributes->get(
            'passenger'
        );

        abort_unless(
            $passenger,
            401,
            'Passenger authentication required.'
        );

        return response()->json([
            'success' => true,

            'passenger' => [
                'id' => (int) $passenger->id,
                'full_name' => $passenger->name,
                'name' => $passenger->name,
                'email' => $passenger->email,
                'phone' => $passenger->phone,
                'role' => $passenger->role,
                'status' => $passenger->status,
                'is_active' => (bool) $passenger->is_active,
                'email_verified' =>
                    $passenger->email_verified_at !== null,
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Logout
    |--------------------------------------------------------------------------
    */

    public function logout(Request $request)
    {
        $tokenHash = $request->attributes->get(
            'passenger_token_hash'
        );

        if ($tokenHash) {
            DB::table('passenger_api_tokens')
                ->where('token_hash', $tokenHash)
                ->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Find Passenger by Email
    |--------------------------------------------------------------------------
    */

    private function findPassengerByEmail(
        string $email
    ): ?object {
        return DB::table('users')
            ->where('role', 'PASSENGER')
            ->whereRaw(
                'LOWER(email) = ?',
                [strtolower(trim($email))]
            )
            ->first();
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Password Reset OTP
    |--------------------------------------------------------------------------
    */

    private function validateResetOtp(
        object $user,
        string $otp
    ) {
        if (
            empty($user->password_reset_otp) ||
            !hash_equals(
                (string) $user->password_reset_otp,
                $otp
            )
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid password reset OTP.',
            ], 422);
        }

        if (
            empty($user->password_reset_expires_at) ||
            now()->greaterThan(
                $user->password_reset_expires_at
            )
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Password reset OTP has expired.',
                'otp_expired' => true,
            ], 422);
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Generate OTP
    |--------------------------------------------------------------------------
    */

    private function generateOtp(): string
    {
        return (string) random_int(
            100000,
            999999
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Failed Login Security
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
            now()->addMinutes(
                self::FAILED_LOGIN_EXPIRY_MINUTES
            )
        );

        if ($attempts === self::SECURITY_ALERT_ATTEMPT) {
            $device = (string) (
                $request->userAgent()
                ?? 'Unknown'
            );

            try {
                $this->mailService->securityAlert(
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
    | Clear Failed Login Counter
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