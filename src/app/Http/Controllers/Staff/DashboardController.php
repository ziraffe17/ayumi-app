<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * S-04S: 事業所ダッシュボード
     */
    public function organization(Request $request)
    {
        $users = User::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
        
        return view('staff.dashboards.organization', compact('users'));
    }

    /**
     * S-03S: 個人ダッシュボード（職員用）
     */
    public function personal(Request $request)
    {
        $users = User::where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name']);
        
        $selectedUserId = $request->input('user_id');
        
        return view('staff.dashboards.personal', compact('users', 'selectedUserId'));
    }
}