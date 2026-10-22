<?php

use App\Http\Controllers\Api\Admin\AdminAccountController;
use App\Http\Controllers\Api\Admin\AdminDashboardController;
use App\Http\Controllers\Api\Admin\AdminOrderController;
use App\Http\Controllers\Api\Admin\AdminPaymentController;
use App\Http\Controllers\Api\Admin\AdminProfileController;
use App\Http\Controllers\Api\Admin\AdminStoreController;
use App\Http\Controllers\Api\Admin\AdminStoreRequestController;
use App\Http\Controllers\Api\Admin\AdminSupportConversationController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\Admin\AdminVehicleCatalogController;
use App\Http\Controllers\Api\CmsController;
use App\Http\Controllers\Api\Customer\CustomerCarController;
use App\Http\Controllers\Api\Customer\CustomerCarPictureController;
use App\Http\Controllers\Api\Customer\CustomerOrderController;
use App\Http\Controllers\Api\Customer\ProfileController as CustomerProfileController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\OrderOfferController;
use App\Http\Controllers\Api\Provider\ProviderDashboardController;
use App\Http\Controllers\Api\Provider\ProviderOrderController;
use App\Http\Controllers\Api\Provider\ProviderOrderConversationController;
use App\Http\Controllers\Api\Provider\ProviderProfileController;
use App\Http\Controllers\Api\Provider\ProviderStoreCarComponentController;
use App\Http\Controllers\Api\Provider\ProviderStoreCarController;
use App\Http\Controllers\Api\Provider\ProviderStoreController;
use App\Http\Controllers\Api\Provider\ProviderStoreRequestController;
use App\Http\Controllers\Api\Reference\CarNameController;
use App\Http\Controllers\Api\Reference\CarSectionController;
use App\Http\Controllers\Api\Reference\CityController;
use App\Http\Controllers\Api\Reference\ColorController;
use App\Http\Controllers\Api\Reference\CompanyController;
use App\Http\Controllers\Api\Reference\ComponentController;
use App\Http\Controllers\Api\Reference\FuelTypeController;
use App\Http\Controllers\Api\Shared\ChatRealtimeController;
use App\Http\Controllers\Api\Shared\ConversationController;
use App\Http\Controllers\Api\Shared\DeviceTokenController;
use App\Http\Controllers\Api\Shared\NotificationController;
use App\Http\Controllers\Api\Shared\ProfileController;
use App\Http\Controllers\Api\Shared\RatingController;
use App\Http\Controllers\Api\Shared\SupportConversationController;
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\Auth\AdminPasswordController;
use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::middleware(['locale'])->group(function () {

    Route::middleware('auth:sanctum,admin')->prefix('media')->name('media.')->group(function () {
        Route::get('/stores/{store}/pictures/{storePicture}', [MediaController::class, 'storePicture'])->name('store-pictures.show');
        Route::get('/stores/{store}/cars/{storeCar}/pictures/{storeCarPicture}', [MediaController::class, 'storeCarPicture'])->name('store-car-pictures.show');
        Route::get('/stores/{store}/registration', [MediaController::class, 'storeRegistration'])->name('store-registration.show');
        Route::get('/store-requests/{storeRequest}/pictures/{storeRequestPicture}', [MediaController::class, 'storeRequestPicture'])->name('store-request-pictures.show');
        Route::get('/store-requests/{storeRequest}/registration', [MediaController::class, 'storeRequestRegistration'])->name('store-request-registration.show');
        Route::get('/orders/{order}/images/{orderImage}', [MediaController::class, 'orderImage'])->name('order-images.show');
        Route::get('/offers/{offer}/images/{offerImage}', [MediaController::class, 'offerImage'])->name('offer-images.show');
    });

    Route::prefix('auth')->group(function () {
        Route::post('/otp/send', [AuthController::class, 'sendOtp'])->middleware('throttle:customerLogin');
        Route::post('/otp/verify', [AuthController::class, 'verifyOtp'])->middleware('throttle:customerLogin');
        Route::post('/register', [AuthController::class, 'register']); // Done
        Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum,admin');
        Route::post('/admin/password-setup', [AdminPasswordController::class, 'setup'])->middleware('throttle:adminPassword');
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
        Route::get('/components', [ComponentController::class, 'index']);
        Route::get('/sections', [CarSectionController::class, 'index']);
        Route::get('/sections/{section}/components', [CarSectionController::class, 'components']);
    });

    Route::get('/cms/{cms}', [CmsController::class, 'show']); // Done

    // Admin panel — web dashboard. Tokens come from /auth/admin/login.
    Route::middleware(['auth:sanctum,admin', 'admin.active'])->prefix('admin')->group(function () {
        Route::prefix('support')->middleware('admin.permission:support.access')->controller(AdminSupportConversationController::class)->group(function () {
            Route::get('/realtime', 'realtime');
            Route::post('/realtime/auth', 'authorizeChannel')->middleware('throttle:30,1');
            Route::get('/conversations', 'index');
            Route::get('/conversations/{supportConversation}', 'show');
            Route::get('/conversations/{supportConversation}/timeline', 'timeline');
            Route::get('/conversations/{supportConversation}/events', 'events');
            Route::post('/conversations/{supportConversation}/messages', 'send')->middleware('throttle:supportMessages');
            Route::patch('/conversations/{supportConversation}/read', 'read');
            Route::patch('/conversations/{supportConversation}/status', 'status');
        });
        Route::patch('/profile/password', [AdminPasswordController::class, 'change'])->middleware('throttle:adminPassword');
        Route::controller(AdminAccountController::class)->group(function () {
            Route::get('/accounts', 'index')->middleware('admin.permission:admins.view');
            Route::get('/accounts/roles', 'roles')->middleware('admin.permission:admins.view');
            Route::get('/accounts/{admin}/events', 'events')->middleware('admin.permission:admins.view');
            Route::post('/accounts', 'store')->middleware(['admin.permission:admins.manage', 'throttle:adminPassword']);
            Route::patch('/accounts/{admin}', 'update')->middleware(['admin.permission:admins.manage', 'throttle:adminPassword']);
            Route::post('/accounts/{admin}/reset-password', 'resetPassword')->middleware(['admin.permission:admins.manage', 'throttle:adminPassword']);
            Route::delete('/accounts/{admin}', 'destroy')->middleware(['admin.permission:admins.manage', 'throttle:adminPassword']);
        });
        Route::get('/profile', [AdminProfileController::class, 'show']);
        Route::patch('/profile/language', [AdminProfileController::class, 'updateLanguage']);
        Route::get('/cities/options', [CityController::class, 'options']);
        Route::get('/car-companies', [AdminVehicleCatalogController::class, 'companies'])->middleware('admin.permission:catalog.view');
        Route::get('/car-companies/options', [AdminVehicleCatalogController::class, 'companyOptions'])->middleware('admin.permission:catalog.view');
        Route::get('/car-names', [AdminVehicleCatalogController::class, 'names'])->middleware('admin.permission:catalog.view');
        Route::get('/catalog-countries/options', [AdminVehicleCatalogController::class, 'countryOptions'])->middleware('admin.permission:catalog.view');
        Route::post('/car-companies', [AdminVehicleCatalogController::class, 'createCompany'])->middleware('admin.permission:catalog.manage');
        Route::patch('/car-companies/{company}', [AdminVehicleCatalogController::class, 'updateCompany'])->middleware('admin.permission:catalog.manage');
        Route::delete('/car-companies/{company}', [AdminVehicleCatalogController::class, 'deleteCompany'])->middleware('admin.permission:catalog.manage');
        Route::post('/car-names', [AdminVehicleCatalogController::class, 'createName'])->middleware('admin.permission:catalog.manage');
        Route::patch('/car-names/{name}', [AdminVehicleCatalogController::class, 'updateName'])->middleware('admin.permission:catalog.manage');
        Route::delete('/car-names/{name}', [AdminVehicleCatalogController::class, 'deleteName'])->middleware('admin.permission:catalog.manage');
        Route::get('/dashboard', [AdminDashboardController::class, 'show'])->middleware('admin.permission:dashboard.view');

        Route::get('/store-requests', [AdminStoreRequestController::class, 'index'])->middleware('admin.permission:requests.view');
        Route::get('/store-requests/{storeRequest}', [AdminStoreRequestController::class, 'show'])->middleware('admin.permission:requests.view');
        Route::post('/store-requests/{storeRequest}/accept', [AdminStoreRequestController::class, 'accept'])->middleware('admin.permission:requests.manage');
        Route::post('/store-requests/{storeRequest}/reject', [AdminStoreRequestController::class, 'reject'])->middleware('admin.permission:requests.manage');

        Route::get('/users', [AdminUserController::class, 'index'])->middleware('admin.permission:users.view');
        Route::get('/users/customers', [AdminUserController::class, 'customers'])->middleware('admin.permission:users.view');
        Route::get('/users/providers', [AdminUserController::class, 'providers'])->middleware('admin.permission:users.view');
        Route::get('/users/{user}', [AdminUserController::class, 'show'])->middleware('admin.permission:users.view');
        Route::patch('/users/{user}/status', [AdminUserController::class, 'status'])->middleware('admin.permission:users.manage');

        Route::get('/stores', [AdminStoreController::class, 'index'])->middleware('admin.permission:stores.view');
        Route::get('/stores/{store}', [AdminStoreController::class, 'show'])->middleware('admin.permission:stores.view');
        Route::patch('/stores/{store}/status', [AdminStoreController::class, 'status'])->middleware('admin.permission:stores.manage');

        Route::get('/orders', [AdminOrderController::class, 'index'])->middleware('admin.permission:orders.view');
        Route::get('/payments', [AdminPaymentController::class, 'index'])->middleware('admin.permission:payments.view');
        Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->middleware('admin.permission:orders.view');
        Route::post('/orders/{order}/cancel', [AdminOrderController::class, 'cancel'])->middleware('admin.permission:orders.manage');
        Route::post('/orders/{order}/refund', [AdminOrderController::class, 'refund'])->middleware('admin.permission:payments.manage');
    });

    // Marketplace browsing — shared between customers and providers (read-only).
    // The `can_manage` flag in the resources tells the frontend whether the
    // current user owns the store (edit/delete affordances vs. plain browse).
    Route::middleware(['auth:sanctum', 'user.active', 'auth.provider'])->group(function () {
        Route::get('/component-cars', [StoreController::class, 'componentCars']); // Done

        Route::get('/stores', [StoreController::class, 'index']); // Done
        Route::get('/stores/{store}', [StoreController::class, 'show']); // Done
        Route::get('/stores/{store}/cars', [StoreController::class, 'cars']);
        Route::get('/stores/{store}/cars/{car}', [StoreController::class, 'showCar']);
        Route::get('/stores/{store}/cars/{car}/components', [StoreController::class, 'components']);
        Route::get('/stores/{store}/cars/{car}/components/{component}', [StoreController::class, 'showComponent']);
    });

    Route::middleware(['auth:sanctum', 'user.active'])->prefix('provider')->group(function () {

        // Provider onboarding — only for customers (becoming a provider)
        Route::middleware('customer')->group(function () {
            Route::post('/store-requests/verify-mobile', [ProviderStoreRequestController::class, 'sendMobileOtp'])->middleware('throttle:customerLogin');
            Route::post('/store-requests/verify-code', [ProviderStoreRequestController::class, 'verifyMobileOtp'])->middleware('throttle:customerLogin');
            Route::post('/store-requests', [ProviderStoreRequestController::class, 'store']);
        });

        Route::middleware('provider')->group(function () {
            Route::get('/dashboard', [ProviderDashboardController::class, 'show']);
            Route::get('/dashboard/orders', [ProviderDashboardController::class, 'orders']);
            Route::get('/orders/{order}/conversation', [ProviderOrderConversationController::class, 'show']);
            Route::post('/orders/{order}/conversation/messages', [ProviderOrderConversationController::class, 'store']);

            Route::put('/profile', [ProviderProfileController::class, 'update']);

            Route::get('/stores', [ProviderStoreController::class, 'index']);
            Route::get('/stores/{store}', [ProviderStoreController::class, 'show']);
            Route::post('/stores/{store}/change-requests', [ProviderStoreController::class, 'update']);
            // Legacy clients must follow the same review rules; no immediate-edit bypass.
            Route::put('/store/{store}', [ProviderStoreController::class, 'update']);

            Route::post('/store/{store}/cars', [ProviderStoreCarController::class, 'store'])->name('provider.store.cars.store');
            Route::put('/store/{store}/cars/{storeCar}', [ProviderStoreCarController::class, 'update'])->name('provider.store.cars.update');
            Route::delete('/store/{store}/cars/{storeCar}', [ProviderStoreCarController::class, 'destroy'])->name('provider.store.cars.destroy');

            Route::post('/store/{store}/cars/{storeCar}/components', [ProviderStoreCarComponentController::class, 'store'])->name('provider.store.cars.components.store');
            Route::post('/store/{store}/cars/{storeCar}/components/batch', [ProviderStoreCarComponentController::class, 'batchStore'])->name('provider.store.cars.components.batch');
            Route::put('/store/{store}/cars/{storeCar}/components/{component}', [ProviderStoreCarComponentController::class, 'update'])->name('provider.store.cars.components.update');
            Route::delete('/store/{store}/cars/{storeCar}/components/{component}', [ProviderStoreCarComponentController::class, 'destroy'])->name('provider.store.cars.components.destroy');

            Route::get('/store-requests', [ProviderStoreRequestController::class, 'index']);
            Route::get('/store-requests/{storeRequest}', [ProviderStoreRequestController::class, 'show']);
            Route::post('/store-requests/{storeRequest}/resubmit', [ProviderStoreRequestController::class, 'resubmit']);
            Route::post('/store-requests/direct', [ProviderStoreRequestController::class, 'storeDirect']);

            Route::get('/orders/general', [ProviderOrderController::class, 'general']);
            Route::get('/orders/specific', [ProviderOrderController::class, 'specific']);
            Route::get('/conversations/{conversation}/offers', [ProviderOrderController::class, 'chatOffers'])->whereNumber('conversation');
            Route::get('/orders/offers', [ProviderOrderController::class, 'offers']);
            Route::get('/orders/offers/{offer}', [ProviderOrderController::class, 'showOffer']);
            Route::get('/orders/paid', [ProviderOrderController::class, 'paidOrders']);
            Route::get('/orders/paid/{order}', [ProviderOrderController::class, 'paidOrder']);
            Route::get('/orders/{order}', [ProviderOrderController::class, 'show']);
            Route::post('/orders/{order}/offer', [ProviderOrderController::class, 'storeOffer']);
            Route::put('/orders/{order}/offer/{offer}', [ProviderOrderController::class, 'updateOffer']);
            Route::delete('/orders/{order}/offer/{offer}', [ProviderOrderController::class, 'destroyOffer']);
            Route::post('/orders/{order}/reject', [ProviderOrderController::class, 'reject']);
            Route::post('/orders/{order}/accept', [ProviderOrderController::class, 'accept']);
        });
    });

    Route::middleware(['auth:sanctum', 'user.active', 'customer'])->prefix('customer')->group(function () {
        Route::patch('/profile', [CustomerProfileController::class, 'update']);
        Route::post('/seller-application/{storeRequest}/resubmit', [ProviderStoreRequestController::class, 'resubmit']);

        Route::get('/customer-cars', [CustomerCarController::class, 'index']);
        Route::post('/customer-cars', [CustomerCarController::class, 'store']);
        Route::post('/customer-cars/{customerCar}/pictures', [CustomerCarPictureController::class, 'store'])
            ->name('customer.customer-cars.pictures.store');
        Route::get('/customer-cars/{customerCar}/pictures/{customerCarPicture}', [CustomerCarPictureController::class, 'show'])
            ->name('customer.customer-cars.pictures.show');
        Route::delete('/customer-cars/{customerCar}/pictures/{customerCarPicture}', [CustomerCarPictureController::class, 'destroy'])
            ->name('customer.customer-cars.pictures.destroy');
        Route::get('/customer-cars/{customerCar}', [CustomerCarController::class, 'show']);
        Route::patch('/customer-cars/{customerCar}', [CustomerCarController::class, 'update']);
        Route::delete('/customer-cars/{customerCar}', [CustomerCarController::class, 'destroy']);

        Route::get('/orders', [CustomerOrderController::class, 'index']);
        Route::post('/orders', [CustomerOrderController::class, 'store']); // Done
        Route::get('/orders/{order}', [CustomerOrderController::class, 'show']);
        Route::patch('/orders/{order}', [CustomerOrderController::class, 'update']);
        Route::delete('/orders/{order}', [CustomerOrderController::class, 'destroy']);
        Route::post('/orders/{order}/accept-offer', [CustomerOrderController::class, 'acceptOffer']);
        Route::post('/orders/{order}/pay', [CustomerOrderController::class, 'pay']);
        Route::post('/orders/{order}/received', [CustomerOrderController::class, 'received']);
        Route::post('/orders/{order}/cancel', [CustomerOrderController::class, 'cancel']);

        Route::get('/orders/{order}/offers', [OrderOfferController::class, 'index']); // Done
        Route::get('/orders/{order}/offers/{offer}', [OrderOfferController::class, 'show']); // Done
        Route::post('/orders/{order}/offers/{offer}/reject', [OrderOfferController::class, 'reject']);
        Route::get('/orders/{order}/offers/{offer}/conversation', [ConversationController::class, 'offerConversation']);
        Route::post('/orders/{order}/offers/{offer}/conversation/messages', [ConversationController::class, 'firstMessage']);
    });

    Route::middleware(['auth:sanctum', 'user.active', 'auth.provider'])->group(function () {
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::patch('/profile/language', [ProfileController::class, 'updateLanguage']);
        Route::get('/seller-application', [ProviderStoreRequestController::class, 'application']);

        Route::post('/push/device', [DeviceTokenController::class, 'store'])->middleware('throttle:30,1');
        Route::delete('/push/device', [DeviceTokenController::class, 'destroy'])->middleware('throttle:30,1');

        Route::prefix('support/conversation')->controller(SupportConversationController::class)->group(function () {
            Route::get('/', 'show');
            Route::get('/timeline', 'timeline');
            Route::post('/messages', 'send')->middleware('throttle:supportMessages');
            Route::patch('/read', 'read');
            Route::patch('/reopen', 'reopen');
        });
        Route::get('/chat/realtime', [ChatRealtimeController::class, 'show']);
        Route::post('/chat/realtime/auth', [ChatRealtimeController::class, 'authorizeChannel'])->middleware('throttle:30,1');
        Route::get('/conversations', [ConversationController::class, 'index']);
        Route::post('/conversations', [ConversationController::class, 'store']);
        Route::get('/conversations/{conversation}', [ConversationController::class, 'show']);
        Route::get('/conversations/{conversation}/timeline', [ConversationController::class, 'timeline']);
        Route::patch('/conversations/{conversation}/read', [ConversationController::class, 'read']);
        Route::get('/conversations/{conversation}/messages', [ConversationController::class, 'messages']);
        Route::post('/conversations/{conversation}/messages', [ConversationController::class, 'sendMessage']);

        Route::get('/ratings', [RatingController::class, 'index']);
        Route::post('/ratings', [RatingController::class, 'store']);

        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/inbox', [NotificationController::class, 'inbox']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::patch('/notifications/read-page', [NotificationController::class, 'markPageAsRead']);
        Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    });
});
