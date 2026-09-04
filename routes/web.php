<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminFixedScheduleController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\OperatorController;
use App\Http\Controllers\OperatorRouteController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'loginForm'])
        ->name('login');

    Route::post('/login', [AuthController::class, 'login'])
        ->name('login.post');

    Route::get('/operator/register', [AuthController::class, 'registerForm'])
        ->name('operator.register');

    Route::post('/operator/register', [AuthController::class, 'registerOperator'])
        ->name('operator.register.post');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'role:admin'])
    ->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard'])
            ->name('dashboard');

        Route::get('/operators', [AdminController::class, 'operators'])
            ->name('operators');

        Route::patch('/operators/{operator}/toggle', [AdminController::class, 'toggleOperator'])
            ->name('operators.toggle');

        Route::get('/passengers', [AdminController::class, 'passengers'])
            ->name('passengers');

        Route::patch('/passengers/{user}/toggle', [AdminController::class, 'togglePassenger'])
            ->name('passengers.toggle');

        Route::get('/buses', [AdminController::class, 'buses'])
            ->name('buses');

        Route::get('/trips', [AdminController::class, 'trips'])
            ->name('trips');

        Route::get('/bookings', [AdminController::class, 'bookings'])
            ->name('bookings');

        Route::get('/payments', [AdminController::class, 'payments'])
            ->name('payments');

        Route::get('/tracking', [AdminController::class, 'tracking'])
            ->name('tracking');

        Route::get('/reports', [AdminController::class, 'reports'])
            ->name('reports');

        Route::get('/notifications', [AdminController::class, 'notifications'])
            ->name('notifications');

        Route::post('/notifications', [AdminController::class, 'sendNotification'])
            ->name('notifications.send');

        Route::get('/alerts', [AdminController::class, 'alerts'])
            ->name('alerts');

        Route::get('/logs', [AdminController::class, 'logs'])
            ->name('logs');

        Route::get('/settings', [AdminController::class, 'settings'])
            ->name('settings');

        Route::post('/backup', [AdminController::class, 'backup'])
            ->name('backup');

        Route::get('/fixed-schedules', [AdminFixedScheduleController::class, 'index'])
            ->name('fixed-schedules.index');

        Route::get('/fixed-schedules/create', [AdminFixedScheduleController::class, 'create'])
            ->name('fixed-schedules.create');

        Route::post('/fixed-schedules', [AdminFixedScheduleController::class, 'store'])
            ->name('fixed-schedules.store');

        Route::get('/fixed-schedules/{id}/edit', [AdminFixedScheduleController::class, 'edit'])
            ->name('fixed-schedules.edit');

        Route::put('/fixed-schedules/{id}', [AdminFixedScheduleController::class, 'update'])
            ->name('fixed-schedules.update');

        Route::patch('/fixed-schedules/{id}/publish', [AdminFixedScheduleController::class, 'togglePublish'])
            ->name('fixed-schedules.publish');

        Route::patch('/fixed-schedules/{id}/active', [AdminFixedScheduleController::class, 'toggleActive'])
            ->name('fixed-schedules.active');

        Route::delete('/fixed-schedules/{id}', [AdminFixedScheduleController::class, 'destroy'])
            ->name('fixed-schedules.destroy');
    });

Route::prefix('operator')
    ->name('operator.')
    ->middleware(['auth', 'role:operator'])
    ->group(function () {
        Route::get('/dashboard', [OperatorController::class, 'dashboard'])
            ->name('dashboard');

        Route::get('/profile', [OperatorController::class, 'profile'])
            ->name('profile');

        Route::put('/profile', [OperatorController::class, 'updateProfile'])
            ->name('profile.update');

        Route::get('/buses', [OperatorController::class, 'buses'])
            ->name('buses');

        Route::post('/buses', [OperatorController::class, 'storeBus'])
            ->name('buses.store');

        Route::put('/buses/{bus}', [OperatorController::class, 'updateBus'])
            ->name('buses.update');

        Route::get('/buses/{bus}/seats', [OperatorController::class, 'seats'])
            ->name('seats');

        Route::patch('/buses/{bus}/seats/{seat}', [OperatorController::class, 'toggleSeat'])
            ->name('seats.toggle');

        Route::get('/staff', [OperatorController::class, 'staff'])
            ->name('staff');

        Route::post('/staff', [OperatorController::class, 'storeStaff'])
            ->name('staff.store');

        Route::patch('/staff/{staff}/toggle', [OperatorController::class, 'toggleStaff'])
            ->name('staff.toggle');

        Route::get('/routes', [OperatorRouteController::class, 'index'])
            ->name('routes.index');

        Route::get('/routes/create', [OperatorRouteController::class, 'create'])
            ->name('routes.create');

        Route::post('/routes', [OperatorRouteController::class, 'store'])
            ->name('routes.store');

        Route::get('/routes/{route}/edit', [OperatorRouteController::class, 'edit'])
            ->name('routes.edit');

        Route::put('/routes/{route}', [OperatorRouteController::class, 'update'])
            ->name('routes.update');

        Route::delete('/routes/{route}', [OperatorRouteController::class, 'destroy'])
            ->name('routes.destroy');

        Route::get('/trips', [OperatorController::class, 'trips'])
            ->name('trips');

        Route::post('/trips', [OperatorController::class, 'storeTrip'])
            ->name('trips.store');

        Route::get('/trips/{trip}/edit', [OperatorController::class, 'editTrip'])
            ->name('trips.edit');

        Route::patch('/trips/{trip}', [OperatorController::class, 'updateTrip'])
            ->name('trips.update');

        Route::patch('/trips/{trip}/publish', [OperatorController::class, 'toggleTripPublish'])
            ->name('trips.publish');

        Route::get('/bookings', [OperatorController::class, 'bookings'])
            ->name('bookings');

        Route::patch('/bookings/{booking}', [OperatorController::class, 'updateBooking'])
            ->name('bookings.update');

        Route::get('/tracking', [OperatorController::class, 'tracking'])
            ->name('tracking');

        Route::get('/payments', [OperatorController::class, 'payments'])
            ->name('payments');

        Route::get('/reports', [OperatorController::class, 'reports'])
            ->name('reports');

        Route::get('/notifications', [OperatorController::class, 'notifications'])
            ->name('notifications');

        Route::get('/alerts', [OperatorController::class, 'alerts'])
            ->name('alerts');

        Route::patch('/alerts/{alert}/resolve', [OperatorController::class, 'resolveAlert'])
            ->name('alerts.resolve');
    });