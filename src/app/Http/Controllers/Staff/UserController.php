<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Interview;
use App\Models\AttendancePlan;
use App\Models\AttendanceRecord;
use App\Models\DailyReportMorning;
use App\Models\DailyReportEvening;
use App\Services\KpiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class UserController extends Controller
{
    protected KpiService $kpiService;

    public function __construct(KpiService $kpiService)
    {
        $this->kpiService = $kpiService;
    }

    /**
     * S-08 利用者一覧
     */
    public function index(Request $request)
    {
        $query = User::query();

        // 検索条件
        if ($request->filled('q')) {
            $query->where('name', 'like', '%' . $request->q . '%');
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status);
        }

        $users = $query->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('staff.users.index', compact('users'));
    }

    /**
     * 利用者詳細
     */
    public function show(User $user)
    {
        // 今月のKPI取得
        $currentMonthStart = Carbon::now()->startOfMonth()->format('Y-m-d');
        $currentMonthEnd = Carbon::now()->endOfMonth()->format('Y-m-d');
        
        $kpi = $this->kpiService->calculatePersonalKpi($user->id, $currentMonthStart, $currentMonthEnd);
        
        $currentMonth = [
            'planned' => $kpi['attendance']['planned_days'],
            'actual' => $kpi['attendance']['attended_days'],
            'rate' => $kpi['attendance']['attendance_rate_display'],
            'report_rate' => $kpi['reports']['report_rate_display'],
        ];

        // 最近の面談記録（直近3件）
        $recentInterviews = Interview::where('user_id', $user->id)
            ->with('staff:id,name')
            ->orderBy('interview_at', 'desc')
            ->limit(3)
            ->get();

        return view('staff.users.show', compact('user', 'currentMonth', 'recentInterviews'));
    }

    /**
     * 利用者新規作成フォーム
     */
    public function create()
    {
        // 次の利用可能なログインコードを取得
        $lastUser = User::where('login_code', 'LIKE', 'u%')
            ->orderByRaw('CAST(SUBSTRING(login_code, 2) AS UNSIGNED) DESC')
            ->first();

        if ($lastUser && preg_match('/^u(\d+)$/', $lastUser->login_code, $matches)) {
            $nextNumber = intval($matches[1]) + 1;
        } else {
            $nextNumber = 1;
        }

        $nextLoginCode = str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

        return view('staff.users.create', compact('nextLoginCode'));
    }

    /**
     * 利用者新規作成処理
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'name_kana' => 'nullable|string|max:100',
            'login_code' => 'required|string|regex:/^u\d{4}$/|unique:users,login_code',
            'password' => 'required|string|min:6',
            'email' => 'nullable|email|max:255|unique:users,email',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'care_notes_enc' => 'nullable|string|max:2000',
            'is_active' => 'required|boolean',
        ], [
            'login_code.regex' => 'ログインコードは u0001 のような形式で入力してください（u + 4桁の数字）',
        ]);

        try {
            DB::beginTransaction();

            $user = User::create([
                'name' => $request->name,
                'name_kana' => $request->name_kana,
                'login_code' => $request->login_code,
                'password' => Hash::make($request->password),
                'email' => $request->email,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'care_notes_enc' => $request->care_notes_enc,
                'is_active' => $request->is_active,
                'created_by' => auth()->guard('staff')->id(),
                'updated_by' => auth()->guard('staff')->id(),
            ]);

            // 監査ログ記録
            \App\Models\AuditLog::create([
                'actor_type' => 'staff',
                'actor_id' => auth()->guard('staff')->id(),
                'occurred_at' => now(),
                'action' => 'create',
                'entity' => 'users',
                'entity_id' => $user->id,
                'diff_json' => null,
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'meta' => json_encode(['user_name' => $user->name]),
            ]);

            DB::commit();

            return redirect()
                ->route('staff.users.show', $user)
                ->with('success', '利用者を登録しました');

        } catch (\Exception $e) {
            DB::rollBack();
            
            \Log::error('User creation failed', [
                'request' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return back()
                ->withInput()
                ->withErrors(['error' => '利用者の登録に失敗しました']);
        }
    }

    /**
     * 利用者編集フォーム
     */
    public function edit(User $user)
    {
        return view('staff.users.edit', compact('user'));
    }

    /**
     * 利用者情報更新処理
     */
    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'name_kana' => 'nullable|string|max:100',
            'login_code' => 'required|string|regex:/^u\d{4}$/|unique:users,login_code,' . $user->id,
            'password' => 'nullable|string|min:6',
            'email' => 'nullable|email|max:255|unique:users,email,' . $user->id,
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'care_notes_enc' => 'nullable|string|max:2000',
            'is_active' => 'required|boolean',
        ], [
            'login_code.regex' => 'ログインコードは u0001 のような形式で入力してください（u + 4桁の数字）',
        ]);

        try {
            DB::beginTransaction();

            $oldData = $user->toArray();
            
            $updateData = [
                'name' => $request->name,
                'name_kana' => $request->name_kana,
                'login_code' => $request->login_code,
                'email' => $request->email,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'care_notes_enc' => $request->care_notes_enc,
                'is_active' => $request->is_active,
                'updated_by' => auth()->guard('staff')->id(),
            ];

            // パスワードが入力された場合のみ更新
            if ($request->filled('password')) {
                $updateData['password'] = Hash::make($request->password);
            }

            $user->update($updateData);

            // 監査ログ記録
            \App\Models\AuditLog::create([
                'actor_type' => 'staff',
                'actor_id' => auth()->guard('staff')->id(),
                'occurred_at' => now(),
                'action' => 'update',
                'entity' => 'users',
                'entity_id' => $user->id,
                'diff_json' => json_encode([
                    'before' => $oldData,
                    'after' => $user->fresh()->toArray()
                ]),
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'meta' => json_encode(['user_name' => $user->name]),
            ]);

            DB::commit();

            return redirect()
                ->route('staff.users.show', $user)
                ->with('success', '利用者情報を更新しました');

        } catch (\Exception $e) {
            DB::rollBack();
            
            \Log::error('User update failed', [
                'user_id' => $user->id,
                'request' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return back()
                ->withInput()
                ->withErrors(['error' => '利用者情報の更新に失敗しました']);
        }
    }

    /**
     * 利用者の無効化（論理削除）
     */
    public function destroy(User $user)
    {
        try {
            DB::beginTransaction();

            $oldData = $user->toArray();
            
            $user->update([
                'is_active' => false,
                'end_date' => now()->format('Y-m-d'),
                'updated_by' => auth()->guard('staff')->id(),
            ]);

            // 監査ログ記録
            \App\Models\AuditLog::create([
                'actor_type' => 'staff',
                'actor_id' => auth()->guard('staff')->id(),
                'occurred_at' => now(),
                'action' => 'update',  // deactivate → update に変更
                'entity' => 'users',
                'entity_id' => $user->id,
                'diff_json' => json_encode([
                    'before' => $oldData,
                    'after' => $user->fresh()->toArray()
                ]),
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'meta' => json_encode(['user_name' => $user->name, 'action_type' => 'deactivate']),
            ]);

            DB::commit();

            return redirect()
                ->route('staff.users.index')
                ->with('success', '利用者を無効化しました');

        } catch (\Exception $e) {
            DB::rollBack();
            
            \Log::error('User deactivation failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return back()
                ->withErrors(['error' => '利用者の無効化に失敗しました']);
        }
    }

    /**
     * 利用者のパスワードリセット
     */
    public function resetPassword(User $user)
    {
        try {
            DB::beginTransaction();

            // 6桁のランダムパスワード生成
            $newPassword = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            
            $user->update([
                'password' => Hash::make($newPassword),
                'updated_by' => auth()->guard('staff')->id(),
            ]);

            // 監査ログ記録
            \App\Models\AuditLog::create([
                'actor_type' => 'staff',
                'actor_id' => auth()->guard('staff')->id(),
                'occurred_at' => now(),
                'action' => 'password_reset',
                'entity' => 'users',
                'entity_id' => $user->id,
                'diff_json' => null,
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'meta' => json_encode(['user_name' => $user->name]),
            ]);

            DB::commit();

            return back()->with('success', "パスワードをリセットしました。新しいパスワード: {$newPassword}");

        } catch (\Exception $e) {
            DB::rollBack();
            
            \Log::error('Password reset failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'パスワードリセットに失敗しました']);
        }
    }
}