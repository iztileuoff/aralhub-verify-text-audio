<?php

use App\Http\Controllers\Api\V1\Admin\ActionController;
use App\Http\Controllers\Api\V1\Admin\DailyQuotaController;
use App\Http\Controllers\Api\V1\Admin\DatasetController;
use App\Http\Controllers\Api\V1\Admin\ExportReportUserController;
use App\Http\Controllers\Api\V1\Admin\FileController;
use App\Http\Controllers\Api\V1\Admin\FinishedAudioTextController;
use App\Http\Controllers\Api\V1\Admin\FinishedCheckTextController;
use App\Http\Controllers\Api\V1\Admin\FinishedEditTextController;
use App\Http\Controllers\Api\V1\Admin\LoginController;
use App\Http\Controllers\Api\V1\Admin\LogoutController;
use App\Http\Controllers\Api\V1\Admin\ProfileController;
use App\Http\Controllers\Api\V1\Admin\RegistrationController;
use App\Http\Controllers\Api\V1\Admin\RoleController;
use App\Http\Controllers\Api\V1\Admin\SpecializationController;
use App\Http\Controllers\Api\V1\Admin\TextController;
use App\Http\Controllers\Api\V1\Admin\TextStatusesController;
use App\Http\Controllers\Api\V1\Admin\UserController;
use App\Http\Controllers\Api\V1\Verify\ModeratorAudioCheckController;
use App\Http\Controllers\Api\V1\Verify\ModeratorAudioController;
use App\Http\Controllers\Api\V1\Verify\ModeratorTextCheckController;
use App\Http\Controllers\Api\V1\Verify\ModeratorTextController;
use App\Http\Controllers\Api\V1\Verify\SpeakTextAudioCompleteController;
use App\Http\Controllers\Api\V1\Verify\SpeakTextController;
use App\Http\Controllers\Api\V1\Verify\TextCancelController;
use App\Http\Controllers\Api\V1\Verify\TextCompleteController;
use App\Http\Controllers\Api\V1\Verify\TextController as VerifyTextController;
use App\Http\Controllers\Api\V1\Verify\TextUpdateController;
use App\Http\Controllers\Api\V1\Verify\UserController as VerifyUserController;
use Illuminate\Support\Facades\Route;

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
        'daily/quota/texts' => DailyQuotaController::class,
    ]);

    Route::apiResource('roles', RoleController::class)->only('index');
    Route::apiResource('users', UserController::class);
    Route::apiResource('text-statuses', TextStatusesController::class)->only('index');
    Route::apiResource('texts', TextController::class);
    Route::apiResource('files', FileController::class)->except('update');
    Route::apiResource('actions', ActionController::class)->only('index');
    Route::apiResource('specializations', SpecializationController::class);

    Route::get('finished/edit/texts', FinishedEditTextController::class)->name('finished.edit.texts');
    Route::get('finished/audio/texts', FinishedAudioTextController::class)->name('finished.audio.texts');
    Route::get('finished/check/texts', FinishedCheckTextController::class)->name('finished.check.texts');

    Route::post('dataset', DatasetController::class)->name('dataset');
    Route::post('export/report-users', ExportReportUserController::class)->name('export.report-users');
});

Route::group([
    'prefix' => 'admin/verify',
    'as' => 'admin.verify.',
    'middleware' => ['auth:sanctum'],
], function () {
    Route::get('users', VerifyUserController::class)->name('users');

    // edit
    Route::get('text', VerifyTextController::class)->name('text');
    Route::post('text/{text}/complete', TextCompleteController::class)->name('text.complete');
    Route::patch('text/{text}/update', TextUpdateController::class)->name('text.update');
    Route::delete('text/{text}/cancel', TextCancelController::class)->name('text.cancel');

    // speak
    Route::get('speak/text', SpeakTextController::class)->name('speak.text');
    Route::post('speak/text/{text}/audio/complete', SpeakTextAudioCompleteController::class)->name('speak.text.audio.complete');

    // moderator
    Route::get('moderator/text', ModeratorTextController::class)->name('moderator.text');
    Route::post('moderator/text/{text}/check', ModeratorTextCheckController::class)->name('moderator.text.check');

    Route::get('moderator/audio', ModeratorAudioController::class)->name('moderator.audio');
    Route::post('moderator/audio/{audio}/check', ModeratorAudioCheckController::class)->name('moderator.audio.check');
});
