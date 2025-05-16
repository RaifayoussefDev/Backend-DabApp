<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddDefaultPlateTypes extends Migration
{
    public function up(): void
    {
        // Remplace '1' par l'ID réel du pays concerné si nécessaire
        DB::table('plate_types')->insert([
            [
                'country_id' => 1,
                'name' => 'Numbers & Alphabets',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'country_id' => 1,
                'name' => 'Numbers only',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('plate_types')->whereIn('name', ['Numbers & Alphabets', 'Numbers only'])->delete();
    }
}
