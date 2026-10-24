<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add requires_approval to ticket_categories
        Schema::table('ticket_categories', function (Blueprint $table) {
            $table->boolean('requires_approval')->default(false)->after('type');
        });

        // 2. Add ticket_type to tickets and expand status enum
        Schema::table('tickets', function (Blueprint $table) {
            $table->enum('ticket_type', ['incident', 'service_request'])->default('incident')->after('category_id')->index();
        });

        // Update tickets status enum to include 'pending_approval' and 'rejected' (MySQL only)
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE tickets MODIFY COLUMN status ENUM(
                'pending_approval',
                'submitted',
                'clarifying',
                'assigned',
                'in_progress',
                'blocked',
                'pending_confirmation',
                'resolved_confirmed',
                'resolved_auto_closed',
                'reopened',
                'rejected',
                'cancelled'
            ) DEFAULT 'submitted'");
        }

        // 3. Create ticket_approvals table
        Schema::create('ticket_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->unsignedInteger('step_number')->default(1);
            $table->string('step_name', 100); // e.g. "Department HOD Approval", "IT Manager Approval"
            $table->string('role_name', 50);  // "department_hod", "it_manager"
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('comments')->nullable();
            $table->timestamp('actioned_at')->nullable();
            $table->timestamps();

            $table->index(['ticket_id', 'step_number']);
            $table->index(['status', 'role_name']);
            $table->index(['approver_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_approvals');

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('ticket_type');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE tickets MODIFY COLUMN status ENUM(
                'submitted',
                'clarifying',
                'assigned',
                'in_progress',
                'blocked',
                'pending_confirmation',
                'resolved_confirmed',
                'resolved_auto_closed',
                'reopened',
                'cancelled'
            ) DEFAULT 'submitted'");
        }

        Schema::table('ticket_categories', function (Blueprint $table) {
            $table->dropColumn('requires_approval');
        });
    }
};
