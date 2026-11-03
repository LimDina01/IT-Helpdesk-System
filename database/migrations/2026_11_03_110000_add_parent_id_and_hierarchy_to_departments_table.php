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
        // 1. Add parent_id to departments for sub-office / unit hierarchy
        if (!Schema::hasColumn('departments', 'parent_id')) {
            Schema::table('departments', function (Blueprint $table) {
                $table->foreignId('parent_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('departments')
                    ->nullOnDelete();
            });
        }

        // 2. Add it_department_id to ticket_categories to dynamically link categories to IT units
        if (!Schema::hasColumn('ticket_categories', 'it_department_id')) {
            Schema::table('ticket_categories', function (Blueprint $table) {
                $table->foreignId('it_department_id')
                    ->nullable()
                    ->after('it_division')
                    ->constrained('departments')
                    ->nullOnDelete();
            });
        }

        // 3. Setup IT Parent Department and nest sub-units (IT-OPR, IT-PRD, IT-SOL)
        $this->seedHierarchy();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('ticket_categories', 'it_department_id')) {
            Schema::table('ticket_categories', function (Blueprint $table) {
                $table->dropConstrainedForeignId('it_department_id');
            });
        }

        if (Schema::hasColumn('departments', 'parent_id')) {
            Schema::table('departments', function (Blueprint $table) {
                $table->dropConstrainedForeignId('parent_id');
            });
        }
    }

    /**
     * Setup parent Information Technology department and nest divisions under it.
     */
    protected function seedHierarchy(): void
    {
        // 1. Ensure top-level parent "Information Technology" department exists
        $itParent = DB::table('departments')->where('code', 'IT')->first();
        if (!$itParent) {
            $adminUser = DB::table('users')->where('email', 'admin@cbvh.com.kh')->first();
            $itParentId = DB::table('departments')->insertGetId([
                'name' => 'Information Technology',
                'code' => 'IT',
                'parent_id' => null,
                'head_user_id' => $adminUser?->id,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $itParentId = $itParent->id;
            DB::table('departments')->where('id', $itParentId)->update(['parent_id' => null]);
        }

        // 2. Nest IT-OPR and IT-PRD under the IT Parent Department
        DB::table('departments')->where('code', 'IT-OPR')->update(['parent_id' => $itParentId]);
        DB::table('departments')->where('code', 'IT-PRD')->update(['parent_id' => $itParentId]);

        // 3. Also introduce IT Solutions (IT-SOL) as a dynamic sub-unit under IT
        $itSol = DB::table('departments')->where('code', 'IT-SOL')->first();
        if (!$itSol) {
            DB::table('departments')->insert([
                'name' => 'IT Solutions & Development (IT-SOL)',
                'code' => 'IT-SOL',
                'parent_id' => $itParentId,
                'head_user_id' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('departments')->where('code', 'IT-SOL')->update(['parent_id' => $itParentId]);
        }

        // 4. Backfill ticket_categories.it_department_id based on it_division
        $oprDept = DB::table('departments')->where('code', 'IT-OPR')->first();
        $prdDept = DB::table('departments')->where('code', 'IT-PRD')->first();

        if ($oprDept) {
            DB::table('ticket_categories')
                ->where('it_division', 'opr')
                ->whereNull('it_department_id')
                ->update(['it_department_id' => $oprDept->id]);
        }

        if ($prdDept) {
            DB::table('ticket_categories')
                ->where('it_division', 'prd')
                ->whereNull('it_department_id')
                ->update(['it_department_id' => $prdDept->id]);
        }
    }
};

