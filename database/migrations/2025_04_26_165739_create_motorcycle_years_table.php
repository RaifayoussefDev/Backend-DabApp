<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('motorcycle_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('model_id')->constrained('motorcycle_models')->onDelete('cascade');
            $table->integer('year'); // Example: 2015, 2016
            $table->timestamps();
        });

        // Load initial data from SQL file
        $path = database_path('sql/motorcycle_years.sql');
        DB::unprepared(file_get_contents($path));
    }

    public function down(): void
    {
        Schema::dropIfExists('motorcycle_years');
    }
};
