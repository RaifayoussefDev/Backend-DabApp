<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('my_garage', function (Blueprint $table) {
            // Supprimer d'abord la clé étrangère
            $table->dropForeign(['type_id']);
            // Puis supprimer la colonne
            $table->dropColumn('type_id');
        });
    }

    public function down()
    {
        Schema::table('my_garage', function (Blueprint $table) {
            $table->unsignedBigInteger('type_id')->nullable();
            // Recréer la clé étrangère si besoin
            $table->foreign('type_id')->references('id')->on('types');
        });
    }
};
