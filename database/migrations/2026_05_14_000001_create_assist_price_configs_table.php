<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assist_price_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('price_min')->default(0);
            $table->unsignedInteger('price_max')->default(150);
            $table->unsignedInteger('price_step')->default(50)->comment('Proposed price must be a multiple of this value');
            $table->timestamps();
        });

        // Seed the single config row
        DB::table('assist_price_configs')->insert([
            'price_min'  => 0,
            'price_max'  => 150,
            'price_step' => 50,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('assist_price_configs');
    }
};
