<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\KpiCalculationService;
use App\Models\User;
use App\Models\AttendancePlan;
use App\Models\AttendanceRecord;
use App\Models\DailyReportMorning;
use App\Models\DailyReportEvening;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Carbon\Carbon;

class CsvExportController extends Controller
{
    protected KpiCalculationService $kpiService;

    public function __construct(KpiCalculationService $kpiService)
    {
        $this->kpiService = $kpiService;
    }

    /**
     * 出席データCSV出力
     */
    public function exportAttendance(Request $request): Response
    {
        Gate::authorize('exportCsv', auth()->user());

        $request->validate([
            'start_date' => 'required|date|date_format:Y-m-d',
            'end_date' => 'required|date|date_format:Y-m-d|after_or_equal:start_date',
            'user_ids' => 'sometimes|array',
            'user_ids.*' => 'integer|exists:users,id',
            'include_plans' => 'sometimes|boolean',
            'include_records' => 'sometimes|boolean',
            'include_comparison' => 'sometimes|boolean',
            'format' => 'sometimes|string|in:utf8,sjis',
        ]);

        try {
            $startTime = microtime(true);

            $startDate = $request->start_date;
            $endDate = $request->end_date;
            $userIds = $request->input('user_ids');
            $includePlans = $request->boolean('include_plans', true);
            $includeRecords = $request->boolean('include_records', true);
            $includeComparison = $request->boolean('include_comparison', false);
            $format = $request->string('format', 'utf8');

            // ユーザー取得
            $usersQuery = User::where('is_active', 1)->orderBy('login_code');
            if ($userIds) {
                $usersQuery->whereIn('id', $userIds);
            }
            $users = $usersQuery->get();

            // CSVデータ生成
            $csvData = $this->generateAttendanceCsvData(
                $users, $startDate, $endDate, 
                $includePlans, $includeRecords, $includeComparison
            );

            // CSV形式に変換
            $csvContent = $this->arrayToCsv($csvData, $format);

            // ファイル名生成
            $filename = "attendance_{$startDate}_{$endDate}_" . now()->format('YmdHis') . '.csv';

            // 監査ログ記録
            $this->auditLog('export', 'attendance_csv', null, [
                'period' => ['start' => $startDate, 'end' => $endDate],
                'user_count' => count($users),
                'options' => compact('includePlans', 'includeRecords', 'includeComparison'),
                'filename' => $filename,
            ]);

            $responseTime = microtime(true) - $startTime;
            
            // パフォーマンス監視（P95 ≤ 5s要件）
            if ($responseTime > 5.0) {
                \Log::warning('CSV export slow response', [
                    'response_time' => $responseTime,
                    'data_size' => count($csvData),
                    'params' => $request->all(),
                ]);
            }

            return response($csvContent)
                ->header('Content-Type', 'application/csv')
                ->header('Content-Disposition', "attachment; filename=\"{$filename}\"")
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');

        } catch (\Exception $e) {
            \Log::error('Attendance CSV export failed', [
                'request' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '出席データの出力に失敗しました',
            ], 500);
        }
    }

    /**
     * 日報データCSV出力
     */
    public function exportReports(Request $request): Response
    {
        Gate::authorize('exportCsv', auth()->user());

        $request->validate([
            'start_date' => 'required|date|date_format:Y-m-d',
            'end_date' => 'required|date|date_format:Y-m-d|after_or_equal:start_date',
            'user_ids' => 'sometimes|array',
            'user_ids.*' => 'integer|exists:users,id',
            'report_type' => 'sometimes|string|in:morning,evening,both',
            'include_averages' => 'sometimes|boolean',
            'format' => 'sometimes|string|in:utf8,sjis',
        ]);

        try {
            $startDate = $request->start_date;
            $endDate = $request->end_date;
            $userIds = $request->input('user_ids');
            $reportType = $request->string('report_type', 'both');
            $includeAverages = $request->boolean('include_averages', true);
            $format = $request->string('format', 'utf8');

            // ユーザー取得
            $usersQuery = User::where('is_active', 1)->orderBy('login_code');
            if ($userIds) {
                $usersQuery->whereIn('id', $userIds);
            }
            $users = $usersQuery->get();

            // CSVデータ生成
            $csvData = $this->generateReportsCsvData(
                $users, $startDate, $endDate, $reportType, $includeAverages
            );

            // CSV形式に変換
            $csvContent = $this->arrayToCsv($csvData, $format);

            // ファイル名生成
            $filename = "reports_{$reportType}_{$startDate}_{$endDate}_" . now()->format('YmdHis') . '.csv';

            // 監査ログ記録
            $this->auditLog('export', 'reports_csv', null, [
                'period' => ['start' => $startDate, 'end' => $endDate],
                'user_count' => count($users),
                'report_type' => $reportType,
                'include_averages' => $includeAverages,
                'filename' => $filename,
            ]);

            return response($csvContent)
                ->header('Content-Type', 'application/csv')
                ->header('Content-Disposition', "attachment; filename=\"{$filename}\"")
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');

        } catch (\Exception $e) {
            \Log::error('Reports CSV export failed', [
                'request' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '日報データの出力に失敗しました',
            ], 500);
        }
    }

