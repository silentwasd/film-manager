<?php

namespace App\Providers;

use App\Services\Shikimori\ShikimoriClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ShikimoriClient::class, fn() => new ShikimoriClient(
            rtrim(config('services.shikimori.base_url'), '/'),
            config('services.shikimori.user_agent'),
            (int)config('services.shikimori.timeout'),
            (int)config('services.shikimori.delay_ms')
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
