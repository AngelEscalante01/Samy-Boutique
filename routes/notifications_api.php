<?php

use App\Http\Controllers\Api\NotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', \App\Http\Middleware\EnsureActiveApiUser::class])->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'count']);
    Route::get('/notifications/recipients', [NotificationController::class, 'recipients'])
        ->middleware('can:notifications.send');
    Route::get('/notifications/sent-history', [NotificationController::class, 'sentHistory'])
        ->middleware('can:notifications.send');
    Route::post('/notifications/manual', [NotificationController::class, 'sendManual'])
        ->middleware(['can:notifications.send', 'throttle:5,1']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::get('/notifications/{notification}', [NotificationController::class, 'show'])->whereNumber('notification');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->whereNumber('notification');
    Route::get('/notification-preferences', [NotificationController::class, 'preferences']);
    Route::put('/notification-preferences', [NotificationController::class, 'updatePreferences']);
    Route::post('/notification-devices', [NotificationController::class, 'registerDevice'])->middleware('throttle:30,1');
    Route::put('/notification-devices', [NotificationController::class, 'registerDevice'])->middleware('throttle:30,1');
    Route::delete('/notification-devices/{installationId}', [NotificationController::class, 'unregisterDevice'])->whereUuid('installationId');
});
