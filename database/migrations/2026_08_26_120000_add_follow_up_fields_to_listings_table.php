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
        // `published_at` is used throughout the app (Listing::scopePublished, publish flows)
        // but no migration ever actually created it — add it defensively if it's missing.
        if (!Schema::hasColumn('listings', 'published_at')) {
            Schema::table('listings', function (Blueprint $table) {
                $table->timestamp('published_at')->nullable()->after('status');
            });
        }

        Schema::table('listings', function (Blueprint $table) {
            $table->timestamp('follow_up_sent_at')->nullable();
            $table->timestamp('follow_up_responded_at')->nullable();
            $table->string('follow_up_response')->nullable();
            $table->string('sale_channel')->nullable();
            $table->string('reason_not_sold')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn([
                'follow_up_sent_at',
                'follow_up_responded_at',
                'follow_up_response',
                'sale_channel',
                'reason_not_sold',
            ]);
        });
    }
};
