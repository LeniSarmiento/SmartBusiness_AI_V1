<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
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
        $organizationKey = static function ($request): string {
            $organization = $request->route('organization');

            return (string) (is_object($organization) && method_exists($organization, 'getKey')
                ? $organization->getKey()
                : ($organization ?? 'none'));
        };

        RateLimiter::for('login', fn ($request) => [
            Limit::perMinute(20)->by($request->ip()),
            Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()),
        ]);
        RateLimiter::for('registration', fn ($request) => Limit::perHour(5)->by($request->ip()));
        RateLimiter::for('password-recovery', fn ($request) => [
            Limit::perHour(20)->by($request->ip()),
            Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()),
        ]);
        RateLimiter::for('email-verification', fn ($request) => Limit::perMinute(6)->by(($request->user()?->id ?? $request->route('id') ?? 'guest').'|'.$request->ip()));
        RateLimiter::for('invitation-inspect', fn ($request) => Limit::perMinute(30)->by($request->ip()));
        RateLimiter::for('invitation-accept', fn ($request) => Limit::perHour(10)->by(hash('sha256', (string) $request->route('token')).'|'.$request->ip()));
        RateLimiter::for('sensitive', fn ($request) => Limit::perMinute(10)->by(($request->user()?->id ?? 'guest').'|'.$request->ip()));
        RateLimiter::for('business-write', fn ($request) => Limit::perMinute(60)->by(($request->user()?->id ?? 'guest').'|'.$organizationKey($request)));
        RateLimiter::for('ai-tools', fn ($request) => Limit::perMinute(20)->by(($request->user()?->id ?? 'guest').'|'.$organizationKey($request)));
        RateLimiter::for('ai-chat', fn ($request) => [
            Limit::perMinute(10)->by(($request->user()?->id ?? 'guest').'|'.$organizationKey($request)),
            Limit::perHour(100)->by(($request->user()?->id ?? 'guest').'|'.$organizationKey($request)),
        ]);
        RateLimiter::for('api', fn ($request) => [
            Limit::perMinute(180)->by('ip:'.$request->ip()),
            Limit::perMinute(120)->by('principal:'.($request->user()?->id ?? $request->ip())),
        ]);
        ResetPassword::createUrlUsing(fn ($user, $token) => config('app.frontend_url').'/reset-password?'.http_build_query(['token' => $token, 'email' => $user->email]));
        VerifyEmail::createUrlUsing(function ($notifiable) {
            $url = URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes(60),
                [
                    'id' => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ]
            );

            return config('app.frontend_url').'/verify-email?'.http_build_query(['url' => $url]);
        });
    }
}
