<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AttendancePlan;
use App\Models\Holiday;
use App\Services\HolidayService;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PlanController extends Controller
{
    protected HolidayService $holidayService;
    protected AuditService $auditService;

    public function __construct(HolidayService $holidayService, AuditService $auditService)
    {
        $this->holidayService = $holidayService;
        $this->auditService = $auditService;
    }

    /**
     * S-04U: 月次出席予定
     */
    public function monthly(Request $request)
    {
        $month = $request->input('month', Carbon::now()->addMonth()->format('Y-m'));
        $userId = auth()->id();

        // 月の範囲を計算
        $startDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $endDate = Carbon::createFromFormat('Y-m', $month)->endOfMonth();

        // 既存の予定を取得（必要なカラムのみ）
        $existingPlans = AttendancePlan::where('user_id', $userId)
            ->whereBetween('plan_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->select('id', 'user_id', 'plan_date', 'plan_type', 'plan_time_slot', 'is_holiday', 'holiday_name')
            ->get()
            ->keyBy(function ($plan) {
                return $plan->plan_date->format('Y-m-d');
            });

        // 祝日データを取得
        $holidays = $this->holidayService->mapForMonth($month);

        // カレンダーデータを構築
        $calendar = $this->buildCalendar($startDate, $endDate, $existingPlans, $holidays);

        // 登録可能期間をチェック
        $canEdit = $this->canEditMonth($month);

        return view('user.plans.monthly', compact(
            'month', 
            'calendar', 
            'existingPlans', 
            'holidays', 
            'canEdit'
        ));
    }

    /**
     * 月次予定の一括保存
     */
    public function saveMonthly(Request $request)
    {
        $request->validate([
            'month' => 'required|date_format:Y-m',
            'plans' => 'array',
            'plans.*.plan_type' => 'sometimes|required|in:onsite,remote,off',
            'plans.*.time_slot' => 'sometimes|in:full,am,pm',
            'delete_all' => 'sometimes|boolean'
        ]);

        $month = $request->input('month');
        $plans = $request->input('plans', []);
        $userId = auth()->id();
        $deleteAll = $request->input('delete_all', false);

        // 編集可能かチェック
        if (!$this->canEditMonth($month)) {
            return redirect()->back()
                ->withErrors(['month' => 'この月の予定は編集できません']);
        }

        try {
            DB::beginTransaction();

            // 一括削除の場合
            if ($deleteAll) {
                $startDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
                $endDate = Carbon::createFromFormat('Y-m', $month)->endOfMonth();

                $deleted = AttendancePlan::where('user_id', $userId)
                    ->whereBetween('plan_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                    ->delete();

                DB::commit();

                // 監査ログ（トランザクション外で非同期的に記録）
                try {
                    $this->auditService->log(
                        actorType: 'user',
                        actorId: $userId,
                        action: 'delete',
                        entity: 'attendance_plans',
                        entityId: null,
                        diffJson: ['month' => $month, 'deleted_count' => $deleted]
                    );
                } catch (\Exception $e) {
                    \Log::warning('Audit log failed for bulk delete', ['error' => $e->getMessage()]);
                }

                // AJAX リクエストの場合
                if ($request->wantsJson() || $request->ajax()) {
                    return response()->json([
                        'success' => true,
                        'message' => "{$deleted}件の予定を削除しました"
                    ]);
                }

                return redirect()->back()
                    ->with('success', "{$deleted}件の予定を削除しました");
            }

            $saved = 0;
            $updated = 0;

            foreach ($plans as $date => $planData) {
                // 日付形式チェック
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    continue;
                }

                $planDate = Carbon::parse($date);

                // 指定月の日付かチェック
                if ($planDate->format('Y-m') !== $month) {
                    continue;
                }

                // 祝日情報を取得
                $holidayMap = $this->holidayService->mapForMonth($month);
                $isHoliday = $this->holidayService->isHoliday($date, $holidayMap);
                $holidayName = $this->holidayService->nameOf($date, $holidayMap);

                $planType = $planData['plan_type'];
                $timeSlot = $planData['time_slot'] ?? 'full';

                $existingPlan = AttendancePlan::where('user_id', $userId)
                    ->where('plan_date', $date)
                    ->where('plan_time_slot', $timeSlot)
                    ->first();

                if ($existingPlan) {
                    // 更新
                    $oldData = $existingPlan->toArray();
                    $existingPlan->update([
                        'plan_type' => $planType,
                        'is_holiday' => $isHoliday,
                        'holiday_name' => $holidayName,
                        'updated_by' => $userId,
                    ]);

                    // 監査ログ
                    $this->auditService->log(
                        actorType: 'user',
                        actorId: $userId,
                        action: 'update',
                        entity: 'attendance_plans',
                        entityId: $existingPlan->id,
                        diffJson: [
                            'before' => $oldData,
                            'after' => $existingPlan->fresh()->toArray()
                        ]
                    );

                    $updated++;
                } else {
                    // 新規作成
                    $newPlan = AttendancePlan::create([
                        'user_id' => $userId,
                        'plan_date' => $date,
                        'plan_type' => $planType,
                        'plan_time_slot' => $timeSlot,
                        'is_holiday' => $isHoliday,
                        'holiday_name' => $holidayName,
                        'source' => 'self',
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]);

                    // 監査ログ
                    $this->auditService->log(
                        actorType: 'user',
                        actorId: $userId,
                        action: 'create',
                        entity: 'attendance_plans',
                        entityId: $newPlan->id,
                        diffJson: null
                    );

                    $saved++;
                }
            }

            DB::commit();

            $message = [];
            if ($saved > 0) $message[] = "新規登録: {$saved}件";
            if ($updated > 0) $message[] = "更新: {$updated}件";

            // AJAX リクエストの場合
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => '月次予定を保存しました (' . implode(', ', $message) . ')'
                ]);
            }

            return redirect()->back()
                ->with('success', '月次予定を保存しました (' . implode(', ', $message) . ')');

        } catch (\Exception $e) {
            DB::rollBack();
            
            \Log::error('Monthly plan save failed', [
                'user_id' => $userId,
                'month' => $month,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->withErrors(['error' => '予定の保存に失敗しました'])
                ->withInput();
        }
    }

    /**
     * 個別日付の予定更新
     */
    public function updateSingle(Request $request)
    {
        // 削除アクションの場合
        if ($request->input('action') === 'delete') {
            return $this->deleteSingle($request);
        }

        $request->validate([
            'date' => 'required|date_format:Y-m-d',
            'plan_type' => 'required|in:onsite,remote,off',
            'time_slot' => 'sometimes|in:full,am,pm'
        ]);

        $date = $request->input('date');
        $planType = $request->input('plan_type');
        $timeSlot = $request->input('time_slot', 'full');
        $userId = auth()->id();

        // 編集可能かチェック
        $month = Carbon::parse($date)->format('Y-m');
        if (!$this->canEditMonth($month)) {
            return response()->json([
                'success' => false,
                'message' => 'この日付の予定は編集できません'
            ], 400);
        }

        try {
            DB::beginTransaction();

            // 祝日情報を取得
            $holidayMap = $this->holidayService->mapForMonth($month);
            $isHoliday = $this->holidayService->isHoliday($date, $holidayMap);
            $holidayName = $this->holidayService->nameOf($date, $holidayMap);

            $plan = AttendancePlan::updateOrCreate(
                [
                    'user_id' => $userId,
                    'plan_date' => $date,
                    'plan_time_slot' => $timeSlot
                ],
                [
                    'plan_type' => $planType,
                    'is_holiday' => $isHoliday,
                    'holiday_name' => $holidayName,
                ]
            );

            // 監査ログ
            $action = $plan->wasRecentlyCreated ? 'create' : 'update';
            $this->auditService->log(
                actorType: 'user',
                actorId: $userId,
                action: $action,
                entity: 'attendance_plans',
                entityId: $plan->id,
                diffJson: $action === 'create' ? null : ['plan_type' => $planType, 'time_slot' => $timeSlot]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '予定を更新しました',
                'data' => [
                    'date' => $date,
                    'plan_type' => $planType,
                    'is_holiday' => $isHoliday,
                    'holiday_name' => $holidayName
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            \Log::error('Single plan update failed', [
                'user_id' => $userId,
                'date' => $date,
                'plan_type' => $planType,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '予定の更新に失敗しました'
            ], 500);
        }
    }

    /**
     * 個別日付の予定削除
     */
    private function deleteSingle(Request $request)
    {
        $request->validate([
            'date' => 'required|date_format:Y-m-d'
        ]);

        $date = $request->input('date');
        $userId = auth()->id();

        // 編集可能かチェック
        $month = Carbon::parse($date)->format('Y-m');
        if (!$this->canEditMonth($month)) {
            return response()->json([
                'success' => false,
                'message' => 'この日付の予定は編集できません'
            ], 400);
        }

        try {
            DB::beginTransaction();

            $plan = AttendancePlan::where('user_id', $userId)
                ->where('plan_date', $date)
                ->first();

            if (!$plan) {
                return response()->json([
                    'success' => false,
                    'message' => '削除する予定が見つかりません'
                ], 404);
            }

            $planId = $plan->id;
            $plan->delete();

            // 監査ログ
            $this->auditService->log(
                actorType: 'user',
                actorId: $userId,
                action: 'delete',
                entity: 'attendance_plans',
                entityId: $planId,
                diffJson: ['date' => $date]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '予定を削除しました'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            \Log::error('Plan delete failed', [
                'user_id' => $userId,
                'date' => $date,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '予定の削除に失敗しました'
            ], 500);
        }
    }

    /**
     * カレンダーデータを構築
     */
    private function buildCalendar($startDate, $endDate, $existingPlans, $holidays)
    {
        $calendar = [];
        $current = $startDate->copy();

        // 月の最初の週の開始日（日曜日）を取得
        $firstWeekStart = $current->copy()->startOfWeek(Carbon::SUNDAY);
        
        // 月の最後の週の終了日（土曜日）を取得
        $lastWeekEnd = $endDate->copy()->endOfWeek(Carbon::SATURDAY);

        $date = $firstWeekStart->copy();
        
        while ($date <= $lastWeekEnd) {
            $dateString = $date->format('Y-m-d');
            $isCurrentMonth = $date->month === $startDate->month;
            
            $calendar[] = [
                'date' => $dateString,
                'day' => $date->day,
                'day_of_week' => $date->dayOfWeek,
                'is_current_month' => $isCurrentMonth,
                'is_weekend' => $date->isWeekend(),
                'is_today' => $date->isToday(),
                'is_holiday' => isset($holidays[$dateString]),
                'holiday_name' => $holidays[$dateString] ?? null,
                'existing_plan' => $existingPlans->get($dateString),
                'plan_type' => $existingPlans->get($dateString)?->plan_type ?? null,
                'time_slot' => $existingPlans->get($dateString)?->plan_time_slot ?? null,
            ];
            
            $date->addDay();
        }

        return $calendar;
    }

    /**
     * 月の編集可能性をチェック
     */
    private function canEditMonth($month)
    {
        $targetMonth = Carbon::createFromFormat('Y-m', $month);
        $now = Carbon::now();
        
        // 当月と翌月のみ編集可能
        return $targetMonth->isSameMonth($now) || 
               $targetMonth->isSameMonth($now->addMonth());
    }
}