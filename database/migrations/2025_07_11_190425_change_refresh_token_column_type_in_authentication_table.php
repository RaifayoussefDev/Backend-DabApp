<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('authentication', function (Blueprint $table) {
            $table->text('refresh_token')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('authentication', function (Blueprint $table) {
            $table->string('refresh_token', 255)->nullable()->change();
        });
    }
};
