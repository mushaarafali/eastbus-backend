<?php

use App\Http\Controllers\OperatorRouteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Add this INSIDE your existing operator-authenticated web route group.
|--------------------------------------------------------------------------
|
| If your project already has:
|
| Route::middleware(...)->prefix('operator')->group(function () {
|     ...
| });
|
| add only the inner Route::resource(...) line there.
|
*/

Route::prefix('operator')->group(function () {
    Route::resource('routes', OperatorRouteController::class)
        ->except(['show'])
        ->names('operator.routes');
});
