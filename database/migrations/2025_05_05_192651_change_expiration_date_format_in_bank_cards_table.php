<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    // In your 2025_05_05_192651_change_expiration_date_format_in_bank_cards_table.php
    public function up()
    {
        Schema::table('bank_cards', function (Blueprint $table) {
            $table->string('expiration_date', 5)->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('bank_cards', function (Blueprint $table) {
            $table->date('expiration_date')->nullable()->change();
        });
    }
};
