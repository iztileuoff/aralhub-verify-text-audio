<?php

use App\Http\Controllers\Web\CallUploadPageController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\SessionController;
use App\Models\CallRecording;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Customer's upload link for phone calls (calls:upload-link); the signature is the only key.
Route::get('/calls-upload/{batch}', CallUploadPageController::class)
    ->where('batch', CallRecording::UPLOAD_BATCH_PATTERN)
    ->middleware('signed:relative')
    ->name('calls.upload.page');

Route::middleware('guest')->group(function () {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', DashboardController::class)
        ->middleware('can:viewPulse')
        ->name('dashboard');
});
