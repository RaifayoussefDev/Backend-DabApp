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
        // Create cities table
        Schema::create('cities', function (Blueprint $table) {
            $table->id(); // Auto-incrementing ID
            $table->string('name'); // Name of the city
            $table->foreignId('country_id') // Foreign key for countries table
                  ->constrained('countries') // Set foreign key constraint
                  ->onDelete('cascade'); // Ensure cities are deleted if the country is deleted
            $table->timestamps(); // created_at, updated_at
        });

        // Insert default cities for each country
        // Saudi Arabia cities
        $saudiId = DB::table('countries')->where('name', 'SAUDI')->value('id');
        DB::table('cities')->insert([
            ['name' => 'Riyadh', 'country_id' => $saudiId, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Jeddah', 'country_id' => $saudiId, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Mecca', 'country_id' => $saudiId, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // United Arab Emirates cities
        $emaratId = DB::table('countries')->where('name', 'EMARAT')->value('id');
        DB::table('cities')->insert([
            ['name' => 'Dubai', 'country_id' => $emaratId, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Abu Dhabi', 'country_id' => $emaratId, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Sharjah', 'country_id' => $emaratId, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Kuwait cities
        $kuwaitId = DB::table('countries')->where('name', 'KWAIT')->value('id');
        DB::table('cities')->insert([
            ['name' => 'Kuwait City', 'country_id' => $kuwaitId, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Salmiya', 'country_id' => $kuwaitId, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Hawally', 'country_id' => $kuwaitId, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the cities table if it exists
        Schema::dropIfExists('cities');
    }
};
