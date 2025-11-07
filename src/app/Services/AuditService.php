<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * 監査ログ記録サービス
 *
 * - audit_logs テーブルに記録
 * - actor_type: 'staff' / 'user' / 'system'
 * - actor_id: ログインID
 * - action: login / logout / create / update / delete / export / setting / two_factor_email_* / plans.store など
 * - entity: 対象テーブル名や論理名
 * - diff_json: 変更内容やリクエストペイロード
 * - meta: 任意のJSONメタ
 */
class AuditService
{
    /**
     * 監査ログを記録
     */
    public function record(
        string $action,
        array $diffJson = [],
        array $meta = [],
        ?int $entityId = null,
        ?string $entity = null
    ): void {
        try {
            [$actorType, $actorId] = $this->resolveActor();

            DB::table('audit_logs')->insert([
                'actor_type' => $actorType,
                'actor_id'   => $actorId,
                'occurred_at'=> now(),
                'action'     => $this->normalizeAction($action),
                'entity'     => $entity,
                'entity_id'  => $entityId,
                'diff_json'  => json_encode($diffJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'ip'         => Request::ip(),
                'user_agent' => Request::header('User-Agent'),
                'meta'       => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        } catch (\Throwable $e) {
            // 監査はアプリの処理を止めない
            report($e);
        }
    }

    /**
     * 互換性のためのlogメソッド（AttendanceControllerで使用）
     */
    public function log(
        string $actorType,
        ?int $actorId,
        string $action,
        ?string $entity = null,
        ?int $entityId = null,
        ?array $diffJson = null,
        ?array $meta = null
    ): void {
        // actor情報は無視してresolveActorで自動判定させる
        $this->record(
            action: $action,
            diffJson: $diffJson ?? [],
            meta: $meta ?? [],
            entityId: $entityId,
            entity: $entity
        );
    }

    /**
     * 操作ユーザーの特定
     */
    private function resolveActor(): array
    {
        if (Auth::guard('staff')->check()) {
            return ['staff', Auth::guard('staff')->id()];
        }
        if (Auth::guard('web')->check()) {
            return ['user', Auth::guard('web')->id()];
        }
        return ['system', null];
    }

    /**
     * アクション文字列を正規化
     */
    private function normalizeAction(string $action): string
    {
        $known = [
            'login','logout','create','update','delete','export','setting',
            'two_factor_email_sent','two_factor_email_verified','two_factor_email_failed'
        ];
        // 未登録アクションは「create/update/delete/export」などにマップ
        if (in_array($action, $known, true)) return $action;

        // 動的action名 plans.store → create として保存
        if (str_contains($action, '.store')) return 'create';
        if (str_contains($action, '.update')) return 'update';
        if (str_contains($action, '.destroy') || str_contains($action, '.delete')) return 'delete';
        if (str_contains($action, '.export')) return 'export';
        if (str_contains($action, 'setting')) return 'setting';

        return 'update'; // デフォルトfallback
    }
}