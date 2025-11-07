<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('daily_reports_morning', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->date('report_date');
            
            // 基本評価（3段階：◯=3, △=2, ✕=1）
            $table->tinyInteger('sleep_rating')->unsigned(); // 3=◯ / 2=△ / 1=✕
            $table->tinyInteger('stress_rating')->unsigned(); // 3=◯ / 2=△ / 1=✕
            $table->tinyInteger('meal_rating')->unsigned(); // 3=◯ / 2=△ / 1=✕
            
            // 睡眠詳細
            $table->time('bed_time_local')->nullable(); // HH:mm（JST入力）
            $table->time('wake_time_local')->nullable(); // HH:mm（JST入力）
            $table->datetime('bed_at')->nullable(); // UTC保存（ローカル入力をサーバー変換）
            $table->datetime('wake_at')->nullable(); // UTC保存（ローカル入力をサーバー変換）
            $table->integer('sleep_minutes')->nullable(); // 0–960（自動算出）
            
            // 睡眠の質
            $table->tinyInteger('mid_awaken_count')->unsigned()->default(0); // 0=なし, 1=1回, 2=2回...（1-10）
            $table->boolean('is_early_awaken')->default(false); // 0=なし, 1=あり
            
            // 生活習慣
            $table->boolean('is_breakfast_done')->default(false);
            $table->boolean('is_bathing_done')->default(false);
            $table->boolean('is_medication_taken')->nullable(); // NULL=習慣なし / 0=未 / 1=済
            
            // 気分・体調
            $table->tinyInteger('mood_score')->unsigned()->default(5); // 1–10
            $table->integer('sign_good')->default(0);
            $table->integer('sign_caution')->default(0);
            $table->integer('sign_bad')->default(0);
            
            // 相談・連絡
            $table->text('note')->nullable();
            
            $table->timestamps();

            $table->unique(['user_id','report_date'], 'uniq_user_morning_date');
            $table->index(['user_id','report_date']);
            $table->foreign('user_id')->references('id')->on('users');
        });
    }
    
    public function down(): void {
        Schema::dropIfExists('daily_reports_morning');
    }
};