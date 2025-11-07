<?php

namespace Tests\Feature;

use App\Models\Staff;
use App\Models\Holiday;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class HolidayManagementTest extends TestCase
{
    use RefreshDatabase;

    private Staff $admin;
    private Staff $staff;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->admin = Staff::factory()->create([
            'role' => 'admin',
            'is_active' => 1,
        ]);
        
        $this->staff = Staff::factory()->create([
            'role' => 'staff',
            'is_active' => 1,
        ]);
    }

    /** @test */
    public function 管理者は祝日一覧を取得できる()
    {
        Sanctum::actingAs($this->admin, ['*'], 'staff');

        // テスト用祝日作成
        Holiday::create([
            'holiday_date' => '2024-01-01',
            'name' => '元日',
            'source' => 'manual',
            'imported_at' => now(),
        ]);

        $response = $this->getJson('/api/holidays?year=2024');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'holidays' => [
                        '*' => ['date', 'name', 'source', 'imported_at']
                    ],
                    'count'
                ]
            ]);
    }

    /** @test */
    public function 管理者は祝日を手動追加できる()
    {
        Sanctum::actingAs($this->admin, ['*'], 'staff');

        $response = $this->postJson('/api/holidays', [
            'date' => '2024-12-25',
            'name' => 'クリスマス',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => '祝日を追加しました',
            ]);

        $this->assertDatabaseHas('holidays', [
            'holiday_date' => '2024-12-25',
            'name' => 'クリスマス',
            'source' => 'manual',
        ]);
    }

    /** @test */
    public function 管理者は祝日を削除できる()
    {
        Sanctum::actingAs($this->admin, ['*'], 'staff');

        $holiday = Holiday::create([
            'holiday_date' => '2024-12-25',
            'name' => 'クリスマス',
            'source' => 'manual',
            'imported_at' => now(),
        ]);

        $response = $this->deleteJson("/api/holidays/{$holiday->holiday_date}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => '祝日を削除しました',
            ]);

        $this->assertDatabaseMissing('holidays', [
            'holiday_date' => '2024-12-25',
        ]);
    }

    /** @test */
    public function CSVファイルから祝日を一括取り込みできる()
    {
        Sanctum::actingAs($this->admin, ['*'], 'staff');

        // CSVファイル作成
        $csvContent = "日付,祝日名\n2024-01-01,元日\n2024-01-08,成人の日\n2024-02-11,建国記念の日";
        $csvFile = UploadedFile::fake()->createWithContent('holidays.csv', $csvContent);

        $response = $this->postJson('/api/holidays/import/csv', [
            'csv_file' => $csvFile,
            'year' => 2024,
            'overwrite' => true,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'imported_count',
                    'skipped_count',
                    'total_processed',
                    'errors'
                ]
            ]);

        $this->assertDatabaseHas('holidays', [
            'holiday_date' => '2024-01-01',
            'name' => '元日',
            'source' => 'import',
        ]);

        $this->assertDatabaseHas('holidays', [
            'holiday_date' => '2024-01-08',
            'name' => '成人の日',
            'source' => 'import',
        ]);
    }

    /** @test */
    public function 職員は祝日管理にアクセスできない()
    {
        Sanctum::actingAs($this->staff, ['*'], 'staff');

        $response = $this->postJson('/api/holidays', [
            'date' => '2024-12-25',
            'name' => 'クリスマス',
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function 重複する祝日は登録できない()
    {
        Sanctum::actingAs($this->admin, ['*'], 'staff');

        Holiday::create([
            'holiday_date' => '2024-01-01',
            'name' => '元日',
            'source' => 'manual',
            'imported_at' => now(),
        ]);

        $response = $this->postJson('/api/holidays', [
            'date' => '2024-01-01',
            'name' => '元日（重複）',
        ]);

        $response->assertStatus(422);
    }
}