<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('motorcycle_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('model_id')->constrained('motorcycle_models')->onDelete('cascade');
            $table->integer('year'); // Example: 2015, 2016
            $table->timestamps();
        });

        // Récupérer les modèles par nom (assure-toi qu'ils existent)
        $models = DB::table('motorcycle_models')->pluck('id', 'name');

        DB::table('motorcycle_years')->insert([
            [
                'model_id' => $models['CBR600RR'] ?? 1,
                'year' => 2015,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'model_id' => $models['CBR600RR'] ?? 1,
                'year' => 2016,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'model_id' => $models['Ninja 400'] ?? 2,
                'year' => 2018,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'model_id' => $models['MT-07'] ?? 3,
                'year' => 2020,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('motorcycle_years');
    }
};
