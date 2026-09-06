<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminFixedScheduleController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\OperatorController;
use App\Http\Controllers\OperatorRouteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Home
|--------------------------------------------------------------------------
*/

Route::get('/', fn () => redirect()->route('login'));

/*
|--------------------------------------------------------------------------
| Guest Routes
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {

    Route::get(
        '/login',
        [AuthController::class, 'loginForm']
    )->name('login');

    Route::post(
        '/login',
        [AuthController::class, 'login']
    )->name('login.post');

    Route::get(
        '/operator/register',
        [AuthController::class, 'registerForm']
    )->name('operator.register');

    Route::post(
        '/operator/register',
        [AuthController::class, 'registerOperator']
    )->name('operator.register.post');
});

/*
|--------------------------------------------------------------------------
| Logout
|--------------------------------------------------------------------------
*/

Route::post(
    '/logout',
    [AuthController::class, 'logout']
)
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
*/

Route::prefix('admin')
    ->name('admin.')
    ->middleware([
        'auth',
        'role:admin',
    ])
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Dashboard
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/dashboard',
            [AdminController::class, 'dashboard']
        )->name('dashboard');

        /*
        |--------------------------------------------------------------------------
        | Operators
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/operators',
            [AdminController::class, 'operators']
        )->name('operators');

        Route::patch(
            '/operators/{operator}/toggle',
            [AdminController::class, 'toggleOperator']
        )->name('operators.toggle');

        /*
        |--------------------------------------------------------------------------
        | Passengers
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/passengers',
            [AdminController::class, 'passengers']
        )->name('passengers');

        Route::patch(
            '/passengers/{user}/toggle',
            [AdminController::class, 'togglePassenger']
        )->name('passengers.toggle');

        /*
        |--------------------------------------------------------------------------
        | Buses
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/buses',
            [AdminController::class, 'buses']
        )->name('buses');

        /*
        |--------------------------------------------------------------------------
        | Master Routes
        |--------------------------------------------------------------------------
        |
        | System Admin manages all master routes and fixed roadways.
        |
        | Example:
        | 86
        | 04/86
        | 48
        | 41/48
        | 22
        | 98
        | 38
        | 21
        | 76/3
        | 35
        |
        */

        Route::get(
            '/routes',
            [AdminController::class, 'masterRoutes']
        )->name('routes.index');

        Route::get(
            '/routes/create',
            [AdminController::class, 'createMasterRoute']
        )->name('routes.create');

        Route::post(
            '/routes',
            [AdminController::class, 'storeMasterRoute']
        )->name('routes.store');

        Route::get(
            '/routes/{id}/edit',
            [AdminController::class, 'editMasterRoute']
        )->name('routes.edit');

        Route::put(
            '/routes/{id}',
            [AdminController::class, 'updateMasterRoute']
        )->name('routes.update');

        Route::delete(
            '/routes/{id}',
            [AdminController::class, 'destroyMasterRoute']
        )->name('routes.destroy');

        /*
        |--------------------------------------------------------------------------
        | Trips
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/trips',
            [AdminController::class, 'trips']
        )->name('trips');

        /*
        |--------------------------------------------------------------------------
        | Bookings
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/bookings',
            [AdminController::class, 'bookings']
        )->name('bookings');

        /*
        |--------------------------------------------------------------------------
        | Payments
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/payments',
            [AdminController::class, 'payments']
        )->name('payments');

        /*
        |--------------------------------------------------------------------------
        | Tracking
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/tracking',
            [AdminController::class, 'tracking']
        )->name('tracking');

        /*
        |--------------------------------------------------------------------------
        | Reports
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/reports',
            [AdminController::class, 'reports']
        )->name('reports');

        /*
        |--------------------------------------------------------------------------
        | Notifications
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/notifications',
            [AdminController::class, 'notifications']
        )->name('notifications');

        Route::post(
            '/notifications',
            [AdminController::class, 'sendNotification']
        )->name('notifications.send');

        /*
        |--------------------------------------------------------------------------
        | Alerts
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/alerts',
            [AdminController::class, 'alerts']
        )->name('alerts');

        /*
        |--------------------------------------------------------------------------
        | Logs
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/logs',
            [AdminController::class, 'logs']
        )->name('logs');

        /*
        |--------------------------------------------------------------------------
        | Settings
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/settings',
            [AdminController::class, 'settings']
        )->name('settings');

        /*
        |--------------------------------------------------------------------------
        | Backup
        |--------------------------------------------------------------------------
        */

        Route::post(
            '/backup',
            [AdminController::class, 'backup']
        )->name('backup');

        /*
        |--------------------------------------------------------------------------
        | Fixed Schedules
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/fixed-schedules',
            [AdminFixedScheduleController::class, 'index']
        )->name('fixed-schedules.index');

        Route::get(
            '/fixed-schedules/create',
            [AdminFixedScheduleController::class, 'create']
        )->name('fixed-schedules.create');

        Route::post(
            '/fixed-schedules',
            [AdminFixedScheduleController::class, 'store']
        )->name('fixed-schedules.store');

        /*
         * Load roadway stops from selected Master Route.
         *
         * Keep this before /fixed-schedules/{id}/edit.
         */

        Route::get(
            '/fixed-schedules/routes/{routeId}/stops',
            [
                AdminFixedScheduleController::class,
                'routeStops',
            ]
        )->name('fixed-schedules.route-stops');

        Route::get(
            '/fixed-schedules/{id}/edit',
            [AdminFixedScheduleController::class, 'edit']
        )->name('fixed-schedules.edit');

        Route::put(
            '/fixed-schedules/{id}',
            [AdminFixedScheduleController::class, 'update']
        )->name('fixed-schedules.update');

        Route::patch(
            '/fixed-schedules/{id}/publish',
            [AdminFixedScheduleController::class, 'togglePublish']
        )->name('fixed-schedules.publish');

        Route::patch(
            '/fixed-schedules/{id}/active',
            [AdminFixedScheduleController::class, 'toggleActive']
        )->name('fixed-schedules.active');

        Route::delete(
            '/fixed-schedules/{id}',
            [AdminFixedScheduleController::class, 'destroy']
        )->name('fixed-schedules.destroy');
    });

