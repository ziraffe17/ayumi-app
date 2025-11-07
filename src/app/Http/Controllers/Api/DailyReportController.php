<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailyReportMorning;
use App\Models\DailyReportEvening;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DailyReportController extends Controller
{
    /**
     * 朝の日報一覧取得
     */
    public function indexMorning(Request $request): JsonResponse
    {
        return $this->indexReports($request, 'morning');
    }

    /**
     * 夕の日報一覧取得
     */
    public function indexEvening(Request $request): JsonResponse
    {
        return $this->indexReports($request, 'evening');
    }

    /**
     * 朝の日報登録
     */
    public function storeMorning(Request $request): JsonResponse
    {
        $rules = [
            'user_id' => 'required_if:is_staff,true|integer|exists:users,id',
            'report_date' => 'required|date|date_format:Y-m-d|before_or_equal:today',
            'sleep_rating' => 'required|integer|between:1,3',
            'stress_rating' => 'required|integer|between:1,3',
            'meal_rating' => 'required|integer|between:1,3',
            'bed_time_local' => 'required|date_format:H:i',
            'wake_time_local' => 'required|date_format:H:i',
            'mid_awaken_count' => 'nullable|integer|between:0,10',
            'is_early_awaken' => 'nullable|boolean',
            'is_breakfast_done' => 'nullable|boolean',
            'is_bathing_done' => 'nullable|boolean',
            'is_medication_taken' => 'nullable|boolean',
            'mood_score' => 'required|integer|between:1,10',
            'sign_good' => 'nullable|integer|min:0',
            'sign_caution' => 'nullable|integer|min:0',
            'sign_bad' => 'nullable|integer|min:0',
            'note' => 'nullable|string|max:1000',
        ];

        return $this->storeReport($request, 'morning', DailyReportMorning::class, $rules);
    }

    /**
     * 夕の日報登録
     */
    public function storeEvening(Request $request): JsonResponse
    {
        $rules = [
            'user_id' => 'required_if:is_staff,true|integer|exists:users,id',
            'report_date' => 'required|date|date_format:Y-m-d|before_or_equal:today',
            'training_summary' => 'required|string|max:1000',    // 必須
            'training_reflection' => 'required|string|max:1000',   // 必須
            'condition_note' => 'required|string|max:1000',      // 必須
            'other_note' => 'nullable|string|max:1000',          // 任意
        ];

        return $this->storeReport($request, 'evening', DailyReportEvening::class, $rules);
    }

    /**
     * 朝の日報更新
     */
    public function updateMorning(Request $request, int $id): JsonResponse
    {
        $rules = [
            'sleep_rating' => 'nullable|integer|between:1,3',
            'stress_rating' => 'nullable|integer|between:1,3',
            'meal_rating' => 'nullable|integer|between:1,3',
            'bed_time_local' => 'nullable|date_format:H:i',
            'wake_time_local' => 'nullable|date_format:H:i',
            'mid_awaken_count' => 'nullable|integer|between:0,10',
            'is_early_awaken' => 'nullable|boolean',
            'is_breakfast_done' => 'nullable|boolean',
            'is_bathing_done' => 'nullable|boolean',
            'is_medication_taken' => 'nullable|boolean',
            'mood_score' => 'nullable|integer|between:1,10',
            'sign_good' => 'nullable|integer|min:0',
            'sign_caution' => 'nullable|integer|min:0',
            'sign_bad' => 'nullable|integer|min:0',
            'note' => 'nullable|string|max:1000',
        ];

        return $this->updateReport($request, $id, 'morning', DailyReportMorning::class, $rules);
    }

    /**
     * 夕の日報更新
     */
    public function updateEvening(Request $request, int $id): JsonResponse
    {
        $rules = [
            'training_summary' => 'nullable|string|max:1000',
            'training_reflection' => 'nullable|string|max:1000',
            'condition_note' => 'nullable|string|max:1000',
            'other_note' => 'nullable|string|max:1000',
        ];

        return $this->updateReport($request, $id, 'evening', DailyReportEvening::class, $rules);
    }

    /**
     * 日報リスト取得（職員用）
     */
    public function list(Request $request): JsonResponse
    {
        $request->validate([
            'user_ids' => 'sometimes|array',
            'user_ids.*' => 'integer|exists:users,id',
            'start_date' => 'sometimes|date|date_format:Y-m-d',
            'end_date' => 'sometimes|date|date_format:Y-m-d|after_or_equal:start_date',
            'report_type' => 'sometimes|in:morning,evening,both',
            'limit' => 'sometimes|integer|min:1|max:100',
            'offset' => 'sometimes|integer|min:0',
        ]);

        if (!$this->isStaffUser()) {
            return response()->json(['success' => false, 'message' => 'アクセス権限がありません'], 403);
        }

        try {
            $userIds = $request->input('user_ids', User::where('is_active', 1)->pluck('id')->toArray());
            $startDate = $request->string('start_date', now()->startOfMonth()->format('Y-m-d'));
            $endDate = $request->string('end_date', now()->format('Y-m-d'));
            $reportType = $request->string('report_type', 'both');
            $limit = $request->integer('limit', 50);
            $offset = $request->integer('offset', 0);

            $reportsData = [];

            if (in_array($reportType, ['morning', 'both'])) {
                $morningReports = DailyReportMorning::with('user:id,name')
                    ->whereIn('user_id', $userIds)
                    ->whereBetween('report_date', [$startDate, $endDate])
                    ->orderBy('report_date', 'desc')
                    ->offset($offset)
                    ->limit($limit)
                    ->get();

                foreach ($morningReports as $report) {
                    $reportsData[] = [
                        'id' => $report->id,
                        'type' => 'morning',
                        'user_id' => $report->user_id,
                        'user_name' => $report->user->name,
                        'report_date' => Carbon::parse($report->report_date)->format('Y-m-d'),
                        'data' => $this->formatMorningReportData($report),
                        'created_at' => $report->created_at->toISOString(),
                    ];
                }
            }

            if (in_array($reportType, ['evening', 'both'])) {
                $eveningReports = DailyReportEvening::with('user:id,name')
                    ->whereIn('user_id', $userIds)
                    ->whereBetween('report_date', [$startDate, $endDate])
                    ->orderBy('report_date', 'desc')
                    ->offset($offset)
                    ->limit($limit)
                    ->get();

                foreach ($eveningReports as $report) {
                    $reportsData[] = [
                        'id' => $report->id,
                        'type' => 'evening',
                        'user_id' => $report->user_id,
                        'user_name' => $report->user->name,
                        'report_date' => Carbon::parse($report->report_date)->format('Y-m-d'),
                        'data' => $this->formatEveningReportData($report),
                        'created_at' => $report->created_at->toISOString(),
                    ];
                }
            }

            usort($reportsData, function ($a, $b) {
                return strcmp($b['report_date'], $a['report_date']);
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'reports' => $reportsData,
                    'period' => ['start' => $startDate, 'end' => $endDate],
                    'pagination' => ['limit' => $limit, 'offset' => $offset, 'total' => count($reportsData)],
                ],
            ]);

        } catch (\Exception $e) {
            \Log::error('Reports list fetch failed', ['request' => $request->all(), 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => '日報リストの取得に失敗しました'], 500);
        }
    }

    private function indexReports(Request $request, string $type): JsonResponse
    {
        $request->validate([
            'user_id' => 'sometimes|integer|exists:users,id',
            'start_date' => 'sometimes|date|date_format:Y-m-d',
            'end_date' => 'sometimes|date|date_format:Y-m-d|after_or_equal:start_date',
            'year_month' => 'sometimes|string|regex:/^\d{4}-\d{2}$/',
        ]);

        try {
            // デバッグログ
            \Log::info('DailyReport indexReports Debug', [
                'is_staff' => $this->isStaffUser(),
                'web_check' => auth()->guard('web')->check(),
                'web_id' => auth()->guard('web')->id(),
                'staff_check' => auth()->guard('staff')->check(),
                'staff_id' => auth()->guard('staff')->id(),
                'default_check' => auth()->check(),
                'default_id' => auth()->id(),
            ]);

            $userId = $this->determineUserId($request);
            \Log::info('Determined user_id: ' . $userId);

            $targetUser = User::findOrFail($userId);
            Gate::authorize('manageReports', $targetUser);

            [$startDate, $endDate] = $this->determineDateRange($request);
            $modelClass = $type === 'morning' ? DailyReportMorning::class : DailyReportEvening::class;
            
            $reports = $modelClass::where('user_id', $userId)
                ->whereBetween('report_date', [$startDate, $endDate])
                ->orderBy('report_date', 'desc')
                ->get();

            $formattedReports = $reports->map(function ($report) use ($type) {
                return [
                    'id' => $report->id,
                    'report_date' => Carbon::parse($report->report_date)->format('Y-m-d'),
                    'data' => $type === 'morning' ? $this->formatMorningReportData($report) : $this->formatEveningReportData($report),
                    'created_at' => $report->created_at->toISOString(),
                    'updated_at' => $report->updated_at->toISOString(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'user_id' => $userId,
                    'user_name' => $targetUser->name,
                    'period' => ['start' => $startDate, 'end' => $endDate],
                    'report_type' => $type,
                    'reports' => $formattedReports,
                ],
            ]);

        } catch (\Exception $e) {
            \Log::error("Reports {$type} index failed", ['request' => $request->all(), 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => '日報の取得に失敗しました'], 500);
        }
    }

    private function storeReport(Request $request, string $type, string $modelClass, array $rules): JsonResponse
    {
        $request->validate($rules);

        try {
            DB::beginTransaction();
            $userId = $this->determineUserId($request);
            $targetUser = User::findOrFail($userId);
            Gate::authorize('manageReports', $targetUser);

            $existingReport = $modelClass::where('user_id', $userId)->where('report_date', $request->report_date)->first();
            if ($existingReport) {
                return response()->json(['success' => false, 'message' => '指定された日付の日報は既に登録されています'], 422);
            }

            $reportData = $this->prepareReportData($request, $userId, $type);
            $report = $modelClass::create($reportData);
            $this->auditLog('create', "daily_report_{$type}", $report->id, ['user_id' => $userId, 'date' => $request->report_date]);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '日報を登録しました',
                'data' => [
                    'id' => $report->id,
                    'report_date' => $report->report_date,
                    'data' => $type === 'morning' ? $this->formatMorningReportData($report) : $this->formatEveningReportData($report),
                ],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("Report {$type} creation failed", ['request' => $request->all(), 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => '日報の登録に失敗しました'], 500);
        }
    }

    private function updateReport(Request $request, int $id, string $type, string $modelClass, array $rules): JsonResponse
    {
        $request->validate($rules);

        try {
            DB::beginTransaction();
            $report = $modelClass::findOrFail($id);
            $targetUser = User::findOrFail($report->user_id);
            Gate::authorize('manageReports', $targetUser);

            if (!$this->isStaffUser()) {
                Gate::authorize('view', $targetUser);
            }

            if (!$this->canEditPastDate($report->report_date)) {
                return response()->json(['success' => false, 'message' => '過去の日付の編集は制限されています'], 422);
            }

            $updateData = $this->prepareUpdateData($request, $type);
            $report->update($updateData);
            $this->auditLog('update', "daily_report_{$type}", $report->id, ['user_id' => $report->user_id, 'date' => $report->report_date, 'changes' => $updateData]);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '日報を更新しました',
                'data' => [
                    'id' => $report->id,
                    'report_date' => $report->report_date,
                    'data' => $type === 'morning' ? $this->formatMorningReportData($report->fresh()) : $this->formatEveningReportData($report->fresh()),
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("Report {$type} update failed", ['id' => $id, 'request' => $request->all(), 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => '日報の更新に失敗しました'], 500);
        }
    }

    private function formatMorningReportData($report): array
    {
        return [
            'sleep_rating' => $report->sleep_rating,
            'sleep_rating_display' => $this->getRatingDisplay($report->sleep_rating),
            'stress_rating' => $report->stress_rating,
            'stress_rating_display' => $this->getRatingDisplay($report->stress_rating),
            'meal_rating' => $report->meal_rating,
            'meal_rating_display' => $this->getRatingDisplay($report->meal_rating),
            'bed_time_local' => $report->bed_time_local, // TIME型なので "HH:MM:SS" 形式（先頭5文字を使用）
            'wake_time_local' => $report->wake_time_local, // TIME型なので "HH:MM:SS" 形式（先頭5文字を使用）
            'sleep_minutes' => $report->sleep_minutes,
            'sleep_hours_display' => $report->sleep_minutes ? sprintf('%.1f時間', $report->sleep_minutes / 60) : null,
            'mid_awaken_count' => $report->mid_awaken_count,
            'is_early_awaken' => $report->is_early_awaken,
            'is_breakfast_done' => $report->is_breakfast_done,
            'is_bathing_done' => $report->is_bathing_done,
            'is_medication_taken' => $report->is_medication_taken,
            'medication_status' => $this->getMedicationStatusDisplay($report->is_medication_taken),
            'mood_score' => $report->mood_score,
            'sign_good' => $report->sign_good,
            'sign_caution' => $report->sign_caution,
            'sign_bad' => $report->sign_bad,
            'total_signs' => $report->sign_good + $report->sign_caution + $report->sign_bad,
            'note' => $report->note,
        ];
    }

    private function formatEveningReportData($report): array
    {
        return [
            'training_summary' => $report->training_summary,
            'training_reflection' => $report->training_reflection,
            'condition_note' => $report->condition_note,
            'other_note' => $report->other_note,
        ];
    }

    private function getRatingDisplay(int $rating): string
    {
        return match ($rating) {
            3 => '◯',
            2 => '△',
            1 => '✕',
            default => '-',
        };
    }

    private function getMedicationStatusDisplay(?bool $status): string
    {
        return match ($status) {
            true => '済',
            false => '未',
            null => '習慣なし',
        };
    }

    private function determineUserId(Request $request): int
    {
        if ($this->isStaffUser()) {
            // $request->integer() は存在しない場合0を返すため、has()で存在チェックが必要
            return $request->has('user_id') ? $request->integer('user_id') : auth()->guard('staff')->id();
        }
        return auth()->guard('web')->id();
    }

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

        return [Carbon::now()->subDays(29)->format('Y-m-d'), Carbon::now()->format('Y-m-d')];
    }

    private function prepareReportData(Request $request, int $userId, string $type): array
    {
        $data = ['user_id' => $userId, 'report_date' => $request->report_date];

        if ($type === 'morning') {
            $data = array_merge($data, [
                'sleep_rating' => $request->sleep_rating,
                'stress_rating' => $request->stress_rating,
                'meal_rating' => $request->meal_rating,
                'bed_time_local' => $request->bed_time_local,
                'wake_time_local' => $request->wake_time_local,
                'mid_awaken_count' => $request->integer('mid_awaken_count', 0),
                'is_early_awaken' => $request->boolean('is_early_awaken', false),
                'is_breakfast_done' => $request->boolean('is_breakfast_done', false),
                'is_bathing_done' => $request->boolean('is_bathing_done', false),
                'is_medication_taken' => $request->has('is_medication_taken') ? $request->boolean('is_medication_taken') : null,
                'mood_score' => $request->mood_score,
                'sign_good' => $request->integer('sign_good', 0),
                'sign_caution' => $request->integer('sign_caution', 0),
                'sign_bad' => $request->integer('sign_bad', 0),
                'note' => $request->input('note', ''),
                'sleep_minutes' => $this->calculateSleepMinutes($request->bed_time_local, $request->wake_time_local),
            ]);
        } else {
            $data = array_merge($data, [
                'training_summary' => $request->input('training_summary', ''),
                'training_reflection' => $request->input('training_reflection', ''),
                'condition_note' => $request->input('condition_note', ''),
                'other_note' => $request->input('other_note', ''),
            ]);
        }

        return $data;
    }

    private function prepareUpdateData(Request $request, string $type): array
    {
        if ($type === 'morning') {
            $data = $request->only([
                'sleep_rating', 'stress_rating', 'meal_rating', 'bed_time_local', 'wake_time_local',
                'mid_awaken_count', 'is_early_awaken', 'is_breakfast_done', 'is_bathing_done', 'is_medication_taken',
                'mood_score', 'sign_good', 'sign_caution', 'sign_bad', 'note'
            ]);

            if ($request->has('bed_time_local') || $request->has('wake_time_local')) {
                $bedTime = $request->string('bed_time_local');
                $wakeTime = $request->string('wake_time_local');
                if ($bedTime && $wakeTime) {
                    $data['sleep_minutes'] = $this->calculateSleepMinutes($bedTime, $wakeTime);
                }
            }
        } else {
            $data = $request->only(['training_summary', 'training_reflection', 'condition_note', 'other_note']);
        }

        return array_filter($data, fn($value) => $value !== null);
    }

    private function calculateSleepMinutes(string $bedTime, string $wakeTime): ?int
    {
        try {
            $bedCarbon = Carbon::createFromFormat('H:i', $bedTime);
            $wakeCarbon = Carbon::createFromFormat('H:i', $wakeTime);
            if ($wakeCarbon->lt($bedCarbon)) $wakeCarbon->addDay();
            $minutes = $bedCarbon->diffInMinutes($wakeCarbon);
            return ($minutes >= 0 && $minutes <= 960) ? $minutes : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function canEditPastDate(string $date): bool
    {
        return Carbon::parse($date)->gte(Carbon::today()->subDay());
    }

    private function isStaffUser(): bool
    {
        return auth()->guard('staff')->check();
    }

    private function auditLog(string $action, string $entity, ?int $entityId, array $meta = []): void
    {
        try {
            $actorId = $this->isStaffUser() ? auth()->guard('staff')->id() : auth()->guard('web')->id();

            \App\Models\AuditLog::create([
                'actor_type' => $this->isStaffUser() ? 'staff' : 'user',
                'actor_id' => $actorId,
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
            \Log::error('Audit log creation failed', ['action' => $action, 'entity' => $entity, 'error' => $e->getMessage()]);
        }
    }
}