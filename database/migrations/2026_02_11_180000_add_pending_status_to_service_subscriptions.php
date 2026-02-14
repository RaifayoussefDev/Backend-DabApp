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
        // We use raw SQL because altering ENUMs in Laravel/Doctrine is not straightforward
        DB::statement("ALTER TABLE service_subscriptions MODIFY COLUMN status ENUM('active', 'cancelled', 'expired', 'payment_failed', 'trial', 'pending') DEFAULT 'active'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original enum
        DB::statement("ALTER TABLE service_subscriptions MODIFY COLUMN status ENUM('active', 'cancelled', 'expired', 'payment_failed', 'trial') DEFAULT 'active'");
    }
};
