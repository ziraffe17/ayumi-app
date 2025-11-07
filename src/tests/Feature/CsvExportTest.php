<?php

namespace Tests\Feature;

use App\Models\Staff;
use App\Models\User;
use App\Models\AttendancePlan;
use App\Models\AttendanceRecord;
use App\Models\DailyReportMorning;
use App\Models\DailyReportEvening;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class CsvExportTest extends TestCase
{
    use RefreshDatabase;

    private Staff $staff;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->staff = Staff::factory()->create([
            'role' => 'staff',
            'is_active' => 1,
        ]);

        $this->user = User::factory()->create([
            'is_active' => 1,
        ]);
    }

    /** @test */
    public function 出席データCSVを出力できる()
    {
        Sanctum::actingAs($this->staff, ['*'], 'staff');

        // テストデータ作成
        $this->createAttendanceTestData();

        $response = $this->postJson('/api/export/attendance', [
            'start_date' => '2024-01-01',
            'end_date' => '2024-01-03',
            'user_ids' => [$this->user->id],
            'include_plans' => true,
            'include_records' => true,
            'include_comparison' => true,
            'format' => 'utf8',
        ]);

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/csv')
            ->assertHeaderMissing('Content-Disposition');

        // CSVヘッダーの確認
        $content = $response->getContent();
        $this->assertStringContainsString('利用者ID', $content);
        $this->assertStringContainsString('氏名', $content);
        $this->assertStringContainsString('予定種別', $content);
        $this->assertStringContainsString('実績種別', $content);
    }

    /** @test */
    public function 日報データCSVを出力できる()
    {
        Sanctum::actingAs($this->staff, ['*'], 'staff');

        // テストデータ作成
        $this->createReportTestData();

        $response = $this->postJson('/api/export/reports', [
            'start_date' => '2024-01-01',
            'end_date' => '2024-01-03',
            'user_ids' => [$this->user->id],
            'report_type' => 'both',
            'include_averages' => true,
            'format' => 'utf8',
        ]);

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/csv');

        $content = $response->getContent();
        $this->assertStringContainsString('睡眠評価', $content);
        $this->assertStringContainsString('今日の訓練内容', $content);
        $this->assertStringContainsString('平均値データ', $content);
    }

    /** @test */
    public function KPIデータCSVを出力できる()
    {
        Sanctum::actingAs($this->staff, ['*'], 'staff');

        // テストデータ作成
        $this->createKpiTestData();

        $response = $this->postJson('/api/export/kpi', [
            'start_date' => '2024-01-01',
            'end_date' => '2024-01-31',
            'user_ids' => [$this->user->id],
            'include_trends' => true,
            'include_alerts' => true,
            'format' => 'utf8',
        ]);

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/csv');

        $content = $response->getContent();
        $this->assertStringContainsString('出席率', $content);
        $this->assertStringContainsString('アラート数', $content);
        $this->assertStringContainsString('睡眠評価平均', $content);
    }

    /** @test */
    public function CSV出力のパフォーマンス要件を満たす()
    {
        Sanctum::actingAs($this->staff, ['*'], 'staff');

        // 大量データ作成
        $users = User::factory()->count(50)->create(['is_active' => 1]);
        foreach ($users as $user) {
            for ($i = 0; $i < 30; $i++) {
                $date = now()->subDays($i)->format('Y-m-d');
                
                AttendancePlan::create([
                    'user_id' => $user->id,
                    'plan_date' => $date,
                    'plan_time_slot' => 'full',
                    'plan_type' => 'onsite',
                ]);
                
                AttendanceRecord::create([
                    'user_id' => $user->id,
                    'record_date' => $date,
                    'record_time_slot' => 'full',
                    'attendance_type' => 'onsite',
                    'source' => 'self',
                ]);
            }
        }

        $startTime = microtime(true);

        $response = $this->postJson('/api/export/attendance', [
            'start_date' => now()->subDays(29)->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
            'include_plans' => true,
            'include_records' => true,
        ]);

        $endTime = microtime(true);
        $responseTime = $endTime - $startTime;

        $response->assertStatus(200);
        
        // P95 ≤ 5s要件
        $this->assertLessThan(5.0, $responseTime, 
            "CSV export response time was {$responseTime}s, should be < 5.0s");
    }

    /** @test */
    public function SJIS形式でCSVを出力できる()
    {
        Sanctum::actingAs($this->staff, ['*'], 'staff');

        $this->createAttendanceTestData();

        $response = $this->postJson('/api/export/attendance', [
            'start_date' => '2024-01-01',
            'end_date' => '2024-01-01',
            'user_ids' => [$this->user->id],
            'format' => 'sjis',
        ]);

        $response->assertStatus(200);
        
        // SJIS BOMの確認
        $content = $response->getContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
    }

    /** @test */
    public function CSVファイル名が適切に生成される()
    {
        Sanctum::actingAs($this->staff, ['*'], 'staff');

        $this->createAttendanceTestData();

        $response = $this->postJson('/api/export/attendance', [
            'start_date' => '2024-01-01',
            'end_date' => '2024-01-03',
        ]);

        $response->assertStatus(200);
        
        $contentDisposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('attendance_2024-01-01_2024-01-03_', $contentDisposition);
        $this->assertStringEndsWith('.csv"', $contentDisposition);
    }

    private function createAttendanceTestData(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $date = "2024-01-0{$i}";
            
            AttendancePlan::create([
                'user_id' => $this->user->id,
                'plan_date' => $date,
                'plan_time_slot' => 'full',
                'plan_type' => 'onsite',
            ]);
            
            AttendanceRecord::create([
                'user_id' => $this->user->id,
                'record_date' => $date,
                'record_time_slot' => 'full',
                'attendance_type' => 'onsite',
                'source' => 'self',
            ]);
        }
    }

    private function createReportTestData(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $date = "2024-01-0{$i}";
            
            DailyReportMorning::create([
                'user_id' => $this->user->id,
                'report_date' => $date,
                'sleep_rating' => 3,
                'stress_rating' => 2,
                'meal_rating' => 3,
                'bed_time_local' => '23:00:00',
                'wake_time_local' => '07:00:00',
                'mood_score' => 8,
            ]);
            
            DailyReportEvening::create([
                'user_id' => $this->user->id,
                'report_date' => $date,
                'training_summary' => 'パソコン訓練',
                'training_feedback' => '集中して取り組めました',
            ]);
        }
    }

    private function createKpiTestData(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            $date = now()->subDays($i-1)->format('Y-m-d');
            
            AttendancePlan::create([
                'user_id' => $this->user->id,
                'plan_date' => $date,
                'plan_time_slot' => 'full',
                'plan_type' => 'onsite',
            ]);
            
            if ($i <= 25) { // 25/30日出席
                AttendanceRecord::create([
                    'user_id' => $this->user->id,
                    'record_date' => $date,
                    'record_time_slot' => 'full',
                    'attendance_type' => 'onsite',
                    'source' => 'self',
                ]);
            }
            
            if ($i <= 20) { // 20/30日日報
                DailyReportMorning::create([
                    'user_id' => $this->user->id,
                    'report_date' => $date,
                    'sleep_rating' => rand(1, 3),
                    'stress_rating' => rand(1, 3),
                    'meal_rating' => rand(1, 3),
                    'bed_time_local' => '23:00:00',
                    'wake_time_local' => '07:00:00',
                    'mood_score' => rand(1, 10),
                ]);
            }
        }
    }
}