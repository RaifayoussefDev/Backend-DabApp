<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_product_reviews', function (Blueprint $table) {
            // "What did you like the most?" chips, e.g. ["Build Quality","Delivery Time"]. A fixed client-side list, stored as-is.
            $table->json('tags')->nullable()->after('comment');
            // Photos attached to the review, URLs from POST /marketplace/upload-image (type=product).
            $table->json('images')->nullable()->after('tags');
            // "Hide my name from the review": the API still knows the author, the public response just omits the name.
            $table->boolean('is_anonymous')->default(false)->after('images');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_product_reviews', function (Blueprint $table) {
            $table->dropColumn(['tags', 'images', 'is_anonymous']);
        });
    }
};
