<?php

use App\Http\Controllers\AdminFixedScheduleController;

Route::get('/fixed-schedules', [AdminFixedScheduleController::class, 'index'])->name('fixed-schedules.index');
Route::get('/fixed-schedules/create', [AdminFixedScheduleController::class, 'create'])->name('fixed-schedules.create');
Route::post('/fixed-schedules', [AdminFixedScheduleController::class, 'store'])->name('fixed-schedules.store');
Route::get('/fixed-schedules/{id}/edit', [AdminFixedScheduleController::class, 'edit'])->name('fixed-schedules.edit');
Route::put('/fixed-schedules/{id}', [AdminFixedScheduleController::class, 'update'])->name('fixed-schedules.update');
Route::patch('/fixed-schedules/{id}/publish', [AdminFixedScheduleController::class, 'togglePublish'])->name('fixed-schedules.publish');
Route::patch('/fixed-schedules/{id}/active', [AdminFixedScheduleController::class, 'toggleActive'])->name('fixed-schedules.active');
Route::delete('/fixed-schedules/{id}', [AdminFixedScheduleController::class, 'destroy'])->name('fixed-schedules.destroy');
