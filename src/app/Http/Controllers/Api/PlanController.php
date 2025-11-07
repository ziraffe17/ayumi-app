<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendancePlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use App\Services\HolidayService;
use App\Services\AuditService; // 追加（明示的にuse。app()でも可）
use Illuminate\Support\Facades\DB;

class PlanController extends Controller
{
    /**
     * GET /api/plans?user_id=...&month=YYYY-MM
     * GET /api/me/plans?month=YYYY-MM  （利用者本人向けシンタックス）
     */
    public function index(Request $request)
    {
        $isStaff = Auth::guard('staff')->check();
        $isUser  = Auth::guard('web')->check(); // 利用者

        // どのエンドポイントで来たかで user_id の既定を分岐
        $routeName   = optional($request->route())->getName();
        $fromMeRoute = is_string($routeName) && str_contains($routeName, '.user');

        // 入力バリデーション
        $validated = $request->validate([
            'user_id' => [$fromMeRoute ? 'nullable' : 'required', 'integer', 'min:1'],
            'month'   => ['required', 'regex:/^\d{4}\-\d{2}$/'], // YYYY-MM
        ], [
            'month.regex' => 'month は YYYY-MM 形式で指定してください。',
        ]);

        // user_id 解決
        if ($fromMeRoute) {
            // /api/me/plans は利用者自身のみ
            abort_unless($isUser, 401);
            $userId = Auth::guard('web')->id();
        } else {
            // /api/plans は staff 専用
            abort_unless($isStaff, 401);
            $userId = (int)$validated['user_id'];
        }

        // 権限チェック（利用者は自分のみ）
        if ($isUser) {
            abort_unless(Auth::guard('web')->id() === $userId, 403);
        }

        // 期間計算（JST）
        $month = $validated['month']; // YYYY-MM
        $start = Carbon::createFromFormat('Y-m', $month, 'Asia/Tokyo')->startOfMonth()->toDateString();
        $end   = Carbon::createFromFormat('Y-m', $month, 'Asia/Tokyo')->endOfMonth()->toDateString();

        // 取得
        $rows = AttendancePlan::query()
            ->where('user_id', $userId)
            ->whereBetween('plan_date', [$start, $end])
            ->orderBy('plan_date')
            ->orderBy('plan_time_slot')
            ->get([
                'id','user_id','plan_date','plan_time_slot','plan_type',
                'note','is_holiday','holiday_name','template_source',
                'created_at','updated_at'
            ]);

        return response()->json([
            'user_id' => $userId,
            'month'   => $month,
            'count'   => $rows->count(),
            'items'   => $rows->map(fn($r) => [
                'id'             => $r->id,
                'plan_date'      => $r->plan_date->toDateString(),
                'plan_time_slot' => $r->plan_time_slot,
                'plan_type'      => $r->plan_type,
                'note'           => $r->note,
                'is_holiday'     => $r->is_holiday,
                'holiday_name'   => $r->holiday_name,
                'template_source'=> $r->template_source,
                'created_at'     => optional($r->created_at)->toIso8601String(),
                'updated_at'     => optional($r->updated_at)->toIso8601String(),
            ]),
        ]);
    }

