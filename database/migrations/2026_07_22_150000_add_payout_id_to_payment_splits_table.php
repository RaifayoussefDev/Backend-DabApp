<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Moves the payout <-> payment_split relationship from "one payout per booking"
     * (trainer_payouts.payment_split_id, one-to-one) to "one monthly payout covers
     * many bookings" (payment_splits.payout_id, one-to-many). Old single-linked
     * payouts keep working via the existing payment_split_id column.
     */
    public function up(): void
    {
        Schema::table('payment_splits', function (Blueprint $table) {
            $table->foreignId('payout_id')->nullable()->after('trainer_id')
                ->constrained('trainer_payouts')->nullOnDelete();
        });

        // No doctrine/dbal installed — use a raw MODIFY instead of ->nullable()->change().
        DB::statement('ALTER TABLE trainer_payouts MODIFY payment_split_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        Schema::table('payment_splits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payout_id');
        });

        DB::statement('ALTER TABLE trainer_payouts MODIFY payment_split_id BIGINT UNSIGNED NOT NULL');
    }
};
