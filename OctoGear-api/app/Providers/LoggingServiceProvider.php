<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\HandlerInterface;

class LoggingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->environment('production')) {
            $this->configureJsonLogging();
        }
    }

    private function configureJsonLogging(): void
    {
        Log::configureUsing(function ($logger) {
            foreach ($logger->getHandlers() as $handler) {
                if ($handler instanceof HandlerInterface) {
                    $handler->setFormatter(new JsonFormatter);
                }
            }
        });
    }
}