    /**
     * KPI集計データCSV出力
     */
    public function exportKpi(Request $request): Response
    {
        Gate::authorize('exportCsv', auth()->user());

        $request->validate([
            'start_date' => 'required|date|date_format:Y-m-d',
            'end_date' => 'required|date|date_format:Y-m-d|after_or_equal:start_date',
            'user_ids' => 'sometimes|array',
            'user_ids.*' => 'integer|exists:users,id',
            'include_trends' => 'sometimes|boolean',
            'include_alerts' => 'sometimes|boolean',
            'format' => 'sometimes|string|in:utf8,sjis',
        ]);

        try {
            $startDate = $request->start_date;
            $endDate = $request->end_date;
            $userIds = $request->input('user_ids');
            $includeTrends = $request->boolean('include_trends', false);
            $includeAlerts = $request->boolean('include_alerts', true);
            $format = $request->string('format', 'utf8');

            // ユーザー取得
            $usersQuery = User::where('is_active', 1)->orderBy('login_code');
            if ($userIds) {
                $usersQuery->whereIn('id', $userIds);
            }
            $users = $usersQuery->get();

            // KPIデータ生成
            $csvData = $this->generateKpiCsvData(
                $users, $startDate, $endDate, $includeTrends, $includeAlerts
            );

            // CSV形式に変換
            $csvContent = $this->arrayToCsv($csvData, $format);

            // ファイル名生成
            $filename = "kpi_{$startDate}_{$endDate}_" . now()->format('YmdHis') . '.csv';

            // 監査ログ記録
            $this->auditLog('export', 'kpi_csv', null, [
                'period' => ['start' => $startDate, 'end' => $endDate],
                'user_count' => count($users),
                'options' => compact('includeTrends', 'includeAlerts'),
                'filename' => $filename,
            ]);

            return response($csvContent)
                ->header('Content-Type', 'application/csv')
                ->header('Content-Disposition', "attachment; filename=\"{$filename}\"")
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');

        } catch (\Exception $e) {
            \Log::error('KPI CSV export failed', [
                'request' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'KPIデータの出力に失敗しました',
            ], 500);
        }
    }

