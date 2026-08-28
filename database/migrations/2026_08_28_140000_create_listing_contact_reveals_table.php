<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only audit trail: who revealed a listing seller's contact details
 * (phone / email) and when. Feeds seller-lead analytics — distinct from a plain
 * listing "view".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listing_contact_reveals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained('listings')->cascadeOnDelete();
            // The seller whose contact was shown (denormalised for fast reporting).
            $table->foreignId('seller_id')->nullable()->constrained('users')->nullOnDelete();
            // Who revealed it. The endpoint is auth-only, so this is normally set;
            // nullable keeps old rows valid if the account is later deleted.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 30)->nullable(); // echoes listings.contacting_channel
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['listing_id', 'created_at']);
            $table->index(['seller_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_contact_reveals');
    }
};
