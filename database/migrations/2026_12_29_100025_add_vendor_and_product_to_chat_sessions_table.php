<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_sessions', function (Blueprint $table) {
            // Marketplace sessions have no booking / service provider / price.
            $table->unsignedBigInteger('booking_id')->nullable()->change();
            $table->unsignedBigInteger('provider_id')->nullable()->change();
            $table->decimal('session_price', 10, 2)->nullable()->change();

            $table->foreignId('vendor_id')->nullable()->after('provider_id')->constrained('marketplace_vendors')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->after('vendor_id')->constrained('marketplace_products')->nullOnDelete();

            $table->index(['vendor_id', 'session_status'], 'chat_sessions_vendor_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->dropForeign(['vendor_id']);
            $table->dropForeign(['product_id']);
            $table->dropIndex('chat_sessions_vendor_status_idx');
            $table->dropColumn(['vendor_id', 'product_id']);
        });

        // booking_id / provider_id / session_price stay nullable on rollback: restoring NOT NULL would
        // fail as soon as a marketplace session exists.
    }
};
