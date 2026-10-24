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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_code', 30)->unique(); // e.g. IT-202610-0001
            $table->foreignId('requester_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('category_id')->constrained('ticket_categories')->cascadeOnUpdate()->restrictOnDelete();
            
            $table->foreignId('current_helpdesk_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('current_technician_id')->nullable()->constrained('users')->nullOnDelete();
            
            $table->enum('status', [
                'pending_approval',      // Waiting for HOD / IT Manager sign-off
                'submitted',             // Approved or Direct incident, ready for triage
                'clarifying',            // Waiting for requester response
                'assigned',              // Assigned to technician, awaiting acceptance
                'in_progress',           // Technician accepted and actively working
                'blocked',               // Blocked (parts/vendor/procurement)
                'pending_confirmation',  // Tech resolved, waiting for employee confirmation
                'resolved_confirmed',    // Employee confirmed solved
                'resolved_auto_closed',  // Closed automatically after business days timeout
                'reopened',              // Employee reported still broken
                'rejected',              // Rejected by HOD or IT Manager
                'cancelled'              // Voided/withdrawn
            ])->default('submitted');

            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            
            $table->string('subject', 255);
            $table->text('description');

            // Lifecycle Timestamps
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('auto_close_deadline')->nullable();

            // Notes / Justifications
            $table->text('blocked_reason')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->timestamps();

            // High performance query indexes
            $table->index(['status', 'priority']);
            $table->index('requester_id');
            $table->index(['current_technician_id', 'status']);
            $table->index('branch_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
