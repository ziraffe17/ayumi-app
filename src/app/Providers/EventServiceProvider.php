<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * ここに「1回だけ」定義する。
     * 既存のイベントがあれば、この配列に一緒に並べる。
     */
    protected $listen = [
        // 例）他に既存のイベント/リスナーがあればここに残す
        // \App\Events\SomethingHappened::class => [
        //     \App\Listeners\DoSomething::class,
        // ],

        // ★ 2FA（メール）監査イベント
        \App\Events\TwoFactorEmailSent::class     => [\App\Listeners\TwoFactorEmailLogger::class],
        \App\Events\TwoFactorEmailVerified::class => [\App\Listeners\TwoFactorEmailLogger::class],
        \App\Events\TwoFactorEmailFailed::class   => [\App\Listeners\TwoFactorEmailLogger::class],
    ];

    public function boot(): void
    {
        //
    }

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
