<?php

namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AuditLogController extends Controller
{
    /**
     * 監査ログ検索・一覧取得 (S-12)
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAuditLogs');

        $request->validate([
            'start_date' => 'sometimes|date|date_format:Y-m-d',
            'end_date' => 'sometimes|date|date_format:Y-m-d|after_or_equal:start_date',
            'actor_type' => 'sometimes|string|in:user,staff,system',
            'actor_id' => 'sometimes|integer',
            'action' => 'sometimes|string|in:login,logout,create,update,delete,export,setting,view,import',
            'entity' => 'sometimes|string',
            'entity_id' => 'sometimes|integer',
            'ip' => 'sometimes|string|ip',
            'search' => 'sometimes|string|max:100',
            'limit' => 'sometimes|integer|min:1|max:100',
            'offset' => 'sometimes|integer|min:0',
            'sort_by' => 'sometimes|string|in:occurred_at,actor_type,action,entity',
            'sort_order' => 'sometimes|string|in:asc,desc',
        ]);

        try {
            $startTime = microtime(true);

            // 基本クエリ構築
            $query = AuditLog::query();

            // 期間フィルタ
            $startDate = $request->string('start_date', now()->subDays(30)->format('Y-m-d'));
            $endDate = $request->string('end_date', now()->format('Y-m-d'));
            $query->whereBetween('occurred_at', [
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59'
            ]);

            // 行為者フィルタ
            if ($request->has('actor_type')) {
                $query->where('actor_type', $request->actor_type);
            }
            if ($request->has('actor_id')) {
                $query->where('actor_id', $request->actor_id);
            }

            // 操作フィルタ
            if ($request->has('action')) {
                $query->where('action', $request->action);
            }

            // エンティティフィルタ
            if ($request->has('entity')) {
                $query->where('entity', 'LIKE', '%' . $request->entity . '%');
            }
            if ($request->has('entity_id')) {
                $query->where('entity_id', $request->entity_id);
            }

            // IPアドレスフィルタ
            if ($request->has('ip')) {
                $query->where('ip', $request->ip);
            }

            // 検索フィルタ
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('entity', 'LIKE', "%{$search}%")
                      ->orWhere('action', 'LIKE', "%{$search}%")
                      ->orWhere('ip', 'LIKE', "%{$search}%")
                      ->orWhere('user_agent', 'LIKE', "%{$search}%")
                      ->orWhere('meta', 'LIKE', "%{$search}%");
                });
            }

            // 件数取得（ページング用）
            $totalCount = $query->count();

            // ソート
            $sortBy = $request->string('sort_by', 'occurred_at');
            $sortOrder = $request->string('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // ページング
            $limit = $request->integer('limit', 50);
            $offset = $request->integer('offset', 0);
            $query->offset($offset)->limit($limit);

            // データ取得
            $auditLogs = $query->get();

            // データ整形（PII最小化）
            $formattedLogs = $auditLogs->map(function ($log) {
                return $this->formatAuditLogForDisplay($log);
            });

            $responseTime = microtime(true) - $startTime;

            return response()->json([
                'success' => true,
                'data' => [
                    'logs' => $formattedLogs,
                    'period' => ['start' => $startDate, 'end' => $endDate],
                    'pagination' => [
                        'total' => $totalCount,
                        'limit' => $limit,
                        'offset' => $offset,
                        'has_more' => ($offset + $limit) < $totalCount,
                        'current_page' => floor($offset / $limit) + 1,
                        'total_pages' => ceil($totalCount / $limit),
                    ],
                    'statistics' => $this->calculateLogStatistics($startDate, $endDate),
                ],
                'performance' => [
                    'response_time' => round($responseTime * 1000, 2),
                ],
            ]);

        } catch (\Exception $e) {
            \Log::error('Audit log fetch failed', [
                'request' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '監査ログの取得に失敗しました',
            ], 500);
        }
    }

    /**
     * 監査ログ詳細取得
     */
    public function show(int $id): JsonResponse
    {
        Gate::authorize('viewAuditLogs', auth()->user());

        try {
            $auditLog = AuditLog::findOrFail($id);
            
            $formattedLog = $this->formatAuditLogForDisplay($auditLog, true);

            return response()->json([
                'success' => true,
                'data' => $formattedLog,
            ]);

        } catch (\Exception $e) {
            \Log::error('Audit log detail fetch failed', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '監査ログ詳細の取得に失敗しました',
            ], 500);
        }
    }

    /**
     * 監査ログCSV出力
     */
    public function export(Request $request)
    {
        Gate::authorize('viewAuditLogs', auth()->user());

        $request->validate([
            'start_date' => 'sometimes|date|date_format:Y-m-d',
            'end_date' => 'sometimes|date|date_format:Y-m-d|after_or_equal:start_date',
            'actor_type' => 'sometimes|string|in:user,staff,system',
            'action' => 'sometimes|string',
            'format' => 'sometimes|string|in:utf8,sjis',
        ]);

        try {
            $startDate = $request->string('start_date', now()->subDays(30)->format('Y-m-d'));
            $endDate = $request->string('end_date', now()->format('Y-m-d'));
            $format = $request->string('format', 'utf8');

            // データ取得
            $query = AuditLog::whereBetween('occurred_at', [
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59'
            ]);

            if ($request->has('actor_type')) {
                $query->where('actor_type', $request->actor_type);
            }
            if ($request->has('action')) {
                $query->where('action', $request->action);
            }

            $auditLogs = $query->orderBy('occurred_at', 'desc')->get();

            // CSV生成
            $csvData = $this->generateAuditLogCsv($auditLogs);
            $csvContent = $this->arrayToCsv($csvData, $format);

            // ファイル名生成
            $filename = "audit_logs_{$startDate}_{$endDate}_" . now()->format('YmdHis') . '.csv';

            // 出力操作を監査ログに記録
            $this->auditLog('export', 'audit_log_csv', null, [
                'period' => ['start' => $startDate, 'end' => $endDate],
                'record_count' => count($auditLogs),
                'filename' => $filename,
            ]);

            return response($csvContent)
                ->header('Content-Type', 'application/csv')
                ->header('Content-Disposition', "attachment; filename=\"{$filename}\"")
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate');

        } catch (\Exception $e) {
            \Log::error('Audit log export failed', [
                'request' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '監査ログの出力に失敗しました',
            ], 500);
        }
    }

    /**
     * 監査ログ統計情報取得
     */
    public function statistics(Request $request): JsonResponse
    {
        Gate::authorize('viewAuditLogs', auth()->user());

        $request->validate([
            'start_date' => 'sometimes|date|date_format:Y-m-d',
            'end_date' => 'sometimes|date|date_format:Y-m-d|after_or_equal:start_date',
            'granularity' => 'sometimes|string|in:hour,day,week,month',
        ]);

        try {
            $startDate = $request->string('start_date', now()->subDays(7)->format('Y-m-d'));
            $endDate = $request->string('end_date', now()->format('Y-m-d'));
            $granularity = $request->string('granularity', 'day');

            $statistics = [
                'summary' => $this->calculateLogStatistics($startDate, $endDate),
                'timeline' => $this->calculateTimelineStatistics($startDate, $endDate, $granularity),
                'top_actions' => $this->getTopActions($startDate, $endDate),
                'top_entities' => $this->getTopEntities($startDate, $endDate),
                'security_events' => $this->getSecurityEvents($startDate, $endDate),
            ];

            return response()->json([
                'success' => true,
                'data' => $statistics,
            ]);

        } catch (\Exception $e) {
            \Log::error('Audit log statistics failed', [
                'request' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '統計情報の取得に失敗しました',
            ], 500);
        }
    }

    /**
     * 監査ログ表示用フォーマット（PII最小化）
     */
    private function formatAuditLogForDisplay(AuditLog $log, bool $detailed = false): array
    {
        $formatted = [
            'id' => $log->id,
            'occurred_at' => $log->occurred_at->toISOString(),
            'occurred_at_jst' => $log->occurred_at->setTimezone('Asia/Tokyo')->format('Y-m-d H:i:s'),
            'actor_type' => $log->actor_type,
            'actor_id' => $log->actor_id,
            'actor_name' => $this->getActorName($log->actor_type, $log->actor_id),
            'action' => $log->action,
            'action_display' => $this->translateAction($log->action),
            'entity' => $log->entity,
            'entity_id' => $log->entity_id,
            'entity_display' => $this->translateEntity($log->entity),
            'ip' => $this->maskIpForDisplay($log->ip),
            'user_agent_summary' => $this->summarizeUserAgent($log->user_agent),
        ];

        if ($detailed) {
            $formatted['meta'] = $this->formatMetaForDisplay($log->meta);
            $formatted['diff_json'] = $this->formatDiffForDisplay($log->diff_json);
            $formatted['raw_user_agent'] = $log->user_agent;
        }

        return $formatted;
    }

    /**
     * 行為者名取得
     */
    private function getActorName(string $actorType, ?int $actorId): string
    {
        if (!$actorId) {
            return $actorType === 'system' ? 'システム' : '不明';
        }

        try {
            if ($actorType === 'user') {
                $user = User::find($actorId);
                return $user ? $user->name : "利用者ID:{$actorId}（削除済み）";
            }

            if ($actorType === 'staff') {
                $staff = Staff::find($actorId);
                return $staff ? $staff->name : "職員ID:{$actorId}（削除済み）";
            }

            return '不明';
        } catch (\Exception $e) {
            return "ID:{$actorId}（取得エラー）";
        }
    }

    /**
     * IPアドレスマスキング（PII保護）
     */
    private function maskIpForDisplay(?string $ip): string
    {
        if (!$ip) {
            return '';
        }

        // IPv4の場合、最後のオクテットをマスク
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            $parts[3] = 'xxx';
            return implode('.', $parts);
        }

        // IPv6の場合、後半をマスク
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $parts = explode(':', $ip);
            for ($i = 4; $i < count($parts); $i++) {
                $parts[$i] = 'xxxx';
            }
            return implode(':', $parts);
        }

        return 'xxx.xxx.xxx.xxx';
    }

    /**
     * User Agent要約
     */
    private function summarizeUserAgent(?string $userAgent): string
    {
        if (!$userAgent) {
            return '';
        }

        // ブラウザ検出
        if (preg_match('/Chrome\/[\d.]+/', $userAgent)) {
            return 'Chrome';
        }
        if (preg_match('/Firefox\/[\d.]+/', $userAgent)) {
            return 'Firefox';
        }
        if (preg_match('/Safari\/[\d.]+/', $userAgent)) {
            return 'Safari';
        }
        if (preg_match('/Edge\/[\d.]+/', $userAgent)) {
            return 'Edge';
        }

        return 'その他ブラウザ';
    }

    /**
     * メタ情報表示用フォーマット
     */
    private function formatMetaForDisplay(?string $meta): array
    {
        if (!$meta) {
            return [];
        }

        try {
            $decoded = json_decode($meta, true);
            
            // PII除去・マスキング
            $filtered = $this->filterSensitiveData($decoded);
            
            return $filtered;
        } catch (\Exception $e) {
            return ['error' => 'メタ情報の解析に失敗'];
        }
    }

    /**
     * 差分情報表示用フォーマット
     */
    private function formatDiffForDisplay(?string $diffJson): array
    {
        if (!$diffJson) {
            return [];
        }

        try {
            $decoded = json_decode($diffJson, true);
            
            // 機密情報除去
            $filtered = $this->filterSensitiveData($decoded);
            
            return $filtered;
        } catch (\Exception $e) {
            return ['error' => '差分情報の解析に失敗'];
        }
    }

    /**
     * 機密情報フィルタリング
     */
    private function filterSensitiveData(array $data): array
    {
        $sensitiveKeys = [
            'password', 'password_confirmation', 'token', 'api_key', 
            'secret', 'private_key', 'care_notes_enc', 'summary_enc', 'detail_enc'
        ];

        array_walk_recursive($data, function (&$value, $key) use ($sensitiveKeys) {
            if (in_array(strtolower($key), $sensitiveKeys)) {
                $value = '[FILTERED]';
            }
        });

        return $data;
    }

    /**
     * 操作種別の日本語変換
     */
    private function translateAction(string $action): string
    {
        return match ($action) {
            'login' => 'ログイン',
            'logout' => 'ログアウト',
            'create' => '作成',
            'update' => '更新',
            'delete' => '削除',
            'export' => '出力',
            'import' => '取り込み',
            'setting' => '設定変更',
            'view' => '閲覧',
            default => $action,
        };
    }

    /**
     * エンティティの日本語変換
     */
    private function translateEntity(string $entity): string
    {
        return match ($entity) {
            'user' => '利用者',
            'staff' => '職員',
            'attendance_plan' => '出席予定',
            'attendance_record' => '出席実績',
            'daily_report_morning' => '朝の日報',
            'daily_report_evening' => '夕の日報',
            'interview' => '面談記録',
            'holiday' => '祝日',
            'audit_log' => '監査ログ',
            'dashboard_personal' => '個人ダッシュボード',
            'dashboard_facility' => '事業所ダッシュボード',
            default => $entity,
        };
    }

    /**
     * ログ統計計算
     */
    private function calculateLogStatistics(string $startDate, string $endDate): array
    {
        $query = AuditLog::whereBetween('occurred_at', [
            $startDate . ' 00:00:00',
            $endDate . ' 23:59:59'
        ]);

        $totalLogs = $query->count();
        $userLogs = $query->clone()->where('actor_type', 'user')->count();
        $staffLogs = $query->clone()->where('actor_type', 'staff')->count();
        $systemLogs = $query->clone()->where('actor_type', 'system')->count();

        $loginAttempts = $query->clone()->where('action', 'login')->count();
        $failedLogins = $query->clone()
            ->where('action', 'login')
            ->where('meta', 'LIKE', '%failed%')
            ->count();

        return [
            'total_logs' => $totalLogs,
            'user_logs' => $userLogs,
            'staff_logs' => $staffLogs,
            'system_logs' => $systemLogs,
            'login_attempts' => $loginAttempts,
            'failed_logins' => $failedLogins,
            'success_rate' => $loginAttempts > 0 
                ? round((($loginAttempts - $failedLogins) / $loginAttempts) * 100, 1) 
                : 100,
        ];
    }

    /**
     * タイムライン統計計算
     */
    private function calculateTimelineStatistics(string $startDate, string $endDate, string $granularity): array
    {
        $format = match ($granularity) {
            'hour' => '%Y-%m-%d %H:00:00',
            'day' => '%Y-%m-%d',
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            default => '%Y-%m-%d',
        };

        $timeline = DB::table('audit_logs')
            ->select(
                DB::raw("DATE_FORMAT(occurred_at, '{$format}') as period"),
                DB::raw('COUNT(*) as count'),
                DB::raw("COUNT(CASE WHEN action = 'login' THEN 1 END) as logins"),
                DB::raw("COUNT(CASE WHEN action IN ('create', 'update', 'delete') THEN 1 END) as modifications")
            )
            ->whereBetween('occurred_at', [
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59'
            ])
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return $timeline->toArray();
    }

    /**
     * 上位操作取得
     */
    private function getTopActions(string $startDate, string $endDate): array
    {
        return DB::table('audit_logs')
            ->select('action', DB::raw('COUNT(*) as count'))
            ->whereBetween('occurred_at', [
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59'
            ])
            ->groupBy('action')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'action' => $item->action,
                    'action_display' => $this->translateAction($item->action),
                    'count' => $item->count,
                ];
            })
            ->toArray();
    }

    /**
     * 上位エンティティ取得
     */
    private function getTopEntities(string $startDate, string $endDate): array
    {
        return DB::table('audit_logs')
            ->select('entity', DB::raw('COUNT(*) as count'))
            ->whereBetween('occurred_at', [
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59'
            ])
            ->whereNotNull('entity')
            ->groupBy('entity')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'entity' => $item->entity,
                    'entity_display' => $this->translateEntity($item->entity),
                    'count' => $item->count,
                ];
            })
            ->toArray();
    }

    /**
     * セキュリティイベント検出
     */
    private function getSecurityEvents(string $startDate, string $endDate): array
    {
        $events = [];

        // 連続ログイン失敗
        $failedLogins = DB::table('audit_logs')
            ->select('ip', 'actor_id', DB::raw('COUNT(*) as attempts'))
            ->where('action', 'login')
            ->where('meta', 'LIKE', '%failed%')
            ->whereBetween('occurred_at', [
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59'
            ])
            ->groupBy('ip', 'actor_id')
            ->having('attempts', '>=', 5)
            ->get();

        foreach ($failedLogins as $failed) {
            $events[] = [
                'type' => 'multiple_failed_logins',
                'severity' => 'high',
                'message' => "IP {$failed->ip} で連続ログイン失敗 ({$failed->attempts}回)",
                'count' => $failed->attempts,
            ];
        }

        // 深夜アクセス
        $nightAccess = DB::table('audit_logs')
            ->whereBetween('occurred_at', [
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59'
            ])
            ->where(function ($query) {
                $query->whereTime('occurred_at', '>=', '00:00:00')
                      ->whereTime('occurred_at', '<=', '05:00:00');
            })
            ->count();

        if ($nightAccess > 0) {
            $events[] = [
                'type' => 'night_access',
                'severity' => 'medium',
                'message' => "深夜時間帯のアクセス ({$nightAccess}件)",
                'count' => $nightAccess,
            ];
        }

        return $events;
    }

    /**
     * 監査ログCSV生成
     */
    private function generateAuditLogCsv($auditLogs): array
    {
        $csvData = [];

        // ヘッダー
        $csvData[] = [
            'ID', '発生日時', '行為者種別', '行為者ID', '行為者名',
            '操作', 'エンティティ', 'エンティティID', 'IPアドレス', 'ブラウザ'
        ];

        foreach ($auditLogs as $log) {
            $csvData[] = [
                $log->id,
                $log->occurred_at->setTimezone('Asia/Tokyo')->format('Y-m-d H:i:s'),
                $this->translateActorType($log->actor_type),
                $log->actor_id ?? '',
                $this->getActorName($log->actor_type, $log->actor_id),
                $this->translateAction($log->action),
                $this->translateEntity($log->entity ?? ''),
                $log->entity_id ?? '',
                $this->maskIpForDisplay($log->ip),
                $this->summarizeUserAgent($log->user_agent),
            ];
        }

        return $csvData;
    }

    /**
     * 行為者種別の日本語変換
     */
    private function translateActorType(string $actorType): string
    {
        return match ($actorType) {
            'user' => '利用者',
            'staff' => '職員',
            'system' => 'システム',
            default => $actorType,
        };
    }

    /**
     * 配列をCSV形式に変換
     */
    private function arrayToCsv(array $data, string $format): string
    {
        $output = '';
        
        foreach ($data as $row) {
            $escapedRow = array_map(function ($field) {
                $field = (string) $field;
                $field = str_replace('"', '""', $field);
                if (strpos($field, ',') !== false || strpos($field, "\n") !== false || strpos($field, '"') !== false) {
                    $field = '"' . $field . '"';
                }
                return $field;
            }, $row);
            
            $output .= implode(',', $escapedRow) . "\n";
        }

        // 文字コード変換
        if ($format === 'sjis') {
            $output = "\xEF\xBB\xBF" . mb_convert_encoding($output, 'SJIS-win', 'UTF-8');
        } else {
            $output = "\xEF\xBB\xBF" . $output;
        }

        return $output;
    }

    /**
     * 監査ログ記録
     */
    private function auditLog(string $action, string $entity, ?int $entityId, array $meta = []): void
    {
        try {
            AuditLog::create([
                'actor_type' => 'staff',
                'actor_id' => auth()->id(),
                'occurred_at' => now(),
                'action' => $action,
                'entity' => $entity,
                'entity_id' => $entityId,
                'diff_json' => null,
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'meta' => json_encode($meta),
            ]);
        } catch (\Exception $e) {
            \Log::error('Audit log creation failed', [
                'action' => $action,
                'entity' => $entity,
                'error' => $e->getMessage(),
            ]);
        }
    }
}