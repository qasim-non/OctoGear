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
use Illuminate\Support\Facades\Gate;
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
}
