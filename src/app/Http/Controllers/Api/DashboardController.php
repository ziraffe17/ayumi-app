<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    protected DashboardService $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * 個人ダッシュボードデータ取得
     * GET /api/dashboard/personal?user_id=1&period=current_month&month=2025-10 (職員用)
     * GET /api/me/dashboard?period=current_month&month=2025-10 (利用者用)
     */
    public function personal(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'sometimes|integer|exists:users,id',
            'period' => 'sometimes|in:current_month,recent_3months,all,specific_month',
            'month' => 'sometimes|date_format:Y-m',
        ]);

        try {
            $periodType = $request->input('period', 'current_month');
            $month = $request->input('month'); // カレンダー表示月（オプション）

            // 利用者認証の場合は自分のIDを使用
            if (auth()->guard('web')->check()) {
                $userId = auth()->guard('web')->id();
            }
            // 職員認証の場合はuser_idパラメータを使用
            elseif (auth()->guard('staff')->check()) {
                $userId = $request->integer('user_id');
                if (!$userId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'user_idパラメータが必要です',
                    ], 400);
                }
            }
            else {
                return response()->json([
                    'success' => false,
                    'message' => '認証が必要です',
                ], 401);
            }

            $data = $this->dashboardService->getPersonalDashboard($userId, $periodType, $month);
            $user = User::find($userId);
            $data['user_name'] = $user->name;

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);

        } catch (\Exception $e) {
            \Log::error('Personal dashboard error', [
                'user_id' => $request->input('user_id'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'ダッシュボードデータの取得に失敗しました',
            ], 500);
        }
    }

    /**
     * 事業所ダッシュボードデータ取得
     * GET /api/dashboard/facility?period=current&status=all
     */
    public function facility(Request $request): JsonResponse
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
                'message' => '事業所ダッシュボードデータの取得に失敗しました',
            ], 500);
        }
    }

    /**
     * アラート情報取得
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
            $users = User::where('is_active', true)->pluck('id')->toArray();
            
            $startDate = now()->startOfMonth()->format('Y-m-d');
            $endDate = now()->endOfMonth()->format('Y-m-d');

            $alerts = $this->dashboardService->getAlertSummary($users, $startDate, $endDate);

            return response()->json([
                'success' => true,
                'data' => $alerts,
            ]);

        } catch (\Exception $e) {
            \Log::error('Alerts fetch error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'アラート情報の取得に失敗しました',
            ], 500);
        }
    }
}