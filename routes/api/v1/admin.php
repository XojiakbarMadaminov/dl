<?php

use App\Modules\Moderation\Http\Controllers\Admin\AdminModerationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin API Routes
|--------------------------------------------------------------------------
|
| Base: /api/v1/admin
|
*/

Route::get('ping', function () {
    return response()->json(['status' => 'ok', 'context' => 'admin']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('moderation')->group(function () {
        Route::get('surveys', [AdminModerationController::class, 'index']);
        Route::get('surveys/{survey}', [AdminModerationController::class, 'show']);
        Route::post('surveys/{survey}/approve', [AdminModerationController::class, 'approve']);
        Route::post('surveys/{survey}/request-changes', [AdminModerationController::class, 'requestChanges']);
    });
});
