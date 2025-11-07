<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;

class RateLimitServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Fortify の login で使われるレートリミッター
        RateLimiter::for('login', function (Request $request) {
            // email ログイン（または login_id を使う構成でも拾えるように）
            $key = (string) ($request->input('email') ?? $request->input('login_id') ?? 'guest');
            return [
                Limit::perMinute(5)->by($key.'|'.$request->ip()),
            ];
        });

        // Fortify の 2FA チャレンジ用
        RateLimiter::for('two-factor', function (Request $request) {
            // セッションに login.id があればそれを、無ければ IP をキーに
            $key = (string) ($request->session()->get('login.id') ?? '2fa|'.$request->ip());
            return [
                Limit::perMinute(5)->by($key),
            ];
        });

            RateLimiter::for('two-factor-email', function (Request $request) {
        $id = optional($request->user('staff'))->id ?: $request->ip();
        return [
            Limit::perMinute(5)->by($id.':tf-verify'),
            Limit::perMinute(3)->by($id.':tf-resend'),
        ];
    });
}
}
