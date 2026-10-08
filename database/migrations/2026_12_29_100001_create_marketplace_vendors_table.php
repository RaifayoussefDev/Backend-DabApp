<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_vendors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete(); // shop owner
            $table->string('shop_name');
            $table->string('shop_name_ar')->nullable();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->text('description_ar')->nullable();

            // Branding (All Shops screen)
            $table->string('logo_path')->nullable();
            $table->string('cover_image_path')->nullable();
            $table->string('brand_color', 7)->nullable(); // hex, e.g. #E63946

            // Contact / location
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();

            // Moderation
            $table->enum('status', ['pending', 'active', 'suspended', 'rejected'])->default('pending');
            $table->text('status_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();

            // Money
            $table->decimal('commission_override', 5, 2)->nullable(); // % ; null = category/global rate
            $table->string('bank_name')->nullable();
            $table->text('iban')->nullable(); // encrypted cast on the model

            // Denormalised counters
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('reviews_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_vendors');
    }
};
