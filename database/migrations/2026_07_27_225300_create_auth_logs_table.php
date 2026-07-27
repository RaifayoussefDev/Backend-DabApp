<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_logs', function (Blueprint $table) {
            $table->id();
            // Nullable: a failed login attempt may never resolve to a real user.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('event', ['register', 'login', 'logout', 'otp_whatsapp', 'otp_email'])->index();
            $table->boolean('success')->default(true);
            $table->string('method', 30)->nullable(); // e.g. 'email' | 'phone' for login/register, echoes the channel for otp_*
            $table->string('identifier')->nullable(); // the email/phone typed, kept even when no user was resolved
            $table->string('message')->nullable(); // short human-readable outcome, e.g. failure reason
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'event', 'created_at']);
            $table->index(['event', 'success', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_logs');
    }
};
