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
        Schema::table('trainers', function (Blueprint $table) {
            if (!Schema::hasColumn('trainers', 'price_per_mission')) {
                $table->decimal('price_per_mission', 10, 2)->nullable()->after('price_per_hour');
            }
        });
    }

    public function down(): void
    {
        Schema::table('trainers', function (Blueprint $table) {
            if (Schema::hasColumn('trainers', 'price_per_mission')) {
                $table->dropColumn('price_per_mission');
            }
        });
    }
};
