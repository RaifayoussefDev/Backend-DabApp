<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expertise_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique(); // tire_repair, fuel, mechanical, towing, first_aid, ev_support
            $table->string('icon');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expertise_types');
    }
};