    /**
     * 出席データCSV生成
     */
    private function generateAttendanceCsvData(
        $users, string $startDate, string $endDate,
        bool $includePlans, bool $includeRecords, bool $includeComparison
    ): array {
        $csvData = [];

        // ヘッダー生成
        $headers = [
            '利用者ID', '氏名', 'ログインコード', '日付', '曜日', '時間枠'
        ];

        if ($includePlans) {
            $headers = array_merge($headers, ['予定種別', '予定備考']);
        }

        if ($includeRecords) {
            $headers = array_merge($headers, ['実績種別', '実績備考', '入力者']);
        }

        if ($includeComparison) {
            $headers = array_merge($headers, ['差分状況', '差分メッセージ']);
        }

        $csvData[] = $headers;

        // データ生成
        foreach ($users as $user) {
            $userPlans = $includePlans ? AttendancePlan::where('user_id', $user->id)
                ->whereBetween('plan_date', [$startDate, $endDate])
                ->get() : collect();

            $userRecords = $includeRecords ? AttendanceRecord::where('user_id', $user->id)
                ->whereBetween('record_date', [$startDate, $endDate])
                ->get() : collect();

            // 日付範囲でループ
            $currentDate = Carbon::parse($startDate);
            $endDateCarbon = Carbon::parse($endDate);

            while ($currentDate <= $endDateCarbon) {
                $dateString = $currentDate->format('Y-m-d');
                $dayOfWeek = $currentDate->isoFormat('ddd');

                foreach (['am', 'pm', 'full'] as $timeSlot) {
                    $plan = $userPlans->where('plan_date', $dateString)
                        ->where('plan_time_slot', $timeSlot)
                        ->first();

                    $record = $userRecords->where('record_date', $dateString)
                        ->where('record_time_slot', $timeSlot)
                        ->first();

                    // データがない場合はスキップ
                    if (!$plan && !$record) {
                        continue;
                    }

                    $row = [
                        $user->id,
                        $user->name,
                        $user->login_code,
                        $dateString,
                        $dayOfWeek,
                        $timeSlot,
                    ];

                    if ($includePlans) {
                        $row[] = $plan ? $this->translatePlanType($plan->plan_type) : '';
                        $row[] = $plan ? $plan->note : '';
                    }

                    if ($includeRecords) {
                        $row[] = $record ? $this->translateRecordType($record->attendance_type) : '';
                        $row[] = $record ? $record->note : '';
                        $row[] = $record ? $this->translateSource($record->source) : '';
                    }

                    if ($includeComparison) {
                        $difference = $this->calculateDifference($plan, $record);
                        $row[] = $this->translateDifferenceStatus($difference['status']);
                        $row[] = $difference['message'] ?? '';
                    }

                    $csvData[] = $row;
                }

                $currentDate->addDay();
            }
        }

        return $csvData;
    }

    /**
     * 日報データCSV生成
     */
    private function generateReportsCsvData(
        $users, string $startDate, string $endDate,
        string $reportType, bool $includeAverages
    ): array {
        $csvData = [];

        if ($reportType === 'morning' || $reportType === 'both') {
            $csvData = array_merge($csvData, $this->generateMorningReportsCsv($users, $startDate, $endDate));
        }

        if ($reportType === 'evening' || $reportType === 'both') {
            if (!empty($csvData)) {
                $csvData[] = []; // 空行で区切り
            }
            $csvData = array_merge($csvData, $this->generateEveningReportsCsv($users, $startDate, $endDate));
        }

        if ($includeAverages && ($reportType === 'morning' || $reportType === 'both')) {
            $csvData[] = []; // 空行
            $csvData = array_merge($csvData, $this->generateReportsAverages($users, $startDate, $endDate));
        }

        return $csvData;
    }

    /**
     * 朝の日報CSV生成
     */
    private function generateMorningReportsCsv($users, string $startDate, string $endDate): array
    {
        $csvData = [];

        // ヘッダー
        $csvData[] = [
            '利用者ID', '氏名', 'ログインコード', '日付', '曜日',
            '睡眠評価', 'ストレス評価', '食事評価',
            '就寝時刻', '起床時刻', '睡眠時間（分）',
            '中途覚醒回数', '早朝覚醒', '朝食済', '入浴済', '服薬状況',
            '気分スコア', '良好サイン', '注意サイン', '悪化サイン',
            '相談・連絡', '作成日時'
        ];

        foreach ($users as $user) {
            $reports = DailyReportMorning::where('user_id', $user->id)
                ->whereBetween('report_date', [$startDate, $endDate])
                ->orderBy('report_date')
                ->get();

            foreach ($reports as $report) {
                $date = Carbon::parse($report->report_date);
                
                $csvData[] = [
                    $user->id,
                    $user->name,
                    $user->login_code,
                    $report->report_date,
                    $date->isoFormat('ddd'),
                    $this->getRatingDisplay($report->sleep_rating),
                    $this->getRatingDisplay($report->stress_rating),
                    $this->getRatingDisplay($report->meal_rating),
                    $report->bed_time_local,
                    $report->wake_time_local,
                    $report->sleep_minutes,
                    $report->mid_awaken_count,
                    $report->is_early_awaken ? 'あり' : 'なし',
                    $report->is_breakfast_done ? '済' : '未',
                    $report->is_bathing_done ? '済' : '未',
                    $this->getMedicationStatusDisplay($report->is_medication_taken),
                    $report->mood_score,
                    $report->sign_good,
                    $report->sign_caution,
                    $report->sign_bad,
                    $report->note,
                    $report->created_at->format('Y-m-d H:i:s'),
                ];
            }
        }

        return $csvData;
    }

