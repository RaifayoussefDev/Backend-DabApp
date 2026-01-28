<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('service_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('service_bookings')->onDelete('cascade');
            $table->foreignId('service_id')->constrained('services')->onDelete('cascade');
            $table->foreignId('provider_id')->constrained('service_providers')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->tinyInteger('rating')->comment('1-5 stars');
            $table->text('comment')->nullable();
            $table->text('comment_ar')->nullable();
            $table->boolean('is_approved')->default(true);
            $table->text('provider_response')->nullable();
            $table->text('provider_response_ar')->nullable();
            $table->timestamp('provider_response_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['service_id', 'is_approved']);
            $table->index(['provider_id', 'is_approved']);
            $table->index('rating');
        });
    }

    public function down()
    {
        Schema::dropIfExists('service_reviews');
    }
};