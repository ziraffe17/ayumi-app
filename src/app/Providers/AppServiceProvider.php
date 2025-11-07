<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Contracts\ResetsUserPasswords;
use App\Actions\Fortify\ResetUserPassword;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ★ 一発で効く暫定バインド（まずはこれで通す）
        $this->app->singleton(ResetsUserPasswords::class, ResetUserPassword::class);
    }

    public function boot(): void
    {
        // タイムゾーンを強制的に設定
        date_default_timezone_set(config('app.timezone'));

        // データベース文字セットをUTF-8に設定
        \Illuminate\Support\Facades\DB::statement("SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'");
    }
}
