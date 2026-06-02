<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('provider_service_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('service_providers')->onDelete('cascade');
            $table->foreignId('service_category_id')->constrained('service_categories')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['provider_id', 'service_category_id'], 'psc_provider_category_unique');
            $table->index('provider_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('provider_service_categories');
    }
};
