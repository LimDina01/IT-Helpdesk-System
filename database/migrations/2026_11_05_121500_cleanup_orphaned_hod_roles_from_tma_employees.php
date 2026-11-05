<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\User;
use App\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $hodRole = Role::where('name', 'hod')->first();
        if (!$hodRole) {
            return;
        }

        // Detach Spatie hod role from non-IT employees.
        // Business approver authority is governed dynamically via the department_approvers table.
        $nonItUsers = User::whereNotNull('employee_id')
            ->where('email', '!=', 'admin@cbvh.com.kh')
            ->whereHas('employee.department', function ($q) {
                $q->whereNotIn('code', ['IT', 'IT-OPR', 'IT-PRD']);
            })
            ->get();

        foreach ($nonItUsers as $user) {
            $user->roles()->detach($hodRole->id);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reverse needed as approver authority is governed dynamically.
    }
};

