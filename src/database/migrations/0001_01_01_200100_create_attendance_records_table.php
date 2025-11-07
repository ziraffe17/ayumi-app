<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->date('record_date')->comment('出席日（JST基準）');
            $table->enum('record_time_slot', ['am','pm','full'])->comment('時間帯');
            $table->enum('attendance_type', ['onsite','remote','absent'])->comment('出席種別');
            $table->text('note')->nullable()->comment('備考');
            $table->enum('source', ['self','staff'])->default('self')->comment('登録元');

            // 承認関連カラム
            $table->boolean('is_approved')->default(false)->comment('承認済みフラグ');
            $table->unsignedBigInteger('approved_by')->nullable()->comment('承認者ID');
            $table->timestamp('approved_at')->nullable()->comment('承認日時');
            $table->text('approval_note')->nullable()->comment('承認時メモ');

            // ロック関連カラム
            $table->boolean('is_locked')->default(false)->comment('ロック済みフラグ');
            $table->unsignedBigInteger('locked_by')->nullable()->comment('ロック者ID');
            $table->timestamp('locked_at')->nullable()->comment('ロック日時');

            $table->timestamps();

            // ユニーク制約・インデックス
            $table->unique(['user_id','record_date','record_time_slot'], 'uniq_user_record_date_slot');
            $table->index(['user_id','record_date'], 'idx_user_record_date');
            $table->index(['is_approved', 'approved_at'], 'idx_approval');
            $table->index(['is_locked', 'locked_at'], 'idx_lock');

            // 外部キー制約
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('approved_by')->references('id')->on('staffs')->onDelete('set null');
            $table->foreign('locked_by')->references('id')->on('staffs')->onDelete('set null');
        });
    }

    public function down(): void {
        Schema::dropIfExists('attendance_records');
    }
};