<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('trainer_equipment', function (Blueprint $table) {
            // equipment_type_id already exists — only add the unique constraint
            $table->unique(['trainer_id', 'equipment_type_id'], 'trainer_equipment_type_unique');
        });
    }

    public function down(): void
    {
        Schema::table('trainer_equipment', function (Blueprint $table) {
            $table->dropUnique('trainer_equipment_type_unique');
        });
    }
};
