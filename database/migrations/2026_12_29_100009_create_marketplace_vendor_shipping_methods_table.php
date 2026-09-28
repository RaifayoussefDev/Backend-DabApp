<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_vendor_shipping_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('marketplace_vendors')->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained('marketplace_shipping_providers')->restrictOnDelete();
            $table->boolean('uses_own_api')->default(false);
            $table->text('credentials')->nullable(); // encrypted cast, never exposed by the API
            $table->decimal('flat_rate', 10, 2)->default(0); // v1: flat rate per method
            $table->unsignedSmallInteger('estimated_days_min')->nullable();
            $table->unsignedSmallInteger('estimated_days_max')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['vendor_id', 'is_active'], 'mp_vendor_ship_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_vendor_shipping_methods');
    }
};
