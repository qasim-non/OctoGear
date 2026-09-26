<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('customer-car-media:purge-expired-idempotency-keys', function () {
    $cleared = app(\App\Services\CustomerCarService::class)->purgeExpiredIdempotencyKeys();

    $this->info("Cleared {$cleared} expired customer-car idempotency record(s).");
})->purpose('Release expired customer-car idempotency keys and fingerprints');

Schedule::command('customer-car-media:purge-expired-idempotency-keys')
    ->hourly()
    ->withoutOverlapping();
