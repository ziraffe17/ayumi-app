<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AttendancePlan;
use App\Models\AttendanceRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AdditionalAttendanceSeeder extends Seeder
{
    /**
     * user_id=2: 2025-08-01開始 → 8月、9月、10月のデータ
     * user_id=3: 2025-09-01開始 → 9月、10月のデータ
     */
    public function run(): void
    {
        DB::beginTransaction();

        try {
            // user_id=2のデータ削除
            AttendancePlan::where('user_id', 2)->delete();
            AttendanceRecord::where('user_id', 2)->delete();

            // user_id=3のデータ削除
            AttendancePlan::where('user_id', 3)->delete();
            AttendanceRecord::where('user_id', 3)->delete();

            // ========================================
            // user_id=2 (佐藤花子): 8月開始
            // ========================================

            // 8月: 週3日（月・水・金）
            $this->createMonthData(2, '2025-08', ['1', '3', '5'], 0.88);

            // 9月: 週4日（月・火・木・金）
            $this->createMonthData(2, '2025-09', ['1', '2', '4', '5'], 0.92);

            // 10月: 週5日（月〜金）、実績は本日まで
            $this->createMonthData(2, '2025-10', ['1', '2', '3', '4', '5'], 0.90, true);

            // ========================================
            // user_id=3 (鈴木太郎): 9月開始
            // ========================================

            // 9月: 週3日（火・木・金）
            $this->createMonthData(3, '2025-09', ['2', '4', '5'], 0.85);

            // 10月: 週4日（月・水・木・金）、実績は本日まで
            $this->createMonthData(3, '2025-10', ['1', '3', '4', '5'], 0.87, true);

            DB::commit();

            echo "✓ user_id=2,3 の出席予定・実績データを作成しました\n";

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('エラー: ' . $e->getMessage());
            throw $e;
        }
    }

    private function createMonthData(int $userId, string $month, array $targetDays, float $attendanceRate, bool $onlyUntilToday = false)
    {
        $startDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $endDate = Carbon::createFromFormat('Y-m', $month)->endOfMonth();
        $today = Carbon::today();

        $current = $startDate->copy();

        while ($current <= $endDate) {
            // 土日はスキップ
            if ($current->isWeekend()) {
                $current->addDay();
                continue;
            }

            $dayOfWeek = $current->dayOfWeek; // 0=日, 1=月, ..., 6=土

            // 対象曜日かチェック
            if (in_array((string)$dayOfWeek, $targetDays)) {
                // 出席予定を作成
                AttendancePlan::create([
                    'user_id' => $userId,
                    'plan_date' => $current->format('Y-m-d'),
                    'plan_type' => rand(1, 10) <= 8 ? 'onsite' : 'remote', // 8割通所、2割在宅
                    'plan_time_slot' => 'full',
                    'is_holiday' => false,
                ]);

                // 実績データは今日まで、または月末まで（onlyUntilToday による）
                $shouldCreateRecord = $onlyUntilToday ? $current < $today : true;

                if ($shouldCreateRecord) {
                    // 出席率に基づいてランダムに実績を作成
                    if (rand(1, 100) <= ($attendanceRate * 100)) {
                        $isOnsite = rand(1, 10) <= 8;
                        $attendanceType = $isOnsite ? 'onsite' : 'remote';

                        AttendanceRecord::create([
                            'user_id' => $userId,
                            'record_date' => $current->format('Y-m-d'),
                            'attendance_type' => $attendanceType,
                            'record_time_slot' => 'full',
                        ]);
                    } else {
                        // 欠席
                        AttendanceRecord::create([
                            'user_id' => $userId,
                            'record_date' => $current->format('Y-m-d'),
                            'attendance_type' => 'absent',
                            'record_time_slot' => 'full',
                        ]);
                    }
                }
            }

            $current->addDay();
        }
    }
}
