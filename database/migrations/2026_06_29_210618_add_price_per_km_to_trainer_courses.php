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
        Schema::table('trainer_courses', function (Blueprint $table) {
            // Price the trainer charges per km when can_travel=true
            $table->decimal('price_per_km', 8, 2)->nullable()->after('can_travel');
        });
    }

    public function down(): void
    {
        Schema::table('trainer_courses', function (Blueprint $table) {
            $table->dropColumn('price_per_km');
        });
    }
};
