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
        Schema::table('soom_negotiations', function (Blueprint $table) {
            // Rendre la colonne response nullable
            $table->string('response')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('soom_negotiations', function (Blueprint $table) {
            // Remettre la colonne comme non-nullable si besoin
            $table->string('response')->nullable(false)->change();
        });
    }
};
