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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('can_access_portal')->default(false)->after('is_active');
        });

        // Initialize can_access_portal = true for existing staff members (helpdesk, technician, hod, system_admin)
        $staffUserIds = DB::table('role_user')
            ->join('roles', 'role_user.role_id', '=', 'roles.id')
            ->whereIn('roles.name', ['helpdesk', 'technician', 'hod', 'system_admin'])
            ->pluck('role_user.user_id')
            ->unique();

        if ($staffUserIds->isNotEmpty()) {
            DB::table('users')->whereIn('id', $staffUserIds)->update(['can_access_portal' => true]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('can_access_portal');
        });
    }
};
