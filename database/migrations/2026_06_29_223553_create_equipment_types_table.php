<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('name_ar', 100)->nullable();
            $table->string('icon', 100)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Link existing trainer_equipment rows to the catalog (nullable FK)
        Schema::table('trainer_equipment', function (Blueprint $table) {
            $table->foreignId('equipment_type_id')
                ->nullable()
                ->after('trainer_id')
                ->constrained('equipment_types')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('trainer_equipment', function (Blueprint $table) {
            $table->dropForeign(['equipment_type_id']);
            $table->dropColumn('equipment_type_id');
        });

        Schema::dropIfExists('equipment_types');
    }
};
