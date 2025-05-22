<?php

namespace Database\Seeders;
use Illuminate\Database\Seeder;
use App\Models\Currency;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        Currency::insert([
            ['code' => 'SAR', 'symbol' => 'ر.س', 'conversion_rate' => 3.75, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'AED', 'symbol' => 'د.إ', 'conversion_rate' => 3.67, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'KWD', 'symbol' => 'د.ك', 'conversion_rate' => 0.31, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}

