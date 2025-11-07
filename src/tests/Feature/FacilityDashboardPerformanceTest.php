<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Staff;
use App\Models\AttendancePlan;
use App\Models\AttendanceRecord;
use App\Models\DailyReportMorning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;
use Carbon\Carbon;

class FacilityDashboardPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private Staff $testStaff;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->testStaff = Staff::factory()->create([
            'role' => 'staff',
            'is_active' => 1,
        ]);
    }

    /** @test */
    public function P95が2秒以下で50名30日のデータを処理できる()
    {
        Sanctum::actingAs($this->testStaff, ['*'], 'staff');

        // 50名×30日のサンプルデータ作成
        $this->createLargeDataset(50, 30);

        $responseTimes = [];

        // 10回実行してP95を測定
        for ($i = 0; $i < 10; $i++) {
            $startTime = microtime(true);
            
            $response = $this->getJson('/api/dashboard/facility?limit=50');
            
            $endTime = microtime(true);
            $responseTime = $endTime - $startTime;
            $responseTimes[] = $responseTime;

            $response->assertStatus(200);
        }

        // P95計算
        sort($responseTimes);
        $p95Index = (int) ceil(0.95 * count($responseTimes)) - 1;
        $p95ResponseTime = $responseTimes[$p95Index];

        $this->assertLessThan(2.0, $p95ResponseTime, 
            "P95 response time was {$p95ResponseTime}s, should be < 2.0s");

        echo "\nP95 Response Time: {$p95ResponseTime}s\n";
        echo "Average Response Time: " . (array_sum($responseTimes) / count($responseTimes)) . "s\n";
    }

    /** @test */
    public function 仮想スクロールでページングが正しく動作する()
    {
        Sanctum::actingAs($this->testStaff, ['*'], 'staff');

        // 100名のデータ作成
        $users = User::factory()->count(100)->create(['is_active' => 1]);
        
        // 各ユーザーに最小限のデータ
        foreach ($users as $user) {
            AttendancePlan::factory()->create([
                'user_id' => $user->id,
                'plan_date' => now()->format('Y-m-d'),
                'plan_type' => 'onsite',
            ]);
        }

        // 1ページ目
        $response1 = $this->getJson('/api/dashboard/facility?limit=30&offset=0');
        $response1->assertStatus(200);
        
        $data1 = $response1->json('data.users');
        $pagination1 = $response1->json('pagination');
        
        $this->assertCount(30, $data1);
        $this->assertEquals(100, $pagination1['total']);
        $this->assertTrue($pagination1['has_more']);

        // 2ページ目
        $response2 = $this->getJson('/api/dashboard/facility?limit=30&offset=30');
        $response2->assertStatus(200);
        
        $data2 = $response2->json('data.users');
        
        $this->assertCount(30, $data2);
        
        // データの重複がないことを確認
        $userIds1 = collect($data1)->pluck('user_id')->toArray();
        $userIds2 = collect($data2)->pluck('user_id')->toArray();
        $this->assertEmpty(array_intersect($userIds1, $userIds2));
    }

    /** @test */
    public function アラートフィルタリングが正しく動作する()
    {
        Sanctum::actingAs($this->testStaff, ['*'], 'staff');

        // アラートありユーザー（未計画）
        $userWithAlert = User::factory()->create(['is_active' => 1]);
        
        // アラートなしユーザー（計画あり）
        $userWithoutAlert = User::factory()->create(['is_active' => 1]);
        AttendancePlan::factory()->create([
            'user_id' => $userWithoutAlert->id,
            'plan_date' => now()->format('Y-m-d'),
            'plan_type' => 'onsite',
        ]);
        DailyReportMorning::factory()->create([
            'user_id' => $userWithoutAlert->id,
            'report_date' => now()->format('Y-m-d'),
        ]);

        // アラートフィルタなし
        $responseAll = $this->getJson('/api/dashboard/facility');
        $this->assertGreaterThanOrEqual(2, count($responseAll->json('data.users')));

        // アラートフィルタあり
        $responseFiltered = $this->getJson('/api/dashboard/facility?filter_alerts=true');
        $filteredUsers = $responseFiltered->json('data.users');
        
        // アラートがあるユーザーのみ表示されることを確認
        foreach ($filteredUsers as $user) {
            $this->assertGreaterThan(0, $user['alerts_count']);
        }
    }

    /** @test */
    public function ソート機能が正しく動作する()
    {
        Sanctum::actingAs($this->testStaff, ['*'], 'staff');

        // 異なる出席率のユーザーを作成
        $users = [];
        $attendanceRates = [90, 70, 85, 60, 95];
        
        foreach ($attendanceRates as $index => $rate) {
            $user = User::factory()->create([
                'name' => "テストユーザー" . ($index + 1),
                'is_active' => 1,
            ]);
            
            // 計画10日、出席をrate%に設定
            for ($day = 0; $day < 10; $day++) {
                $date = now()->subDays($day)->format('Y-m-d');
                AttendancePlan::factory()->create([
                    'user_id' => $user->id,
                    'plan_date' => $date,
                    'plan_type' => 'onsite',
                ]);
                
                if ($day < ($rate / 10)) { // 出席率に応じて実績作成
                    AttendanceRecord::factory()->create([
                        'user_id' => $user->id,
                        'record_date' => $date,
                        'attendance_type' => 'onsite',
                    ]);
                }
            }
            $users[] = $user;
        }

        // 出席率降順ソート
        $response = $this->getJson('/api/dashboard/facility?sort_by=attendance_rate&sort_order=desc');
        $response->assertStatus(200);
        
        $sortedUsers = $response->json('data.users');
        
        // ソートされていることを確認
        for ($i = 0; $i < count($sortedUsers) - 1; $i++) {
            $currentRate = $sortedUsers[$i]['attendance_rate'] ?? 0;
            $nextRate = $sortedUsers[$i + 1]['attendance_rate'] ?? 0;
            $this->assertGreaterThanOrEqual($nextRate, $currentRate);
        }
    }

    /** @test */
    public function 検索機能が正しく動作する()
    {
        Sanctum::actingAs($this->testStaff, ['*'], 'staff');

        $user1 = User::factory()->create([
            'name' => '田中太郎',
            'login_code' => 'u0001',
            'is_active' => 1,
        ]);
        
        $user2 = User::factory()->create([
            'name' => '佐藤花子',
            'login_code' => 'u0002',
            'is_active' => 1,
        ]);

        $user3 = User::factory()->create([
            'name' => '鈴木一郎',
            'login_code' => 'u0003',
            'is_active' => 1,
        ]);

        // 名前で検索
        $response1 = $this->getJson('/api/dashboard/facility?search=田中');
        $response1->assertStatus(200);
        
        $searchResults1 = $response1->json('data.users');
        $this->assertCount(1, $searchResults1);
        $this->assertEquals('田中太郎', $searchResults1[0]['user_name']);

        // ログインコードで検索
        $response2 = $this->getJson('/api/dashboard/facility?search=u000');
        $response2->assertStatus(200);
        
        $searchResults2 = $response2->json('data.users');
        $this->assertGreaterThanOrEqual(3, count($searchResults2));
    }

    /**
     * 大規模データセット作成
     */
    private function createLargeDataset(int $userCount, int $dayCount): void
    {
        $users = User::factory()->count($userCount)->create(['is_active' => 1]);
        
        foreach ($users as $userIndex => $user) {
            for ($day = 0; $day < $dayCount; $day++) {
                $date = now()->subDays($day)->format('Y-m-d');
                
                // 80%の確率で計画作成
                if (rand(1, 100) <= 80) {
                    AttendancePlan::create([
                        'user_id' => $user->id,
                        'plan_date' => $date,
                        'plan_time_slot' => 'full',
                        'plan_type' => rand(1, 100) <= 5 ? 'off' : 'onsite', // 5%は休み
                    ]);
                    
                    // 90%の確率で実績作成
                    if (rand(1, 100) <= 90) {
                        AttendanceRecord::create([
                            'user_id' => $user->id,
                            'record_date' => $date,
                            'record_time_slot' => 'full',
                            'attendance_type' => rand(1, 100) <= 10 ? 'absent' : 'onsite', // 10%は欠席
                            'source' => 'self',
                        ]);
                    }
                    
                    // 70%の確率で日報作成
                    if (rand(1, 100) <= 70) {
                        DailyReportMorning::create([
                            'user_id' => $user->id,
                            'report_date' => $date,
                            'sleep_rating' => rand(1, 3),
                            'stress_rating' => rand(1, 3),
                            'meal_rating' => rand(1, 3),
                            'bed_time_local' => sprintf('%02d:%02d:00', rand(21, 23), rand(0, 59)),
                            'wake_time_local' => sprintf('%02d:%02d:00', rand(6, 8), rand(0, 59)),
                            'mood_score' => rand(1, 10),
                        ]);
                    }
                }
            }
        }
    }
}

