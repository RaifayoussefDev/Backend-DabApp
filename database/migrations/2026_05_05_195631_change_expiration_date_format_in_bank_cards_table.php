<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('bank_cards', function (Blueprint $table) {
            $table->string('expiration_date', 5)->change();
        });
    }

    public function down()
    {
        Schema::table('bank_cards', function (Blueprint $table) {
            $table->date('expiration_date')->change();
        });
    }
};
