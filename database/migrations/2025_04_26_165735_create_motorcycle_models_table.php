<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
{
    Schema::create('motorcycle_models', function (Blueprint $table) {
        $table->id();
        $table->foreignId('brand_id')->constrained('motorcycle_brands')->onDelete('cascade');
        $table->string('name'); // CBR600RR, Ninja 400, etc.
        $table->foreignId('type_id')->constrained('motorcycle_types')->onDelete('cascade');
        $table->timestamps();
    });

    // Load initial data from SQL file
    $path = database_path('sql/motorcycle_models.sql');
    DB::unprepared(file_get_contents($path));

}
    public function down(): void
    {
        Schema::dropIfExists('motorcycle_models');
    }
};
