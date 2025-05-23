<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pricing_rules_licence_plate', function (Blueprint $table) {
            // Pour supprimer une colonne avec une clé étrangère, il faut d'abord drop la contrainte étrangère
            $table->dropForeign(['plate_type_id']); // supprime la FK
            $table->dropColumn('plate_type_id'); // supprime la colonne
        });
    }

    public function down(): void
    {
        Schema::table('pricing_rules_licence_plate', function (Blueprint $table) {
            $table->foreignId('plate_type_id')->constrained('plate_types')->onDelete('cascade');
        });
    }
};
