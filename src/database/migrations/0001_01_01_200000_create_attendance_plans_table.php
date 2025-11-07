<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('attendance_plans', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->date('plan_date');
            $table->enum('plan_time_slot', ['am','pm','full']);
            $table->enum('plan_type', ['onsite','remote','off'])->default('onsite');
            $table->text('note')->nullable();

            $table->boolean('is_holiday')->default(false);
            $table->string('holiday_name', 50)->nullable();
            $table->enum('template_source', ['prev_month','weekday'])->nullable();

            $table->timestamps();

            $table->unique(['user_id','plan_date','plan_time_slot'], 'uniq_user_plan_date_slot');
            $table->index(['user_id','plan_date'], 'idx_user_plan_date');

            $table->foreign('user_id')->references('id')->on('users');
        });
    }
    public function down(): void {
        Schema::dropIfExists('attendance_plans');
    }
};
