<?php

use App\Http\Controllers\Api\V1\Admin\FileController;
use App\Http\Controllers\Api\V1\Admin\LoginController;
use App\Http\Controllers\Api\V1\Admin\LogoutController;
use App\Http\Controllers\Api\V1\Admin\ProfileController;
use App\Http\Controllers\Api\V1\Admin\RegistrationController;
use App\Http\Controllers\Api\V1\Admin\RoleController;
use App\Http\Controllers\Api\V1\Admin\TextController;
use App\Http\Controllers\Api\V1\Admin\UserController;
use App\Http\Controllers\Api\V1\Verify\TextCancelController;
use App\Http\Controllers\Api\V1\Verify\TextCompleteController;
use App\Http\Controllers\Api\V1\Verify\TextController as VerifyTextController;

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
        'roles' => RoleController::class,
        'users' => UserController::class,
        'files' => FileController::class,
        'texts' => TextController::class,
    ]);
});

Route::group([
    'prefix' => 'admin/verify',
    'as' => 'admin.verify.',
    'middleware' => ['auth:sanctum'],
], function () {
    Route::get('text', VerifyTextController::class)->name('text');
    Route::post('text/{text}/complete', TextCompleteController::class)->name('text.complete');
    Route::delete('text/{text}/cancel', TextCancelController::class)->name('text.cancel');

    //    Route::get('edited/text');
    //    Route::post('edited/text/{text}/audio/complete');
});
