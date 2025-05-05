<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // In the new migration file
    public function up()
    {
        // Update all existing dates to MM/YY format
        DB::table('bank_cards')->whereNotNull('expiration_date')->each(function ($card) {
            $date = $card->expiration_date;

            // Convert from YYYY-MM-DD to MM/YY if needed
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $month = substr($date, 5, 2);
                $year = substr($date, 2, 2);
                $newDate = $month . '/' . $year;
            }
            // Handle other formats if they exist
            else {
                $digits = preg_replace('/[^0-9]/', '', $date);
                if (strlen($digits) >= 4) {
                    $newDate = substr($digits, 0, 2) . '/' . substr($digits, 2, 2);
                } else {
                    $newDate = null;
                }
            }

            DB::table('bank_cards')
                ->where('id', $card->id)
                ->update(['expiration_date' => $newDate]);
        });
    }

    public function down()
    {
        // This is a one-way migration for data cleanup
    }
};
