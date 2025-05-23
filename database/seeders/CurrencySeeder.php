<?php

namespace Database\Seeders;
use Illuminate\Database\Seeder;
use App\Models\CurrencyExchangeRate;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        CurrencyExchangeRate::insert([
            ['code' => 'SAR', 'symbol' => 'SAR', 'conversion_rate' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'AED', 'symbol' => 'AED', 'conversion_rate' => 0.98, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'KWD', 'symbol' => 'KWD', 'conversion_rate' => 0.082, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'MAD', 'symbol' => 'MAD', 'conversion_rate' => 2.46, 'created_at' => now(), 'updated_at' => now()],
        ]);
        
    }
}

