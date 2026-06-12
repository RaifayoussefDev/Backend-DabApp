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
        Schema::create('commission_settings', function (Blueprint $table) {
            $table->id();
            $table->enum('entity_type', ['global', 'trainer'])->default('global');
            $table->foreignId('entity_id')->nullable()->constrained('trainers')->onDelete('cascade'); // null = global
            $table->decimal('commission_percentage', 5, 2);
            $table->boolean('is_active')->default(true);
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->index(['entity_type', 'entity_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commission_settings');
    }
};
