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
        Schema::create('ticket_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('assigned_by_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('technician_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->text('reassignment_reason')->nullable();
            $table->timestamps();

            $table->index(['ticket_id', 'technician_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_assignments');
    }
};
