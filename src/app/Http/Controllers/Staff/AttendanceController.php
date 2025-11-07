<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AttendancePlan;
use App\Models\AttendanceRecord;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    /**
     * S-06S: 出席管理画面
     */
    public function manage()
    {
        $users = User::where('is_active', true)
                    ->whereNull('deleted_at')
                    ->orderBy('name')
                    ->get(['id', 'name']);

        return view('staff.attendance.manage', compact('users'));
    }

    /**
     * 全利用者の予定と実績を一覧取得
     * GET /staff/attendance/monthly-overview?month=2025-10
     */
    public function monthlyOverview(Request $request): JsonResponse
    {
        $request->validate([
            'month' => 'required|date_format:Y-m',
        ]);

        try {
            $month = $request->input('month');
            $startDate = Carbon::parse($month . '-01')->startOfMonth()->format('Y-m-d');
            $endDate = Carbon::parse($month . '-01')->endOfMonth()->format('Y-m-d');

            // アクティブな全利用者を取得
            $users = User::where('is_active', true)
                ->whereNull('deleted_at')
                ->orderBy('name')
                ->get(['id', 'name']);

            $userIds = $users->pluck('id')->toArray();

            // 全利用者の予定を一括取得（N+1問題を解消）
            $allPlans = AttendancePlan::whereIn('user_id', $userIds)
                ->whereBetween('plan_date', [$startDate, $endDate])
                ->get(['id', 'user_id', 'plan_date', 'plan_type', 'plan_time_slot', 'note'])
                ->groupBy('user_id');

            // 全利用者の実績を一括取得（N+1問題を解消）
            $allRecords = AttendanceRecord::whereIn('user_id', $userIds)
                ->whereBetween('record_date', [$startDate, $endDate])
                ->get(['id', 'user_id', 'record_date', 'record_time_slot', 'attendance_type', 'note'])
                ->groupBy('user_id');

            $usersData = [];

            foreach ($users as $user) {
                // ユーザーごとの予定を取得
                $plans = ($allPlans->get($user->id) ?? collect())->map(function($plan) {
                    return [
                        'id' => $plan->id,
                        'plan_date' => Carbon::parse($plan->plan_date)->format('Y-m-d'),
                        'plan_type' => $plan->plan_type,
                        'plan_time_slot' => $plan->plan_time_slot,
                        'note' => $plan->note,
                    ];
                })->toArray();

                // ユーザーごとの実績を取得
                $records = ($allRecords->get($user->id) ?? collect())->map(function($record) {
                    return [
                        'id' => $record->id,
                        'record_date' => Carbon::parse($record->record_date)->format('Y-m-d'),
                        'record_time_slot' => $record->record_time_slot,
                        'attendance_type' => $record->attendance_type,
                        'note' => $record->note,
                    ];
                })->toArray();

                $usersData[] = [
                    'id' => $user->id,
                    'name' => $user->name,
                    'plans' => $plans,
                    'records' => $records,
                ];
            }

            return response()->json([
                'success' => true,
                'users' => $usersData,
                'period' => [
                    'start' => $startDate,
                    'end' => $endDate,
                ],
            ]);

        } catch (\Exception $e) {
            \Log::error('Monthly overview failed', [
                'month' => $request->input('month'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '月次一覧の取得に失敗しました',
            ], 500);
        }
    }
}