<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class StaffAccountSeeder extends Seeder
{
    /**
     * 職員アカウントを作成
     * - admin@example.com: 管理者（role=admin）
     * - staff@example.com: 一般職員（role=staff）
     */
    public function run(): void
    {
        // 既存のデータを削除
        DB::table('staffs')->whereIn('email', ['admin@example.com', 'staff@example.com'])->delete();

        // 管理者アカウント
        DB::table('staffs')->insert([
            'name' => '管理者 太郎',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 一般職員アカウント
        DB::table('staffs')->insert([
            'name' => '職員 花子',
            'email' => 'staff@example.com',
            'password' => Hash::make('password'),
            'role' => 'staff',
            'is_active' => true,
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        echo "✓ 職員アカウントを作成しました\n";
        echo "  - admin@example.com (管理者)\n";
        echo "  - staff@example.com (一般職員)\n";
    }
}
