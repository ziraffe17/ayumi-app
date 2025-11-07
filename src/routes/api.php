<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\DailyReportController;
use App\Http\Controllers\Api\HolidayController;
use App\Http\Controllers\Api\CsvExportController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FacilityDashboardController;
use App\Http\Controllers\Api\AuditLogController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// ヘルスチェック
Route::get('/health', function () {
    return response()->json([
        'status' => 'OK',
        'timestamp' => now()->toISOString(),
        'version' => config('app.version', '1.0.0'),
    ]);
})->name('api.health');

/**
 * 祝日API(参照系は認証不要)
 */
Route::get('/holidays', [HolidayController::class, 'index'])->name('api.holidays.index');
Route::get('/holidays/{date}', [HolidayController::class, 'show'])
    ->where('date', '\d{4}-\d{2}-\d{2}')
    ->name('api.holidays.show');

/**
 * 職員(Staff)専用API
 */
Route::middleware(['web', 'auth:staff', 'email2fa'])->group(function () {
    
    // 出席予定管理
    Route::prefix('plans')->name('api.plans.')->group(function () {
        Route::get('/', [PlanController::class, 'index'])->name('index.staff');
        Route::post('/', [PlanController::class, 'store'])->name('store.staff');
        Route::match(['put', 'patch'], '/{id}', [PlanController::class, 'update'])
            ->whereNumber('id')->name('update.staff');
        Route::delete('/{id}', [PlanController::class, 'destroy'])
            ->whereNumber('id')->name('destroy.staff');
        Route::post('/template/copy-previous', [PlanController::class, 'templateCopyPrevious'])->name('tpl.copyPrev.staff');
        Route::post('/template/weekday-bulk', [PlanController::class, 'templateWeekdayBulk'])->name('tpl.weekday.staff');
    });
    
    // 出席実績管理
    Route::prefix('attendance')->name('api.attendance.')->group(function () {
        Route::get('/records', [AttendanceController::class, 'index'])->name('records.index.staff');
        Route::post('/records', [AttendanceController::class, 'store'])->name('records.store.staff');
        Route::match(['put', 'patch'], '/records/{id}', [AttendanceController::class, 'update'])
            ->whereNumber('id')->name('records.update.staff');
        Route::delete('/records/{id}', [AttendanceController::class, 'destroy'])
            ->whereNumber('id')->name('records.destroy.staff');
        Route::get('/comparison', [AttendanceController::class, 'comparison'])->name('comparison.staff');
        
        // 承認・ロック機能
        Route::post('/records/{id}/approve', [AttendanceController::class, 'approve'])
            ->whereNumber('id')->name('records.approve.staff');
        Route::post('/records/{id}/lock', [AttendanceController::class, 'lock'])
            ->whereNumber('id')->name('records.lock.staff');
    });
    
    // 日報管理
    Route::prefix('reports')->name('api.reports.')->group(function () {
        Route::get('/morning', [DailyReportController::class, 'indexMorning'])->name('morning.index.staff');
        Route::get('/evening', [DailyReportController::class, 'indexEvening'])->name('evening.index.staff');
        Route::get('/list', [DailyReportController::class, 'list'])->name('list.staff');
    });
    
    // ★ ダッシュボードAPI（追加）
    Route::prefix('dashboard')->name('api.dashboard.')->group(function () {
        Route::get('/personal', [DashboardController::class, 'personal'])->name('personal.staff');
        Route::get('/facility', [FacilityDashboardController::class, 'index'])->name('facility.staff');
        Route::get('/alerts', [FacilityDashboardController::class, 'alerts'])->name('alerts.staff');
    });

    // 職員ホーム統計
    Route::get('/staff/home-stats', [\App\Http\Controllers\Staff\HomeController::class, 'stats'])->name('api.staff.home.stats');
    
    // CSV出力
    Route::prefix('export')->name('api.export.')->group(function () {
        Route::post('/attendance', [CsvExportController::class, 'exportAttendance'])->name('attendance.staff');
        Route::post('/reports', [CsvExportController::class, 'exportReports'])->name('reports.staff');
        Route::post('/kpi', [CsvExportController::class, 'exportKpi'])->name('kpi.staff');
    });
    
    // 祝日管理(管理者のみ)
    Route::prefix('holidays')->name('api.holidays.')->middleware('admin')->group(function () {
        Route::post('/', [HolidayController::class, 'store'])->name('store');
        Route::delete('/{date}', [HolidayController::class, 'destroy'])->name('destroy');
        Route::post('/import/csv', [HolidayController::class, 'importCsv'])->name('import.csv');
        Route::post('/import/government', [HolidayController::class, 'fetchGovernmentData'])->name('import.government');
    });
    
    // 監査ログ
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('api.audit.index');
    Route::get('/audit-logs/{id}', [AuditLogController::class, 'show'])->name('api.audit.show');
    Route::get('/audit-logs-export', [AuditLogController::class, 'export'])->name('api.audit.export');
    Route::get('/audit-logs-statistics', [AuditLogController::class, 'statistics'])->name('api.audit.stats');
});

/**
 * 利用者(User)専用API
 */
Route::middleware(['web', 'auth'])->group(function () {
    
    // ★ 個人ダッシュボードAPI（追加）
    Route::get('/me/dashboard', [DashboardController::class, 'personal'])->name('api.dashboard.personal.user');
    
    // 出席予定管理（登録とテンプレートのみ。変更・削除は職員）
    Route::prefix('me/plans')->name('api.plans.')->group(function () {
        Route::get('/', [PlanController::class, 'index'])->name('index.user');
        Route::post('/', [PlanController::class, 'store'])->name('store.user');
        Route::post('/template/copy-previous', [PlanController::class, 'templateCopyPrevious'])->name('tpl.copyPrev.user');
        Route::post('/template/weekday-bulk', [PlanController::class, 'templateWeekdayBulk'])->name('tpl.weekday.user');
    });
    
    // 出席実績管理
    Route::prefix('me/attendance')->name('api.attendance.')->group(function () {
        Route::get('/records', [AttendanceController::class, 'index'])->name('records.index.user');
        Route::post('/records', [AttendanceController::class, 'store'])->name('records.store.user');
        Route::match(['put', 'patch'], '/records/{id}', [AttendanceController::class, 'update'])
            ->whereNumber('id')->name('records.update.user');
        Route::get('/comparison', [AttendanceController::class, 'comparison'])->name('comparison.user');
    });
    
    // 日報管理
    Route::prefix('me/reports')->name('api.reports.')->group(function () {
        Route::get('/morning', [DailyReportController::class, 'indexMorning'])->name('morning.index.user');
        Route::get('/evening', [DailyReportController::class, 'indexEvening'])->name('evening.index.user');
        Route::post('/morning', [DailyReportController::class, 'storeMorning'])->name('morning.store.user');
        Route::post('/evening', [DailyReportController::class, 'storeEvening'])->name('evening.store.user');
        Route::match(['put', 'patch'], '/morning/{id}', [DailyReportController::class, 'updateMorning'])
            ->whereNumber('id')->name('morning.update.user');
        Route::match(['put', 'patch'], '/evening/{id}', [DailyReportController::class, 'updateEvening'])
            ->whereNumber('id')->name('evening.update.user');
    });
});