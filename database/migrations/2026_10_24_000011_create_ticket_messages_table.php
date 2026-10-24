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
        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->enum('visibility', ['requester_visible', 'internal_staff_only', 'system_event'])->default('requester_visible');
            $table->enum('message_type', ['text', 'attachment', 'status_change', 'reassignment'])->default('text');
            $table->text('body')->nullable();
            $table->timestamps();

            // Composite index for fast timeline queries filtered by visibility
            $table->index(['ticket_id', 'visibility', 'created_at'], 'idx_ticket_timeline');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_messages');
    }
};
