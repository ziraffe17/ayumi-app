<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;
use App\Models\Staff;
use App\Models\User;

class InitSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // ===== 1) 職員（管理者）=====
            $admin = Staff::firstOrCreate(
                ['email' => 'admin@example.com'],
                [
                    'name' => 'Admin',
                    'password' => Hash::make('password'),
                    'role' => 'admin',
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );

            // ===== 2) 利用者 =====
            $user = User::firstOrCreate(
                ['login_code' => 'u0001'],
                [
                    'name'       => 'テスト太郎',
                    'name_kana'  => 'テストタロウ',
                    'email'      => 'user1@example.com',
                    'password'   => Hash::make('password'),
                    'is_active'  => true,
                    'start_date' => Carbon::now('Asia/Tokyo')->startOfMonth()->toDateString(),
                ]
            );

            // ===== 3) 祝日サンプル =====
            $todayJst = Carbon::now('Asia/Tokyo');
            $y  = (int) $todayJst->format('Y');
            $m  = (int) $todayJst->format('m');
            
            $holidayRows = [
                ['holiday_date' => Carbon::create($y, $m, 15)->toDateString(), 'name' => 'サンプル祝日1'],
            ];
            DB::table('holidays')->upsert($holidayRows, ['holiday_date'], ['name']);

            $holidayMap = DB::table('holidays')->pluck('name', 'holiday_date');

            // ===== 4) 当月の出席予定 =====
            $start = $todayJst->copy()->startOfMonth();
            $end   = $todayJst->copy()->endOfMonth();
            $today = $todayJst->copy();

            $planPayload = [];
            $recordPayload = [];
            
            for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
                $dow = (int) $d->dayOfWeekIso; // 1(月)..7(日)
                if ($dow >= 6) continue; // 土日スキップ

                $date  = $d->toDateString();
                $hName = $holidayMap[$date] ?? null;
                
                // 祝日もスキップ
                if ($hName !== null) continue;

                // 出席予定を作成
                $planPayload[] = [
                    'user_id'         => $user->id,
                    'plan_date'       => $date,
                    'plan_time_slot'  => 'full',
                    'plan_type'       => 'onsite',
                    'note'            => null,
                    'is_holiday'      => false,
                    'holiday_name'    => null,
                    'template_source' => 'weekday',
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ];

                // 今日より前の日付は出席実績も作成（85%の出席率になるよう調整）
                if ($d->lt($today)) {
                    // 約85%の確率で出席、15%で欠席
                    $isAttend = rand(1, 100) <= 85;
                    
                    $recordPayload[] = [
                        'user_id'          => $user->id,
                        'record_date'      => $date,
                        'record_time_slot' => 'full',
                        'attendance_type'  => $isAttend ? 'onsite' : 'absent',
                        'note'             => null,
                        'source'           => 'self',
                        'created_at'       => now(),
                        'updated_at'       => now(),
                    ];

                    // 出席した日は日報も作成
                    if ($isAttend) {
                        // 朝の日報
                        DB::table('daily_reports_morning')->updateOrInsert(
                            [
                                'user_id' => $user->id,
                                'report_date' => $date,
                            ],
                            [
                                'sleep_rating' => rand(2, 3),
                                'stress_rating' => rand(2, 3),
                                'meal_rating' => rand(2, 3),
                                'bed_time_local' => '23:00',
                                'wake_time_local' => '07:00',
                                'sleep_minutes' => 480,
                                'mid_awaken_count' => 0,
                                'is_early_awaken' => false,
                                'is_breakfast_done' => true,
                                'is_bathing_done' => true,
                                'is_medication_taken' => null,
                                'mood_score' => rand(5, 8),
                                'sign_good' => rand(0, 3),
                                'sign_caution' => rand(0, 2),
                                'sign_bad' => 0,
                                'note' => '特になし',
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]
                        );

                        // 夕方の日報（50%の確率で作成）
                        if (rand(1, 100) <= 50) {
                            DB::table('daily_reports_evening')->updateOrInsert(
                                [
                                    'user_id' => $user->id,
                                    'report_date' => $date,
                                ],
                                [
                                    'training_summary' => '作業訓練を行いました',
                                    'training_reflection' => '集中して取り組めました',
                                    'condition_note' => '体調は良好でした',
                                    'other_note' => null,
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]
                            );
                        }
                    }
                }
                // 今日の出席実績（既に出席済みとして記録）
                elseif ($d->eq($today)) {
                    $recordPayload[] = [
                        'user_id'          => $user->id,
                        'record_date'      => $date,
                        'record_time_slot' => 'full',
                        'attendance_type'  => 'onsite',
                        'note'             => null,
                        'source'           => 'self',
                        'created_at'       => now(),
                        'updated_at'       => now(),
                    ];
                }
            }

            // 出席予定を一括挿入
            if (!empty($planPayload)) {
                DB::table('attendance_plans')->upsert(
                    $planPayload,
                    ['user_id', 'plan_date', 'plan_time_slot'],
                    ['plan_type', 'note', 'is_holiday', 'holiday_name', 'template_source', 'updated_at']
                );
            }

            // 出席実績を一括挿入
            if (!empty($recordPayload)) {
                DB::table('attendance_records')->upsert(
                    $recordPayload,
                    ['user_id', 'record_date', 'record_time_slot'],
                    ['attendance_type', 'note', 'source', 'updated_at']
                );
            }

            // ===== 5) 統計情報を表示 =====
            $totalPlans = count($planPayload);
            $totalRecords = count($recordPayload);
            $attendedCount = count(array_filter($recordPayload, fn($r) => $r['attendance_type'] !== 'absent'));
            
            echo "===== Seeder実行結果 =====\n";
            echo "利用者作成: {$user->name} (ID: {$user->id})\n";
            echo "出席予定作成: {$totalPlans}件\n";
            echo "出席実績作成: {$totalRecords}件\n";
            echo "出席日数: {$attendedCount}日\n";
            echo "予想出席率: " . ($totalPlans > 0 ? round(($attendedCount / $totalPlans) * 100, 1) : 0) . "%\n";
        });
    }
}