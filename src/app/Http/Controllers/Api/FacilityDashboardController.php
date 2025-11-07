<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class FacilityDashboardController extends Controller
{
    protected DashboardService $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * 事業所ダッシュボード
     * GET /api/dashboard/facility
     */
    public function index(Request $request): JsonResponse
    {
        if (!auth()->guard('staff')->check()) {
            return response()->json([
                'success' => false,
                'message' => 'アクセス権限がありません',
            ], 403);
        }

        $request->validate([
            'period' => 'sometimes|in:current,last,custom',
            'status' => 'sometimes|in:all,active',
            'start_date' => 'required_if:period,custom|date_format:Y-m-d',
            'end_date' => 'required_if:period,custom|date_format:Y-m-d|after_or_equal:start_date',
        ]);

        try {
            $filters = [
                'period' => $request->input('period', 'current'),
                'status' => $request->input('status', 'all'),
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date'),
            ];

            $data = $this->dashboardService->getFacilityDashboard($filters);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);

        } catch (\Exception $e) {
            \Log::error('Facility dashboard error', [
                'filters' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'データの取得に失敗しました',
            ], 500);
        }
    }

    /**
     * アラート情報
     * GET /api/dashboard/alerts
     */
    public function alerts(Request $request): JsonResponse
    {
        if (!auth()->guard('staff')->check()) {
            return response()->json([
                'success' => false,
                'message' => 'アクセス権限がありません',
            ], 403);
        }

        try {
            $users = \App\Models\User::where('is_active', true)->pluck('id')->toArray();
            
            $startDate = now()->startOfMonth()->format('Y-m-d');
            $endDate = now()->endOfMonth()->format('Y-m-d');

            $alerts = $this->dashboardService->getAlertSummary($users, $startDate, $endDate);

            return response()->json([
                'success' => true,
                'data' => $alerts,
            ]);

        } catch (\Exception $e) {
            \Log::error('Alerts error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'アラート取得に失敗しました',
            ], 500);
        }
    }
}