<?php

use App\Http\Controllers\Api\V1\Guest\AdminController;
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
});
