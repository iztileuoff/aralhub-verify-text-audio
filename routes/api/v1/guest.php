<?php

use App\Http\Controllers\Api\V1\Admin\ExportReportAllUserController;
use App\Http\Controllers\Api\V1\Admin\ExportReportUserController;
use App\Http\Controllers\Api\V1\Guest\AdminController;
use App\Http\Controllers\Api\V1\Guest\AudioUpdateController;
use App\Http\Controllers\Api\V1\Guest\AudioUploadController;
use App\Http\Controllers\Api\V1\Guest\SpecializationController;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => 'guest',
    'as' => 'guest.',
], function () {
    Route::get('specializations', SpecializationController::class);
    Route::get('admins', AdminController::class);

    // The external duration script; the .tsv export is built from what it writes.
    Route::middleware('duration.token')->group(function () {
        Route::post('audio/upload', AudioUploadController::class);
        Route::post('audio/update', AudioUpdateController::class);
    });

    // Full names and phone numbers of every user.
    Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
        Route::get('export/report-users', ExportReportUserController::class);
        Route::get('export/report-all-users', ExportReportAllUserController::class);
    });
});
