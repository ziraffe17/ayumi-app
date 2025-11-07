<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAuditLogs');

        $today  = now()->toDateString();
        $start  = now()->subDays(30)->toDateString();
        $limit  = 50;
        $page   = 1; // 初期表示
        $total  = 0;

        // ★ 空のPaginatorを作って渡す（Bladeの ->links() が動く）
        $logs = new LengthAwarePaginator(
            Collection::make([]), // items
            $total,               // total
            $limit,               // per page
            $page,                // current page
            ['path' => route('staff.audit-logs.index')]
        );

        return view('staff.audit-logs.index', [
            'logs' => $logs,
            'period' => ['start' => $start, 'end' => $today],
            'pagination' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => 0,
                'has_more' => false,
                'current_page' => $page,
                'total_pages' => 1,
            ],
            'statistics' => [
                'summary' => [
                    'total_logs' => 0,
                    'user_logs' => 0,
                    'staff_logs' => 0,
                    'system_logs' => 0,
                    'login_attempts' => 0,
                    'failed_logins' => 0,
                    'success_rate' => 100,
                ],
                'timeline' => [],
                'top_actions' => [],
                'top_entities' => [],
                'security_events' => [],
            ],
        ]);
    }
}
