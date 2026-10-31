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
        if (!Schema::hasColumn('ticket_approvals', 'branch_id')) {
            Schema::table('ticket_approvals', function (Blueprint $table) {
                $table->foreignId('branch_id')->nullable()->after('department_id')->constrained('branches')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('ticket_approvals', 'branch_id')) {
            Schema::table('ticket_approvals', function (Blueprint $table) {
                $table->dropConstrainedForeignId('branch_id');
            });
        }
    }
};
