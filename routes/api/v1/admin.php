<?php

use App\Http\Controllers\Api\V1\Admin\FileController;
use App\Http\Controllers\Api\V1\Admin\LoginController;
use App\Http\Controllers\Api\V1\Admin\LogoutController;
use App\Http\Controllers\Api\V1\Admin\ProfileController;
use App\Http\Controllers\Api\V1\Admin\RegistrationController;
use App\Http\Controllers\Api\V1\Admin\TextController;

Route::group([
    'prefix' => 'admin/auth',
    'as' => 'admin.auth.',
], function () {
    Route::post('registration', RegistrationController::class)->name('registration');

    Route::post('login', LoginController::class)->name('login');
    Route::delete('logout', LogoutController::class)->name('logout')->middleware('auth:sanctum');
});

Route::group([
    'prefix' => 'admin',
    'as' => 'admin.',
    'middleware' => ['auth:sanctum'],
], function () {
    Route::apiSingletons([
        'profile' => ProfileController::class,
    ]);

    Route::apiResources([
        'files' => FileController::class,
        'texts' => TextController::class,
    ]);
});

Route::group([
    'prefix' => 'admin/verify',
    'as' => 'admin.verify.',
    'middleware' => ['auth:sanctum'],
], function () {
    //    Route::get('text');
    //    Route::post('text/{text}/complete');
    //    Route::post('text/{text}/cancel');
    //
    //    Route::get('edited/text');
    //    Route::post('edited/text/{text}/audio/complete');
});
