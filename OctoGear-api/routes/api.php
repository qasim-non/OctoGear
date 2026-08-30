<?php

use App\Http\Controllers\Api\CmsController;
use App\Http\Controllers\Api\Customer\CustomerCarController;
use App\Http\Controllers\Api\Customer\CustomerConversationController;
use App\Http\Controllers\Api\Customer\CustomerNotificationController;
use App\Http\Controllers\Api\Customer\CustomerOrderController;
use App\Http\Controllers\Api\Customer\CustomerRatingController;
use App\Http\Controllers\Api\Customer\CustomerStoreController;
use App\Http\Controllers\Api\Customer\ProfileController;
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

    Route::get('/cms/{type}', [CmsController::class, 'show']);

    Route::middleware(['auth:sanctum', 'user.active', 'customer'])->prefix('customer')->group(function () {
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::patch('/profile', [ProfileController::class, 'update']);

        Route::get('/customer-cars', [CustomerCarController::class, 'index']);
        Route::post('/customer-cars', [CustomerCarController::class, 'store']);
        Route::delete('/customer-cars/{customerCar}', [CustomerCarController::class, 'destroy']);

        Route::get('/orders', [CustomerOrderController::class, 'index']);
        Route::post('/orders', [CustomerOrderController::class, 'store']);
        Route::get('/orders/{order}', [CustomerOrderController::class, 'show']);
        Route::post('/orders/{order}/accept-offer', [CustomerOrderController::class, 'acceptOffer']);
        Route::post('/orders/{order}/pay', [CustomerOrderController::class, 'pay']);
        Route::post('/orders/{order}/received', [CustomerOrderController::class, 'received']);
        Route::post('/orders/{order}/cancel', [CustomerOrderController::class, 'cancel']);

        Route::get('/stores', [CustomerStoreController::class, 'index']);
        Route::get('/stores/{store}', [CustomerStoreController::class, 'show']);
        Route::get('/stores/{store}/cars', [CustomerStoreController::class, 'cars']);
        Route::get('/stores/{store}/cars/{car}/components', [CustomerStoreController::class, 'components']);

        Route::get('/conversations', [CustomerConversationController::class, 'index']);
        Route::post('/conversations', [CustomerConversationController::class, 'store']);
        Route::get('/conversations/{conversation}/messages', [CustomerConversationController::class, 'messages']);
        Route::post('/conversations/{conversation}/messages', [CustomerConversationController::class, 'sendMessage']);

        Route::get('/ratings', [CustomerRatingController::class, 'index']);
        Route::post('/ratings', [CustomerRatingController::class, 'store']);

        Route::get('/notifications', [CustomerNotificationController::class, 'index']);
        Route::put('/notifications/{notification}/read', [CustomerNotificationController::class, 'markAsRead']);
    });
});
