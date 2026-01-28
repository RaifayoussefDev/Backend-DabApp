<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('chat_sessions')->onDelete('cascade');
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
            $table->enum('sender_type', ['user', 'provider'])->comment('Who sent the message');
            $table->text('message');
            $table->enum('message_type', ['text', 'image', 'file'])->default('text');
            $table->string('attachment_url')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['session_id', 'created_at']);
            $table->index(['sender_id', 'is_read']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('chat_messages');
    }
};