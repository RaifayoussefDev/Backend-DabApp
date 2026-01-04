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
        Schema::create('report_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('type')->index(); // e.g 'guide', 'listing', 'comment', 'default'
            $table->string('label_en');
            $table->string('label_ar');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_reasons');
    }
};
