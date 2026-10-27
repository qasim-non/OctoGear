<?php

use App\Repositories\AuthSessionRepository;
use App\Services\CustomerCarService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('auth-sessions:prune', function () {
    app(AuthSessionRepository::class)->prune();
    $this->info('Expired authentication credentials cleaned.');
})->purpose('Clean expired logins and refresh recovery receipts, preserving active channel anchors');
Schedule::command('auth-sessions:prune')->everyFiveMinutes()->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('customer-car-media:purge-expired-idempotency-keys', function () {
    $cleared = app(CustomerCarService::class)->purgeExpiredIdempotencyKeys();

    $this->info("Cleared {$cleared} expired customer-car idempotency record(s).");
})->purpose('Release expired customer-car idempotency keys and fingerprints');

Schedule::command('customer-car-media:purge-expired-idempotency-keys')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('images:cleanup')
    ->everyFifteenMinutes()
    ->withoutOverlapping();
