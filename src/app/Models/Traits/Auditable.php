<?php

namespace App\Models\Traits;

use App\Models\AuditLog;

trait Auditable
{
    /**
     * 簡易に監査ログを書き込む（実運用はミドルウェアで詳細化）
     */
    public function writeAudit(string $action, ?int $actorId = null, string $actorType = 'staff', ?array $diff = null, ?array $meta = null): void
    {
        try {
            AuditLog::create([
                'actor_type' => $actorType,
                'actor_id'   => $actorId ?? auth('staff')->id() ?? auth()->id(),
                'occurred_at'=> now(),
                'action'     => $action,
                'entity'     => $this->getTable(),
                'entity_id'  => $this->getKey(),
                'diff_json'  => $diff ? json_encode($diff, JSON_UNESCAPED_UNICODE) : null,
                'ip'         => request()->ip() ?? null,
                'user_agent' => substr(request()->userAgent() ?? '', 0, 512),
                'meta'       => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
            ]);
        } catch (\Throwable $e) {
            // ログ失敗は本処理を阻害しない
        }
    }
}