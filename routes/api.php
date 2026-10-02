<?php

declare(strict_types=1);

use App\Http\Controllers\NotificationApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| JSON API のルート定義。
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->prefix('v1/notifications')->group(function () {
    Route::get('/', [NotificationApiController::class, 'index']);
    Route::post('/read-all', [NotificationApiController::class, 'markAllAsRead']);
    Route::post('/{notification}/read', [NotificationApiController::class, 'markAsRead']);
});
