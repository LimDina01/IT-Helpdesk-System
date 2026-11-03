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
        if (Schema::hasTable('department_approvers') && !Schema::hasColumn('department_approvers', 'branch_id')) {
            Schema::table('department_approvers', function (Blueprint $table) {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->after('department_id')
                    ->constrained('branches')
                    ->nullOnDelete();

                $table->index(['branch_id', 'department_id', 'level', 'is_active'], 'dept_appr_branch_dept_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('department_approvers') && Schema::hasColumn('department_approvers', 'branch_id')) {
            Schema::table('department_approvers', function (Blueprint $table) {
                $table->dropIndex('dept_appr_branch_dept_idx');
                $table->dropConstrainedForeignId('branch_id');
            });
        }
    }
};

