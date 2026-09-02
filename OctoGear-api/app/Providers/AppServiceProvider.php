<?php

namespace App\Providers;

use App\Models\CustomerCar;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\Payment;
use App\Models\Rating;
use App\Models\Store;
use App\Models\StoreCarComponent;
use App\Models\StoresCar;
use App\Policies\CustomerCarPolicy;
use App\Policies\ConversationPolicy;
use App\Policies\MessagePolicy;
use App\Policies\NotificationPolicy;
use App\Policies\OrderPolicy;
use App\Policies\OrderOfferPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\RatingPolicy;
use App\Policies\StorePolicy;
use App\Policies\StoreCarComponentPolicy;
use App\Policies\StoresCarPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Notifications\DatabaseNotification;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configurePolicies();
        $this->configureRateLimiting();
    }

    private function configurePolicies(): void
    {
        Gate::policy(CustomerCar::class, CustomerCarPolicy::class);
        Gate::policy(Store::class, StorePolicy::class);
        Gate::policy(StoresCar::class, StoresCarPolicy::class);
        Gate::policy(StoreCarComponent::class, StoreCarComponentPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(OrderOffer::class, OrderOfferPolicy::class);
        Gate::policy(Conversation::class, ConversationPolicy::class);
        Gate::policy(Message::class, MessagePolicy::class);
        Gate::policy(Rating::class, RatingPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(DatabaseNotification::class, NotificationPolicy::class);
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        RateLimiter::for('customerLogin', function (Request $request) {
            $mobile = $request->input('mobile');

            return [
                Limit::perMinute(3)->by('mobile:'.$mobile),
                Limit::perMinute(3)->by('ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('adminLogin', function (Request $request) {
            $email = $request->input('email');

            return [
                Limit::perMinute(5)->by('email:'.$email),
                Limit::perMinute(5)->by('ip:'.$request->ip()),
            ];
        });
    }
}
