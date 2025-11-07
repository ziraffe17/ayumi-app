<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlanController extends Controller
{
    /**
     * S-05S: 月次出席予定画面
     * GET /staff/plans/monthly
     */
    public function monthly(Request $request): View
    {
        // アクティブな利用者一覧を取得
        $users = User::where('is_active', true)
            ->orderBy('name', 'asc')
            ->get(['id', 'name']);

        return view('staff.plans.monthly', [
            'users' => $users,
        ]);
    }
}
