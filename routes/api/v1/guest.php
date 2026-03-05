<?php

use App\Http\Controllers\Api\V1\Guest\SpecializationController;

Route::group([
    'prefix' => 'guest',
    'as' => 'guest.',
], function () {
    Route::get('specializations', SpecializationController::class);
});
