<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class AdditionalUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * user_id=2: 利用開始 2025-08-01
     * user_id=3: 利用開始 2025-09-01
     */
    public function run(): void
    {
        // ユーザー2: 8月開始
        DB::table('users')->insert([
            'id' => 2,
            'login_code' => 'u0002',
            'password' => Hash::make('password'),
            'name' => '佐藤花子',
            'name_kana' => 'サトウハナコ',
            'email' => 'sato.hanako@example.com',
            'start_date' => '2025-08-01',
            'care_notes_enc' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // ユーザー3: 9月開始
        DB::table('users')->insert([
            'id' => 3,
            'login_code' => 'u0003',
            'password' => Hash::make('password'),
            'name' => '鈴木太郎',
            'name_kana' => 'スズキタロウ',
            'email' => 'suzuki.taro@example.com',
            'start_date' => '2025-09-01',
            'care_notes_enc' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        echo "✓ テスト利用者2名を追加しました（user_id=2,3）\n";
    }
}
