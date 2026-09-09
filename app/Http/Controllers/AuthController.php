<?php

namespace App\Http\Controllers;

use App\Models\Operator;
use App\Models\User;
use App\Services\EastBusMailService;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Form
    |--------------------------------------------------------------------------
    */

    public function loginForm()
    {
        return view('auth.login');
    }

    /*
    |--------------------------------------------------------------------------
    | Login
    |--------------------------------------------------------------------------
    */

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors([
                    'email' => 'Invalid email or password.',
                ])
                ->onlyInput('email');
        }

        /*
         * Prevent session fixation.
         */
        $request->session()->regenerate();

        $user = Auth::user();

        if (!$user) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => 'Unable to login. Please try again.',
                ]);
        }

        /*
         * Only active users may enter the portal.
         */
        if (!(bool) $user->is_active) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => 'Your account is inactive.',
                ])
                ->onlyInput('email');
        }

        /*
         * Only portal roles are allowed here.
         */
        if (!in_array($user->role, ['admin', 'operator'], true)) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => 'This account cannot access the management portal.',
                ]);
        }

        Audit::log('Login', 'Authentication');

        if ($user->role === 'admin') {
            return redirect()->route('admin.dashboard');
        }

        return redirect()->route('operator.dashboard');
    }

    /*
    |--------------------------------------------------------------------------
    | Operator Registration Form
    |--------------------------------------------------------------------------
    */

    public function registerForm()
    {
        return view('auth.operator-register');
    }

    /*
    |--------------------------------------------------------------------------
    | Register Operator
    |--------------------------------------------------------------------------
    */

    public function registerOperator(Request $request)
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:150'],
            'owner_name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],

            'password' => [
                'required',
                'confirmed',
                Password::min(8),
            ],
        ]);

        $user = User::create([
            'name' => trim($data['owner_name']),
            'email' => strtolower(trim($data['email'])),
            'phone' => trim($data['phone']),
            'password' => Hash::make($data['password']),
            'role' => 'operator',
            'is_active' => false,
        ]);

        $operator = Operator::create([
            'user_id' => $user->id,
            'company_name' => trim($data['company_name']),
            'owner_name' => trim($data['owner_name']),
            'email' => strtolower(trim($data['email'])),
            'phone' => trim($data['phone']),
            'address' => isset($data['address'])
                ? trim((string) $data['address'])
                : null,
            'status' => 'pending',
            'is_published' => false,
        ]);

        $adminEmails = User::query()
            ->where('role', 'admin')
            ->where('is_active', true)
            ->whereNotNull('email')
            ->pluck('email');

        foreach ($adminEmails as $adminEmail) {
            try {
                app(EastBusMailService::class)->send(
                    $adminEmail,
                    'EastBus.lk - New Operator Registration',
                    'New Operator Approval Required',
                    'A new bus operator has registered and is waiting for approval.',
                    [
                        'Company' => $operator->company_name,
                        'Owner' => $operator->owner_name,
                        'Email' => $operator->email,
                    ],
                    'ADMIN'
                );
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect()
            ->route('login')
            ->with(
                'success',
                'Registration submitted. Admin approval is required before login.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Logout
    |--------------------------------------------------------------------------
    */

    public function logout(Request $request)
    {
        if (Auth::check()) {
            Audit::log('Logout', 'Authentication');
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}