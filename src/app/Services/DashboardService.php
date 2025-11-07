<?php

namespace App\Services;

use App\Models\User;
use App\Models\AttendancePlan;
use App\Models\AttendanceRecord;
use App\Models\DailyReportMorning;
use App\Models\DailyReportEvening;
use App\Models\Setting;
use App\Models\Holiday;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DashboardService
{
    protected KpiService $kpiService;

    public function __construct(KpiService $kpiService)
    {
        $this->kpiService = $kpiService;
    }

    public function getPersonalDashboard(int $userId, ?string $periodType = 'current_month', ?string $calendarMonth = null): array
    {
        // 期間を決定
        [$startDate, $endDate, $periodLabel] = $this->determineDashboardPeriod($userId, $periodType, $calendarMonth);

        // カレンダー月が指定されていない場合は当月
        $calendarMonth = $calendarMonth ?? Carbon::today()->format('Y-m-d');

        // 出席率推移グラフ用: 利用開始月～今日
        $firstPlan = AttendancePlan::where('user_id', $userId)->orderBy('plan_date', 'asc')->first();
        $firstRecord = AttendanceRecord::where('user_id', $userId)->orderBy('record_date', 'asc')->first();

        $trendStartDate = null;
        if ($firstPlan && $firstRecord) {
            $trendStartDate = min($firstPlan->plan_date, $firstRecord->record_date);
        } elseif ($firstPlan) {
            $trendStartDate = $firstPlan->plan_date;
        } elseif ($firstRecord) {
            $trendStartDate = $firstRecord->record_date;
        }

        if ($trendStartDate) {
            $trendStartDate = Carbon::parse($trendStartDate)->startOfMonth()->format('Y-m-d');
        } else {
            $trendStartDate = Carbon::today()->startOfMonth()->format('Y-m-d');
        }

        // 全データの範囲を決定（利用開始月～今日）
        $allDataStartDate = $trendStartDate;
        $calendarEndDate = Carbon::createFromFormat('Y-m', substr($calendarMonth, 0, 7))->endOfMonth()->format('Y-m-d');
        $allDataEndDate = max(Carbon::today()->format('Y-m-d'), $calendarEndDate);

        // データを一括取得（N+1クエリ対策）
        $plans = AttendancePlan::where('user_id', $userId)
            ->whereBetween('plan_date', [$allDataStartDate, $allDataEndDate])
            ->select('plan_date', 'plan_type', 'plan_time_slot', 'is_holiday', 'holiday_name')
            ->get();

        $records = AttendanceRecord::where('user_id', $userId)
            ->whereBetween('record_date', [$allDataStartDate, $allDataEndDate])
            ->select('record_date', 'attendance_type', 'record_time_slot')
            ->get();

        // 体調・気分トレンド用: 必要なカラムをすべて取得
        $morningReports = DailyReportMorning::where('user_id', $userId)
            ->whereBetween('report_date', [$allDataStartDate, $allDataEndDate])
            ->select('id', 'user_id', 'report_date', 'sleep_rating', 'stress_rating', 'meal_rating', 'mood_score')
            ->get();

        $eveningReports = DailyReportEvening::where('user_id', $userId)
            ->whereBetween('report_date', [$allDataStartDate, $allDataEndDate])
            ->select('id', 'user_id', 'report_date')
            ->get();

        // KPI計算（フィルタ済みデータを使用）
        $kpi = $this->kpiService->calculatePersonalKpiFromData(
            $plans->whereBetween('plan_date', [$startDate, $endDate]),
            $records->whereBetween('record_date', [$startDate, $endDate]),
            $morningReports->whereBetween('report_date', [$startDate, $endDate]),
            $eveningReports->whereBetween('report_date', [$startDate, $endDate]),
            $startDate,
            $endDate
        );

        // トレンドグラフ用データ
        $attendanceTrend = $this->kpiService->calculateAttendanceTrendFromData($userId, $plans, $records, $startDate, $endDate);
        $reportTrend = $this->kpiService->calculateReportTrendFromData($userId, $plans, $morningReports, $eveningReports, $startDate, $endDate);

        // 当月KPI
        $currentMonthStart = Carbon::today()->startOfMonth()->format('Y-m-d');
        $currentMonthEnd = Carbon::today()->format('Y-m-d');
        $currentMonthKpi = $this->kpiService->calculatePersonalKpiFromData(
            $plans->whereBetween('plan_date', [$currentMonthStart, $currentMonthEnd]),
            $records->whereBetween('record_date', [$currentMonthStart, $currentMonthEnd]),
            $morningReports->whereBetween('report_date', [$currentMonthStart, $currentMonthEnd]),
            $eveningReports->whereBetween('report_date', [$currentMonthStart, $currentMonthEnd]),
            $currentMonthStart,
            $currentMonthEnd
        );

        // カレンダーデータ
        $calendarData = $this->getCalendarDataFromCollections($plans, $records, substr($calendarMonth, 0, 7));

        return [
            'user_id' => $userId,
            'period' => [
                'type' => $periodType,
                'label' => $periodLabel,
                'start' => $startDate,
                'end' => $endDate
            ],
            'kpi' => $kpi,
            'current_month' => ['kpi' => $currentMonthKpi, 'calendar' => $calendarData],
            'trends' => ['attendance' => $attendanceTrend, 'reports' => $reportTrend],
        ];
    }

    /**
     * ダッシュボード表示期間を決定
     *
     * @param int $userId
     * @param string $periodType 'current_month', 'recent_3months', 'all', 'specific_month'
     * @param string|null $specificMonth 'Y-m' 形式（specific_monthの場合に使用）
     * @return array [startDate, endDate, label]
     */
    protected function determineDashboardPeriod(int $userId, string $periodType, ?string $specificMonth = null): array
    {
        $today = Carbon::today();

        switch ($periodType) {
            case 'current_month':
                // 今月: 当月1日～当日
                $startDate = $today->copy()->startOfMonth()->format('Y-m-d');
                $endDate = $today->format('Y-m-d');
                $label = $today->format('Y年n月');
                break;

            case 'specific_month':
                // 特定の月: 指定月の1日～月末
                if ($specificMonth) {
                    $monthDate = Carbon::parse($specificMonth . '-01');
                    $startDate = $monthDate->copy()->startOfMonth()->format('Y-m-d');
                    $endDate = $monthDate->copy()->endOfMonth()->format('Y-m-d');
                    $label = $monthDate->format('Y年n月');
                } else {
                    // フォールバック: 今月
                    $startDate = $today->copy()->startOfMonth()->format('Y-m-d');
                    $endDate = $today->format('Y-m-d');
                    $label = $today->format('Y年n月');
                }
                break;

            case 'recent_3months':
                // 直近3ヶ月: 前月までの直近3ヶ月（当月を含まない）
                $endDate = $today->copy()->subMonth()->endOfMonth()->format('Y-m-d');
                $startDate = $today->copy()->subMonths(3)->startOfMonth()->format('Y-m-d');
                $label = '直近3ヶ月';
                break;

            case 'all':
                // 全期間: 利用開始月～当日（当月を含む）
                $endDate = $today->format('Y-m-d');

                // 最初のデータ（予定または実績）の月を取得
                $firstPlan = AttendancePlan::where('user_id', $userId)
                    ->orderBy('plan_date', 'asc')
                    ->first();
                $firstRecord = AttendanceRecord::where('user_id', $userId)
                    ->orderBy('record_date', 'asc')
                    ->first();

                $firstDate = null;
                if ($firstPlan && $firstRecord) {
                    $firstDate = min($firstPlan->plan_date, $firstRecord->record_date);
                } elseif ($firstPlan) {
                    $firstDate = $firstPlan->plan_date;
                } elseif ($firstRecord) {
                    $firstDate = $firstRecord->record_date;
                }

                if ($firstDate) {
                    $startDate = Carbon::parse($firstDate)->startOfMonth()->format('Y-m-d');
                } else {
                    // データがない場合は当月開始
                    $startDate = $today->copy()->startOfMonth()->format('Y-m-d');
                }

                $label = '全期間';
                break;

            default:
                // デフォルトは今月
                $startDate = $today->copy()->startOfMonth()->format('Y-m-d');
                $endDate = $today->format('Y-m-d');
                $label = $today->format('Y年n月');
        }

        return [$startDate, $endDate, $label];
    }

    public function getFacilityDashboard(array $filters = []): array
    {
        $period = $filters['period'] ?? 'current';
        [$startDate, $endDate] = $this->determinePeriod($period, $filters);

        $users = User::where('is_active', true)
            ->when(isset($filters['status']), function ($q) use ($filters) {
                if ($filters['status'] === 'active') {
                    $q->where('is_active', true);
                }
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        $userIds = $users->pluck('id')->toArray();

        if (empty($userIds)) {
            return [
                'summary' => [
                    'total_users' => 0,
                    'active_users' => 0,
                    'avg_attendance_rate' => null,
                    'avg_attendance_rate_display' => '—',
                    'pending_reports' => 0,
                    'no_plan_users' => 0,
                ],
                'users' => [],
                'alerts' => [],
                'period' => ['start' => $startDate, 'end' => $endDate],
            ];
        }

        $facilityKpi = $this->kpiService->calculateFacilityKpi($userIds, $startDate, $endDate);
        $nextMonth = Carbon::today()->addMonth()->format('Y-m');
        $noPlanUsers = $this->countNoPlanUsers($userIds, $nextMonth);
        $alerts = $this->kpiService->generateAlerts($userIds, $startDate, $endDate);

        $usersData = [];
        foreach ($users as $user) {
            $kpi = $this->kpiService->calculatePersonalKpi($user->id, $startDate, $endDate);
            $usersData[] = [
                'id' => $user->id,
                'name' => $user->name,
                'planned' => $kpi['attendance']['planned_days'],
                'actual' => $kpi['attendance']['attended_days'],
                'rate' => $kpi['attendance']['attendance_rate'],
                'rate_display' => $kpi['attendance']['attendance_rate_display'],
                'diff' => $kpi['attendance']['difference'],
                'report_rate' => $kpi['reports']['report_rate'],
                'report_rate_display' => $kpi['reports']['report_rate_display'],
            ];
        }

        usort($usersData, function ($a, $b) {
            if ($a['rate'] === null) return 1;
            if ($b['rate'] === null) return -1;
            return $b['rate'] <=> $a['rate'];
        });

        foreach ($usersData as $index => &$userData) {
            $userData['rank'] = $index + 1;
        }

        // コマ数計算（選択期間に基づく）
        $today = Carbon::today()->format('Y-m-d');

        // 選択期間の終了日が未来の場合は今日までに制限
        $effectiveEndDate = min($endDate, $today);

        // 選択期間内の実績コマ数（出席記録のレコード数）
        $actualSlots = AttendanceRecord::whereIn('user_id', $userIds)
            ->whereBetween('record_date', [$startDate, $effectiveEndDate])
            ->whereIn('attendance_type', ['onsite', 'remote'])
            ->count();

        // 選択期間内の全予定コマ数（予定のレコード数、休み以外）
        $plannedSlots = AttendancePlan::whereIn('user_id', $userIds)
            ->whereBetween('plan_date', [$startDate, $endDate])
            ->where('plan_type', '!=', 'off')
            ->count();

        // 選択期間内の今日までの予定コマ数
        $plannedSlotsToDate = AttendancePlan::whereIn('user_id', $userIds)
            ->whereBetween('plan_date', [$startDate, $effectiveEndDate])
            ->where('plan_type', '!=', 'off')
            ->count();

        // 全体出席率の計算（選択期間の今日までの実績／選択期間の今日までの予定）
        $overallRate = $plannedSlotsToDate > 0
            ? round(($actualSlots / $plannedSlotsToDate) * 100, 1)
            : null;
        $overallRateDisplay = $overallRate !== null ? $overallRate . '%' : '—';

        // 予測コマ数の計算（現在の出席率で期間末まで続いた場合の着地予測）
        $forecastSlots = $plannedSlots > 0 && $overallRate !== null
            ? round(($plannedSlots * $overallRate) / 100)
            : 0;

        // 予測稼働率の計算
        // 分母 = 選択期間の祝日含む月～金の日数 × 事業所の定員数
        $facilityCapacity = Setting::get('facility_capacity', 20);
        $weekdaysCount = $this->countWeekdaysInMonth($startDate, $endDate);
        $maxCapacity = $weekdaysCount * $facilityCapacity;

        $forecastUtilization = $maxCapacity > 0
            ? round(($forecastSlots / $maxCapacity) * 100, 1)
            : null;
        $forecastUtilizationDisplay = $forecastUtilization !== null ? $forecastUtilization . '%' : '—';

        return [
            'summary' => [
                // 1段目: 全体出席率、実績コマ数、予定コマ数、予測コマ数
                'avg_attendance_rate' => $overallRate,
                'avg_attendance_rate_display' => $overallRateDisplay,
                'actual_slots' => $actualSlots,
                'planned_slots' => $plannedSlots,
                'forecast_slots' => $forecastSlots,

                // 2段目: 総利用者数、未入力日報、未計画利用者、予測稼働率
                'total_users' => count($users),
                'active_users' => count($users),
                'pending_reports' => $facilityKpi['pending_reports'],
                'no_plan_users' => $noPlanUsers,
                'forecast_utilization' => $forecastUtilization,
                'forecast_utilization_display' => $forecastUtilizationDisplay,
            ],
            'users' => $usersData,
            'alerts' => $this->formatAlerts($alerts, $users),
            'period' => ['start' => $startDate, 'end' => $endDate, 'type' => $period],
        ];
    }

    /**
     * 指定期間の平日（月～金）日数をカウント（祝日含む）
     */
    private function countWeekdaysInMonth(string $startDate, string $endDate): int
    {
        $count = 0;
        $current = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        while ($current <= $end) {
            // 月～金（1=月曜、5=金曜）
            if ($current->dayOfWeek >= Carbon::MONDAY && $current->dayOfWeek <= Carbon::FRIDAY) {
                $count++;
            }
            $current->addDay();
        }

        return $count;
    }

    /**
     * 既存メソッド: データベースから取得してカレンダーデータ生成
     */
    private function getCalendarData(int $userId, string $month): array
    {
        $startDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->format('Y-m-d');
        $endDate = Carbon::createFromFormat('Y-m', $month)->endOfMonth()->format('Y-m-d');

        // 必要なカラムのみ取得してパフォーマンス向上
        $plans = AttendancePlan::where('user_id', $userId)
            ->whereBetween('plan_date', [$startDate, $endDate])
            ->select('plan_date', 'plan_type', 'plan_time_slot', 'is_holiday', 'holiday_name')
            ->get();

        $records = AttendanceRecord::where('user_id', $userId)
            ->whereBetween('record_date', [$startDate, $endDate])
            ->select('record_date', 'attendance_type', 'record_time_slot')
            ->get();

        return $this->getCalendarDataFromCollections($plans, $records, $month);
    }

    /**
     * 新メソッド: 既存Collectionからカレンダーデータ生成（N+1対策）
     */
    private function getCalendarDataFromCollections(Collection $plans, Collection $records, string $month): array
    {
        $startDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->format('Y-m-d');
        $endDate = Carbon::createFromFormat('Y-m', $month)->endOfMonth()->format('Y-m-d');

        // 指定月のデータのみフィルタ
        $monthPlans = $plans
            ->whereBetween('plan_date', [$startDate, $endDate])
            ->keyBy(function($plan) {
                $date = $plan->plan_date instanceof Carbon ? $plan->plan_date : Carbon::parse($plan->plan_date);
                return $date->format('Y-m-d');
            });

        $monthRecords = $records
            ->whereBetween('record_date', [$startDate, $endDate])
            ->groupBy(function($record) {
                $date = $record->record_date instanceof Carbon ? $record->record_date : Carbon::parse($record->record_date);
                return $date->format('Y-m-d');
            });

        $calendarData = [];
        $currentDate = Carbon::parse($startDate);
        $endDateCarbon = Carbon::parse($endDate);

        while ($currentDate <= $endDateCarbon) {
            $dateString = $currentDate->format('Y-m-d');
            $plan = $monthPlans->get($dateString);
            $dayRecords = $monthRecords->get($dateString);

            // 予定と実績を分離して取得
            $plannedStatus = null;
            $actualStatus = null;

            // 予定の処理
            if ($plan) {
                $planTypeText = '';
                if ($plan->plan_type === 'off') {
                    $planTypeText = '休み';
                } elseif ($plan->plan_type === 'onsite') {
                    $planTypeText = '通所';
                } elseif ($plan->plan_type === 'remote') {
                    $planTypeText = '在宅';
                }

                $planTimeText = '';
                if ($plan->plan_time_slot === 'full') {
                    $planTimeText = '終日';
                } elseif ($plan->plan_time_slot === 'am') {
                    $planTimeText = '午前';
                } elseif ($plan->plan_time_slot === 'pm') {
                    $planTimeText = '午後';
                }

                $plannedStatus = $planTypeText . ($planTimeText ? "\n" . $planTimeText : '');
            }

            // 実績の処理
            if ($dayRecords && $dayRecords->count() > 0) {
                $recordTypeText = '';
                $recordTimeText = '';
                
                if ($dayRecords->contains('attendance_type', 'onsite')) {
                    $recordTypeText = '通所';
                } elseif ($dayRecords->contains('attendance_type', 'remote')) {
                    $recordTypeText = '在宅';
                } elseif ($dayRecords->contains('attendance_type', 'absent')) {
                    $recordTypeText = '欠席';
                }
                
                // 時間帯の処理（複数レコードがある場合は優先順位で選択）
                $timeSlots = $dayRecords->pluck('record_time_slot')->unique();
                if ($timeSlots->contains('full')) {
                    $recordTimeText = '終日';
                } elseif ($timeSlots->contains('am') && $timeSlots->contains('pm')) {
                    $recordTimeText = '終日'; // 午前・午後両方ある場合は終日とみなす
                } elseif ($timeSlots->contains('am')) {
                    $recordTimeText = '午前';
                } elseif ($timeSlots->contains('pm')) {
                    $recordTimeText = '午後';
                }
                
                $actualStatus = $recordTypeText . ($recordTimeText ? "\n" . $recordTimeText : '');
            }

            // 下位互換性のための従来のstatus（実績優先、なければ予定）
            $status = null;
            if ($actualStatus) {
                if ($actualStatus === '通所') $status = 'onsite';
                elseif ($actualStatus === '在宅') $status = 'remote';
                elseif ($actualStatus === '欠席') $status = 'absent';
            } elseif ($plannedStatus) {
                if ($plannedStatus === '休み') $status = 'off';
                elseif ($plannedStatus === '通所') $status = 'planned_onsite';
                elseif ($plannedStatus === '在宅') $status = 'planned_remote';
            }

            $calendarData[] = [
                'date' => $dateString,
                'day' => $currentDate->day,
                'day_of_week' => $currentDate->dayOfWeekIso,
                'is_weekend' => $currentDate->isWeekend(),
                'is_holiday' => $plan ? $plan->is_holiday : false,
                'holiday_name' => $plan ? $plan->holiday_name : null,
                'status' => $status, // 下位互換性のため
                'planned_status' => $plannedStatus, // 予定
                'actual_status' => $actualStatus,   // 実績
            ];

            $currentDate->addDay();
        }

        return $calendarData;
    }

    private function determinePeriod(string $period, array $filters): array
    {
        if ($period === 'custom' && isset($filters['start_date']) && isset($filters['end_date'])) {
            return [$filters['start_date'], $filters['end_date']];
        }

        if ($period === 'last') {
            $start = Carbon::today()->subMonth()->startOfMonth()->format('Y-m-d');
            $end = Carbon::today()->subMonth()->endOfMonth()->format('Y-m-d');
            return [$start, $end];
        }

        $start = Carbon::today()->startOfMonth()->format('Y-m-d');
        $end = Carbon::today()->endOfMonth()->format('Y-m-d');
        return [$start, $end];
    }

    private function countNoPlanUsers(array $userIds, string $month): int
    {
        $startDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->format('Y-m-d');
        $endDate = Carbon::createFromFormat('Y-m', $month)->endOfMonth()->format('Y-m-d');

        $usersWithPlans = AttendancePlan::whereIn('user_id', $userIds)
            ->whereBetween('plan_date', [$startDate, $endDate])
            ->distinct('user_id')
            ->pluck('user_id')
            ->toArray();

        return count($userIds) - count($usersWithPlans);
    }

    private function formatAlerts(array $alerts, $users): array
    {
        $userMap = $users->keyBy('id');
        $formatted = [];

        foreach ($alerts as $alert) {
            $user = $userMap->get($alert['user_id']);
            if ($user) {
                $formatted[] = [
                    'type' => $alert['type'],
                    'user_id' => $alert['user_id'],
                    'user_name' => $user->name,
                    'message' => $alert['message'],
                    'value' => $alert['value'],
                ];
            }
        }

        return $formatted;
    }

    public function getAlertSummary(array $userIds, string $startDate, string $endDate): array
    {
        $alerts = $this->kpiService->generateAlerts($userIds, $startDate, $endDate);

        $summary = [
            'low_attendance' => 0,
            'low_report_rate' => 0,
            'no_plan_next_month' => 0,
        ];

        foreach ($alerts as $alert) {
            if (str_contains($alert['message'], '出席率')) {
                $summary['low_attendance']++;
            } elseif (str_contains($alert['message'], '日報')) {
                $summary['low_report_rate']++;
            }
        }

        $nextMonth = Carbon::today()->addMonth()->format('Y-m');
        $summary['no_plan_next_month'] = $this->countNoPlanUsers($userIds, $nextMonth);

        return [
            [
                'type' => 'warning',
                'label' => '出席率70%未満',
                'count' => $summary['low_attendance'],
            ],
            [
                'type' => 'warning',
                'label' => '日報入力率50%未満',
                'count' => $summary['low_report_rate'],
            ],
            [
                'type' => 'info',
                'label' => '来月予定未登録',
                'count' => $summary['no_plan_next_month'],
            ],
        ];
    }
}