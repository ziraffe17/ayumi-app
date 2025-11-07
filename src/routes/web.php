<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\UserLoginController;
use App\Http\Controllers\StaffEmailTwoFactorController;

// Staff画面用コントローラ
use App\Http\Controllers\Staff\HomeController;
use App\Http\Controllers\Staff\DashboardController;
use App\Http\Controllers\Staff\UserManagementController;
use App\Http\Controllers\Staff\InterviewController;
use App\Http\Controllers\Staff\SettingsController;
use App\Http\Controllers\Staff\AuditLogController;
use App\Http\Controllers\Staff\ExportController;

// Fortify (職員アカウントのPWリセット系)
use Laravel\Fortify\Http\Controllers\PasswordResetLinkController;
use Laravel\Fortify\Http\Controllers\NewPasswordController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

/**
 * 公開ページ
 */
Route::get('/', fn () => view('welcome'));

/**
 * 利用者(User)認証
 */
Route::middleware(['web', 'guest'])->group(function () {
    Route::get('/login', [UserLoginController::class, 'showLoginForm'])->name('user.login');
    Route::post('/login', [UserLoginController::class, 'login'])->middleware('throttle:login');
});

Route::middleware(['web', 'auth:web'])->group(function () {
    Route::post('/u/logout', [UserLoginController::class, 'logout'])->name('user.logout');

    // S-02U: 利用者ホーム
    Route::get('/user/home', fn () => view('user.home'))->name('user.home');

    // S-03U: 個人ダッシュボード
    Route::get('/user/dashboard', [\App\Http\Controllers\User\DashboardController::class, 'index'])
        ->name('user.dashboard');
    // S-04U: 月次出席予定
    Route::get('/user/plans/monthly', [\App\Http\Controllers\User\PlanController::class, 'monthly'])
        ->name('user.plans.monthly');
    Route::post('/user/plans/monthly', [\App\Http\Controllers\User\PlanController::class, 'saveMonthly']);
    Route::patch('/user/plans/monthly', [\App\Http\Controllers\User\PlanController::class, 'updateSingle']);

    // S-07U: 日報入力
    Route::get('/user/reports/daily', [\App\Http\Controllers\User\ReportController::class, 'daily'])
        ->name('user.reports.daily');

    // 利用者設定
    Route::prefix('user/settings')->name('user.settings.')->group(function () {
        Route::get('/', [\App\Http\Controllers\User\SettingsController::class, 'index'])->name('index');
        Route::post('/password', [\App\Http\Controllers\User\SettingsController::class, 'updatePassword'])->name('password');
        Route::post('/profile', [\App\Http\Controllers\User\SettingsController::class, 'updateProfile'])->name('profile');
    });
});

/**
 * 職員(Staff)認証（Fortify使用）
 * - Fortifyのhome設定は /staff/home（config/fortify.phpに合わせる）
 * - Fortifyのprefixを's'に設定しているため、ログインは /s/login
 */
Route::get('/staff/login', fn () => redirect('/s/login'))->name('staff.login');

// Fortifyの /home 互換用
Route::get('/home', fn () => redirect('/staff/home'));

// 職員用 パスワードリセット（guest:staff）
Route::middleware(['web', 'guest:staff'])->group(function () {
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.update');
});

// 職員ログアウト
Route::post('/staff/logout', function (Request $request) {
    Auth::guard('staff')->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect('/s/login');
})->middleware(['web', 'auth:staff'])->name('staff.logout');

/**
 * メール2FA(職員専用)
 */
Route::prefix('staff')->middleware(['web', 'auth:staff'])->group(function () {
    Route::get('/two-factor', [StaffEmailTwoFactorController::class, 'show'])->name('staff.2fa.email.show');
    Route::post('/two-factor/verify', [StaffEmailTwoFactorController::class, 'verify'])
        ->name('staff.2fa.email.verify')->middleware('throttle:two-factor-email');
    Route::post('/two-factor/resend', [StaffEmailTwoFactorController::class, 'resend'])
        ->name('staff.2fa.email.resend')->middleware('throttle:two-factor-email');
    Route::post('/two-factor/cancel', [StaffEmailTwoFactorController::class, 'cancel'])->name('staff.2fa.email.cancel');
});

/**
 * 職員保護ページ（email2fa 必須）
 * Fortifyの home=/staff/home に合わせた実画面を用意
 */
