<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add timestamps to roles table
        if (!Schema::hasColumn('roles', 'created_at')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->timestamps();
            });
        }

        // Add timestamps to permissions table
        if (!Schema::hasColumn('permissions', 'created_at')) {
            Schema::table('permissions', function (Blueprint $table) {
                $table->timestamps();
            });
        }

        // Add timestamps to role_permissions table
        if (!Schema::hasColumn('role_permissions', 'created_at')) {
            Schema::table('role_permissions', function (Blueprint $table) {
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropTimestamps();
        });

        Schema::table('permissions', function (Blueprint $table) {
            $table->dropTimestamps();
        });

        Schema::table('role_permissions', function (Blueprint $table) {
            $table->dropTimestamps();
        });
    }
};
