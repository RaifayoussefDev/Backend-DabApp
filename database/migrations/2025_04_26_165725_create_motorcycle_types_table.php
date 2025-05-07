<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('motorcycle_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // Sport, Touring, etc.
            $table->timestamps();
        });

        DB::table('motorcycle_types')->insert([
            ['name' => 'Sport', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Touring', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Naked', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }


    public function down(): void
    {
        Schema::dropIfExists('motorcycle_types');
    }
};
