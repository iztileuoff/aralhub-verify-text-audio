<?php

use App\Http\Controllers\Api\V1\Admin\LoginController;
use App\Http\Controllers\Api\V1\Admin\LogoutController;

Route::group([
    'prefix' => 'admin/auth',
    'as' => 'admin.auth.',
], function () {
    Route::post('login', LoginController::class)->name('login');
    Route::delete('logout', LogoutController::class)->name('logout')->middleware('auth:sanctum');
});
