<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->onDelete('cascade');
            $table->string('title');
            $table->text('content');
            $table->foreignId('posted_by')->nullable()->constrained('users')->onDelete('set null');
            $table->tinyInteger('is_important')->default(0);
            $table->timestamps();

            $table->index(['event_id', 'is_important']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_updates');
    }
};
