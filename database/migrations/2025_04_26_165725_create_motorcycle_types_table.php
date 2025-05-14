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
            $table->string('name')->unique();
            $table->timestamps();
        });

        $path = database_path('sql/motorcycle_types.sql');
        DB::unprepared(file_get_contents($path));
    }



    public function down(): void
    {
        Schema::dropIfExists('motorcycle_types');
    }
};