    /**
     * POST /api/plans | /api/me/plans
     * body:
     * {
     *   "user_id": 1,              // /me では不要
     *   "month": "YYYY-MM",        // 必須
     *   "mode": "merge|overwrite",
     *   "items": [
     *     {"plan_date":"2025-10-02","plan_time_slot":"am","plan_type":"onsite","note":null},
     *     ...
     *   ]
     * }
     */
    public function store(Request $request)
    {
        $isStaff     = Auth::guard('staff')->check();
        $isUser      = Auth::guard('web')->check();
        $routeName   = optional($request->route())->getName();
        $fromMeRoute = is_string($routeName) && str_contains($routeName, '.user');

        $validated = $request->validate([
            'user_id' => [$fromMeRoute ? 'nullable' : 'required','integer','min:1'],
            'month'   => ['required','regex:/^\d{4}\-\d{2}$/'],
            'mode'    => ['nullable','in:merge,overwrite'],
            'items'   => ['required','array','min:1'],
            'items.*.plan_date'      => ['nullable','date_format:Y-m-d'],
            'items.*.plan_time_slot' => ['nullable','in:am,pm,full'],
            'items.*.plan_type'      => ['nullable','in:onsite,remote,off'],
            'items.*.note'           => ['nullable','string'],
            // 旧キー互換
            'items.*.ymd'            => ['nullable','date_format:Y-m-d'],
            'items.*.slot'           => ['nullable','string'],
        ]);

        $userId = $fromMeRoute
            ? tap(Auth::guard('web')->id(), fn() => abort_unless($isUser, 401))
            : tap((int)$validated['user_id'], fn() => abort_unless($isStaff, 401));
        if ($isUser) { abort_unless(Auth::guard('web')->id() === $userId, 403); }

        [$ok, $resp] = $this->upsertItems($userId, $validated['month'], $validated['items'], $validated['mode'] ?? 'merge');
        if (!$ok) return $resp;

        // ★監査：作成（複数 upsert なので entityId は null）
        $this->audit('plans.store', [
            'user_id' => $userId,
            'count'   => count($validated['items']),
            'mode'    => $validated['mode'] ?? 'merge',
        ], [
            'month' => $validated['month'],
        ], null);

        return response()->json([
            'user_id' => $userId,
            'month'   => $validated['month'],
            'mode'    => $validated['mode'] ?? 'merge',
            'count'   => count($validated['items']),
        ], 201);
    }

    /**
     * PUT/PATCH /api/plans/{id} / /api/me/plans/{id}
     * 変更可能フィールド: plan_date, plan_time_slot, plan_type, note
     * （旧: ymd / slot を受けたら後方互換で正規化）
     */
    public function update(Request $request, int $id)
    {
        $isStaff     = Auth::guard('staff')->check();
        $isUser      = Auth::guard('web')->check();

        // ★ 利用者は更新不可
        if ($isUser) {
            return response()->json([
                'message' => '予定の変更は職員が行います。変更が必要な場合は職員にご相談ください。'
            ], 403);
        }

        // 職員のみ以降の処理を実行
        abort_unless($isStaff, 401);

        $routeName   = optional($request->route())->getName();
        $fromMeRoute = is_string($routeName) && str_contains($routeName, '.user');

        $validated = $request->validate([
            // 新キー
            'plan_date'      => ['nullable','date_format:Y-m-d'],
            'plan_time_slot' => ['nullable','in:am,pm,full'],
            'plan_type'      => ['nullable','in:onsite,remote,off'],
            'note'           => ['nullable','string'],
            // 後方互換
            'ymd'            => ['nullable','date_format:Y-m-d'],
            'slot'           => ['nullable','string'],
        ]);

        $plan = AttendancePlan::query()->findOrFail($id);

        // 権限：利用者は自分のレコードのみ / 職員はOK
        if ($fromMeRoute) {
            abort_unless($isUser, 401);
            abort_unless(Auth::guard('web')->id() === $plan->user_id, 403);
        } else {
            abort_unless($isStaff, 401);
        }

        // 後方互換：旧キー→新キーへマップ（slot=home は remote 相当と解釈）
        $newDate = $validated['plan_date'] ?? $validated['ymd'] ?? $plan->plan_date->toDateString();
        $newSlot = $validated['plan_time_slot'] ?? $validated['slot'] ?? $plan->plan_time_slot;
        $newType = $validated['plan_type'] ?? (
            isset($validated['slot']) && $validated['slot'] === 'home' ? 'remote' : $plan->plan_type
        );
        $newNote = array_key_exists('note', $validated) ? $validated['note'] : $plan->note;

        // ユニーク衝突（(user_id, plan_date, plan_time_slot)）
        $exists = AttendancePlan::query()
            ->where('user_id', $plan->user_id)
            ->where('plan_date', $newDate)
            ->where('plan_time_slot', $newSlot)
            ->where('id', '!=', $plan->id)
            ->exists();
        if ($exists) {
            return response()->json([
                'message'  => '同一 (user_id, plan_date, plan_time_slot) の予定が既に存在します。',
                'conflict' => ['user_id' => $plan->user_id, 'plan_date' => $newDate, 'plan_time_slot' => $newSlot],
            ], 409);
        }

        $plan->plan_date      = $newDate;
        $plan->plan_time_slot = $newSlot;
        $plan->plan_type      = $newType;
        $plan->note           = $newNote;

        try {
            $plan->save();
        } catch (QueryException $e) {
            return response()->json(['message' => '保存に失敗しました', 'error' => $e->getMessage()], 409);
        }

        // ★監査：更新
        $this->audit('plans.update', [
            'id'   => $plan->id,
            'after'=> [
                'plan_date'      => (string)$plan->plan_date,
                'plan_time_slot' => $plan->plan_time_slot,
                'plan_type'      => $plan->plan_type,
                'note'           => $plan->note,
            ],
        ], [], $plan->id);

        return response()->json([
            'id'             => $plan->id,
            'user_id'        => $plan->user_id,
            'plan_date'      => (string)$plan->plan_date,
            'plan_time_slot' => $plan->plan_time_slot,
            'plan_type'      => $plan->plan_type,
            'note'           => $plan->note,
            'updated_at'     => optional($plan->updated_at)->toIso8601String(),
        ]);
    }

