<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;


class PlateColorsTableSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('plate_colors')->insert([
            ['type_id' => 1, 'name' => 'green'],
            ['type_id' => 1, 'name' => 'orange'],
            ['type_id' => 1, 'name' => 'violet'],
        ]);
    }
}
