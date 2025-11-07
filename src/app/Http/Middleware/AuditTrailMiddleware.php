<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\DiffUtil;

class AuditTrailMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // 事前スナップショット（必要なときのみモデルを読む形に最適化可）
        $before = [];

        // ルートメタから entity / id 推定（コントローラで明示設定しても良い）
        $route = $request->route();
        $entity = optional($route)->getName() ?? optional($route)->uri();
        $entity = is_string($entity) ? explode('.', $entity)[0] : 'http';

        // 実行
        $response = $next($request);

        // ステータス系監査（403/401/429など）
        if (in_array($response->getStatusCode(), [401,403,429], true)) {
            $this->write('setting', $entity, null, null, $request, ['status'=>$response->getStatusCode()]);
            return $response;
        }

        // CRUD 推定（雑にHTTPメソッドで分類。必要に応じてカスタムも可）
        $method = strtoupper($request->getMethod());
        $action = match($method){
            'POST'   => 'create',
            'PUT','PATCH' => 'update',
            'DELETE' => 'delete',
            default  => null,
        };

        if ($action) {
            $after = $request->attributes->get('audit_after', []); // コントローラで埋めれば精密
            $diff  = DiffUtil::diff($before, $after, config('audit.mask_keys', []));
            $this->write($action, $entity, $after['id'] ?? null, $diff, $request, null);
        }

        return $response;
    }

    private function write(string $action, ?string $entity, $entityId, ?array $diff, Request $req, ?array $meta): void
    {
        $staff = auth('staff')->user();
        DB::table('audit_logs')->insert([
            'actor_type'  => $staff ? 'staff' : 'user',
            'actor_id'    => $staff?->id ?? optional(auth()->user())->id,
            'occurred_at' => now()->utc(),
            'action'      => $action,
            'entity'      => $entity,
            'entity_id'   => $entityId,
            'diff_json'   => $diff ? json_encode($diff, JSON_UNESCAPED_UNICODE) : null,
            'ip'          => $req->ip(),
            'user_agent'  => $req->userAgent(),
            'meta'        => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
        ]);
    }
}
