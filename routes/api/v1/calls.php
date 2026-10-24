<?php

use App\Http\Controllers\Api\V1\Calls\CallApproveController;
use App\Http\Controllers\Api\V1\Calls\CallCurrentController;
use App\Http\Controllers\Api\V1\Calls\CallModerationController;
use App\Http\Controllers\Api\V1\Calls\CallRejectController;
use App\Http\Controllers\Api\V1\Calls\CallReleaseController;
use App\Http\Controllers\Api\V1\Calls\CallRestoreController;
use App\Http\Controllers\Api\V1\Calls\CallSkipController;
use App\Http\Controllers\Api\V1\Calls\CallStatsController;
use App\Http\Controllers\Api\V1\Calls\CallSubmitController;
use App\Http\Controllers\Api\V1\Calls\MyCallsController;
use App\Http\Controllers\Api\V1\Calls\MyCallStatsController;
use Illuminate\Support\Facades\Route;

/*
 * Phone-call trimming (docs/frontend/call-trim.md). Trimmers reach only the
 * first group — every other admin/verify route refuses them (role.deny).
 */

Route::group([
    'prefix' => 'admin/calls',
    'as' => 'admin.calls.',
    'middleware' => ['auth:sanctum', 'role:trimmer'],
], function () {
    Route::get('current', CallCurrentController::class)->name('current');
    Route::get('my', MyCallsController::class)->name('my');
    Route::get('my/stats', MyCallStatsController::class)->name('my.stats');
    Route::post('{call}/submit', CallSubmitController::class)->name('submit');
    Route::post('{call}/skip', CallSkipController::class)->name('skip');
    Route::post('{call}/release', CallReleaseController::class)->name('release');
});

Route::group([
    'prefix' => 'admin/calls',
    'as' => 'admin.calls.',
    'middleware' => ['auth:sanctum', 'role:admin'],
], function () {
    Route::get('moderation', CallModerationController::class)->name('moderation');
    Route::get('stats', CallStatsController::class)->name('stats');
    Route::post('{call}/approve', CallApproveController::class)->name('approve');
    Route::post('{call}/reject', CallRejectController::class)->name('reject');
    Route::post('{call}/restore', CallRestoreController::class)->name('restore');
});
