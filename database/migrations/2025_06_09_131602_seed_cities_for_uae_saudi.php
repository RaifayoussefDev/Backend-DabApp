<?php
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class SeedCitiesForUaeSaudi extends Migration
{
    public function up()
    {
        $countries = DB::table('countries')->pluck('id', 'code'); // ['AE' => 1, 'SA' => 2]

        $cities = [
            // Émirats Arabes Unis (AE)
            ['name' => 'Abu Dhabi', 'country_code' => 'AE'],
            ['name' => 'Dubai', 'country_code' => 'AE'],
            ['name' => 'Sharjah', 'country_code' => 'AE'],
            ['name' => 'Ajman', 'country_code' => 'AE'],
            ['name' => 'Fujairah', 'country_code' => 'AE'],

            // Arabie Saoudite (SA)
            ['name' => 'Riyadh', 'country_code' => 'SA'],
            ['name' => 'Jeddah', 'country_code' => 'SA'],
            ['name' => 'Mecca', 'country_code' => 'SA'],
            ['name' => 'Medina', 'country_code' => 'SA'],
            ['name' => 'Dammam', 'country_code' => 'SA'],
        ];

        foreach ($cities as $city) {
            $country_id = $countries[$city['country_code']] ?? null;
            if ($country_id) {
                DB::table('cities')->insert([
                    'name' => $city['name'],
                    'country_id' => $country_id,
                ]);
            }
        }
    }

    public function down()
    {
        DB::table('cities')->whereIn('name', [
            'Abu Dhabi', 'Dubai', 'Sharjah', 'Ajman', 'Fujairah',
            'Riyadh', 'Jeddah', 'Mecca', 'Medina', 'Dammam'
        ])->delete();
    }
}
