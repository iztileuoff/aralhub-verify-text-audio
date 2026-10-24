<?php

use App\Http\Controllers\Api\V1\Calls\CallApproveController;
use App\Http\Controllers\Api\V1\Calls\CallArchiveCompleteController;
use App\Http\Controllers\Api\V1\Calls\CallArchivePartsController;
use App\Http\Controllers\Api\V1\Calls\CallArchiveStartController;
use App\Http\Controllers\Api\V1\Calls\CallCurrentController;
use App\Http\Controllers\Api\V1\Calls\CallModerationController;
use App\Http\Controllers\Api\V1\Calls\CallRejectController;
use App\Http\Controllers\Api\V1\Calls\CallReleaseController;
use App\Http\Controllers\Api\V1\Calls\CallRestoreController;
use App\Http\Controllers\Api\V1\Calls\CallSkipController;
use App\Http\Controllers\Api\V1\Calls\CallStatsController;
use App\Http\Controllers\Api\V1\Calls\CallSubmitController;
use App\Http\Controllers\Api\V1\Calls\CallUploadExistingController;
use App\Http\Controllers\Api\V1\Calls\CallUploadFilesController;
use App\Http\Controllers\Api\V1\Calls\MyCallsController;
use App\Http\Controllers\Api\V1\Calls\MyCallStatsController;
use App\Models\CallRecording;
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

/*
 * Behind the customer's upload link (routes/web.php "calls.upload.page"): no
 * login, the signed URL is the key. The browser sends the bytes straight to
 * the calls bucket; these routes only hand out signed bucket URLs for one batch.
 */
Route::group([
    'prefix' => 'calls-upload/{batch}',
    'as' => 'calls.upload.',
    'where' => ['batch' => CallRecording::UPLOAD_BATCH_PATTERN],
    'middleware' => ['signed:relative', 'throttle:600,1'],
], function () {
    Route::post('existing', CallUploadExistingController::class)->name('existing');
    Route::post('files', CallUploadFilesController::class)->name('files');
    Route::post('archive/start', CallArchiveStartController::class)->name('archive.start');
    Route::post('archive/parts', CallArchivePartsController::class)->name('archive.parts');
    Route::post('archive/complete', CallArchiveCompleteController::class)->name('archive.complete');
});