    /**
     * DELETE /api/plans/{id} / /api/me/plans/{id}
     */
    public function destroy(Request $request, int $id)
    {
        $isStaff     = Auth::guard('staff')->check();
        $isUser      = Auth::guard('web')->check();
        $routeName   = optional($request->route())->getName();
        $fromMeRoute = is_string($routeName) && str_contains($routeName, '.user');

        $plan = AttendancePlan::query()->findOrFail($id);

        if ($fromMeRoute) {
            abort_unless($isUser, 401);
            abort_unless(Auth::guard('web')->id() === $plan->user_id, 403);
        } else {
            abort_unless($isStaff, 401);
        }

        $plan->delete();

        // ★監査：削除
        $this->audit('plans.delete', [
            'id' => $id,
        ], [], $id);

        return response()->json(['deleted' => true, 'id' => $id]);
    }

    /** 共通: items を保存（mode: merge|overwrite） */
    private function upsertItems(int $userId, string $month, array $items, string $mode = 'merge')
    {
        $start = Carbon::createFromFormat('Y-m', $month, 'Asia/Tokyo')->startOfMonth()->toDateString();
        $end   = Carbon::createFromFormat('Y-m', $month, 'Asia/Tokyo')->endOfMonth()->toDateString();

        // 正規化
        $norm = [];
        foreach ($items as $i) {
            $plan_date      = $i['plan_date'] ?? $i['ymd'] ?? null;
            $plan_time_slot = $i['plan_time_slot'] ?? $i['slot'] ?? null;
            $plan_type      = $i['plan_type'] ?? (isset($i['slot']) && $i['slot'] === 'home' ? 'remote' : 'onsite');
            $note           = $i['note'] ?? null;
            $template_source= $i['template_source'] ?? null;
            if (!$plan_date || !$plan_time_slot) continue;
            $norm[] = compact('plan_date','plan_time_slot','plan_type','note','template_source');
        }

        // 月内範囲チェック
        foreach ($norm as $i) {
            if ($i['plan_date'] < $start || $i['plan_date'] > $end) {
                return [false, response()->json(['message' => "plan_date {$i['plan_date']} は month {$month} の範囲外です。"], 422)];
            }
        }

        // 祝日マップ取得（同月のみ）
        $holidaySvc = app(HolidayService::class);
        $holidayMap = $holidaySvc->mapForMonth($month, 'Asia/Tokyo');

        $now = now();
        DB::beginTransaction();
        try {
            if ($mode === 'overwrite') {
                AttendancePlan::query()
                    ->where('user_id', $userId)
                    ->whereBetween('plan_date', [$start, $end])
                    ->delete();
            }

            // upsert ペイロード作成（祝日注釈も付与）
            $payload = array_map(function ($i) use ($userId, $holidaySvc, $holidayMap, $now) {
                $isHoliday   = $holidaySvc->isHoliday($i['plan_date'], $holidayMap);
                $holidayName = $holidaySvc->nameOf($i['plan_date'], $holidayMap);

                return [
                    'user_id'         => $userId,
                    'plan_date'       => $i['plan_date'],
                    'plan_time_slot'  => $i['plan_time_slot'],
                    'plan_type'       => $i['plan_type'] ?? 'onsite',
                    'note'            => $i['note'] ?? null,
                    'is_holiday'      => $isHoliday ? 1 : 0,
                    'holiday_name'    => $holidayName,
                    'template_source' => $i['template_source'] ?? null,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ];
            }, $norm);

            if (empty($payload)) {
                DB::commit(); // 何もすることがないがトランザクション整合のため
                return [true, null];
            }

            DB::table('attendance_plans')->upsert(
                $payload,
                ['user_id', 'plan_date', 'plan_time_slot'],
                ['plan_type', 'note', 'is_holiday', 'holiday_name', 'template_source', 'updated_at']
            );

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return [false, response()->json(['message' => '保存に失敗しました', 'error' => $e->getMessage()], 500)];
        }
        return [true, null];
    }

