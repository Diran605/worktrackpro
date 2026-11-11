<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DailyPlanController;
use App\Http\Controllers\Api\V1\ActivityLogController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\TeamController;
use App\Http\Controllers\Api\V1\LookupController;
use App\Http\Controllers\Api\V1\WorkSessionController;
use App\Http\Controllers\Api\V1\TimerController;
use App\Http\Controllers\Api\V1\PersonalRecurringTaskController;
use App\Http\Controllers\Api\V1\CarryOverController;
use App\Http\Controllers\Api\V1\InboxController;
use App\Http\Controllers\Api\V1\InboxAcknowledgeController;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('reset-password', [AuthController::class, 'resetPassword']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);
            Route::patch('password', [AuthController::class, 'updatePassword']);
        });
    });

    Route::middleware(['auth:sanctum', \App\Http\Middleware\CheckOrganisationActive::class])->group(function () {
        Route::get('dashboard/stats/weekly', [DashboardController::class, 'weeklyStats']);

        // Work sessions (attendance)
        Route::prefix('sessions')->group(function () {
            Route::post('clock-in', [WorkSessionController::class, 'clockIn']);
            Route::post('clock-out', [WorkSessionController::class, 'clockOut']);
            Route::get('current', [WorkSessionController::class, 'currentSession']);
            Route::post('{session}/request-reopen', [WorkSessionController::class, 'requestReopen']);
        });

        // Timers
        Route::prefix('timers')->group(function () {
            Route::post('{plan}/start', [TimerController::class, 'start']);
            Route::post('{plan}/pause', [TimerController::class, 'pause']);
            Route::post('{plan}/resume', [TimerController::class, 'resume']);
            Route::post('{plan}/stop', [TimerController::class, 'stop']);
        });

        // Worker personal recurring tasks
        Route::apiResource('recurring-tasks', PersonalRecurringTaskController::class)
            ->only(['index', 'store', 'update', 'destroy']);

        // Carry-overs
        Route::prefix('carry-overs')->group(function () {
            Route::get('pending', [CarryOverController::class, 'getPendingCarryOvers']);
            Route::post('{plan}/resolve', [CarryOverController::class, 'resolveCarryOver']);
        });

        // Inbox
        Route::prefix('inbox')->group(function () {
            Route::get('/', [InboxController::class, 'index']);
            Route::get('unread-count', [InboxController::class, 'unreadCount']);
            Route::get('{id}', [InboxController::class, 'show']);
            Route::post('send', [InboxController::class, 'send']);
            Route::post('request-reopen', [InboxController::class, 'requestReopenLatest']);
            Route::get('attachments/{id}/download', [InboxController::class, 'downloadAttachment']);
            Route::post('{id}/acknowledge', InboxAcknowledgeController::class);
        });
        
        Route::apiResource('plans', DailyPlanController::class);
        Route::patch('plans/{plan}/complete', [DailyPlanController::class, 'complete']);
        
        Route::apiResource('logs', ActivityLogController::class);

        // Lookup endpoints for frontend dropdowns
        Route::get('work-types', [LookupController::class, 'workTypes']);
        Route::get('project-clients', [LookupController::class, 'projectClients']);

        Route::prefix('notifications')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\NotificationController::class, 'index']);
            Route::patch('/{id}/read', [\App\Http\Controllers\Api\V1\NotificationController::class, 'markAsRead']);
        });

        // Team directory
        Route::get('team', [TeamController::class, 'index']);
        Route::get('team/departments', [TeamController::class, 'departments']);
        Route::patch('team/{user}/toggle-status', [TeamController::class, 'toggleStatus']);
    });
});
