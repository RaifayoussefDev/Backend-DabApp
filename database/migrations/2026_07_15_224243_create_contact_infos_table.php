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
        Schema::create('contact_infos', function (Blueprint $table) {
            $table->id();
            $table->string('address_en')->nullable();
            $table->string('address_ar')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('hours_en')->nullable();
            $table->string('hours_ar')->nullable();
            $table->timestamps();
        });

        // Insert default row
        \Illuminate\Support\Facades\DB::table('contact_infos')->insert([
            'address_en' => 'King Fahd Road, Riyadh, Saudi Arabia',
            'address_ar' => 'طريق الملك فهد، الرياض، المملكة العربية السعودية',
            'phone' => '+966 12 345 6789',
            'email' => 'support@dabapp.co',
            'hours_en' => 'Sun - Thu: 9:00 AM - 6:00 PM',
            'hours_ar' => 'الأحد - الخميس: 9:00 صباحاً - 6:00 مساءً',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_infos');
    }
};
