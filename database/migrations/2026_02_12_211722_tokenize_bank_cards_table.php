<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bank_cards', function (Blueprint $table) {
            // Drop sensitive columns if they exist
            if (Schema::hasColumn('bank_cards', 'card_number')) {
                $table->dropColumn(['card_number', 'cvv', 'expiration_date']);
            }

            // Add token columns
            $table->string('payment_token')->nullable()->after('user_id'); // Store PayTabs token
            $table->string('last_four', 4)->nullable()->after('payment_token');
            $table->string('brand', 20)->nullable()->after('last_four'); // Visa, Mastercard
            $table->string('expiry_month', 2)->nullable()->after('brand');
            $table->integer('expiry_year')->nullable()->after('expiry_month');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bank_cards', function (Blueprint $table) {
            $table->dropColumn(['payment_token', 'last_four', 'brand', 'expiry_month', 'expiry_year']);
            $table->string('card_number')->nullable();
            $table->string('cvv')->nullable();
            $table->string('expiration_date')->nullable();
        });
    }
};
