<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('notification_templates')
            ->where('type', 'dealer_approved')
            ->update([
                'title_template_ar' => 'تمت الموافقة على حالة التاجر', // Correct Arabic translation
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('notification_templates')
            ->where('type', 'dealer_approved')
            ->update([
                'title_template_ar' => 'تمت الموافقة sur le statut de revendeur', // Revert to old mixed one (optional, but standard rollback)
                'updated_at' => now(),
            ]);
    }
};
