<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB; // Import DB facade for inserting data

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable(); // Optional ISO code like 'FR', 'US'
            $table->timestamps();
        });

        // Insert default countries
        DB::table('countries')->insert([
            ['name' => 'SAUDI', 'code' => 'SA', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'EMARAT', 'code' => 'AE', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'KWAIT', 'code' => 'KW', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
