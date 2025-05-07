<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('motorcycle_brands', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // Honda, Yamaha, etc.
            $table->timestamps();
        });

        // Insertion des valeurs initiales
        DB::table('motorcycle_brands')->insert([
            ['name' => 'Honda', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Yamaha', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Suzuki', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Kawasaki', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Ducati', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
    public function down(): void
    {
        Schema::dropIfExists('motorcycle_brands');
    }
};
