<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 100)->primary()->comment('設定キー');
            $table->text('value')->nullable()->comment('設定値');
            $table->string('type', 20)->default('string')->comment('データ型: string, integer, boolean, json');
            $table->text('description')->nullable()->comment('設定の説明');
            $table->timestamps();
        });

        // デフォルト値を挿入
        DB::table('settings')->insert([
            [
                'key' => 'facility_capacity',
                'value' => '20',
                'type' => 'integer',
                'description' => '事業所の定員数',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
