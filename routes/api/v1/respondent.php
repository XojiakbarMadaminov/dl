<?php

use App\Modules\Participation\Http\Controllers\RespondentParticipationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Respondent API Routes
|--------------------------------------------------------------------------
|
| Base: /api/v1/respondent
|
*/

Route::get('ping', function () {
    return response()->json(['status' => 'ok', 'context' => 'respondent']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('surveys/{survey}/participate', [RespondentParticipationController::class, 'participate']);
    Route::get('participations/{participation}', [RespondentParticipationController::class, 'show']);
    Route::put('participations/{participation}/pages/{page}', [RespondentParticipationController::class, 'savePage']);
    Route::post('participations/{participation}/complete', [RespondentParticipationController::class, 'complete']);
});
