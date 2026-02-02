<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Copy 'verified' status to 'is_registration_completed'
        // If user was verified (registered), they are now 'registration_completed'
        DB::table('users')->where('verified', true)->update(['is_registration_completed' => true]);

        // 2. Reset 'verified' to false for everyone (Identity Verification defaults to false)
        // Unless we want to keep some as identity verified? 
        // For safety/logic transition, we assume previous 'verified' was ONLY for registration.
        DB::table('users')->update(['verified' => false]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse is tricky, but we can assume 'is_registration_completed' maps back to 'verified'
        DB::table('users')->where('is_registration_completed', true)->update(['verified' => true]);
    }
};