    /**
     * 夕の日報CSV生成
     */
    private function generateEveningReportsCsv($users, string $startDate, string $endDate): array
    {
        $csvData = [];

        // ヘッダー
        $csvData[] = [
            '利用者ID', '氏名', 'ログインコード', '日付', '曜日',
            '今日の訓練内容', '訓練の振り返り', '体調について', 'その他',
            '作成日時'
        ];

        foreach ($users as $user) {
            $reports = DailyReportEvening::where('user_id', $user->id)
                ->whereBetween('report_date', [$startDate, $endDate])
                ->orderBy('report_date')
                ->get();

            foreach ($reports as $report) {
                $date = Carbon::parse($report->report_date);
                
                $csvData[] = [
                    $user->id,
                    $user->name,
                    $user->login_code,
                    $report->report_date,
                    $date->isoFormat('ddd'),
                    $report->training_summary,
                    $report->training_feedback,
                    $report->condition_note,
                    $report->other_note,
                    $report->created_at->format('Y-m-d H:i:s'),
                ];
            }
        }

        return $csvData;
    }

    /**
     * 日報平均値CSV生成
     */
    private function generateReportsAverages($users, string $startDate, string $endDate): array
    {
        $csvData = [];

        // ヘッダー
        $csvData[] = [
            '【平均値データ】',
            '利用者ID', '氏名', 'ログインコード',
            '睡眠評価平均', 'ストレス評価平均', '食事評価平均',
            '平均睡眠時間（分）', '気分スコア平均',
            '日報入力日数', '入力率（%）'
        ];

        foreach ($users as $user) {
            $reports = DailyReportMorning::where('user_id', $user->id)
                ->whereBetween('report_date', [$startDate, $endDate])
                ->get();

            if ($reports->count() > 0) {
                $totalDays = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1;
                $inputRate = round(($reports->count() / $totalDays) * 100, 1);

                $csvData[] = [
                    '',
                    $user->id,
                    $user->name,
                    $user->login_code,
                    round($reports->avg('sleep_rating'), 1),
                    round($reports->avg('stress_rating'), 1),
                    round($reports->avg('meal_rating'), 1),
                    round($reports->avg('sleep_minutes'), 0),
                    round($reports->avg('mood_score'), 1),
                    $reports->count(),
                    $inputRate,
                ];
            }
        }

        return $csvData;
    }

    /**
     * KPIデータCSV生成
     */
    private function generateKpiCsvData(
        $users, string $startDate, string $endDate,
        bool $includeTrends, bool $includeAlerts
    ): array {
        $csvData = [];

        // ヘッダー
        $headers = [
            '利用者ID', '氏名', 'ログインコード',
            '計画日数', '出席日数', '出席率（%）',
            '日報入力日数', '日報入力率（%）'
        ];

        if ($includeAlerts) {
            $headers = array_merge($headers, ['アラート数', 'アラート詳細']);
        }

        if ($includeTrends) {
            $headers = array_merge($headers, [
                '睡眠評価平均', 'ストレス評価平均', '食事評価平均', '気分スコア平均'
            ]);
        }

        $csvData[] = $headers;

        // データ生成
        foreach ($users as $user) {
            $kpiData = $this->kpiService->calculatePersonalKpi($user->id, null);
            $kpiData['start_date'] = $startDate;
            $kpiData['end_date'] = $endDate;

            $row = [
                $user->id,
                $user->name,
                $user->login_code,
                $kpiData['attendance']['planned_days'],
                $kpiData['attendance']['attended_days'],
                $kpiData['attendance']['attendance_rate'],
                $kpiData['daily_reports_trend']['report_count'],
                $this->calculateReportInputRate($user->id, $startDate, $endDate),
            ];

            if ($includeAlerts) {
                $alerts = $this->kpiService->detectAlerts($user->id, $startDate, $endDate);
                $row[] = count($alerts);
                $row[] = $this->formatAlertsForCsv($alerts);
            }

            if ($includeTrends && isset($kpiData['daily_reports_trend']['averages'])) {
                $averages = $kpiData['daily_reports_trend']['averages'];
                $row[] = $averages['sleep_rating_avg'] ?? '';
                $row[] = $averages['stress_rating_avg'] ?? '';
                $row[] = $averages['meal_rating_avg'] ?? '';
                $row[] = $averages['mood_score_avg'] ?? '';
            }

            $csvData[] = $row;
        }

        return $csvData;
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
                // ダブルクォートをエスケープ
                $field = str_replace('"', '""', $field);
                // カンマや改行が含まれる場合はダブルクォートで囲む
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
            $output = "\xEF\xBB\xBF" . $output; // UTF-8 BOM付き
        }

