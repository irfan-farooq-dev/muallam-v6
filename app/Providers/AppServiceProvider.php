<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Post;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
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
        Paginator::useBootstrapFive();

        View::composer('partials.header', fn ($view) => $view->with('navCategories', Category::forNavigation()));

        VerifyEmail::toMailUsing(fn (object $notifiable, string $url): MailMessage => (new MailMessage)
            ->subject('Verify your Muallam email address')
            ->greeting("Hi {$notifiable->name},")
            ->line('Thanks for joining Muallam. Please confirm this is your email address by clicking the button below.')
            ->action('Verify email address', $url)
            ->line('This link expires in '.config('auth.verification.expire', 60).' minutes.')
            ->line("If you didn't create a Muallam account, you can ignore this email."));

        ResetPassword::toMailUsing(fn (object $notifiable, string $token): MailMessage => (new MailMessage)
            ->subject('Reset your Muallam password')
            ->greeting("Hi {$notifiable->name},")
            ->line('We received a request to reset the password for your Muallam account.')
            ->action('Choose a new password', route('password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()]))
            ->line('This link expires in '.config('auth.passwords.users.expire', 60).' minutes and can be used once.')
            ->line("If you didn't ask for this, you can ignore this email. Your password will not change."));

        Password::defaults(fn (): Password => Password::min(8)->max(72));

        RateLimiter::for('register', fn (Request $request): Limit => Limit::perMinute(10)->by($request->ip()));

        // Per-IP ceiling across all emails; LoginRequest also locks each email + IP pair after 5 failures.
        // Guessing a restricted post's password: 5 tries per minute per post and IP.
        // Rate limiting runs before the {post} slug is turned into a model, so the raw slug is used as the key.
        RateLimiter::for('post-access', fn (Request $request): Limit => Limit::perMinute(5)->by(
            $request->ip().'|'.($request->route('post') instanceof Post ? $request->route('post')->slug : (string) $request->route('post'))
        ));

        RateLimiter::for('password-reset', fn (Request $request): Limit => Limit::perMinute(5)->by($request->ip()));

        RateLimiter::for('login', fn (Request $request): Limit => Limit::perMinute(20)->by($request->ip()));
    }
}
