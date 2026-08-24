<?php

use App\Http\Controllers\Api\CmsController;
use App\Http\Controllers\Api\ReferenceController;
use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::middleware(['locale'])->group(function () {

    Route::prefix('auth')->group(function () {
        Route::post('/otp/send', [AuthController::class, 'sendOtp']);
        Route::post('/otp/verify', [AuthController::class, 'verifyOtp']);
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/admin/login', [AuthController::class, 'adminLogin']);
    });

    Route::prefix('reference')->group(function () {
        Route::get('/cities', [ReferenceController::class, 'cities']);
        Route::get('/companies', [ReferenceController::class, 'companies']);
        Route::get('/companies/{company}/names', [ReferenceController::class, 'companyNames']);
        Route::get('/names/{name}/models', [ReferenceController::class, 'nameModels']);
        Route::get('/fuel-types', [ReferenceController::class, 'fuelTypes']);
        Route::get('/colors', [ReferenceController::class, 'colors']);
        Route::get('/sections', [ReferenceController::class, 'sections']);
        Route::get('/sections/{section}/components', [ReferenceController::class, 'sectionComponents']);
    });

    Route::get('/cms/{cms}', [CmsController::class, 'show']);
});
