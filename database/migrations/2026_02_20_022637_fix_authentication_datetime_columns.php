<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('authentication', function (Blueprint $table) {
            $table->dateTime('token_expiration')->nullable()->change();
            $table->dateTime('refresh_token_expiration')->nullable()->change();
            $table->dateTime('connection_date')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('authentication', function (Blueprint $table) {
            $table->timestamp('token_expiration')->nullable()->change();
            $table->timestamp('refresh_token_expiration')->nullable()->change();
            $table->timestamp('connection_date')->nullable()->change();
        });
    }
};
