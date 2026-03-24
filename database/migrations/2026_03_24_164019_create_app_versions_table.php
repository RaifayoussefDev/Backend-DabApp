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
        Schema::create('app_versions', function (Blueprint $table) {
            $table->id();
            $table->string('latest_version')->nullable();
            $table->string('min_supported_version')->nullable();
            $table->string('store_url_android')->nullable();
            $table->string('store_url_ios')->nullable();
            $table->boolean('force')->default(false);
            $table->timestamps();
        });

        // Insert default row
        \Illuminate\Support\Facades\DB::table('app_versions')->insert([
            'latest_version' => '1.0.0',
            'min_supported_version' => '1.0.0',
            'store_url_android' => '',
            'store_url_ios' => '',
            'force' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_versions');
    }
};
