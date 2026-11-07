<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        Password::defaults(fn (): Password => Password::min(8)->max(72));

        RateLimiter::for('register', fn (Request $request): Limit => Limit::perMinute(10)->by($request->ip()));

        // Per-IP ceiling across all emails; LoginRequest also locks each email + IP pair after 5 failures.
        RateLimiter::for('login', fn (Request $request): Limit => Limit::perMinute(20)->by($request->ip()));
    }
}
