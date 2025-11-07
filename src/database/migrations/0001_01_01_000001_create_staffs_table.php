<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('staffs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');

            // RBAC
            $table->enum('role', ['admin','staff'])->default('staff')->index();

            // Email 2FA（メールコード式）
            $table->string('two_factor_email_code', 10)->nullable();
            $table->timestamp('two_factor_expires_at')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();

            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->unsignedInteger('failed_attempts')->default(0);
            $table->boolean('is_active')->default(true);

            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['role','is_active']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('staffs');
    }
};