        return $output;
    }

    /**
     * 日報入力率計算
     */
    private function calculateReportInputRate(int $userId, string $startDate, string $endDate): float
    {
        $totalDays = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1;
        $reportCount = DailyReportMorning::where('user_id', $userId)
            ->whereBetween('report_date', [$startDate, $endDate])
            ->count();

        return $totalDays > 0 ? round(($reportCount / $totalDays) * 100, 1) : 0;
    }

    /**
     * アラートCSV用フォーマット
     */
    private function formatAlertsForCsv(array $alerts): string
    {
        if (empty($alerts)) {
            return '';
        }

        $messages = array_map(function ($alert) {
            return $alert['message'] ?? '';
        }, $alerts);

        return implode('；', $messages);
    }

    /**
     * 予定種別の日本語変換
     */
    private function translatePlanType(string $type): string
    {
        return match ($type) {
            'onsite' => '通所',
            'remote' => '在宅',
            'off' => '休み',
            default => $type,
        };
    }

    /**
     * 実績種別の日本語変換
     */
    private function translateRecordType(string $type): string
    {
        return match ($type) {
            'onsite' => '通所',
            'remote' => '在宅',
            'absent' => '欠席',
            default => $type,
        };
    }

    /**
     * 入力者の日本語変換
     */
    private function translateSource(string $source): string
    {
        return match ($source) {
            'self' => '本人',
            'staff' => '職員',
            default => $source,
        };
    }

    /**
     * 差分状況の日本語変換
     */
    private function translateDifferenceStatus(string $status): string
    {
        return match ($status) {
            'success' => '一致',
            'warning' => '注意',
            'error' => 'エラー',
            'info' => '情報',
            default => $status,
        };
    }

    /**
     * 評価表示変換（◯/△/✕）
     */
    private function getRatingDisplay(int $rating): string
    {
        return match ($rating) {
            3 => '◯',
            2 => '△',
            1 => '✕',
            default => '',
        };
    }

    /**
     * 服薬状況表示変換
     */
    private function getMedicationStatusDisplay(?bool $status): string
    {
        return match ($status) {
            true => '済',
            false => '未',
            null => '習慣なし',
        };
    }

    /**
     * 差分計算
     */
    private function calculateDifference($plan, $record): array
    {
        if (!$plan && !$record) {
            return ['status' => 'info', 'message' => null];
        }

        if (!$plan && $record) {
            return ['status' => 'warning', 'message' => '予定外の出席'];
        }

        if ($plan && !$record) {
            if ($plan->plan_type === 'off') {
                return ['status' => 'success', 'message' => '休み（予定通り）'];
            }
            return ['status' => 'error', 'message' => '実績未入力'];
        }

        // 計画と実績の両方がある場合
        if ($plan->plan_type === 'off') {
            return ['status' => 'warning', 'message' => '休み予定だが出席'];
        }

        if ($record->attendance_type === 'absent') {
            return ['status' => 'error', 'message' => '予定ありだが欠席'];
        }

        if (($plan->plan_type === 'onsite' && $record->attendance_type === 'onsite') ||
            ($plan->plan_type === 'remote' && $record->attendance_type === 'remote')) {
            return ['status' => 'success', 'message' => '予定通り'];
        }

        if (($plan->plan_type === 'onsite' && $record->attendance_type === 'remote') ||
            ($plan->plan_type === 'remote' && $record->attendance_type === 'onsite')) {
            return ['status' => 'warning', 'message' => '出席方法が異なる'];
        }

        return ['status' => 'info', 'message' => null];
    }

    /**
     * 監査ログ記録
     */
    private function auditLog(string $action, string $entity, ?int $entityId, array $meta = []): void
    {
        try {
            \App\Models\AuditLog::create([
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