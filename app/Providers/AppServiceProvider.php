<?php

namespace App\Providers;

use App\Services\WhatsappManager; // Make sure this import exists
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // THIS IS THE NEW, MORE ROBUST WAY.
        $this->app->singleton(WhatsappManager::class, function ($app) {
            // We are explicitly getting the configuration from the application's
            // config repository and passing it to the manager.
            return new WhatsappManager(
                $app->make('config')->get('whatsapp')
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}