Route::middleware(['web', 'auth:staff', 'email2fa'])
    ->prefix('staff')->name('staff.')->group(function () {

    // ★ Fortifyのhome遷移先：/staff/home
    Route::get('/home', [HomeController::class, 'index'])->name('home');

    // ダッシュボード（画面用）
    Route::prefix('dashboards')->name('dashboards.')->group(function () {
        Route::get('/organization', [DashboardController::class, 'organization'])->name('organization');
        Route::get('/personal', [DashboardController::class, 'personal'])->name('personal');
    });

    // 予定・実績
    Route::prefix('plans')->name('plans.')->group(function () {
        // S-05S: 月次出席予定
        Route::get('/monthly', [\App\Http\Controllers\Staff\PlanController::class, 'monthly'])->name('monthly');
    });

    Route::prefix('attendance')->name('attendance.')->group(function () {
        // S-06S: 出席管理
        Route::get('/manage', [\App\Http\Controllers\Staff\AttendanceController::class, 'manage'])->name('manage');
        Route::get('/monthly-overview', [\App\Http\Controllers\Staff\AttendanceController::class, 'monthlyOverview'])->name('monthly-overview');
    });

    Route::prefix('reports')->name('reports.')->group(function () {
        // S-07S: 日報確認
        Route::get('/daily', fn () => view('staff.reports.daily'))->name('daily');
    });

    // 利用者管理
    Route::prefix('users')->name('users.')->group(function () {
        // S-08: 利用者一覧
        Route::get('/', [\App\Http\Controllers\Staff\UserController::class, 'index'])->name('index');
        // 作成
        Route::get('/create', [\App\Http\Controllers\Staff\UserController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\Staff\UserController::class, 'store'])->name('store');
        // 詳細
        Route::get('/{user}', [\App\Http\Controllers\Staff\UserController::class, 'show'])->name('show');
        // 編集
        Route::get('/{user}/edit', [\App\Http\Controllers\Staff\UserController::class, 'edit'])->name('edit');
        Route::put('/{user}', [\App\Http\Controllers\Staff\UserController::class, 'update'])->name('update');
        // 削除（無効化）
        Route::delete('/{user}', [\App\Http\Controllers\Staff\UserController::class, 'destroy'])->name('destroy');
        // パスワードリセット
        Route::post('/{user}/reset-password', [\App\Http\Controllers\Staff\UserController::class, 'resetPassword'])->name('reset-password');
    });

    // 面談記録
    Route::prefix('interviews')->name('interviews.')->group(function () {
        Route::get('/', [InterviewController::class, 'index'])->name('index');
        Route::post('/', [InterviewController::class, 'store'])->name('store');
        Route::put('/{interview}', [InterviewController::class, 'update'])->name('update');
        Route::delete('/{interview}', [InterviewController::class, 'destroy'])->name('destroy');
    });

    // CSV出力
    Route::prefix('export')->name('export.')->group(function () {
        Route::get('/csv', [ExportController::class, 'index'])->name('csv');
        Route::get('/download', [ExportController::class, 'download'])->name('download');
    });

    // 設定（管理者のみ）
    Route::prefix('settings')->name('settings.')->middleware('admin')->group(function () {
        Route::get('/', [SettingsController::class, 'index'])->name('index');
        Route::post('/add-holiday', [SettingsController::class, 'addHoliday'])->name('add-holiday');
        Route::post('/import-holidays', [SettingsController::class, 'importHolidays'])->name('import-holidays');
        Route::post('/import-from-api', [SettingsController::class, 'importFromApi'])->name('import-from-api');
        Route::post('/cleanup-holidays', [SettingsController::class, 'cleanupHolidays'])->name('cleanup-holidays');
        Route::delete('/delete-holiday/{date}', [SettingsController::class, 'deleteHoliday'])->name('delete-holiday');
        Route::post('/update-facility', [SettingsController::class, 'updateFacility'])->name('update-facility');
        Route::post('/update-organization', [SettingsController::class, 'updateOrganization'])->name('update-organization');
        Route::post('/update-system', [SettingsController::class, 'updateSystem'])->name('update-system');
        Route::post('/backup', [SettingsController::class, 'backup'])->name('backup');
    });

    // 監査ログ
    Route::prefix('audit-logs')->name('audit-logs.')->group(function () {
        // 画面
        Route::get('/', [\App\Http\Controllers\Staff\AuditLogController::class, 'index'])
            ->name('index')                   // staff.audit-logs.index
            ->middleware('can:viewAuditLogs');
    });
});

/**
 * 開発用: バリデーションテスト
 */
Route::post('/validate-test', function (Request $request) {
    $validated = $request->validate(['name' => 'required|string|min:3']);
    return response()->json(['message' => 'バリデーション成功', 'data' => $validated]);
})->middleware('web');
