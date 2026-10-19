<?php

use App\Modules\Organization\Http\Controllers\Business\OrganizationController;
use App\Modules\Results\Http\Controllers\Business\SurveyResultsController;
use App\Modules\Survey\Http\Controllers\Business\BusinessSurveyLifecycleController;
use App\Modules\Survey\Http\Controllers\Business\SurveyController;
use App\Modules\Survey\Http\Controllers\Business\SurveyLogicController;
use App\Modules\Survey\Http\Controllers\Business\SurveyOptionController;
use App\Modules\Survey\Http\Controllers\Business\SurveyPageController;
use App\Modules\Survey\Http\Controllers\Business\SurveyPreviewController;
use App\Modules\Survey\Http\Controllers\Business\SurveyQuestionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Business API Routes
|--------------------------------------------------------------------------
|
| Base: /api/v1/business
|
*/

Route::get('ping', function () {
    return response()->json(['status' => 'ok', 'context' => 'business']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('organizations', [OrganizationController::class, 'index']);
    Route::post('organizations', [OrganizationController::class, 'store']);
    Route::get('organizations/{organization}', [OrganizationController::class, 'show']);
    Route::patch('organizations/{organization}', [OrganizationController::class, 'update']);

    Route::prefix('organizations/{organization}')->group(function () {
        // Surveys
        Route::get('surveys', [SurveyController::class, 'index']);
        Route::post('surveys', [SurveyController::class, 'store']);
        Route::get('surveys/{survey}', [SurveyController::class, 'show']);
        Route::patch('surveys/{survey}', [SurveyController::class, 'update']);
        Route::delete('surveys/{survey}', [SurveyController::class, 'destroy']);
        Route::get('surveys/{survey}/logic', [SurveyLogicController::class, 'index']);
        Route::put('surveys/{survey}/logic', [SurveyLogicController::class, 'update']);

        // Results & Analytics
        Route::get('surveys/{survey}/results/summary', [SurveyResultsController::class, 'summary']);
        Route::get('surveys/{survey}/results/questions/{question}', [SurveyResultsController::class, 'questionAggregate']);
        Route::get('surveys/{survey}/participations', [SurveyResultsController::class, 'participations']);
        Route::get('surveys/{survey}/participations/{participation}', [SurveyResultsController::class, 'participationDetail']);
        Route::get('surveys/{survey}/results/export', [SurveyResultsController::class, 'export']);

        // Lifecycle & Moderation Submission
        Route::post('surveys/{survey}/submit-review', [BusinessSurveyLifecycleController::class, 'submitReview']);
        Route::post('surveys/{survey}/publish', [BusinessSurveyLifecycleController::class, 'publish']);
        Route::post('surveys/{survey}/pause', [BusinessSurveyLifecycleController::class, 'pause']);
        Route::post('surveys/{survey}/resume', [BusinessSurveyLifecycleController::class, 'resume']);
        Route::post('surveys/{survey}/close', [BusinessSurveyLifecycleController::class, 'close']);
        Route::post('surveys/{survey}/archive', [BusinessSurveyLifecycleController::class, 'archive']);

        // Preview
        Route::get('surveys/{survey}/preview', [SurveyPreviewController::class, 'show']);
        Route::post('surveys/{survey}/preview/pages/{page}/validate', [SurveyPreviewController::class, 'validateStep']);

        // Pages
        Route::post('surveys/{survey}/pages', [SurveyPageController::class, 'store']);
        Route::patch('pages/{page}', [SurveyPageController::class, 'update']);
        Route::delete('pages/{page}', [SurveyPageController::class, 'destroy']);
        Route::put('surveys/{survey}/pages/reorder', [SurveyPageController::class, 'reorder']);

        // Questions
        Route::post('pages/{page}/questions', [SurveyQuestionController::class, 'store']);
        Route::patch('questions/{question}', [SurveyQuestionController::class, 'update']);
        Route::delete('questions/{question}', [SurveyQuestionController::class, 'destroy']);
        Route::post('questions/{question}/duplicate', [SurveyQuestionController::class, 'duplicate']);
        Route::put('pages/{page}/questions/reorder', [SurveyQuestionController::class, 'reorder']);

        // Options
        Route::post('questions/{question}/options', [SurveyOptionController::class, 'store']);
        Route::patch('options/{option}', [SurveyOptionController::class, 'update']);
        Route::delete('options/{option}', [SurveyOptionController::class, 'destroy']);
        Route::put('questions/{question}/options/reorder', [SurveyOptionController::class, 'reorder']);
    });
});
