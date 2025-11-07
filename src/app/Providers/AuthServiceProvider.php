<?php

namespace App\Providers;

use App\Models\User;
use App\Models\Staff;
use App\Models\AttendancePlan;
use App\Models\AttendanceRecord;
use App\Models\DailyReportMorning;
use App\Models\DailyReportEvening;
use App\Models\Interview;
use App\Policies\UserPolicy;
use App\Policies\AttendancePlanPolicy;
use App\Policies\AttendanceRecordPolicy;
use App\Policies\DailyReportPolicy;
use App\Policies\InterviewPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        User::class                => UserPolicy::class,
        AttendancePlan::class      => AttendancePlanPolicy::class,
        AttendanceRecord::class    => AttendanceRecordPolicy::class,
        DailyReportMorning::class  => DailyReportPolicy::class,
        DailyReportEvening::class  => DailyReportPolicy::class,
        Interview::class           => InterviewPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        // 管理者は全許可
        Gate::before(function ($user, string $ability) {
            if ($user instanceof \App\Models\Staff) {
                $isAdmin = (($user->role ?? null) === 'admin') || (bool)($user->is_admin ?? false);
                if ($isAdmin) return true;
            }
            return null;
        });

        // 便利Gate: 管理者判定
        Gate::define('is-admin', function ($user): bool {
            return $user instanceof Staff && $user->role === 'admin';
        });

        // ダッシュボード関連
        Gate::define('viewFacilityDashboard', function ($user): bool {
            return $user instanceof Staff;
        });

        Gate::define('exportCsv', function ($user): bool {
            return $user instanceof Staff;
        });

        Gate::define('viewAlerts', function ($user): bool {
            return $user instanceof Staff;
        });

        Gate::define('viewAuditLogs', function ($user): bool {
            return $user instanceof Staff && $user->role === 'admin';
        });

        // 利用者管理
        Gate::define('manageUsers', function ($user): bool {
            return $user instanceof Staff;
        });

        // 出席管理（利用者も自分の実績は操作可能）
        Gate::define('manageAttendance', function ($user, User $targetUser = null): bool {
            if ($user instanceof Staff) {
                return true;
            }
            
            if ($user instanceof User && $targetUser) {
                return $user->id === $targetUser->id;
            }
            
            return false;
        });

        // 日報管理（利用者は自分のみ、職員は全員）
        Gate::define('manageReports', function ($user, User $targetUser = null): bool {
            if ($user instanceof Staff) {
                return true;
            }
            
            if ($user instanceof User && $targetUser) {
                return $user->id === $targetUser->id;
            }
            
            return false;
        });

        // 面談記録管理（職員のみ）
        Gate::define('manageInterviews', function ($user): bool {
            return $user instanceof Staff;
        });

        // 設定管理（管理者のみ）
        Gate::define('manageSettings', function ($user): bool {
            return $user instanceof Staff && $user->role === 'admin';
        });

        // 祝日管理（管理者のみ）
        Gate::define('manageHolidays', function ($user): bool {
            return $user instanceof Staff && $user->role === 'admin';
        });

        // 職員管理（管理者のみ）
        Gate::define('manageStaffs', function ($user): bool {
            return $user instanceof Staff && $user->role === 'admin';
        });

        // システム設定（管理者のみ）
        Gate::define('manageSystem', function ($user): bool {
            return $user instanceof Staff && $user->role === 'admin';
        });

        // 個人データ閲覧（利用者は自分のみ、職員は全員）
        Gate::define('view', function ($user, User $targetUser): bool {
            if ($user instanceof Staff) {
                return true;
            }
            
            if ($user instanceof User) {
                return $user->id === $targetUser->id;
            }
            
            return false;
        });

        // 個人データ編集（職員のみ）
        Gate::define('editPersonalData', function ($user, User $targetUser): bool {
            return $user instanceof Staff;
        });

        // 暗号化データ閲覧（職員のみ）
        Gate::define('viewEncryptedData', function ($user): bool {
            return $user instanceof Staff;
        });
    }
}