// ===== app/Console/Commands/GenerateSampleData.php =====

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Staff;
use App\Models\AttendancePlan;
use App\Models\AttendanceRecord;
use App\Models\DailyReportMorning;
use App\Models\DailyReportEvening;
use App\Models\Holiday;
use Carbon\Carbon;

class GenerateSampleData extends Command
{
    protected $signature = 'sample:generate {--users=50 : 利用者数} {--days=90 : 日数} {--clean : 既存データを削除}';
    protected $description = 'パフォーマンステスト用のサンプルデータを生成';

    public function handle(): int
    {
        $userCount = (int) $this->option('users');
        $dayCount = (int) $this->option('days');
        $clean = $this->option('clean');

        if ($clean) {
            $this->info('既存データを削除中...');
            $this->cleanExistingData();
        }

        $this->info("サンプルデータ生成開始: {$userCount}名 × {$dayCount}日");

        // プログレスバー初期化
        $totalOperations = $userCount * $dayCount * 3; // plan + record + report
        $bar = $this->output->createProgressBar($totalOperations);
        $bar->start();

        // 管理者・職員作成
        $this->createStaffUsers();

        // 利用者作成
        $users = $this->createUsers($userCount);

        // 祝日データ作成
        $this->createHolidays();

        // 各利用者のデータ作成
        foreach ($users as $user) {
            $this->createUserData($user, $dayCount, $bar);
        }

        $bar->finish();
        $this->newLine(2);
        $this->info('サンプルデータ生成完了！');

        // 統計表示
        $this->displayStatistics();

        return Command::SUCCESS;
    }

