<?php

use App\Http\Controllers\Api\CmsController;
use App\Http\Controllers\Api\Customer\CustomerCarController;
use App\Http\Controllers\Api\Customer\CustomerConversationController;
use App\Http\Controllers\Api\Customer\CustomerNotificationController;
use App\Http\Controllers\Api\Customer\CustomerOrderController;
use App\Http\Controllers\Api\Customer\CustomerRatingController;
use App\Http\Controllers\Api\Customer\CustomerStoreController;
use App\Http\Controllers\Api\Customer\ProfileController;
use App\Http\Controllers\Api\OrderOfferController;
use App\Http\Controllers\Api\Reference\CarNameController;
use App\Http\Controllers\Api\Reference\CarSectionController;
use App\Http\Controllers\Api\Reference\CityController;
use App\Http\Controllers\Api\Reference\ColorController;
use App\Http\Controllers\Api\Reference\CompanyController;
use App\Http\Controllers\Api\Reference\FuelTypeController;
use App\Http\Controllers\Api\Provider\ProviderOrderController;
use App\Http\Controllers\Api\Provider\ProviderProfileController;
use App\Http\Controllers\Api\Provider\ProviderStoreCarComponentController;
use App\Http\Controllers\Api\Provider\ProviderStoreCarController;
use App\Http\Controllers\Api\Provider\ProviderStoreController;
use App\Http\Controllers\Api\Provider\ProviderStoreRequestController;
use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::middleware(['locale'])->group(function () {

    Route::prefix('auth')->group(function () {
        Route::post('/otp/send', [AuthController::class, 'sendOtp'])->middleware('throttle:customerLogin');
        Route::post('/otp/verify', [AuthController::class, 'verifyOtp'])->middleware('throttle:customerLogin');
        Route::post('/register', [AuthController::class, 'register']); // Done
        Route::post('/admin/login', [AuthController::class, 'adminLogin'])->middleware('throttle:adminLogin');
    });

    // All done
    Route::prefix('reference')->group(function () {
        Route::get('/cities', [CityController::class, 'index']);
        Route::get('/companies', [CompanyController::class, 'index']);
        Route::get('/companies/{company}/names', [CompanyController::class, 'names']);
        Route::get('/names/{name}/models', [CarNameController::class, 'models']);
        Route::get('/fuel-types', [FuelTypeController::class, 'index']);
        Route::get('/colors', [ColorController::class, 'index']);
        Route::get('/sections', [CarSectionController::class, 'index']);
        Route::get('/sections/{section}/components', [CarSectionController::class, 'components']);
    });


    Route::get('/cms/{type}', [CmsController::class, 'show']); // Done

    Route::middleware(['auth:sanctum', 'user.active', 'provider'])->prefix('provider')->group(function () {
        Route::get('/profile', [ProviderProfileController::class, 'show']);
        Route::put('/profile', [ProviderProfileController::class, 'update']);

        Route::get('/stores', [ProviderStoreController::class, 'index']);
        Route::get('/store/{store}', [ProviderStoreController::class, 'show']);
        Route::put('/store/{store}', [ProviderStoreController::class, 'update']);

        Route::get('/store/{store}/cars', [ProviderStoreCarController::class, 'index'])->name('provider.store.cars.index');
        Route::post('/store/{store}/cars', [ProviderStoreCarController::class, 'store'])->name('provider.store.cars.store');
        Route::get('/store/{store}/cars/{storeCar}', [ProviderStoreCarController::class, 'show'])->name('provider.store.cars.show');
        Route::put('/store/{store}/cars/{storeCar}', [ProviderStoreCarController::class, 'update'])->name('provider.store.cars.update');
        Route::delete('/store/{store}/cars/{storeCar}', [ProviderStoreCarController::class, 'destroy'])->name('provider.store.cars.destroy');

        Route::get('/store/{store}/cars/{storeCar}/components', [ProviderStoreCarComponentController::class, 'index'])->name('provider.store.cars.components.index');
        Route::post('/store/{store}/cars/{storeCar}/components', [ProviderStoreCarComponentController::class, 'store'])->name('provider.store.cars.components.store');
        Route::post('/store/{store}/cars/{storeCar}/components/batch', [ProviderStoreCarComponentController::class, 'batchStore'])->name('provider.store.cars.components.batch');
        Route::get('/store/{store}/cars/{storeCar}/components/{component}', [ProviderStoreCarComponentController::class, 'show'])->name('provider.store.cars.components.show');
        Route::put('/store/{store}/cars/{storeCar}/components/{component}', [ProviderStoreCarComponentController::class, 'update'])->name('provider.store.cars.components.update');
        Route::delete('/store/{store}/cars/{storeCar}/components/{component}', [ProviderStoreCarComponentController::class, 'destroy'])->name('provider.store.cars.components.destroy');

        Route::get('/store-requests', [ProviderStoreRequestController::class, 'index']);
        Route::post('/store-requests/verify-mobile', [ProviderStoreRequestController::class, 'sendMobileOtp']);
        Route::post('/store-requests/verify-code', [ProviderStoreRequestController::class, 'verifyMobileOtp']);
        Route::post('/store-requests', [ProviderStoreRequestController::class, 'store']);
        Route::get('/store-requests/{storeRequest}', [ProviderStoreRequestController::class, 'show']);

        Route::get('/orders/general', [ProviderOrderController::class, 'general']);
        Route::get('/orders/specific', [ProviderOrderController::class, 'specific']);
        Route::get('/orders/offers', [ProviderOrderController::class, 'offers']);
        Route::get('/orders/paid', [ProviderOrderController::class, 'paidOrders']);
        Route::get('/orders/{order}', [ProviderOrderController::class, 'show']);
        Route::post('/orders/{order}/offer', [ProviderOrderController::class, 'storeOffer']);
        Route::put('/orders/{order}/offer/{offer}', [ProviderOrderController::class, 'updateOffer']);
        Route::delete('/orders/{order}/offer/{offer}', [ProviderOrderController::class, 'destroyOffer']);
        Route::post('/orders/{order}/reject', [ProviderOrderController::class, 'reject']);
    });

    Route::middleware(['auth:sanctum', 'user.active', 'customer'])->prefix('customer')->group(function () {
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::patch('/profile', [ProfileController::class, 'update']);

        Route::get('/customer-cars', [CustomerCarController::class, 'index']);
        Route::post('/customer-cars', [CustomerCarController::class, 'store']);
        Route::get('/customer-cars/{customerCar}', [CustomerCarController::class, 'show']);
        Route::patch('/customer-cars/{customerCar}', [CustomerCarController::class, 'update']);
        Route::delete('/customer-cars/{customerCar}', [CustomerCarController::class, 'destroy']);

        Route::get('/orders', [CustomerOrderController::class, 'index']);
        Route::post('/orders', [CustomerOrderController::class, 'store']); // Done
        Route::get('/orders/{order}', [CustomerOrderController::class, 'show']);
        Route::post('/orders/{order}/accept-offer', [CustomerOrderController::class, 'acceptOffer']);
        Route::post('/orders/{order}/pay', [CustomerOrderController::class, 'pay']);
        Route::post('/orders/{order}/received', [CustomerOrderController::class, 'received']);
        Route::post('/orders/{order}/cancel', [CustomerOrderController::class, 'cancel']);

        Route::get('/orders/{order}/offers', [OrderOfferController::class, 'index']); // Done
        Route::get('/orders/{order}/offers/{offer}', [OrderOfferController::class, 'show']); // Done
        Route::post('/orders/{order}/offers/{offer}/reject', [OrderOfferController::class, 'reject']);

        Route::get('/component-cars', [CustomerStoreController::class, 'componentCars']); // Done

        Route::get('/stores', [CustomerStoreController::class, 'index']); // Done
        Route::get('/stores/{store}', [CustomerStoreController::class, 'show']); // Done
        Route::get('/stores/{store}/cars', [CustomerStoreController::class, 'cars']);
        Route::get('/stores/{store}/cars/{car}', [CustomerStoreController::class, 'showCar']);
        Route::get('/stores/{store}/cars/{car}/components', [CustomerStoreController::class, 'components']);
        Route::get('/stores/{store}/cars/{car}/components/{component}', [CustomerStoreController::class, 'showComponent']);

        Route::get('/conversations', [CustomerConversationController::class, 'index']);
        Route::post('/conversations', [CustomerConversationController::class, 'store']);
        Route::get('/conversations/{conversation}/messages', [CustomerConversationController::class, 'messages']);
        Route::post('/conversations/{conversation}/messages', [CustomerConversationController::class, 'sendMessage']);

        Route::get('/ratings', [CustomerRatingController::class, 'index']);
        Route::post('/ratings', [CustomerRatingController::class, 'store']);

        Route::get('/notifications', [CustomerNotificationController::class, 'index']);
        Route::patch('/notifications/read-all', [CustomerNotificationController::class, 'markAllAsRead']);
        Route::patch('/notifications/{notification}/read', [CustomerNotificationController::class, 'markAsRead']);
    });
});
