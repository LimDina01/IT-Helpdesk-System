<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Guarantee all application services are loaded even if Composer classmap is stale/authoritative
        if (is_dir(app_path('Services'))) {
            foreach (glob(app_path('Services/*.php')) as $serviceFile) {
                require_once $serviceFile;
            }
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
