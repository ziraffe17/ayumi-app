<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;

class FortifyViewServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // 職員ログイン（Fortifyは職員専用）
        Fortify::loginView(fn() => view('auth.login'));

        // パスワードリセット（職員用）
        Fortify::requestPasswordResetLinkView(fn() => view('auth.two-factor.forgot-password'));
        Fortify::resetPasswordView(fn($request) => view('auth.two-factor.reset-password', ['request' => $request]));

        // 2FA チャレンジ（職員用）
        Fortify::twoFactorChallengeView(fn() => view('auth.two-factor-challenge'));
    }
}
