<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('service_provider_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('service_providers')->onDelete('cascade');
            $table->string('image_url');
            $table->text('caption')->nullable();
            $table->text('caption_ar')->nullable();
            $table->integer('order_position')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->timestamps();

            $table->index(['provider_id', 'order_position']);
            $table->index('is_featured');
        });
    }

    public function down()
    {
        Schema::dropIfExists('service_provider_images');
    }
};