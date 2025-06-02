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
        Schema::table('listings', function (Blueprint $table) {
            // Make the price column non-nullable
            $table->decimal('price', 10, 2)->nullable(false)->change();
            // Add a default value of 0.00 to the price column
            $table->decimal('price', 10, 2)->default(0.00)->change();
            //
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            // Make the price column nullable
            $table->decimal('price', 10, 2)->nullable()->change();

            //
        });
    }
};
