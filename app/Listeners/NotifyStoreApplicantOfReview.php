<?php

namespace App\Listeners;

use App\Events\StoreRequestReviewed;
use App\Models\User;
use App\Notifications\StoreRequestReviewedNotification;

class NotifyStoreApplicantOfReview
{
    public function handle(StoreRequestReviewed $event): void
    {
        (User::find($event->request->user_id))?->notify(new StoreRequestReviewedNotification($event->request));
    }
}
