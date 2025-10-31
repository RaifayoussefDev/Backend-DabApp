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
        Schema::create('newsletter_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscriber_id')->constrained('newsletter_subscribers')->onDelete('cascade');
            $table->boolean('receive_new_articles')->default(true);
            $table->boolean('receive_new_listings')->default(true);
            $table->boolean('receive_promotions')->default(true);
            $table->boolean('receive_weekly_digest')->default(false);
            $table->string('frequency', 50)->default('immediate')->comment('immediate, daily, weekly');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('newsletter_preferences');
    }
};
