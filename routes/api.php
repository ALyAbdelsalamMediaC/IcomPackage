<?php

use Illuminate\Support\Facades\Route;
use AlyIcom\MyPackage\Http\Controllers\Api\V1\UserNotificationController;
use AlyIcom\MyPackage\Http\Controllers\Api\V1\CheckUpdateController;

$prefix = config('my-package.api.prefix', 'api/v1');
$middleware = config('my-package.api.middleware', ['api']);

// Ensure middleware is an array
if (!is_array($middleware)) {
    $middleware = [$middleware];
}

Route::prefix($prefix)
    ->middleware($middleware)
    ->group(function () {
        // Notification routes
        Route::get('notifications', [UserNotificationController::class, 'all'])->name('notifications.all');
        Route::get('notifications/unread', [UserNotificationController::class, 'unread'])->name('notifications.unread');
        Route::post('notifications/{id}/read', [UserNotificationController::class, 'markAsRead'])->name('notifications.mark-as-read');
        Route::post('notifications/read-all', [UserNotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-as-read');
        
        // Check Update routes
        Route::get('check-update', [CheckUpdateController::class, 'get'])->name('check-update.get');
        Route::post('check-update', [CheckUpdateController::class, 'update'])->name('check-update.update');
    });

