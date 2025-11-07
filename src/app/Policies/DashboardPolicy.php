<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Staff;
use Illuminate\Auth\Access\HandlesAuthorization;

class DashboardPolicy
{
    use HandlesAuthorization;

    /**
     * 事業所ダッシュボードの閲覧権限
     * 職員・管理者のみ可能
     */
    public function viewFacilityDashboard($user): bool
    {
        return $user instanceof Staff;
    }

    /**
     * 集計データのCSV出力権限
     * 職員・管理者のみ可能
     */
    public function exportCsv($user): bool
    {
        return $user instanceof Staff;
    }

    /**
     * アラート情報の閲覧権限
     * 職員・管理者のみ可能
     */
    public function viewAlerts($user): bool
    {
        return $user instanceof Staff;
    }

    /**
     * 監査ログの閲覧権限
     * 管理者のみ可能
     */
    public function viewAuditLogs($user): bool
    {
        return $user instanceof Staff && $user->role === 'admin';
    }
}