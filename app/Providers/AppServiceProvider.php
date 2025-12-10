<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Instrument;          // <--- เพิ่ม use
use App\Observers\InstrumentObserver; // <--- เพิ่ม use

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
        // สั่งให้ InstrumentObserver คอยจับตาดู Instrument
        Instrument::observe(InstrumentObserver::class);

        if ($this->app->environment('production')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
    }
}
