<?php

namespace App\Listeners;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

class TwoFactorEmailLogger
{
    public function handle(object $event): void
    {
        $staff = $event->staff ?? null;
        $staffId = $staff?->id;

        // イベント名 → action ENUM へ
        $map = [
            'TwoFactorEmailSent'     => 'two_factor_email_sent',
            'TwoFactorEmailVerified' => 'two_factor_email_verified',
            'TwoFactorEmailFailed'   => 'two_factor_email_failed',
        ];
        $name   = class_basename($event);
        $action = $map[$name] ?? null;
        if (!$action) return;

        // 監査要件のカラムでINSERT（UTC保存・JST表示方針に従い UTC で記録）
        DB::table('audit_logs')->insert([
            'actor_type'  => 'staff',
            'actor_id'    => $staffId,
            'occurred_at' => now()->utc(),         // ★ UTC保存
            'action'      => $action,
            'entity'      => 'auth',               // 認証関連として固定
            'entity_id'   => null,
            'diff_json'   => null,
            'ip'          => Request::ip(),
            'user_agent'  => Request::userAgent(),
            'meta'        => json_encode([
                'reason' => $event->reason ?? null,   // 失敗理由など（あれば）
                // 'to'  => maskEmail($staff->email) など必要なら追記
            ], JSON_UNESCAPED_UNICODE),
        ]);
    }
}