    /** テンプレ1: 前月コピー（mode: merge|overwrite, exclude_weekends, exclude_holidays に対応） */
    public function templateCopyPrevious(Request $request)
    {
        $isStaff     = Auth::guard('staff')->check();
        $isUser      = Auth::guard('web')->check();
        $routeName   = optional($request->route())->getName();
        $fromMeRoute = is_string($routeName) && str_contains($routeName, '.user');

        $validated = $request->validate([
            'user_id'          => [$fromMeRoute ? 'nullable' : 'required','integer','min:1'],
            'month'            => ['required','regex:/^\d{4}\-\d{2}$/'], // コピー先
            'mode'             => ['nullable','in:merge,overwrite'],
            'exclude_weekends' => ['nullable','boolean'],
            'exclude_holidays' => ['nullable','boolean'],
        ]);

        $userId = $fromMeRoute
            ? tap(Auth::guard('web')->id(), fn() => abort_unless($isUser, 401))
            : tap((int)$validated['user_id'], fn() => abort_unless($isStaff, 401));
        if ($isUser) { abort_unless(Auth::guard('web')->id() === $userId, 403); }

        $month    = $validated['month'];
        $dstStart = Carbon::createFromFormat('Y-m', $month, 'Asia/Tokyo')->startOfMonth();
        $srcMonth = $dstStart->copy()->subMonth()->format('Y-m');

        $srcStart = Carbon::createFromFormat('Y-m', $srcMonth, 'Asia/Tokyo')->startOfMonth()->toDateString();
        $srcEnd   = Carbon::createFromFormat('Y-m', $srcMonth, 'Asia/Tokyo')->endOfMonth()->toDateString();

        $src = AttendancePlan::query()
            ->where('user_id', $userId)
            ->whereBetween('plan_date', [$srcStart, $srcEnd])
            ->orderBy('plan_date')->orderBy('plan_time_slot')
            ->get(['plan_date','plan_time_slot','plan_type','note']);

        // 祝日マップ（コピー先の月）
        $holidaySvc = app(HolidayService::class);
        $holidayMap = $holidaySvc->mapForMonth($month,'Asia/Tokyo');

        $items = [];
        foreach ($src as $r) {
            $newDate = Carbon::parse($r->plan_date, 'Asia/Tokyo')->addMonth()->toDateString();
            $dow     = Carbon::parse($newDate,'Asia/Tokyo')->dayOfWeekIso; // 1..7

            if (($validated['exclude_weekends'] ?? false) && ($dow >= 6)) continue;

            $isHol = $holidaySvc->isHoliday($newDate,$holidayMap);
            if (($validated['exclude_holidays'] ?? false) && $isHol) continue;

            $items[] = [
                'plan_date'       => $newDate,
                'plan_time_slot'  => $r->plan_time_slot,
                'plan_type'       => $r->plan_type,
                'note'            => $r->note,
                'template_source' => 'prev_month',
            ];
        }

        [$ok, $resp] = $this->upsertItems($userId, $month, $items, $validated['mode'] ?? 'merge');
        if (!$ok) return $resp;

        // ★監査：テンプレ（前月コピー）
        $this->audit('plans.template.copy_previous', [
            'user_id' => $userId,
            'count'   => count($items),
            'mode'    => $validated['mode'] ?? 'merge',
            'exclude_weekends' => (bool)($validated['exclude_weekends'] ?? false),
            'exclude_holidays' => (bool)($validated['exclude_holidays'] ?? false),
        ], [
            'month' => $month,
        ], null);

        return response()->json([
            'user_id' => $userId, 'month' => $month, 'mode' => $validated['mode'] ?? 'merge',
            'count'   => count($items), 'items' => $items
        ], 201);
    }

