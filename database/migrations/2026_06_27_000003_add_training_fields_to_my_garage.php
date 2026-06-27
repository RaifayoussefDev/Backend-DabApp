<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('my_garage', function (Blueprint $table) {
            $table->string('plate_number')->nullable()->after('picture');
            $table->date('insurance_expiry')->nullable()->after('plate_number');
            $table->boolean('insurance_covers_training')->nullable()->default(false)->after('insurance_expiry');
        });
    }

    public function down(): void
    {
        Schema::table('my_garage', function (Blueprint $table) {
            $table->dropColumn(['plate_number', 'insurance_expiry', 'insurance_covers_training']);
        });
    }
};
