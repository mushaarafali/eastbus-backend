<?php

use App\Http\Controllers\Api\FixedScheduleController;

Route::get('/passenger/fixed-schedules/search', [FixedScheduleController::class, 'search']);
Route::get('/passenger/fixed-schedules/{id}', [FixedScheduleController::class, 'show']);
