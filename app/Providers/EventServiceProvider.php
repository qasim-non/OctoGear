<?php

namespace App\Providers;

use App\Events\MessageSent;
use App\Events\OfferCreated;
use App\Events\OrderCompleted;
use App\Events\OrderCreated;
use App\Events\OrderPaid;
use App\Listeners\NotifyConversationParticipant;
use App\Listeners\NotifyCustomerOfOffer;
use App\Listeners\NotifyProviderOfCompletion;
use App\Listeners\NotifyProviderOfPayment;
use App\Listeners\NotifyStoresOfNewOrder;
use App\Listeners\QueueChatRealtime;
use App\Listeners\QueueCustomerPush;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Notifications\Events\NotificationSent;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     */
    protected $listen = [
        NotificationSent::class => [QueueCustomerPush::class],
        OrderCreated::class => [NotifyStoresOfNewOrder::class],
        OfferCreated::class => [NotifyCustomerOfOffer::class],
        OrderPaid::class => [NotifyProviderOfPayment::class],
        OrderCompleted::class => [NotifyProviderOfCompletion::class],
        MessageSent::class => [NotifyConversationParticipant::class, QueueChatRealtime::class],
    ];

    public function boot(): void
    {
        //
    }
}
