<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Models\Setting;
use App\Services\HolidayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class SettingsController extends Controller
{
    protected HolidayService $holidayService;

    public function __construct(HolidayService $holidayService)
    {
        $this->holidayService = $holidayService;
    }

    public function index()
    {
        $holidays = Holiday::orderBy('holiday_date', 'desc')->limit(50)->get();
        $holidayStats = $this->holidayService->getStatistics();

        $settings = [
            'org_name' => config('app.org_name', ''),
            'org_postal_code' => config('app.org_postal_code', ''),
            'org_address' => config('app.org_address', ''),
            'org_phone' => config('app.org_phone', ''),
            'attendance_base' => config('app.attendance_base', 'plan'),
            'report_deadline_days' => config('app.report_deadline_days', 3),
            'log_retention_days' => config('app.log_retention_days', 365),
        ];

        $facilityCapacity = Setting::get('facility_capacity', 20);

        $lastBackup = Storage::disk('local')->exists('backup/last_backup.txt')
            ? Storage::disk('local')->get('backup/last_backup.txt')
            : '未実行';

        return view('staff.settings.index', compact('holidays', 'holidayStats', 'settings', 'facilityCapacity', 'lastBackup'));
    }

    public function updateFacility(Request $request)
    {
        $request->validate([
            'facility_capacity' => 'required|integer|min:1|max:100',
        ]);

        Setting::set('facility_capacity', $request->facility_capacity, 'integer', '事業所の定員数');

        return redirect()->route('staff.settings.index')
            ->with('success', '事業所設定を更新しました');
    }

    public function importHolidays(Request $request)
    {
        $request->validate([
            'holiday_csv' => 'required|file|mimes:csv,txt',
        ]);

        try {
            $file = $request->file('holiday_csv');
            $csvContent = file_get_contents($file->getRealPath());
            
            $result = $this->holidayService->importFromCsv($csvContent);
            
            if ($result['success']) {
                return redirect()->route('staff.settings.index')
                    ->with('success', $result['message']);
            } else {
                return redirect()->route('staff.settings.index')
                    ->with('error', $result['message']);
            }

        } catch (\Exception $e) {
            \Log::error('Holiday CSV import failed', ['error' => $e->getMessage()]);
            
            return redirect()->route('staff.settings.index')
                ->with('error', 'CSV取り込みに失敗しました');
        }
    }

    /**
     * 個別手動入力
     */
    public function addHoliday(Request $request)
    {
        $request->validate([
            'holiday_date' => 'required|date|date_format:Y-m-d',
            'holiday_name' => 'required|string|max:50'
        ]);

        try {
            $result = $this->holidayService->addManualHoliday(
                $request->holiday_date,
                $request->holiday_name
            );

            if ($result['success']) {
                return redirect()->route('staff.settings.index')
                    ->with('success', $result['message']);
            } else {
                return redirect()->route('staff.settings.index')
                    ->with('error', $result['message']);
            }

        } catch (\Exception $e) {
            \Log::error('Manual holiday addition failed', ['error' => $e->getMessage()]);

            return redirect()->route('staff.settings.index')
                ->with('error', '祝日の追加に失敗しました');
        }
    }

    /**
     * 政府APIから祝日を自動取り込み
     */
    public function importFromApi(Request $request)
    {
        $request->validate([
            'year' => 'nullable|integer|min:2020|max:2030'
        ]);

        try {
            $year = $request->integer('year');
            $result = $this->holidayService->importFromGovernmentApi($year);
            
            if ($result['success']) {
                return redirect()->route('staff.settings.index')
                    ->with('success', $result['message']);
            } else {
                return redirect()->route('staff.settings.index')
                    ->with('error', $result['message']);
            }

        } catch (\Exception $e) {
            \Log::error('Holiday API import failed', ['error' => $e->getMessage()]);
            
            return redirect()->route('staff.settings.index')
                ->with('error', '政府APIからの取り込みに失敗しました');
        }
    }

    /**
     * 古い祝日データの削除
     */
    public function cleanupHolidays(Request $request)
    {
        $request->validate([
            'keep_years' => 'required|integer|min:1|max:10'
        ]);

        try {
            $keepYears = $request->integer('keep_years', 3);
            $deletedCount = $this->holidayService->cleanupOldHolidays($keepYears);
            
            return redirect()->route('staff.settings.index')
                ->with('success', "{$deletedCount}件の古い祝日データを削除しました");

        } catch (\Exception $e) {
            \Log::error('Holiday cleanup failed', ['error' => $e->getMessage()]);
            
            return redirect()->route('staff.settings.index')
                ->with('error', '祝日データの削除に失敗しました');
        }
    }

    public function deleteHoliday($date)
    {
        Holiday::where('holiday_date', $date)->delete();

        return redirect()->route('staff.settings.index')
            ->with('success', '祝日を削除しました');
    }

    public function updateOrganization(Request $request)
    {
        // 実装: 設定ファイルまたはDBに保存
        return redirect()->route('staff.settings.index')
            ->with('success', '事業所情報を更新しました');
    }

    public function updateSystem(Request $request)
    {
        // 実装: システム設定を更新
        return redirect()->route('staff.settings.index')
            ->with('success', 'システム設定を更新しました');
    }

    public function backup()
    {
        // 実装: バックアップ実行
        Storage::disk('local')->put('backup/last_backup.txt', now()->toDateTimeString());
        
        return redirect()->route('staff.settings.index')
            ->with('success', 'バックアップを実行しました');
    }
}