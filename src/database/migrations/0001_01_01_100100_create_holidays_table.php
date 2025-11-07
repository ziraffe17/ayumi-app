<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('holidays', function (Blueprint $table) {
            $table->date('holiday_date')->primary()->comment('祝日日付（主キー、JST基準）');
            $table->string('name', 50)->comment('祝日名');
            $table->enum('source', ['government_api', 'csv', 'manual'])
                ->default('manual')
                ->comment('登録元: government_api=政府API, csv=CSV取込, manual=手動登録');
            $table->datetime('imported_at')->nullable()->comment('取込日時（UTC）');
        });
    }

    public function down(): void {
        Schema::dropIfExists('holidays');
    }
};
