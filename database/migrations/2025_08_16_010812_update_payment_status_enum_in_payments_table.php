<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        DB::statement("ALTER TABLE payments MODIFY COLUMN payment_status ENUM('pending', 'initiated', 'completed', 'failed') NOT NULL DEFAULT 'pending'");
    }

    public function down()
    {
        DB::statement("ALTER TABLE payments MODIFY COLUMN payment_status ENUM('pending', 'completed', 'failed') NOT NULL DEFAULT 'pending'");
    }

};
