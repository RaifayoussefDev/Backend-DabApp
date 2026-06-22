<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE trainer_bookings MODIFY COLUMN status ENUM('pending','accepted','confirmed','in_progress','awaiting_confirmation','completed','cancelled','rejected','disputed') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE trainer_bookings MODIFY COLUMN status ENUM('pending','accepted','confirmed','in_progress','completed','cancelled','rejected') NOT NULL DEFAULT 'pending'");
    }
};
