<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Prefix: /api
|
*/

Route::prefix('v1')->group(function () {
    Route::get('health', function () {
        return response()->json([
            'status' => 'ok',
            'version' => 'v1',
            'timestamp' => now()->toIso8601String(),
        ]);
    });

    Route::prefix('auth')->group(base_path('routes/api/v1/auth.php'));
    Route::prefix('business')->group(base_path('routes/api/v1/business.php'));
    Route::prefix('respondent')->group(base_path('routes/api/v1/respondent.php'));
    Route::prefix('admin')->group(base_path('routes/api/v1/admin.php'));
});