/*
|--------------------------------------------------------------------------
| Operator Routes
|--------------------------------------------------------------------------
*/

Route::prefix('operator')
    ->name('operator.')
    ->middleware([
        'auth',
        'role:operator',
    ])
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Dashboard
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/dashboard',
            [OperatorController::class, 'dashboard']
        )->name('dashboard');

        /*
        |--------------------------------------------------------------------------
        | Profile
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/profile',
            [OperatorController::class, 'profile']
        )->name('profile');

        Route::put(
            '/profile',
            [OperatorController::class, 'updateProfile']
        )->name('profile.update');

        /*
        |--------------------------------------------------------------------------
        | Buses
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/buses',
            [OperatorController::class, 'buses']
        )->name('buses');

        Route::post(
            '/buses',
            [OperatorController::class, 'storeBus']
        )->name('buses.store');

        Route::put(
            '/buses/{bus}',
            [OperatorController::class, 'updateBus']
        )->name('buses.update');

        Route::get(
            '/buses/{bus}/seats',
            [OperatorController::class, 'seats']
        )->name('seats');

        Route::patch(
            '/buses/{bus}/seats/{seat}',
            [OperatorController::class, 'toggleSeat']
        )->name('seats.toggle');

        /*
        |--------------------------------------------------------------------------
        | Staff
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/staff',
            [OperatorController::class, 'staff']
        )->name('staff');

        Route::post(
            '/staff',
            [OperatorController::class, 'storeStaff']
        )->name('staff.store');

        Route::patch(
            '/staff/{staff}/toggle',
            [OperatorController::class, 'toggleStaff']
        )->name('staff.toggle');

        /*
        |--------------------------------------------------------------------------
        | Operator Bus Services
        |--------------------------------------------------------------------------
        |
        | Operator does NOT create or edit master routes.
        |
        | Operator:
        |
        | Select Bus
        |      ↓
        | Select Master Route
        |      ↓
        | Load Fixed Roadway
        |      ↓
        | Select Booking Points
        |      ↓
        | Add Times
        |
        | Existing /routes URL names are retained so old navigation
        | does not need immediate changes.
        |
        */

        Route::get(
            '/routes',
            [OperatorRouteController::class, 'index']
        )->name('routes.index');

        Route::get(
            '/routes/create',
            [OperatorRouteController::class, 'create']
        )->name('routes.create');

        /*
         * AJAX:
         * Load fixed roadway from selected master route.
         *
         * Keep this before /routes/{route}/edit.
         */

        Route::get(
            '/routes/master/{routeId}/stops',
            [
                OperatorRouteController::class,
                'routeStops',
            ]
        )->name('routes.stops');

        Route::post(
            '/routes',
            [OperatorRouteController::class, 'store']
        )->name('routes.store');

        Route::get(
            '/routes/{route}/edit',
            [OperatorRouteController::class, 'edit']
        )->name('routes.edit');

        Route::put(
            '/routes/{route}',
            [OperatorRouteController::class, 'update']
        )->name('routes.update');

        Route::delete(
            '/routes/{route}',
            [OperatorRouteController::class, 'destroy']
        )->name('routes.destroy');

        /*
        |--------------------------------------------------------------------------
        | Trips
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/trips',
            [OperatorController::class, 'trips']
        )->name('trips');

        Route::post(
            '/trips',
            [OperatorController::class, 'storeTrip']
        )->name('trips.store');

        Route::get(
            '/trips/{trip}/edit',
            [OperatorController::class, 'editTrip']
        )->name('trips.edit');

        Route::patch(
            '/trips/{trip}',
            [OperatorController::class, 'updateTrip']
        )->name('trips.update');

        Route::patch(
            '/trips/{trip}/publish',
            [OperatorController::class, 'toggleTripPublish']
        )->name('trips.publish');

        /*
        |--------------------------------------------------------------------------
        | Bookings
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/bookings',
            [OperatorController::class, 'bookings']
        )->name('bookings');

        Route::patch(
            '/bookings/{booking}',
            [OperatorController::class, 'updateBooking']
        )->name('bookings.update');

        /*
        |--------------------------------------------------------------------------
        | Tracking
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/tracking',
            [OperatorController::class, 'tracking']
        )->name('tracking');

        /*
        |--------------------------------------------------------------------------
        | Payments
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/payments',
            [OperatorController::class, 'payments']
        )->name('payments');

        /*
        |--------------------------------------------------------------------------
        | Reports
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/reports',
            [OperatorController::class, 'reports']
        )->name('reports');

        /*
        |--------------------------------------------------------------------------
        | Notifications
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/notifications',
            [OperatorController::class, 'notifications']
        )->name('notifications');

        /*
        |--------------------------------------------------------------------------
        | Emergency Alerts
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/alerts',
            [OperatorController::class, 'alerts']
        )->name('alerts');

        Route::patch(
            '/alerts/{alert}/resolve',
            [OperatorController::class, 'resolveAlert']
        )->name('alerts.resolve');
    });