<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // migration: create_currencies_table.php
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code'); // e.g. 'SAR', 'AED'
            $table->string('symbol'); // e.g. 'ر.س', 'د.إ'
            $table->decimal('conversion_rate', 10, 4); // compared to base currency like USD
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
