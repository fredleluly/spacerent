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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Nama hari dan bulan dalam bahasa Indonesia (translatedFormat).
        \Carbon\Carbon::setLocale('id');
        \Illuminate\Support\Carbon::setLocale('id');
    }
}
