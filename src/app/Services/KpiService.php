<?php

namespace App\Services;

use App\Models\AttendancePlan;
use App\Models\AttendanceRecord;
use App\Models\DailyReportMorning;
use App\Models\DailyReportEvening;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class KpiService
{
    /**
     * 既存メソッド: データベースから取得してKPI計算
     */
    public function calculatePersonalKpi(int $userId, string $startDate, string $endDate): array
    {
        // 今日の日付を取得
        $today = Carbon::today()->format('Y-m-d');

        // 終了日が未来の場合は今日までに制限（出席率計算用）
        $effectiveEndDate = min($endDate, $today);

        $plans = AttendancePlan::where('user_id', $userId)
            ->whereBetween('plan_date', [$startDate, $endDate])
            ->get();

        $records = AttendanceRecord::where('user_id', $userId)
            ->whereBetween('record_date', [$startDate, $endDate])
            ->get();

        $morningReports = DailyReportMorning::where('user_id', $userId)
            ->whereBetween('report_date', [$startDate, $endDate])
            ->get();

        $eveningReports = DailyReportEvening::where('user_id', $userId)
            ->whereBetween('report_date', [$startDate, $endDate])
            ->get();

        return [
            'attendance' => $this->calculateAttendanceKpi($plans, $records, $startDate, $effectiveEndDate),
            'reports' => $this->calculateReportKpi($plans, $morningReports, $eveningReports, $startDate, $effectiveEndDate),
            'period' => ['start' => $startDate, 'end' => $endDate],
        ];
    }

    /**
     * 新メソッド: 既存Collectionから KPI計算（N+1対策）
     */
    public function calculatePersonalKpiFromData(Collection $plans, Collection $records, Collection $morningReports, Collection $eveningReports, string $startDate, string $endDate): array
    {
        $today = Carbon::today()->format('Y-m-d');
        $effectiveEndDate = min($endDate, $today);

        return [
            'attendance' => $this->calculateAttendanceKpi($plans, $records, $startDate, $effectiveEndDate),
            'reports' => $this->calculateReportKpi($plans, $morningReports, $eveningReports, $startDate, $effectiveEndDate),
            'period' => ['start' => $startDate, 'end' => $endDate],
        ];
    }

    private function calculateAttendanceKpi(Collection $plans, Collection $records, string $startDate, string $effectiveEndDate): array
    {
        // 今日までの予定日数を計算（分母）
        $plannedDaysUntilToday = $plans
            ->where('plan_type', '!=', 'off')
            ->where('plan_date', '<=', $effectiveEndDate)
            ->pluck('plan_date')
            ->unique()
            ->count();

        // 当月全体の予定日数を計算（ホーム画面用）
        $totalPlannedDays = $plans
            ->where('plan_type', '!=', 'off')
            ->pluck('plan_date')
            ->unique()
            ->count();

        // 今日までの実績日数を計算（分子）
        $attendedDaysUntilToday = $records
            ->whereIn('attendance_type', ['onsite', 'remote'])
            ->where('record_date', '<=', $effectiveEndDate)
            ->pluck('record_date')
            ->unique()
            ->count();

        // 欠席日数
        $absentDays = $records
            ->where('attendance_type', 'absent')
            ->where('record_date', '<=', $effectiveEndDate)
            ->pluck('record_date')
            ->unique()
            ->count();

        // 出席率計算（今日までの予定に対する実績の割合）
        $attendanceRate = $plannedDaysUntilToday > 0
            ? round(($attendedDaysUntilToday / $plannedDaysUntilToday) * 100, 1)
            : null;

        return [
            'planned_days' => $plannedDaysUntilToday,          // 今日までの予定日数
            'total_planned_days' => $totalPlannedDays,         // 当月全体の予定日数
            'attended_days' => $attendedDaysUntilToday,        // 今日までの出席日数
            'absent_days' => $absentDays,                      // 欠席日数
            'attendance_rate' => $attendanceRate,              // 出席率
            'attendance_rate_display' => $attendanceRate !== null ? $attendanceRate . '%' : '—',
            'difference' => $attendedDaysUntilToday - $plannedDaysUntilToday,
            'onsite_count' => $records->where('attendance_type', 'onsite')->count(),
            'remote_count' => $records->where('attendance_type', 'remote')->count(),
            // 月全体の予定も参考情報として保持
            'total_planned_days' => $plans->where('plan_type', '!=', 'off')->pluck('plan_date')->unique()->count(),
        ];
    }

    private function calculateReportKpi(Collection $plans, Collection $morningReports, Collection $eveningReports, string $startDate, string $effectiveEndDate): array
    {
        // 今日までの予定日数
        $plannedDaysUntilToday = $plans
            ->where('plan_type', '!=', 'off')
            ->where('plan_date', '<=', $effectiveEndDate)
            ->pluck('plan_date')
            ->unique()
            ->count();
            
        $morningCount = $morningReports->pluck('report_date')->unique()->count();
        $eveningCount = $eveningReports->pluck('report_date')->unique()->count();
        $completeDays = $morningReports->pluck('report_date')
            ->intersect($eveningReports->pluck('report_date'))
            ->count();
        
        $reportRate = $plannedDaysUntilToday > 0 
            ? round(($completeDays / $plannedDaysUntilToday) * 100, 1) 
            : null;

        return [
            'planned_days' => $plannedDaysUntilToday,
            'morning_count' => $morningCount,
            'evening_count' => $eveningCount,
            'complete_days' => $completeDays,
            'report_rate' => $reportRate,
            'report_rate_display' => $reportRate !== null ? $reportRate . '%' : '—',
        ];
    }

    /**
     * 既存メソッド: データベースから取得してトレンド計算
     */
    public function calculateAttendanceTrend(int $userId, string $startDate, string $endDate): array
    {
        // 利用者が持つ全データの範囲を取得
        $firstPlan = AttendancePlan::where('user_id', $userId)
            ->orderBy('plan_date')
            ->first();

        $firstRecord = AttendanceRecord::where('user_id', $userId)
            ->orderBy('record_date')
            ->first();

        // データが存在しない場合は空配列を返す
        if (!$firstPlan && !$firstRecord) {
            return [];
        }

        // 最も古いデータの日付を取得
        $dataStartDate = null;
        if ($firstPlan && $firstRecord) {
            $dataStartDate = min($firstPlan->plan_date, $firstRecord->record_date);
        } elseif ($firstPlan) {
            $dataStartDate = $firstPlan->plan_date;
        } else {
            $dataStartDate = $firstRecord->record_date;
        }

        // 全データを取得（期間指定なし）
        $plans = AttendancePlan::where('user_id', $userId)
            ->orderBy('plan_date')
            ->get();

        $records = AttendanceRecord::where('user_id', $userId)
            ->orderBy('record_date')
            ->get();

        return $this->calculateAttendanceTrendFromData($userId, $plans, $records, $startDate, $endDate);
    }

    /**
     * 新メソッド: 既存Collectionからトレンド計算（N+1対策）
     */
    public function calculateAttendanceTrendFromData(int $userId, Collection $plans, Collection $records, string $startDate, string $endDate): array
    {
        // データが存在しない場合は空配列を返す
        if ($plans->isEmpty() && $records->isEmpty()) {
            return [];
        }

        // 最も古いデータの日付を取得
        $firstPlanDate = $plans->min('plan_date');
        $firstRecordDate = $records->min('record_date');

        $dataStartDate = null;
        if ($firstPlanDate && $firstRecordDate) {
            $dataStartDate = min($firstPlanDate, $firstRecordDate);
        } elseif ($firstPlanDate) {
            $dataStartDate = $firstPlanDate;
        } elseif ($firstRecordDate) {
            $dataStartDate = $firstRecordDate;
        } else {
            return [];
        }

        // 月ごとにグループ化
        $monthlyData = [];

        foreach ($plans as $plan) {
            $planDate = $plan->plan_date instanceof Carbon ? $plan->plan_date : Carbon::parse($plan->plan_date);
            $month = $planDate->format('Y-m');
            if (!isset($monthlyData[$month])) {
                $monthlyData[$month] = ['planned' => 0, 'attended' => 0];
            }
            if ($plan->plan_type !== 'off') {
                $monthlyData[$month]['planned']++;
            }
        }

        foreach ($records as $record) {
            $recordDate = $record->record_date instanceof Carbon ? $record->record_date : Carbon::parse($record->record_date);
            $month = $recordDate->format('Y-m');
            if (!isset($monthlyData[$month])) {
                $monthlyData[$month] = ['planned' => 0, 'attended' => 0];
            }
            if (in_array($record->attendance_type, ['onsite', 'remote'])) {
                $monthlyData[$month]['attended']++;
            }
        }

        // データの最初の月から今月までの範囲でグラフを作成
        $startMonth = Carbon::parse($dataStartDate)->startOfMonth();
        $endMonth = Carbon::today()->startOfMonth();
        $currentMonth = $startMonth->copy();
        $today = Carbon::today()->format('Y-m-d');
        $todayMonth = Carbon::today()->format('Y-m');

        $trend = [];

        while ($currentMonth <= $endMonth) {
            $monthKey = $currentMonth->format('Y-m');

            // 当月の場合は前日までのデータで計算
            if ($monthKey === $todayMonth) {
                $yesterday = Carbon::yesterday()->format('Y-m-d');

                // 前日までの予定日数を計算
                $plannedUntilYesterday = 0;
                foreach ($plans as $plan) {
                    $planDate = $plan->plan_date instanceof Carbon ? $plan->plan_date : Carbon::parse($plan->plan_date);
                    if ($planDate->format('Y-m') === $monthKey
                        && $planDate->format('Y-m-d') <= $yesterday
                        && $plan->plan_type !== 'off') {
                        $plannedUntilYesterday++;
                    }
                }

                // 前日までの出席日数を計算
                $attendedUntilYesterday = 0;
                foreach ($records as $record) {
                    $recordDate = $record->record_date instanceof Carbon ? $record->record_date : Carbon::parse($record->record_date);
                    if ($recordDate->format('Y-m') === $monthKey
                        && $recordDate->format('Y-m-d') <= $yesterday
                        && in_array($record->attendance_type, ['onsite', 'remote'])) {
                        $attendedUntilYesterday++;
                    }
                }

                $planned = $plannedUntilYesterday;
                $attended = $attendedUntilYesterday;
            } else {
                // 過去の月は全体のデータを使用
                $planned = $monthlyData[$monthKey]['planned'] ?? 0;
                $attended = $monthlyData[$monthKey]['attended'] ?? 0;
            }

            $rate = $planned > 0 ? round(($attended / $planned) * 100, 1) : 0;

            $trend[] = [
                'date' => $currentMonth->format('Y-m-01'), // 月の最初の日
                'month' => $currentMonth->format('Y年m月'), // 表示用
                'rate' => $rate,
                'planned' => $planned,
                'attended' => $attended
            ];

            $currentMonth->addMonth();
        }

        return $trend;
    }

    /**
     * 既存メソッド: データベースから取得してレポートトレンド計算
     */
    public function calculateReportTrend(int $userId, string $startDate, string $endDate): array
    {
        // 直近7日分のデータを取得（当日を含む）
        $sevenDaysAgo = Carbon::today()->subDays(6)->format('Y-m-d');
        $today = Carbon::today()->format('Y-m-d');

        $morningReports = DailyReportMorning::where('user_id', $userId)
            ->whereBetween('report_date', [$sevenDaysAgo, $today])
            ->orderBy('report_date')
            ->get();

        return $this->calculateReportTrendFromData($userId, collect(), $morningReports, collect(), $startDate, $endDate);
    }

    /**
     * 新メソッド: 既存Collectionからレポートトレンド計算（N+1対策）
     */
    public function calculateReportTrendFromData(int $userId, Collection $plans, Collection $morningReports, Collection $eveningReports, string $startDate, string $endDate): array
    {
        // 直近7日分のデータを取得（当日を含む）
        $sevenDaysAgo = Carbon::today()->subDays(6)->format('Y-m-d');
        $today = Carbon::today()->format('Y-m-d');

        // 直近7日分のみフィルタ
        $recentReports = $morningReports
            ->whereBetween('report_date', [$sevenDaysAgo, $today])
            ->keyBy(function($r) {
                $date = $r->report_date instanceof Carbon ? $r->report_date : Carbon::parse($r->report_date);
                return $date->format('Y-m-d');
            });

        $trend = [
            'sleep' => [],
            'stress' => [],
            'meal' => [],
            'mood' => []
        ];

        // 直近7日分の日付を生成（当日を含む）
        $currentDate = Carbon::parse($sevenDaysAgo);
        $endDateCarbon = Carbon::parse($today);

        while ($currentDate <= $endDateCarbon) {
            $dateString = $currentDate->format('Y-m-d');
            $report = $recentReports->get($dateString);

            if ($report) {
                $trend['sleep'][] = ['date' => $dateString, 'value' => $report->sleep_rating];
                $trend['stress'][] = ['date' => $dateString, 'value' => $report->stress_rating];
                $trend['meal'][] = ['date' => $dateString, 'value' => $report->meal_rating];
                $trend['mood'][] = ['date' => $dateString, 'value' => $report->mood_score];
            } else {
                // データがない日はnull値を入れる
                $trend['sleep'][] = ['date' => $dateString, 'value' => null];
                $trend['stress'][] = ['date' => $dateString, 'value' => null];
                $trend['meal'][] = ['date' => $dateString, 'value' => null];
                $trend['mood'][] = ['date' => $dateString, 'value' => null];
            }

            $currentDate->addDay();
        }

        return $trend;
    }

    public function calculateFacilityKpi(array $userIds, string $startDate, string $endDate): array
    {
        $usersKpi = [];
        foreach ($userIds as $userId) {
            $kpi = $this->calculatePersonalKpi($userId, $startDate, $endDate);
            $usersKpi[] = [
                'user_id' => $userId,
                'attendance' => $kpi['attendance'],
                'reports' => $kpi['reports']
            ];
        }

        $rates = collect($usersKpi)->pluck('attendance.attendance_rate')->filter();
        $avgRate = $rates->count() > 0 ? round($rates->avg(), 1) : null;

        $today = Carbon::today()->format('Y-m-d');
        $pendingReports = DailyReportMorning::whereIn('user_id', $userIds)
            ->where('report_date', $today)
            ->count();
        $totalUsers = count($userIds);
        $pendingCount = $totalUsers - $pendingReports;

        return [
            'total_users' => $totalUsers,
            'active_users' => $totalUsers,
            'avg_attendance_rate' => $avgRate,
            'avg_attendance_rate_display' => $avgRate !== null ? $avgRate . '%' : '—',
            'pending_reports' => $pendingCount,
            'users' => $usersKpi,
        ];
    }

    public function generateAlerts(array $userIds, string $startDate, string $endDate): array
    {
        $alerts = [];
        foreach ($userIds as $userId) {
            $kpi = $this->calculatePersonalKpi($userId, $startDate, $endDate);

            if ($kpi['attendance']['attendance_rate'] !== null && 
                $kpi['attendance']['attendance_rate'] < 70) {
                $alerts[] = [
                    'type' => 'warning',
                    'user_id' => $userId,
                    'message' => '出席率が70%を下回っています',
                    'value' => $kpi['attendance']['attendance_rate_display']
                ];
            }

            if ($kpi['reports']['report_rate'] !== null && 
                $kpi['reports']['report_rate'] < 50) {
                $alerts[] = [
                    'type' => 'warning',
                    'user_id' => $userId,
                    'message' => '日報入力率が50%を下回っています',
                    'value' => $kpi['reports']['report_rate_display']
                ];
            }
        }

        return $alerts;
    }
}