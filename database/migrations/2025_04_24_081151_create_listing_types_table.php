<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB; // <-- Don't forget this!

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('listing_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->decimal('price', 10, 2);
            $table->timestamps();
        });

        // Insert default listing types with prices
        DB::table('listing_types')->insert([
            ['name' => 'cat A', 'price' => 10.00, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'cat B', 'price' => 20.00, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'cat C', 'price' => 30.00, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listing_types');
    }
};
