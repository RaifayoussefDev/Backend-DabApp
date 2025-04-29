<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;


class PlateTypesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('plate_types')->insert([
            ['country_id' => 1, 'name' => 'number and alphabet'],
            ['country_id' => 1, 'name' => 'number only'],
        ]);
    }
}