    /** テンプレ2: 平日一括（weekdays配列で指定・例 [1,2,3,4,5]、plan_time_slot必須） */
    public function templateWeekdayBulk(Request $request)
    {
        $isStaff     = Auth::guard('staff')->check();
        $isUser      = Auth::guard('web')->check();
        $routeName   = optional($request->route())->getName();
        $fromMeRoute = is_string($routeName) && str_contains($routeName, '.user');

        $validated = $request->validate([
            'user_id'            => [$fromMeRoute ? 'nullable' : 'required','integer','min:1'],
            'month'              => ['required','regex:/^\d{4}\-\d{2}$/'],
            'plan_time_slot'     => ['required','in:am,pm,full'],
            'plan_type'          => ['nullable','in:onsite,remote,off'],
            'weekdays'           => ['required','array'],
            'weekdays.*'         => ['integer','between:1,7'],
            'mode'               => ['nullable','in:merge,overwrite'],
            'exclude_holidays'   => ['nullable','boolean'],
        ]);

        $userId = $fromMeRoute
            ? tap(Auth::guard('web')->id(), fn() => abort_unless($isUser, 401))
            : tap((int)$validated['user_id'], fn() => abort_unless($isStaff, 401));
        if ($isUser) { abort_unless(Auth::guard('web')->id() === $userId, 403); }

        $month = $validated['month'];
        $slot  = $validated['plan_time_slot'];
        $type  = $validated['plan_type'] ?? 'onsite';
        $want  = array_map('intval', $validated['weekdays']);

        $start = Carbon::createFromFormat('Y-m', $month, 'Asia/Tokyo')->startOfMonth();
        $end   = $start->copy()->endOfMonth();

        $holidaySvc = app(HolidayService::class);
        $holidayMap = $holidaySvc->mapForMonth($month, 'Asia/Tokyo');
        $excludeHol = (bool)($validated['exclude_holidays'] ?? false);

        $items = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $dow = $d->dayOfWeekIso; // 1..7
            if (!in_array($dow, $want, true)) continue;

            $date = $d->toDateString();
            if ($excludeHol && $holidaySvc->isHoliday($date, $holidayMap)) continue;

            $items[] = [
                'plan_date'       => $date,
                'plan_time_slot'  => $slot,
                'plan_type'       => $type,
                'note'            => null,
                'template_source' => 'weekday',
            ];
        }

        [$ok, $resp] = $this->upsertItems($userId, $month, $items, $validated['mode'] ?? 'merge');
        if (!$ok) return $resp;

        // ★監査：テンプレ（平日一括）
        $this->audit('plans.template.weekday_bulk', [
            'user_id' => $userId,
            'count'   => count($items),
            'mode'    => $validated['mode'] ?? 'merge',
            'slot'    => $slot,
            'type'    => $type,
            'weekdays'=> $want,
            'exclude_holidays' => $excludeHol,
        ], [
            'month' => $month,
        ], null);

        return response()->json([
            'user_id' => $userId,
            'month'   => $month,
            'mode'    => $validated['mode'] ?? 'merge',
            'count'   => count($items),
            'items'   => $items
        ], 201);
    }

    /** 監査のヘルパ（アクション名は AuditService 側で normalize されます） */
    private function audit(string $action, array $diff = [], array $meta = [], ?int $entityId = null): void
    {
        try {
            app(AuditService::class)->record(
                $action,
                $diff ?? [],
                $meta ?? [],
                $entityId,
                'attendance_plans'
            );
        } catch (\Throwable $e) {
            // 監査失敗は本処理を妨げない（ログ等に落としたい場合はここで処理）
        }
    }
}