    private function cleanExistingData(): void
    {
        DailyReportMorning::truncate();
        DailyReportEvening::truncate();
        AttendanceRecord::truncate();
        AttendancePlan::truncate();
        User::where('email', '!=', 'admin@example.com')->delete();
    }

    private function createStaffUsers(): void
    {
        // 管理者
        Staff::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => '管理者',
                'password' => bcrypt('password'),
                'role' => 'admin',
                'is_active' => 1,
            ]
        );

        // 職員
        Staff::firstOrCreate(
            ['email' => 'staff@example.com'],
            [
                'name' => '職員',
                'password' => bcrypt('password'),
                'role' => 'staff',
                'is_active' => 1,
            ]
        );
    }

    private function createUsers(int $count): \Illuminate\Database\Eloquent\Collection
    {
        $users = collect();
        
        for ($i = 1; $i <= $count; $i++) {
            $user = User::create([
                'name' => "利用者{$i}",
                'name_kana' => "リヨウシャ{$i}",
                'login_code' => sprintf('u%04d', $i),
                'password' => bcrypt('password'),
                'start_date' => now()->subDays(rand(30, 365))->format('Y-m-d'),
                'is_active' => 1,
            ]);
            $users->push($user);
        }

        return $users;
    }

    private function createHolidays(): void
    {
        $holidays = [
            ['2024-01-01', '元日'],
            ['2024-01-08', '成人の日'],
            ['2024-02-11', '建国記念の日'],
            ['2024-02-12', '建国記念の日 振替休日'],
            ['2024-02-23', '天皇誕生日'],
            ['2024-03-20', '春分の日'],
            ['2024-04-29', 'みどりの日'],
            ['2024-05-03', '憲法記念日'],
            ['2024-05-04', 'みどりの日'],
            ['2024-05-05', 'こどもの日'],
            ['2024-05-06', '振替休日'],
            ['2024-07-15', '海の日'],
            ['2024-08-11', '山の日'],
            ['2024-08-12', '振替休日'],
            ['2024-09-16', '敬老の日'],
            ['2024-09-22', '秋分の日'],
            ['2024-09-23', '振替休日'],
            ['2024-10-14', 'スポーツの日'],
            ['2024-11-03', '文化の日'],
            ['2024-11-04', '振替休日'],
            ['2024-11-23', '勤労感謝の日'],
        ];

        foreach ($holidays as [$date, $name]) {
            Holiday::firstOrCreate(
                ['holiday_date' => $date],
                [
                    'name' => $name,
                    'source' => 'manual',
                    'imported_at' => now(),
                ]
            );
        }
    }

    private function createUserData($user, int $dayCount, $bar): void
    {
        $startDate = now()->subDays($dayCount - 1);
        
        for ($day = 0; $day < $dayCount; $day++) {
            $currentDate = $startDate->copy()->addDays($day);
            $dateString = $currentDate->format('Y-m-d');
            
            // 土日祝日の処理
            $isWeekend = $currentDate->isWeekend();
            $isHoliday = Holiday::where('holiday_date', $dateString)->exists();
            
            // 計画作成（平日は90%、土日祝は30%の確率）
            $planProbability = ($isWeekend || $isHoliday) ? 30 : 90;
            
            if (rand(1, 100) <= $planProbability) {
                $planType = $this->determinePlanType($isWeekend, $isHoliday);
                
                $plan = AttendancePlan::create([
                    'user_id' => $user->id,
                    'plan_date' => $dateString,
                    'plan_time_slot' => 'full',
                    'plan_type' => $planType,
                    'is_holiday' => $isHoliday ? 1 : 0,
                    'holiday_name' => $isHoliday 
                        ? Holiday::where('holiday_date', $dateString)->value('name') 
                        : null,
                ]);
                
                $bar->advance();
                
                // 実績作成（計画がonsite/remoteの場合のみ）
                if (in_array($planType, ['onsite', 'remote'])) {
                    $attendanceProbability = $this->getAttendanceProbability($user->id);
                    
                    if (rand(1, 100) <= $attendanceProbability) {
                        $attendanceType = $this->determineAttendanceType($planType);
                        
                        AttendanceRecord::create([
                            'user_id' => $user->id,
                            'record_date' => $dateString,
                            'record_time_slot' => 'full',
                            'attendance_type' => $attendanceType,
                            'source' => rand(1, 100) <= 80 ? 'self' : 'staff',
                        ]);
                        
                        // 日報作成（出席した場合のみ）
                        if ($attendanceType !== 'absent') {
                            $this->createDailyReports($user->id, $dateString);
                        }
                    }
                }
                
                $bar->advance();
            } else {
                $bar->advance(2); // planとrecordをスキップ
            }
            
            $bar->advance(); // report
        }
    }

    private function determinePlanType(bool $isWeekend, bool $isHoliday): string
    {
        if ($isWeekend || $isHoliday) {
            return rand(1, 100) <= 70 ? 'off' : 'onsite'; // 土日祝は70%休み
        }
        
        $rand = rand(1, 100);
        if ($rand <= 85) return 'onsite';
        if ($rand <= 95) return 'remote';
        return 'off';
    }

    private function getAttendanceProbability(int $userId): int
    {
        // ユーザーIDに基づいて出席確率を変動（リアルなばらつき）
        $seed = $userId % 5;
        return match ($seed) {
            0 => 95, // 優秀な利用者
            1 => 88, // 良好な利用者
            2 => 75, // 平均的な利用者
            3 => 60, // やや課題のある利用者
            4 => 45, // 課題のある利用者
            default => 80,
        };
    }

    private function determineAttendanceType(string $planType): string
    {
        $rand = rand(1, 100);
        
        if ($planType === 'onsite') {
            if ($rand <= 85) return 'onsite';
            if ($rand <= 92) return 'remote'; // 予定変更
            return 'absent';
        }
        
        if ($planType === 'remote') {
            if ($rand <= 88) return 'remote';
            if ($rand <= 93) return 'onsite'; // 予定変更
            return 'absent';
        }
        
        return 'absent';
    }

    private function createDailyReports(int $userId, string $date): void
    {
        // 朝の日報（80%の確率）
        if (rand(1, 100) <= 80) {
            DailyReportMorning::create([
                'user_id' => $userId,
                'report_date' => $date,
                'sleep_rating' => $this->getWeightedRating(),
                'stress_rating' => $this->getWeightedRating(),
                'meal_rating' => $this->getWeightedRating(),
                'bed_time_local' => sprintf('%02d:%02d:00', rand(21, 23), rand(0, 59)),
                'wake_time_local' => sprintf('%02d:%02d:00', rand(6, 8), rand(0, 59)),
                'sleep_minutes' => rand(360, 540), // 6-9時間
                'mid_awaken_count' => rand(0, 3),
                'is_early_awaken' => rand(1, 100) <= 20,
                'is_breakfast_done' => rand(1, 100) <= 85,
                'is_bathing_done' => rand(1, 100) <= 90,
                'is_medication_taken' => rand(1, 100) <= 30 ? null : (rand(1, 100) <= 90),
                'mood_score' => $this->getWeightedMoodScore(),
                'sign_good' => rand(0, 5),
                'sign_caution' => rand(0, 3),
                'sign_bad' => rand(0, 2),
                'note' => rand(1, 100) <= 30 ? 'サンプル記録' : '',
            ]);
        }

        // 夕の日報（70%の確率）
        if (rand(1, 100) <= 70) {
            DailyReportEvening::create([
                'user_id' => $userId,
                'report_date' => $date,
                'training_summary' => $this->getRandomTrainingSummary(),
                'training_feedback' => rand(1, 100) <= 60 ? $this->getRandomFeedback() : '',
                'condition_note' => rand(1, 100) <= 40 ? '体調良好' : '',
                'other_note' => rand(1, 100) <= 20 ? 'その他の記録' : '',
            ]);
        }
    }

    private function getWeightedRating(): int
    {
        $rand = rand(1, 100);
        if ($rand <= 60) return 3; // ◯
        if ($rand <= 85) return 2; // △
        return 1; // ✕
    }

    private function getWeightedMoodScore(): int
    {
        $rand = rand(1, 100);
        if ($rand <= 50) return rand(7, 10); // 高い気分
        if ($rand <= 80) return rand(5, 6);  // 普通
        return rand(1, 4); // 低い気分
    }

    private function getRandomTrainingSummary(): string
    {
        $activities = [
            'パソコン訓練',
            'コミュニケーション訓練',
            'グループワーク',
            '職業準備性訓練',
            '生活技能訓練',
            '軽作業訓練',
            '清掃訓練',
        ];
        
        return $activities[array_rand($activities)];
    }

    private function getRandomFeedback(): string
    {
        $feedbacks = [
            '集中して取り組めました',
            '少し疲れましたが頑張りました',
            '新しいことを学べました',
            '他の人と協力できました',
            '難しかったですが勉強になりました',
        ];
        
        return $feedbacks[array_rand($feedbacks)];
    }

    private function displayStatistics(): void
    {
        $userCount = User::count();
        $planCount = AttendancePlan::count();
        $recordCount = AttendanceRecord::count();
        $morningReportCount = DailyReportMorning::count();
        $eveningReportCount = DailyReportEvening::count();

        $this->table(
            ['項目', '件数'],
            [
                ['利用者', number_format($userCount)],
                ['出席予定', number_format($planCount)],
                ['出席実績', number_format($recordCount)],
                ['朝の日報', number_format($morningReportCount)],
                ['夕の日報', number_format($eveningReportCount)],
                ['合計レコード', number_format($planCount + $recordCount + $morningReportCount + $eveningReportCount)],
            ]
        );
    }
}