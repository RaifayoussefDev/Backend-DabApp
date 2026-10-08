<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('marketplace_vendors')->restrictOnDelete();
            $table->foreignId('category_id')->constrained('marketplace_categories')->restrictOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained('marketplace_brands')->nullOnDelete();

            $table->string('reference')->unique(); // SKU, one product = one SKU (no variants)
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->text('description')->nullable();
            $table->text('description_ar')->nullable();

            $table->decimal('price', 12, 2);
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->enum('condition', ['new', 'used'])->default('new');
            $table->enum('status', ['draft', 'pending_review', 'active', 'inactive', 'rejected'])->default('draft');
            $table->text('status_reason')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->timestamp('published_at')->nullable();

            // Denormalised counters (kept in sync by the app)
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('reviews_count')->default(0);
            $table->unsignedInteger('likes_count')->default(0);
            $table->unsignedInteger('sales_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'category_id'], 'mp_products_status_category_idx');
            $table->index(['vendor_id', 'status'], 'mp_products_vendor_status_idx');
            $table->index(['status', 'price'], 'mp_products_status_price_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_products');
    }
};
