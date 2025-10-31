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
        Schema::create('poi_services', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Oil Change, Tire Replacement, Engine Repair, etc.');
            $table->foreignId('type_id')->nullable()->constrained('poi_types')->onDelete('set null')->comment('Related POI type');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('poi_services');
    }
};
