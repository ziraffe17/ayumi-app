<?php
namespace App\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;

class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $id = strtolower((string)$request->input('email'));
            return Limit::perMinute(5)->by($id.'|'.$request->ip());
        });

        RateLimiter::for('two-factor-email', function (Request $request) {
            $userId = optional(auth('staff')->user())->id ?? 'guest';
            return Limit::perMinute(6)->by('2fa|'.$userId.'|'.$request->ip());
        });
    }
}
