<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A payout's linked PaymentSplit rows get released (payout_id set back to null) if the
     * payout is rejected, so the trainer can reclaim that money in a future request. That
     * makes the live splits() relation empty for any rejected payout — this snapshot
     * preserves what the payout originally consolidated, independent of where those splits
     * end up pointing afterward.
     */
    public function up(): void
    {
        Schema::table('trainer_payouts', function (Blueprint $table) {
            $table->json('splits_snapshot')->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('trainer_payouts', function (Blueprint $table) {
            $table->dropColumn('splits_snapshot');
        });
    }
};
