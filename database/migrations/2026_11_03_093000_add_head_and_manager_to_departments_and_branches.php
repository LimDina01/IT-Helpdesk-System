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
        // 1. Add head_user_id to departments
        if (! Schema::hasColumn('departments', 'head_user_id')) {
            Schema::table('departments', function (Blueprint $table) {
                $table->foreignId('head_user_id')
                    ->nullable()
                    ->after('code')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }

        // 2. Add manager_user_id to branches
        if (! Schema::hasColumn('branches', 'manager_user_id')) {
            Schema::table('branches', function (Blueprint $table) {
                $table->foreignId('manager_user_id')
                    ->nullable()
                    ->after('code')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }

        // 3. Optional initial auto-assignment based on existing known HODs
        $this->seedInitialDesignatedApprovers();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('departments', 'head_user_id')) {
            Schema::table('departments', function (Blueprint $table) {
                $table->dropConstrainedForeignId('head_user_id');
            });
        }

        if (Schema::hasColumn('branches', 'manager_user_id')) {
            Schema::table('branches', function (Blueprint $table) {
                $table->dropConstrainedForeignId('manager_user_id');
            });
        }
    }

    /**
     * Auto-assign existing HOD accounts to their departments where applicable.
     */
    protected function seedInitialDesignatedApprovers(): void
    {
        // Operations HOD (Chantha Som)
        $opsUser = DB::table('users')->where('email', 'hod.ops@cbvh.com.kh')->first();
        if ($opsUser) {
            DB::table('departments')->where('code', 'OPS')->update(['head_user_id' => $opsUser->id]);
        }

        // Finance HOD (Chhayly Tep)
        $finUser = DB::table('users')->where('email', 'hod.fin@cbvh.com.kh')->first();
        if ($finUser) {
            DB::table('departments')->where('code', 'FIN')->update(['head_user_id' => $finUser->id]);
        }

        // IT-OPR HOD (Mr. Rithy Seng)
        $oprUser = DB::table('users')->where('email', 'rithyseng.cbvh@gmail.com')->first();
        if ($oprUser) {
            DB::table('departments')->where('code', 'IT-OPR')->update(['head_user_id' => $oprUser->id]);
        }

        // IT-PRD HOD (Vannak Ouk / Admin)
        $adminUser = DB::table('users')->where('email', 'admin@cbvh.com.kh')->first();
        if ($adminUser) {
            DB::table('departments')->where('code', 'IT-PRD')->update(['head_user_id' => $adminUser->id]);
            DB::table('branches')->where('code', 'HQ-PP')->update(['manager_user_id' => $adminUser->id]);
        }
    }
};
