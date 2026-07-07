<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trainer_courses', function (Blueprint $table) {
            // 'per_hour'  → priced from the trainer's approved level rate; total_price = price × hours_per_session × total_sessions
            // 'total'     → original_price/promo_price are the whole package price as entered, no per-hour breakdown
            $table->enum('price_type', ['per_hour', 'total'])->default('per_hour')->after('level_id');
        });
    }

    public function down(): void
    {
        Schema::table('trainer_courses', function (Blueprint $table) {
            $table->dropColumn('price_type');
        });
    }
};
