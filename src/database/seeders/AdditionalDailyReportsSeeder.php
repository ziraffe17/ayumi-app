<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DailyReportMorning;
use App\Models\DailyReportEvening;
use App\Models\AttendanceRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AdditionalDailyReportsSeeder extends Seeder
{
    /**
     * user_id=2,3 の日報データを作成
     * 実績がある日のみ日報を作成（8割程度の入力率を想定）
     */
    public function run(): void
    {
        DB::beginTransaction();

        try {
            // 既存のデータを削除
            DailyReportMorning::whereIn('user_id', [2, 3])->delete();
            DailyReportEvening::whereIn('user_id', [2, 3])->delete();

            // user_id=2の日報作成
            $this->createUserReports(2, 0.80);

            // user_id=3の日報作成
            $this->createUserReports(3, 0.75);

            DB::commit();

            echo "✓ user_id=2,3 の日報データを作成しました\n";

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('エラー: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 指定ユーザーの日報を作成
     *
     * @param int $userId
     * @param float $reportRate 日報入力率（0.0〜1.0）
     */
    private function createUserReports(int $userId, float $reportRate): void
    {
        // 実績がある日を取得（土日除く）
        $attendanceRecords = AttendanceRecord::where('user_id', $userId)
            ->whereIn('attendance_type', ['onsite', 'remote'])
            ->orderBy('record_date')
            ->get();

        foreach ($attendanceRecords as $record) {
            // 日報入力率に基づいてランダムに作成
            if (rand(1, 100) <= ($reportRate * 100)) {
                $date = $record->record_date;

                // 朝日報
                DailyReportMorning::create([
                    'user_id' => $userId,
                    'report_date' => $date,
                    'sleep_rating' => rand(1, 3),
                    'bed_time_local' => sprintf('%02d:%02d', rand(22, 24) % 24, rand(0, 5) * 10),
                    'wake_time_local' => sprintf('%02d:%02d', rand(6, 8), rand(0, 5) * 10),
                    'sleep_minutes' => rand(360, 540), // 6〜9時間
                    'stress_rating' => rand(1, 3),
                    'meal_rating' => rand(1, 3),
                    'mood_score' => rand(1, 10),
                    'is_medication_taken' => rand(0, 10) >= 3, // 70%の確率でtrue
                    'mid_awaken_count' => rand(0, 2),
                    'is_early_awaken' => rand(0, 10) >= 8, // 20%の確率でtrue
                    'is_breakfast_done' => rand(0, 10) >= 2, // 80%の確率でtrue
                    'is_bathing_done' => rand(0, 10) >= 1, // 90%の確率でtrue
                    'sign_good' => rand(0, 5),
                    'sign_caution' => rand(0, 3),
                    'sign_bad' => rand(0, 2),
                    'note' => null,
                ]);

                // 夕日報
                $trainingSummaries = [
                    '作業に取り組みました',
                    'プログラミング学習を進めました',
                    '清掃作業を行いました',
                    '書類整理を行いました',
                    'PC作業を中心に行いました',
                ];

                $reflections = [
                    '良い1日でした',
                    '集中できました',
                    '少し疲れましたが満足です',
                    '楽しく取り組めました',
                    '順調に進みました',
                ];

                DailyReportEvening::create([
                    'user_id' => $userId,
                    'report_date' => $date,
                    'training_summary' => $trainingSummaries[array_rand($trainingSummaries)],
                    'training_reflection' => $reflections[array_rand($reflections)],
                    'condition_note' => rand(0, 10) >= 8 ? '少し疲れています' : null,
                    'other_note' => null,
                ]);
            }
        }
    }
}
