<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveCountryIdFromPlateTypes extends Migration
{
    public function up(): void
    {
        Schema::table('plate_types', function (Blueprint $table) {
            $table->dropForeign(['country_id']); // Supprime la contrainte de clé étrangère
            $table->dropColumn('country_id');    // Supprime la colonne
        });
    }

    public function down(): void
    {
        Schema::table('plate_types', function (Blueprint $table) {
            $table->foreignId('country_id')
                ->constrained()
                ->onDelete('cascade');
        });
    }
}

