<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('actor_type', 20)->nullable();   // 'staff','user' 等
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamp('occurred_at')->useCurrent();

            $table->enum('action', [
                'login','logout','create','update','delete','export','setting',
                'two_factor_email_sent','two_factor_email_verified','two_factor_email_failed'
            ]);

            $table->string('entity', 50)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('diff_json')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->json('meta')->nullable();

            $table->index('occurred_at');
            $table->index(['actor_type','actor_id','action']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('audit_logs');
    }
};
