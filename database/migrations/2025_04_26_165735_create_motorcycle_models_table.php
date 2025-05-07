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
        $table->string('name')->unique(); // CBR600RR, Ninja 400, etc.
        $table->foreignId('type_id')->constrained('motorcycle_types')->onDelete('cascade');
        $table->timestamps();
    });

    // Insertion des modèles (exemple)
    // ⚠️ S'assurer que les marques et types existent (Honda, Yamaha, etc.)
    $brands = DB::table('motorcycle_brands')->pluck('id', 'name');
    $types = DB::table('motorcycle_types')->pluck('id', 'name');

    DB::table('motorcycle_models')->insert([
        [
            'name' => 'CBR600RR',
            'brand_id' => $brands['Honda'] ?? 1,
            'type_id' => $types['Sport'] ?? 1,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'name' => 'Ninja 400',
            'brand_id' => $brands['Kawasaki'] ?? 1,
            'type_id' => $types['Sport'] ?? 1,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'name' => 'MT-07',
            'brand_id' => $brands['Yamaha'] ?? 1,
            'type_id' => $types['Naked'] ?? 1,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);
}
    public function down(): void
    {
        Schema::dropIfExists('motorcycle_models');
    }
};
