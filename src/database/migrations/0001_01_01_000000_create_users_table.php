<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('name_kana')->nullable();
            $table->string('login_code')->unique();          // 利用者ログインID
            $table->string('email')->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->longText('care_notes_enc')->nullable();  // 機微情報（アプリ層暗号化）
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->unsignedInteger('failed_attempts')->default(0);
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active','start_date','end_date']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('users');
    }
};
