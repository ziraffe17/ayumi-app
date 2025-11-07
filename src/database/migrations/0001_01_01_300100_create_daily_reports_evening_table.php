<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('daily_reports_evening', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->date('report_date');
            $table->text('training_summary')->nullable();     // 訓練内容
            $table->text('training_reflection')->nullable();  // 訓練の振り返り
            $table->text('condition_note')->nullable();       // 体調について
            $table->text('other_note')->nullable();           // その他
            $table->timestamps();

            $table->unique(['user_id','report_date'], 'uniq_user_evening_date');
            $table->foreign('user_id')->references('id')->on('users');
        });
    }
    
    public function down(): void {
        Schema::dropIfExists('daily_reports_evening');
    }
};