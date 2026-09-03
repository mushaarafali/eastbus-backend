<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Services\EastBusMailService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PassengerAuthController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | REGISTER PASSENGER
    |--------------------------------------------------------------------------
    */

    public function register(Request $request)
    {
        $data = $request->validate([
            'full_name' => 'required|string|max:150',
            'email' => 'required|email|max:150|unique:users,email',
            'phone' => [
                'required',
                'string',
                'regex:/^\+94[0-9]{9}$/',
                'unique:users,phone',
            ],
            'password' => 'required|string|min:8',
        ], [
            'phone.regex' =>
                'Phone number must use Sri Lankan format: +94XXXXXXXXX',
        ]);

        $email = strtolower(trim($data['email']));

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
            $this->sendRegistrationOtp(
                $email,
                $data['full_name'],
                $otp
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
            'email' =>
                'required|email',

            'otp' =>
                'required|string|size:6',
        ]);

        $email =
            strtolower(
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

        app(EastBusMailService::class)->welcome(
            DB::table('users')->where('id', $user->id)->first()
        );

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
            'email' =>
                'required|email',
        ]);

        $email =
            strtolower(
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
            $this->sendRegistrationOtp(
                $user->email,
                $user->name,
                $otp
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
            'login' =>
                'required|string',

            'password' =>
                'required|string',
        ]);

        $login =
            trim($data['login']);

        $user = DB::table('users')
            ->where(
                'role',
                'PASSENGER'
            )
            ->where(
                function ($query) use ($login) {
                    $query
                        ->where(
                            'email',
                            strtolower($login)
                        )
                        ->orWhere(
                            'phone',
                            $login
                        );
                }
            )
            ->first();

        if (
            !$user ||
            !Hash::check(
                $data['password'],
                $user->password
            )
        ) {
            if ($user) {
                $key = 'eastbus_failed_login_' . $user->id;
                $attempts = (int) Cache::get($key, 0) + 1;
                Cache::put($key, $attempts, now()->addMinutes(30));

                if ($attempts === 3) {
                    app(EastBusMailService::class)->securityAlert(
                        $user,
                        (string) $request->ip(),
                        (string) $request->userAgent()
                    );
                }
            }

            return response()->json([
                'success' => false,
                'message' =>
                    'Invalid email/phone or password.',
            ], 401);
        }

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

        Cache::forget('eastbus_failed_login_' . $user->id);

        $plainToken =
            Str::random(64);

        $tokenHash =
            hash(
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
    | FORGOT PASSWORD - SEND RESET OTP
    |--------------------------------------------------------------------------
    */

    public function forgotPassword(Request $request)
    {
        $data = $request->validate([
            'email' =>
                'required|email',
        ]);

        $email =
            strtolower(
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
            $this->sendPasswordResetOtp(
                $user->email,
                $user->name,
                $otp
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
            'email' =>
                'required|email',

            'otp' =>
                'required|string|size:6',
        ]);

        $email =
            strtolower(
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
            'email' =>
                'required|email',

            'otp' =>
                'required|string|size:6',

            'password' =>
                'required|string|min:8|confirmed',
        ]);

        $email =
            strtolower(
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

                // Logout old passenger sessions after password change.
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

        app(EastBusMailService::class)->passwordChanged(
            DB::table('users')->where('id', $user->id)->first()
        );

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
        $passenger =
            $request->attributes->get(
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
        $tokenHash =
            $request->attributes->get(
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
    | REGISTRATION OTP EMAIL
    |--------------------------------------------------------------------------
    */

    private function sendRegistrationOtp(
        string $email,
        string $name,
        string $otp
    ): void {
        app(EastBusMailService::class)->otp($email, $name, $otp, 'Email Verification');
    }

    private function sendPasswordResetOtp(
        string $email,
        string $name,
        string $otp
    ): void {
        app(EastBusMailService::class)->otp($email, $name, $otp, 'Password Reset');
    }

    /*
    |--------------------------------------------------------------------------
    | EASTBUS EMAIL TEMPLATE
    |--------------------------------------------------------------------------
    */

    private function otpEmailTemplate(
        string $name,
        string $otp,
        string $title,
        string $message
    ): string {
        $safeName =
            e($name);

        $safeOtp =
            e($otp);

        $safeTitle =
            e($title);

        $safeMessage =
            e($message);

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
</head>

<body style="
    margin:0;
    padding:30px;
    background:#f4f7fb;
    font-family:Arial,sans-serif;
">

<div style="
    max-width:560px;
    margin:0 auto;
    background:#ffffff;
    border-radius:14px;
    overflow:hidden;
    box-shadow:0 4px 18px rgba(0,0,0,0.08);
">

    <div style="
        background:#064BD8;
        padding:24px;
        text-align:center;
    ">
        <div style="
            color:white;
            font-size:28px;
            font-weight:800;
        ">
            EastBus.lk
        </div>

        <div style="
            color:#dbe8ff;
            margin-top:5px;
            font-size:13px;
        ">
            Passenger Service
        </div>
    </div>

    <div style="
        padding:30px;
    ">

        <h2 style="
            color:#0A1E52;
            margin-top:0;
        ">
            {$safeTitle}
        </h2>

        <p>
            Hello {$safeName},
        </p>

        <p style="
            color:#555;
            line-height:1.6;
        ">
            {$safeMessage}
        </p>

        <div style="
            background:#eef4ff;
            border:1px solid #d7e4ff;
            border-radius:12px;
            padding:24px;
            margin:25px 0;
            text-align:center;
        ">

            <div style="
                color:#6b7280;
                font-size:12px;
                margin-bottom:10px;
            ">
                YOUR VERIFICATION CODE
            </div>

            <div style="
                color:#064BD8;
                font-size:34px;
                font-weight:800;
                letter-spacing:8px;
            ">
                {$safeOtp}
            </div>

        </div>

        <p style="
            color:#555;
        ">
            This code is valid for
            <strong>10 minutes</strong>.
        </p>

        <p style="
            color:#777;
            font-size:13px;
            line-height:1.5;
        ">
            If you did not request this code,
            you can safely ignore this email.
        </p>

    </div>

    <div style="
        background:#f7f9fd;
        padding:18px;
        text-align:center;
        color:#777;
        font-size:12px;
    ">
        EastBus.lk — Smart Passenger Transportation
    </div>

</div>

</body>
</html>
HTML;
    }
}