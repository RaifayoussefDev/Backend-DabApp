<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB; // <-- Don't forget to import this!

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('card_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // Ex: Visa, Mastercard, etc.
            $table->timestamps();
        });

        // Insert default card types
        DB::table('card_types')->insert([
            ['name' => 'VISA', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'MASTER CARD', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'TAMARA', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'TABBY', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('card_types');
    }
};
