<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserManagementController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();

        // 検索フィルタ
        if ($request->filled('q')) {
            $query->where('name', 'like', '%' . $request->q . '%');
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status);
        }

        $users = $query->paginate(20);

        return view('staff.users.index', compact('users'));
    }

    public function show(User $user)
    {
        // 今月の状況
        $currentMonth = [
            'planned' => 20,
            'actual' => 17,
            'rate' => 85.0,
            'report_rate' => 80.0,
        ];

        /*/ 最近の面談記録
        $recentInterviews = $user->interviews()
            ->with('staff')
            ->latest('interview_date')
            ->limit(5)
            ->get();
            */
        $recentInterviews = []; // ダミー

        return view('staff.users.show', compact('user', 'currentMonth', 'recentInterviews'));
    }

    public function create()
    {
        return view('staff.users.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'login_code' => 'required|string|unique:users,login_code',
            'email' => 'nullable|email|unique:users,email',
            'password' => 'required|string|min:8',
            'start_date' => 'required|date',
        ]);

        $user = User::create($validated);

        return redirect()->route('staff.users.index')
            ->with('success', '利用者を登録しました');
    }

    public function edit(User $user)
    {
        return view('staff.users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:users,email,' . $user->id,
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'is_active' => 'boolean',
        ]);

        $user->update($validated);

        return redirect()->route('staff.users.show', $user)
            ->with('success', '利用者情報を更新しました');
    }
}