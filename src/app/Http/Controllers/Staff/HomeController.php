<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AttendancePlan;
use App\Models\AttendanceRecord;
use App\Models\DailyReportMorning;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class HomeController extends Controller
{
    public function index()
    {
        return view('staff.home');
    }

    /**
     * 職員ホーム統計情報API
     */
    public function stats()
    {
        $stats = [
            'userCount' => $this->getActiveUserCount(),
            'avgRate' => $this->getAverageAttendanceRate(),
            'pendingReports' => $this->getPendingReportsCount(),
            'noPlanUsers' => $this->getNoPlanUsersCount(),
        ];

        return response()->json($stats);
    }

    /**
     * 有効な利用者数を取得
     */
    private function getActiveUserCount(): int
    {
        return User::where('is_active', true)->count();
    }

    /**
     * 今月の平均出席率を計算
     */
    private function getAverageAttendanceRate(): float
    {
        $today = Carbon::today();
        $monthStart = $today->copy()->startOfMonth()->toDateString();
        $monthEnd = $today->toDateString();

        $activeUsers = User::where('is_active', true)->get();

        if ($activeUsers->isEmpty()) {
            return 0.0;
        }

        $totalRate = 0;
        $userCount = 0;

        foreach ($activeUsers as $user) {
            // 当月の予定日数（休み以外）
            $plannedDays = AttendancePlan::where('user_id', $user->id)
                ->whereBetween('plan_date', [$monthStart, $monthEnd])
                ->where('plan_type', '!=', 'off')
                ->count();

            if ($plannedDays === 0) {
                continue; // 予定がないユーザーはスキップ
            }

            // 当月の出席日数（現地・リモート）
            $attendedDays = AttendanceRecord::where('user_id', $user->id)
                ->whereBetween('record_date', [$monthStart, $monthEnd])
                ->whereIn('attendance_type', ['onsite', 'remote'])
                ->count();

            $rate = ($attendedDays / $plannedDays) * 100;
            $totalRate += $rate;
            $userCount++;
        }

        return $userCount > 0 ? round($totalRate / $userCount, 1) : 0.0;
    }

    /**
     * 本日の未入力日報件数を取得
     */
    private function getPendingReportsCount(): int
    {
        $today = Carbon::today()->toDateString();

        // 本日出席予定の利用者数
        $expectedUsers = AttendancePlan::where('plan_date', $today)
            ->where('plan_type', '!=', 'off')
            ->distinct('user_id')
            ->count('user_id');

        // 本日の朝日報提出済み件数
        $submittedReports = DailyReportMorning::whereDate('report_date', $today)
            ->count();

        return max(0, $expectedUsers - $submittedReports);
    }

    /**
     * 来月の予定未登録者数を取得
     */
    private function getNoPlanUsersCount(): int
    {
        $nextMonth = Carbon::today()->addMonth();
        $nextMonthStart = $nextMonth->copy()->startOfMonth()->toDateString();
        $nextMonthEnd = $nextMonth->copy()->endOfMonth()->toDateString();

        $activeUsers = User::where('is_active', true)->pluck('id');

        // 来月の予定が登録されている利用者ID
        $usersWithPlan = AttendancePlan::whereBetween('plan_date', [$nextMonthStart, $nextMonthEnd])
            ->distinct('user_id')
            ->pluck('user_id');

        // 来月の予定がない利用者数
        return $activeUsers->diff($usersWithPlan)->count();
    }
}