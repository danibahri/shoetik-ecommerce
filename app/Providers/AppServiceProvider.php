<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */

    // public function boot(): void
    // {
    //     if(config('app.env') === 'local') {
    //         URL::forceFcheme('https');
    //     }
    // }

    public function boot(): void
    {
        // if (config('app.env') === 'local') {
        //     URL::forceScheme('https');
        // }
        Schema::defaultStringLength(191);
    }
}
