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
        // 1. Create department_approvers table for multi-level department bosses (Level 1, Level 2, Level 3...)
        if (!Schema::hasTable('department_approvers')) {
            Schema::create('department_approvers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedInteger('level')->default(1); // 1 = Team Leader, 2 = Supervisor, 3 = Manager / HOD
                $table->string('title', 100)->nullable();     // e.g. "Team Leader - General Accounting", "Accounting Supervisor"
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['department_id', 'level', 'is_active']);
                $table->index(['user_id', 'is_active']);
            });
        }

        // 2. Add line_manager_user_id to employees table (Option B: direct mapping to Team Leader / Line Manager)
        if (!Schema::hasColumn('employees', 'line_manager_user_id')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->foreignId('line_manager_user_id')
                    ->nullable()
                    ->after('department_id')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }

        // 3. Auto-seed initial department_approvers from existing designated head_user_id records
        $this->seedInitialApprovers();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('employees', 'line_manager_user_id')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropConstrainedForeignId('line_manager_user_id');
            });
        }

        Schema::dropIfExists('department_approvers');
    }

    /**
     * Backfill existing department heads into department_approvers as Level 2 (Supervisor / Department Head).
     */
    protected function seedInitialApprovers(): void
    {
        $departments = DB::table('departments')->whereNotNull('head_user_id')->get();
        foreach ($departments as $dept) {
            $exists = DB::table('department_approvers')
                ->where('department_id', $dept->id)
                ->where('user_id', $dept->head_user_id)
                ->exists();

            if (!$exists) {
                DB::table('department_approvers')->insert([
                    'department_id' => $dept->id,
                    'user_id' => $dept->head_user_id,
                    'level' => 2, // Default to Level 2 (Department Head / Supervisor)
                    'title' => 'Department Head',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
};
