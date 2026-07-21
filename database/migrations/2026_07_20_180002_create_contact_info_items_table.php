<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extra, admin-managed contact channels beyond the 4 fixed fields on `contact_infos`
     * (e.g. WhatsApp, a second address, social links) — addable/removable/toggleable.
     */
    public function up(): void
    {
        Schema::create('contact_info_items', function (Blueprint $table) {
            $table->id();
            $table->string('icon')->nullable(); // PrimeNG icon class suffix, e.g. "whatsapp" -> "pi pi-whatsapp"
            $table->string('label_en');
            $table->string('label_ar')->nullable();
            $table->string('value_en');
            $table->string('value_ar')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_info_items');
    }
};
