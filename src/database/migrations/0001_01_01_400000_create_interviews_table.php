<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('interviews', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->dateTime('interview_at');     // UTC保存・JST表示はアプリ層で
            $table->text('summary')->nullable();
            $table->text('next_action')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['user_id','interview_at']);
            $table->foreign('user_id')->references('id')->on('users');
        });
    }
    public function down(): void {
        Schema::dropIfExists('interviews');
    }
};
