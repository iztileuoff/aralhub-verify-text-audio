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
    Route::post('audio/upload', AudioUploadController::class);
    Route::post('audio/update', AudioUpdateController::class);
    Route::get('export/report-users', ExportReportUserController::class);
    Route::get('export/report-all-users', ExportReportAllUserController::class);
});
