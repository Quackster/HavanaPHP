<?php

namespace App\Providers;

use App\Services\CaptchaGenerator;
use App\Services\HavanaConfig;
use App\Services\HotelStatus;
use App\Services\LegacyLocale;
use App\Services\LegacyPasswordHasher;
use App\Services\LegacyTemplate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(HavanaConfig::class);
        $this->app->singleton(HotelStatus::class);
        $this->app->singleton(CaptchaGenerator::class);
        $this->app->singleton(LegacyLocale::class);
        $this->app->singleton(LegacyPasswordHasher::class);
        $this->app->singleton(LegacyTemplate::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
