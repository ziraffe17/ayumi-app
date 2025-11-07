<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\AttendancePlan;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    protected $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    /**
     * 出席実績一覧取得 (S-06S/利用者用)
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'sometimes|integer|exists:users,id',
            'year_month' => 'sometimes|string|regex:/^\d{4}-\d{2}$/',
            'start_date' => 'sometimes|date|date_format:Y-m-d',
            'end_date' => 'sometimes|date|date_format:Y-m-d|after_or_equal:start_date',
        ]);

        try {
            // ユーザー特定（職員は任意指定、利用者は自分のみ）
            $userId = $this->determineUserId($request);
            $targetUser = User::findOrFail($userId);

            // 認可チェック
            Gate::authorize('view', $targetUser);

            // 期間設定
            [$startDate, $endDate] = $this->determineDateRange($request);

            // 出席実績取得
            $records = AttendanceRecord::where('user_id', $userId)
                ->whereBetween('record_date', [$startDate, $endDate])
                ->orderBy('record_date')
                ->orderBy('record_time_slot')
                ->get();

            // 出席予定も併せて取得（比較用）
            $plans = AttendancePlan::where('user_id', $userId)
                ->whereBetween('plan_date', [$startDate, $endDate])
                ->get()
                ->keyBy(function ($plan) {
                    return $plan->plan_date . '_' . $plan->plan_time_slot;
                });

            // レスポンス整形
            $attendanceData = $this->formatAttendanceData($records, $plans, $startDate, $endDate);

            return response()->json([
                'success' => true,
                'data' => [
                    'user_id' => $userId,
                    'user_name' => $targetUser->name,
                    'period' => ['start' => $startDate, 'end' => $endDate],
                    'attendance_records' => $attendanceData,
                    'summary' => $this->calculateAttendanceSummary($records, $plans),
                ],
            ]);

        } catch (\Exception $e) {
            \Log::error('Attendance records fetch failed', [
                'request' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '出席実績の取得に失敗しました',
            ], 500);
        }
    }

    /**
     * 出席実績登録
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'record_date' => 'required|date|date_format:Y-m-d',
            'record_time_slot' => 'required|in:am,pm,full',
            'attendance_type' => 'required|in:onsite,remote,absent',
            'note' => 'nullable|string|max:1000',
        ]);

        try {
            DB::beginTransaction();

            // ユーザー特定
            $userId = $this->determineUserId($request);
            $targetUser = User::findOrFail($userId);

            // 認可チェック
            Gate::authorize('manageAttendance', $targetUser);

            // 重複チェック
            $existingRecord = AttendanceRecord::where('user_id', $userId)
                ->where('record_date', $request->record_date)
                ->where('record_time_slot', $request->record_time_slot)
                ->first();

            if ($existingRecord) {
                return response()->json([
                    'success' => false,
                    'message' => '指定された日時の実績は既に登録されています',
                ], 422);
            }

            // 過去日編集ポリシーチェック（職員は制限なし）
            if (!$this->isStaffUser() && !$this->canEditPastDate($request->record_date)) {
                return response()->json([
                    'success' => false,
                    'message' => '過去の日付の編集は制限されています',
                ], 422);
            }

            // 実績作成
            $record = AttendanceRecord::create([
                'user_id' => $userId,
                'record_date' => $request->record_date,
                'record_time_slot' => $request->record_time_slot,
                'attendance_type' => $request->attendance_type,
                'note' => $request->note,
                'source' => $this->isStaffUser() ? 'staff' : 'self',
            ]);

            // 監査ログ使用時
            $this->auditService->log(
                actorType: 'staff',
                actorId: auth()->id(),
                action: 'create',
                entity: 'attendance_records',
                entityId: $record->id,
                diffJson: ['new' => $record->toArray()]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '出席実績を登録しました',
                'data' => $record,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            \Log::error('Attendance record creation failed', [
                'request' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '出席実績の登録に失敗しました',
            ], 500);
        }
    }

    /**
     * 出席実績更新
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'attendance_type' => 'nullable|in:onsite,remote,absent',
            'note' => 'nullable|string|max:1000',
        ]);

        try {
            DB::beginTransaction();

            $record = AttendanceRecord::findOrFail($id);
            $targetUser = User::findOrFail($record->user_id);

            // 認可チェック
            Gate::authorize('manageAttendance', $targetUser);

            // 利用者の場合は自分のレコードのみ編集可能
            if (!$this->isStaffUser()) {
                Gate::authorize('view', $targetUser);
            }

            // 過去日編集ポリシーチェック（職員は制限なし）
            if (!$this->isStaffUser() && !$this->canEditPastDate($record->record_date)) {
                return response()->json([
                    'success' => false,
                    'message' => '過去の日付の編集は制限されています',
                ], 422);
            }

            // 承認済み・ロック済みチェック
            if (!$record->canBeEdited()) {
                return response()->json([
                    'success' => false,
                    'message' => '承認済み・ロック済みの実績は編集できません',
                ], 422);
            }

            $oldData = $record->toArray();
            $record->update($request->only(['attendance_type', 'note']));

            // 監査ログ記録
            $this->auditService->log(
                actorType: $this->isStaffUser() ? 'staff' : 'user',
                actorId: auth()->id(),
                action: 'update',
                entity: 'attendance_records',
                entityId: $record->id,
                diffJson: [
                    'before' => $oldData,
                    'after' => $record->fresh()->toArray()
                ]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '出席実績を更新しました',
                'data' => $record->fresh(),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            \Log::error('Attendance record update failed', [
                'id' => $id,
                'request' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '出席実績の更新に失敗しました',
            ], 500);
        }
    }

    /**
     * 出席実績削除
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $record = AttendanceRecord::findOrFail($id);
            $targetUser = User::findOrFail($record->user_id);

            if (!$this->isStaffUser()) {
                return response()->json([
                    'success' => false,
                    'message' => '削除権限がありません',
                ], 403);
            }

            Gate::authorize('manageAttendance', $targetUser);

            // 職員は過去日も削除可能
            // （削除は職員のみなので、このチェックは実質不要だが念のため残す）
            if (!$this->isStaffUser() && !$this->canEditPastDate($record->record_date)) {
                return response()->json([
                    'success' => false,
                    'message' => '過去の日付の削除は制限されています',
                ], 422);
            }

            $recordData = $record->toArray();
            $record->delete();

            // ✅ 修正: auditService->log() を使用
            $this->auditService->log(
                actorType: 'staff',
                actorId: auth()->id(),
                action: 'delete',
                entity: 'attendance_records',
                entityId: $id,
                diffJson: ['deleted' => $recordData]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '出席実績を削除しました',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            \Log::error('Attendance record deletion failed', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '出席実績の削除に失敗しました',
            ], 500);
        }
    }

    /**
     * 計画vs実績の比較データ取得
     */
    public function comparison(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'sometimes|integer|exists:users,id',
            'year_month' => 'sometimes|string|regex:/^\d{4}-\d{2}$/',
        ]);

        try {
            $userId = $this->determineUserId($request);
            $targetUser = User::findOrFail($userId);

            Gate::authorize('view', $targetUser);

            [$startDate, $endDate] = $this->determineDateRange($request);

            // 計画と実績を取得
            $plans = AttendancePlan::where('user_id', $userId)
                ->whereBetween('plan_date', [$startDate, $endDate])
                ->get();

            $records = AttendanceRecord::where('user_id', $userId)
                ->whereBetween('record_date', [$startDate, $endDate])
                ->get();

            // 比較データ生成
            $comparisonData = $this->generateComparisonData($plans, $records, $startDate, $endDate);

            return response()->json([
                'success' => true,
                'data' => [
                    'user_id' => $userId,
                    'user_name' => $targetUser->name,
                    'period' => ['start' => $startDate, 'end' => $endDate],
                    'comparison' => $comparisonData,
                    'statistics' => $this->calculateComparisonStatistics($comparisonData),
                ],
            ]);

        } catch (\Exception $e) {
            \Log::error('Attendance comparison failed', [
                'request' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '比較データの取得に失敗しました',
            ], 500);
        }
    }

    /**
     * ユーザーID決定（職員は指定可、利用者は自分のみ）
     */
    private function determineUserId(Request $request): int
    {
        // 利用者はwebガード、職員はstaffガード
        if (auth()->guard('web')->check()) {
            // 利用者は自分のIDのみ
            return auth()->guard('web')->id();
        }
        
        if (auth()->guard('staff')->check()) {
            // 職員は任意のユーザーIDを指定可能
            return $request->integer('user_id', auth()->guard('staff')->id());
        }
        
        throw new \Exception('認証が必要です');
    }


    /**
     * 日付範囲決定
     */
    private function determineDateRange(Request $request): array
    {
        if ($request->has('start_date') && $request->has('end_date')) {
            return [$request->start_date, $request->end_date];
        }

        if ($request->has('year_month')) {
            $yearMonth = $request->year_month;
            $startDate = Carbon::createFromFormat('Y-m', $yearMonth)->startOfMonth()->format('Y-m-d');
            $endDate = Carbon::createFromFormat('Y-m', $yearMonth)->endOfMonth()->format('Y-m-d');
            return [$startDate, $endDate];
        }

        // デフォルト：当月
        $startDate = Carbon::now()->startOfMonth()->format('Y-m-d');
        $endDate = Carbon::now()->endOfMonth()->format('Y-m-d');
        return [$startDate, $endDate];
    }

    /**
     * 出席データフォーマット
     */
    private function formatAttendanceData($records, $plans, string $startDate, string $endDate): array
    {
        $data = [];
        $currentDate = Carbon::parse($startDate);
        $endDateCarbon = Carbon::parse($endDate);

        while ($currentDate <= $endDateCarbon) {
            $dateString = $currentDate->format('Y-m-d');
            $dayData = [
                'date' => $dateString,
                'day_of_week' => $currentDate->isoFormat('ddd'),
                'is_holiday' => $currentDate->isWeekend(),
                'slots' => [],
            ];

            foreach (['am', 'pm', 'full'] as $slot) {
                $planKey = $dateString . '_' . $slot;
                $plan = $plans->get($planKey);
                
                $record = $records->firstWhere(function ($r) use ($dateString, $slot) {
                    return $r->record_date === $dateString && $r->record_time_slot === $slot;
                });

                $dayData['slots'][] = [
                    'time_slot' => $slot,
                    'plan' => $plan ? [
                        'type' => $plan->plan_type,
                        'note' => $plan->note,
                        'is_holiday' => $plan->is_holiday,
                        'holiday_name' => $plan->holiday_name,
                    ] : null,
                    'record' => $record ? [
                        'id' => $record->id,
                        'type' => $record->attendance_type,
                        'note' => $record->note,
                        'source' => $record->source,
                    ] : null,
                    'difference' => $this->calculateDifference($plan, $record),
                ];
            }

            $data[] = $dayData;
            $currentDate->addDay();
        }

        return $data;
    }

    /**
     * 出席サマリー計算
     */
    private function calculateAttendanceSummary($records, $plans): array
    {
        $plannedDays = $plans->where('plan_type', '!=', 'off')->pluck('plan_date')->unique()->count();
        $attendedDays = $records->whereIn('attendance_type', ['onsite', 'remote'])->pluck('record_date')->unique()->count();
        $absentDays = $records->where('attendance_type', 'absent')->pluck('record_date')->unique()->count();

        $attendanceRate = $plannedDays > 0 ? round(($attendedDays / $plannedDays) * 100, 1) : null;

        return [
            'planned_days' => $plannedDays,
            'attended_days' => $attendedDays,
            'absent_days' => $absentDays,
            'attendance_rate' => $attendanceRate,
            'attendance_rate_display' => $attendanceRate !== null ? $attendanceRate . '%' : '—',
            'total_records' => $records->count(),
            'onsite_count' => $records->where('attendance_type', 'onsite')->count(),
            'remote_count' => $records->where('attendance_type', 'remote')->count(),
        ];
    }

    /**
     * 差分計算
     */
    private function calculateDifference($plan, $record): array
    {
        if (!$plan && !$record) {
            return [
                'type' => 'none',
                'status' => 'no_data',
                'message' => null,
            ];
        }

        if (!$plan && $record) {
            return [
                'type' => 'unexpected',
                'status' => 'warning',
                'message' => '予定外の出席',
            ];
        }

        if ($plan && !$record) {
            if ($plan->plan_type === 'off') {
                return [
                    'type' => 'matched',
                    'status' => 'success',
                    'message' => '休み（予定通り）',
                ];
            }
            return [
                'type' => 'missing',
                'status' => 'error',
                'message' => '実績未入力',
            ];
        }

        // 計画と実績の両方がある場合
        if ($plan->plan_type === 'off') {
            return [
                'type' => 'unexpected',
                'status' => 'warning',
                'message' => '休み予定だが出席',
            ];
        }

        if ($record->attendance_type === 'absent') {
            return [
                'type' => 'absent',
                'status' => 'error',
                'message' => '予定ありだが欠席',
            ];
        }

        if (($plan->plan_type === 'onsite' && $record->attendance_type === 'onsite') ||
            ($plan->plan_type === 'remote' && $record->attendance_type === 'remote')) {
            return [
                'type' => 'matched',
                'status' => 'success',
                'message' => '予定通り',
            ];
        }

        if (($plan->plan_type === 'onsite' && $record->attendance_type === 'remote') ||
            ($plan->plan_type === 'remote' && $record->attendance_type === 'onsite')) {
            return [
                'type' => 'type_mismatch',
                'status' => 'warning',
                'message' => '出席方法が異なる',
            ];
        }

        return [
            'type' => 'unknown',
            'status' => 'info',
            'message' => null,
        ];
    }

    /**
     * 比較データ生成
     */
    private function generateComparisonData($plans, $records, string $startDate, string $endDate): array
    {
        $plansGrouped = $plans->groupBy(function ($plan) {
            return $plan->plan_date . '_' . $plan->plan_time_slot;
        });

        $recordsGrouped = $records->groupBy(function ($record) {
            return $record->record_date . '_' . $record->record_time_slot;
        });

        $comparisonData = [];
        $currentDate = Carbon::parse($startDate);
        $endDateCarbon = Carbon::parse($endDate);

        while ($currentDate <= $endDateCarbon) {
            $dateString = $currentDate->format('Y-m-d');

            foreach (['am', 'pm', 'full'] as $slot) {
                $key = $dateString . '_' . $slot;
                $plan = $plansGrouped->get($key)?->first();
                $record = $recordsGrouped->get($key)?->first();

                if ($plan || $record) {
                    $comparisonData[] = [
                        'date' => $dateString,
                        'time_slot' => $slot,
                        'plan' => $plan ? [
                            'id' => $plan->id,
                            'type' => $plan->plan_type,
                            'note' => $plan->note,
                        ] : null,
                        'record' => $record ? [
                            'id' => $record->id,
                            'type' => $record->attendance_type,
                            'note' => $record->note,
                            'source' => $record->source,
                        ] : null,
                        'difference' => $this->calculateDifference($plan, $record),
                    ];
                }
            }

            $currentDate->addDay();
        }

        return $comparisonData;
    }

    /**
     * 比較統計計算
     */
    private function calculateComparisonStatistics(array $comparisonData): array
    {
        $total = count($comparisonData);
        $matched = 0;
        $mismatched = 0;
        $missing = 0;
        $unexpected = 0;

        foreach ($comparisonData as $item) {
            switch ($item['difference']['type']) {
                case 'matched':
                    $matched++;
                    break;
                case 'type_mismatch':
                case 'absent':
                    $mismatched++;
                    break;
                case 'missing':
                    $missing++;
                    break;
                case 'unexpected':
                    $unexpected++;
                    break;
            }
        }

        return [
            'total' => $total,
            'matched' => $matched,
            'matched_rate' => $total > 0 ? round(($matched / $total) * 100, 1) : 0,
            'mismatched' => $mismatched,
            'missing' => $missing,
            'unexpected' => $unexpected,
        ];
    }

    /**
     * 過去日編集可否判定
     */
    private function canEditPastDate(string $date): bool
    {
        $targetDate = Carbon::parse($date);
        $today = Carbon::today('Asia/Tokyo');
        $yesterday = Carbon::today('Asia/Tokyo')->subDay();

        // 今日と昨日まで編集可能
        return $targetDate->gte($yesterday);
    }

    /**
     * 出席実績承認
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'approval_note' => 'sometimes|string|max:1000',
        ]);

        try {
            DB::beginTransaction();

            $record = AttendanceRecord::findOrFail($id);
            
            // 職員のみ承認可能
            if (!$this->isStaffUser()) {
                return response()->json([
                    'success' => false,
                    'message' => '承認権限がありません',
                ], 403);
            }

            // 承認可能かチェック
            if (!$record->canBeApproved()) {
                return response()->json([
                    'success' => false,
                    'message' => '既に承認済み・ロック済みの実績は承認できません',
                ], 422);
            }

            $oldData = $record->toArray();
            $record->update([
                'is_approved' => true,
                'approved_by' => auth()->guard('staff')->id(),
                'approved_at' => now(),
                'approval_note' => $request->approval_note,
            ]);

            // 監査ログ記録
            $this->auditService->log(
                actorType: 'staff',
                actorId: auth()->guard('staff')->id(),
                action: 'update',  // approve → update に変更
                entity: 'attendance_records',
                entityId: $record->id,
                diffJson: [
                    'before' => $oldData,
                    'after' => $record->fresh()->toArray(),
                    'action_type' => 'approve'  // 詳細をdiffJsonに記録
                ]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '出席実績を承認しました',
                'data' => $record->fresh(),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            \Log::error('Attendance record approval failed', [
                'id' => $id,
                'request' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '承認処理に失敗しました',
            ], 500);
        }
    }

    /**
     * 出席実績ロック/アンロック
     */
    public function lock(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'lock' => 'required|boolean',
        ]);

        try {
            DB::beginTransaction();

            $record = AttendanceRecord::findOrFail($id);
            
            // 職員のみロック操作可能
            if (!$this->isStaffUser()) {
                return response()->json([
                    'success' => false,
                    'message' => 'ロック操作権限がありません',
                ], 403);
            }

            $shouldLock = $request->boolean('lock');
            
            if ($shouldLock && !$record->canBeLocked()) {
                return response()->json([
                    'success' => false,
                    'message' => '既にロック済みです',
                ], 422);
            }

            $oldData = $record->toArray();
            $record->update([
                'is_locked' => $shouldLock,
                'locked_by' => $shouldLock ? auth()->guard('staff')->id() : null,
                'locked_at' => $shouldLock ? now() : null,
            ]);

            // 監査ログ記録
            $this->auditService->log(
                actorType: 'staff',
                actorId: auth()->guard('staff')->id(),
                action: 'update',  // lock/unlock → update に変更
                entity: 'attendance_records',
                entityId: $record->id,
                diffJson: [
                    'before' => $oldData,
                    'after' => $record->fresh()->toArray(),
                    'action_type' => $shouldLock ? 'lock' : 'unlock'  // 詳細をdiffJsonに記録
                ]
            );

            DB::commit();

            $message = $shouldLock ? 'ロックしました' : 'ロックを解除しました';
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $record->fresh(),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            \Log::error('Attendance record lock operation failed', [
                'id' => $id,
                'request' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'ロック操作に失敗しました',
            ], 500);
        }
    }

    /**
     * 職員ユーザー判定
     */
    private function isStaffUser(): bool
    {
        return auth()->guard('staff')->check();
    }


}