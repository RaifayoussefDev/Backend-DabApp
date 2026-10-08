<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_shipping_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique(); // dhl, aramex, smsa, dabapp, manual
            $table->boolean('requires_api_key')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_shipping_providers');
    }
};
