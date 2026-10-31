<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\CompanyHoliday;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Role;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PortalAdminController extends Controller
{
    /**
     * Categories & SLA Administration Overview.
     */
    public function index(): View
    {
        abort_unless(Auth::user()->hasRole(['system_admin', 'hod']), 403, 'System Administrator or HOD privileges required.');

        $categories = TicketCategory::whereNull('parent_id')
            ->with(['children' => fn($q) => $q->withCount('tickets')])
            ->withCount('tickets')
            ->orderBy('name')
            ->get();

        $branches = Branch::withCount('tickets')->orderBy('code')->get();
        $departments = Department::withCount('employees')->orderBy('name')->get();

        return view('portal.admin.index', compact('categories', 'branches', 'departments'));
    }

    /**
     * Create a new category or subcategory.
     */
    public function storeCategory(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()->hasRole(['system_admin', 'hod']), 403);

        $validated = $request->validate([
            'parent_id' => 'nullable|exists:ticket_categories,id',
            'name' => 'required|string|max:100',
            'name_km' => 'nullable|string|max:150',
            'description' => 'nullable|string|max:255',
            'description_km' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:50',
            'icon_file' => 'nullable|file|mimes:png,svg,jpg,jpeg,webp|max:2048',
            'icon_color' => 'nullable|string|max:50',
            'type' => 'required|in:incident_issue,service_request',
            'requires_approval' => 'nullable|boolean',
            'default_priority' => 'nullable|in:low,medium,high,critical',
            'sla_critical_hours' => 'nullable|integer|min:1|max:720',
            'sla_high_hours' => 'nullable|integer|min:1|max:720',
            'sla_medium_hours' => 'nullable|integer|min:1|max:720',
            'sla_low_hours' => 'nullable|integer|min:1|max:720',
            'target_acknowledge_minutes' => 'nullable|integer|min:5|max:1440',
            'target_resolve_hours' => 'nullable|integer|min:1|max:720',
        ]);

        $validated['requires_approval'] = $request->boolean('requires_approval');
        $validated['default_priority'] = $validated['default_priority'] ?? 'medium';
        $validated['sla_critical_hours'] = $validated['sla_critical_hours'] ?? 4;
        $validated['sla_high_hours'] = $validated['sla_high_hours'] ?? 8;
        $validated['sla_medium_hours'] = $validated['sla_medium_hours'] ?? 24;
        $validated['sla_low_hours'] = $validated['sla_low_hours'] ?? 48;

        $defaultPriority = $validated['default_priority'];
        $priorityMap = [
            'critical' => $validated['sla_critical_hours'],
            'high' => $validated['sla_high_hours'],
            'medium' => $validated['sla_medium_hours'],
            'low' => $validated['sla_low_hours'],
        ];
        $validated['target_resolve_hours'] = $priorityMap[$defaultPriority] ?? ($validated['target_resolve_hours'] ?? 24);
        $validated['target_acknowledge_minutes'] = $validated['target_acknowledge_minutes'] ?? 30;
        $validated['is_active'] = true;

        if ($request->hasFile('icon_file')) {
            $file = $request->file('icon_file');
            $filename = 'cat_' . time() . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
            $destinationPath = public_path('uploads/categories');
            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0755, true);
            }
            $file->move($destinationPath, $filename);
            $validated['icon_url'] = '/uploads/categories/' . $filename;
        }

        $created = TicketCategory::create($validated);
        $typeLabel = $created->parent_id ? 'Subcategory' : 'Parent Category Group';

        return back()->with('status', "{$typeLabel} '{$created->name}' created successfully!");
    }

    /**
     * Update an existing category or parent group.
     */
    public function updateCategory(Request $request, TicketCategory $category): RedirectResponse
    {
        abort_unless(Auth::user()->hasRole(['system_admin', 'hod']), 403);

        $validated = $request->validate([
            'parent_id' => 'nullable|exists:ticket_categories,id|not_in:' . $category->id,
            'name' => 'required|string|max:100',
            'name_km' => 'nullable|string|max:150',
            'description' => 'nullable|string|max:255',
            'description_km' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:50',
            'icon_file' => 'nullable|file|mimes:png,svg,jpg,jpeg,webp|max:2048',
            'remove_icon_file' => 'nullable|boolean',
            'icon_color' => 'nullable|string|max:50',
            'type' => 'required|in:incident_issue,service_request',
            'requires_approval' => 'nullable|boolean',
            'default_priority' => 'nullable|in:low,medium,high,critical',
            'sla_critical_hours' => 'nullable|integer|min:1|max:720',
            'sla_high_hours' => 'nullable|integer|min:1|max:720',
            'sla_medium_hours' => 'nullable|integer|min:1|max:720',
            'sla_low_hours' => 'nullable|integer|min:1|max:720',
            'target_acknowledge_minutes' => 'nullable|integer|min:5|max:1440',
            'target_resolve_hours' => 'nullable|integer|min:1|max:720',
        ]);

        $validated['requires_approval'] = $request->boolean('requires_approval');
        $validated['default_priority'] = $validated['default_priority'] ?? $category->default_priority ?? 'medium';
        $validated['target_acknowledge_minutes'] = $validated['target_acknowledge_minutes'] ?? $category->target_acknowledge_minutes ?? 30;

        $crit = $validated['sla_critical_hours'] ?? $category->sla_critical_hours ?? 4;
        $high = $validated['sla_high_hours'] ?? $category->sla_high_hours ?? 8;
        $med  = $validated['sla_medium_hours'] ?? $category->sla_medium_hours ?? 24;
        $low  = $validated['sla_low_hours'] ?? $category->sla_low_hours ?? 48;
        $defaultPriority = $validated['default_priority'];

        $priorityMap = [
            'critical' => $crit,
            'high' => $high,
            'medium' => $med,
            'low' => $low,
        ];
        $validated['target_resolve_hours'] = $priorityMap[$defaultPriority] ?? ($validated['target_resolve_hours'] ?? $category->target_resolve_hours ?? 24);

        if ($request->boolean('remove_icon_file')) {
            if ($category->icon_url && file_exists(public_path(ltrim($category->icon_url, '/')))) {
                @unlink(public_path(ltrim($category->icon_url, '/')));
            }
            $validated['icon_url'] = null;
        } elseif ($request->hasFile('icon_file')) {
            if ($category->icon_url && file_exists(public_path(ltrim($category->icon_url, '/')))) {
                @unlink(public_path(ltrim($category->icon_url, '/')));
            }
            $file = $request->file('icon_file');
            $filename = 'cat_' . time() . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
            $destinationPath = public_path('uploads/categories');
            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0755, true);
            }
            $file->move($destinationPath, $filename);
            $validated['icon_url'] = '/uploads/categories/' . $filename;
        }

        // If explicitly set empty, allow removing parent (promoting to root)
        if ($request->has('parent_id') && empty($request->input('parent_id'))) {
            $validated['parent_id'] = null;
        }

        $category->update($validated);

        $typeLabel = $category->parent_id ? 'Subcategory' : 'Parent Group';
        return back()->with('status', "{$typeLabel} '{$category->name}' updated successfully!");
    }

    /**
     * Toggle category active status.
     */
    public function toggleCategory(TicketCategory $category): RedirectResponse
    {
        abort_unless(Auth::user()->hasRole(['system_admin', 'hod']), 403);

        $category->update(['is_active' => !$category->is_active]);

        $statusStr = $category->is_active ? 'activated' : 'deactivated';
        return back()->with('status', "Category '{$category->name}' {$statusStr}.");
    }

    /**
     * Delete an unused category or parent group.
     */
    public function destroyCategory(TicketCategory $category): RedirectResponse
    {
        abort_unless(Auth::user()->hasRole(['system_admin', 'hod']), 403);

        // 1. Check if it has child subcategories
        if ($category->children()->exists()) {
            $childCount = $category->children()->count();
            return back()->withErrors([
                'error' => "Cannot delete group '{$category->name}' because it contains {$childCount} subcategories. Please delete or move them first."
            ]);
        }

        // 2. Check if it has linked tickets
        if ($category->tickets()->exists()) {
            $ticketCount = $category->tickets()->count();
            return back()->withErrors([
                'error' => "Cannot delete '{$category->name}' because it has {$ticketCount} existing ticket(s). You can disable it instead to preserve historical records."
            ]);
        }

        $name = $category->name;
        $isParent = $category->parent_id === null;
        $category->delete();

        return back()->with('status', ($isParent ? 'Parent Group' : 'Subcategory') . " '{$name}' permanently deleted.");
    }

    /**
     * Company Holidays Calendar view.
     */
    public function holidays(): View
    {
        abort_unless(Auth::user()->hasRole(['system_admin', 'hod']), 403);

        $holidays = CompanyHoliday::orderBy('holiday_date')->get();

        return view('portal.admin.holidays', compact('holidays'));
    }

    /**
     * Store new company holiday.
     */
    public function storeHoliday(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()->hasRole(['system_admin', 'hod']), 403);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'holiday_date' => 'required|date',
            'is_recurring' => 'nullable|boolean',
            'notes' => 'nullable|string|max:255',
        ]);

        $validated['is_recurring'] = $request->boolean('is_recurring');

        CompanyHoliday::create($validated);

        return back()->with('status', "Holiday '{$validated['name']}' added to official calendar!");
    }

    /**
     * Remove a company holiday.
     */
    public function destroyHoliday(CompanyHoliday $holiday): RedirectResponse
    {
        abort_unless(Auth::user()->hasRole(['system_admin', 'hod']), 403);

        $name = $holiday->name;
        $holiday->delete();

        return back()->with('status', "Holiday '{$name}' removed.");
    }

    /**
     * User & Role Management view.
     */
    public function users(Request $request): View
    {
        abort_unless(Auth::user()->hasRole(['system_admin', 'hod']), 403);

        $filter = $request->input('filter', 'all');

        $query = User::with(['roles', 'employee.department', 'employee.branch'])
            ->orderBy('name');

        $staffFilterClosure = function ($q) {
            $q->whereHas('roles', fn($r) => $r->whereIn('name', ['technician', 'helpdesk', 'system_admin']))
              ->orWhere(function ($hodQ) {
                  $hodQ->whereHas('roles', fn($r) => $r->where('name', 'hod'))
                       ->where(function ($deptQ) {
                           $deptQ->whereDoesntHave('employee')
                                 ->orWhereHas('employee.department', fn($d) => $d->where('code', 'IT'));
                       });
              });
        };

        $tmaFilterClosure = function ($q) {
            $q->whereDoesntHave('roles', fn($r) => $r->whereIn('name', ['technician', 'helpdesk', 'system_admin']))
              ->where(function ($subQ) {
                  $subQ->whereDoesntHave('roles', fn($r) => $r->where('name', 'hod'))
                       ->orWhereHas('employee.department', fn($d) => $d->where('code', '!=', 'IT'));
              });
        };

        if ($filter === 'portal') {
            $query->where($staffFilterClosure);
        } elseif ($filter === 'tma') {
            $query->where($tmaFilterClosure);
        }

        $users = $query->paginate(25)->withQueryString();

        $totalCount = User::count();
        $staffCount = User::where($staffFilterClosure)->count();
        $tmaCount = User::where($tmaFilterClosure)->count();

        // Operational IT Staff roles (for staff modal)
        $roles = Role::whereIn('name', ['technician', 'helpdesk', 'system_admin', 'hod'])->get();
        // All roles (for role assignment modal)
        $allRoles = Role::orderBy('id')->get();
        $departments = Department::where('is_active', true)->orderBy('name')->get();
        $branches = Branch::where('is_active', true)->orderBy('name')->get();

        return view('portal.admin.users', compact(
            'users',
            'roles',
            'allRoles',
            'departments',
            'branches',
            'totalCount',
            'staffCount',
            'tmaCount',
            'filter'
        ));
    }

    /**
     * Create or promote an IT staff / admin user.
     */
    public function storeUser(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()->hasRole(['system_admin', 'hod']), 403);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:150',
            'password' => 'nullable|string|min:6',
            'telegram_username' => 'nullable|string|max:100',
            'roles' => 'required|array|min:1',
            'roles.*' => 'exists:roles,id',
        ]);

        $email = strtolower(trim($validated['email']));
        $user = User::where('email', $email)->first();

        $passwordInput = !empty($validated['password']) ? $validated['password'] : null;

        if ($user) {
            // Update existing user profile & promote roles without failing (standalone IT account)
            $updateData = [
                'name' => $validated['name'],
                'employee_id' => null,
                'can_access_portal' => true,
                'is_active' => true,
            ];
            if (!empty($validated['telegram_username'])) {
                $updateData['telegram_username'] = ltrim($validated['telegram_username'], '@');
            }
            if ($passwordInput) {
                $updateData['password'] = \Illuminate\Support\Facades\Hash::make($passwordInput);
            }
            $user->update($updateData);
            $user->roles()->sync($validated['roles']);
            $requesterRoleId = Role::where('name', 'requester')->value('id');
            if ($requesterRoleId) {
                $user->roles()->detach($requesterRoleId);
            }

            return back()->with('status', "IT Staff user '{$user->name}' ({$user->email}) updated and assigned roles successfully!");
        }

        // Register new staff user (standalone IT account: employee_id is always null)
        $password = $passwordInput ?: 'Password123!';
        $newUser = User::create([
            'name' => $validated['name'],
            'email' => $email,
            'password' => \Illuminate\Support\Facades\Hash::make($password),
            'telegram_username' => !empty($validated['telegram_username']) ? ltrim($validated['telegram_username'], '@') : null,
            'employee_id' => null,
            'can_access_portal' => true,
            'is_active' => true,
        ]);

        $newUser->roles()->sync($validated['roles']);
        $requesterRoleId = Role::where('name', 'requester')->value('id');
        if ($requesterRoleId) {
            $newUser->roles()->detach($requesterRoleId);
        }

        return back()->with('status', "IT Staff user '{$newUser->name}' ({$newUser->email}) registered successfully.");
    }

    /**
     * Create or update a Bank Employee & TMA Requester / Department HOD.
     */
    public function storeTmaUser(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()->hasRole(['system_admin', 'hod']), 403);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:150',
            'employee_code' => 'required|string|max:50',
            'department_id' => 'required|exists:departments,id',
            'branch_id' => 'required|exists:branches,id',
            'phone' => 'nullable|string|max:50',
            'role' => 'nullable|in:requester,hod',
            'roles' => 'nullable|array',
            'roles.*' => 'in:requester,hod',
            'password' => 'nullable|string|min:6',
            'telegram_username' => 'nullable|string|max:100',
        ]);

        $employeeCode = strtoupper(trim($validated['employee_code']));
        $email = strtolower(trim($validated['email']));

        // 1. Create or update Employee record with Department & Branch
        $employee = Employee::firstOrNew(['employee_code' => $employeeCode]);
        $employee->full_name = $validated['name'];
        $employee->email = $email;
        $employee->department_id = $validated['department_id'];
        $employee->branch_id = $validated['branch_id'];
        if (!empty($validated['phone'])) {
            $employee->phone = $validated['phone'];
        }
        $employee->is_active = true;
        $employee->save();

        // 2. Create or update User record
        $user = User::where('employee_id', $employee->id)->orWhere('email', $email)->first();
        $passwordInput = !empty($validated['password']) ? $validated['password'] : 'Password123!';

        if ($user) {
            $updateData = [
                'name' => $validated['name'],
                'email' => $email,
                'employee_id' => $employee->id,
                'is_active' => true,
            ];
            if (!empty($validated['telegram_username'])) {
                $updateData['telegram_username'] = ltrim($validated['telegram_username'], '@');
            }
            if (!empty($validated['password'])) {
                $updateData['password'] = \Illuminate\Support\Facades\Hash::make($passwordInput);
            }
            $user->update($updateData);
        } else {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $email,
                'employee_id' => $employee->id,
                'password' => \Illuminate\Support\Facades\Hash::make($passwordInput),
                'telegram_username' => !empty($validated['telegram_username']) ? ltrim($validated['telegram_username'], '@') : null,
                'can_access_portal' => false,
                'is_active' => true,
            ]);
        }

        // 3. Assign roles (HODs always hold both Requester and HOD)
        $selectedRoles = !empty($validated['roles']) ? $validated['roles'] : (!empty($validated['role']) ? [$validated['role']] : ['requester']);
        $rolesToAttach = [];
        $requesterRole = Role::firstOrCreate(['name' => 'requester'], ['label' => 'Requester']);
        $rolesToAttach[] = $requesterRole->id;

        $isHod = in_array('hod', $selectedRoles, true);
        if ($isHod) {
            $hodRole = Role::firstOrCreate(['name' => 'hod'], ['label' => 'Head of Department']);
            $rolesToAttach[] = $hodRole->id;
        }

        $user->roles()->sync($rolesToAttach);

        $deptName = $employee->department?->name ?? 'Department';
        $roleLabel = $isHod ? 'Department HOD & Requester' : 'Requester';

        return back()->with('status', "TMA User '{$user->name}' ({$employee->employee_code}) registered as {$roleLabel} in {$deptName}. Web login & Telegram link ready!");
    }

    /**
     * Update an existing user and their linked employee profile.
     */
    public function updateUser(Request $request, User $user): RedirectResponse
    {
        abort_unless(Auth::user()->hasRole(['system_admin', 'hod']), 403);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'employee_code' => 'nullable|string|max:50',
            'department_id' => 'nullable|exists:departments,id',
            'branch_id' => 'nullable|exists:branches,id',
            'phone' => 'nullable|string|max:50',
            'telegram_username' => 'nullable|string|max:100',
            'password' => 'nullable|string|min:6',
            'is_active' => 'nullable|boolean',
            'can_access_portal' => 'nullable|boolean',
            'roles' => 'nullable|array',
            'roles.*' => 'exists:roles,id',
            'tma_role' => 'nullable|exists:roles,id',
        ]);

        $email = strtolower(trim($validated['email']));

        $isStaff = $user->isItStaff() || (!empty($validated['roles']) && Role::whereIn('id', $validated['roles'])->whereIn('name', ['technician', 'helpdesk', 'system_admin'])->exists());

        if ($isStaff) {
            // Standalone IT staff account: never attach employee records
            $user->employee_id = null;
        } else {
            // 1. Update or link Employee record for TMA users
            $employee = $user->employee;
            if (!empty($validated['employee_code'])) {
                $code = strtoupper(trim($validated['employee_code']));
                if (!$employee) {
                    $employee = Employee::firstOrNew(['employee_code' => $code]);
                } else {
                    $employee->employee_code = $code;
                }
            }

            if ($employee) {
                $employee->full_name = $validated['name'];
                $employee->email = $email;
                if (isset($validated['department_id'])) {
                    $employee->department_id = $validated['department_id'];
                }
                if (isset($validated['branch_id'])) {
                    $employee->branch_id = $validated['branch_id'];
                }
                if (isset($validated['phone'])) {
                    $employee->phone = $validated['phone'];
                }
                $employee->save();
                $user->employee_id = $employee->id;
            }
        }

        // 2. Update User record
        $user->name = $validated['name'];
        $user->email = $email;
        $user->can_access_portal = $isStaff;

        if ($request->boolean('unlink_telegram')) {
            $user->telegram_id = null;
            $user->telegram_username = null;
        } else {
            $newUsername = !empty($validated['telegram_username']) ? ltrim($validated['telegram_username'], '@') : null;
            // If telegram username changed or cleared by admin, clear telegram_id as well
            if ($user->telegram_username !== $newUsername) {
                $user->telegram_id = null;
                $user->telegram_username = $newUsername;
            }
        }

        if (!empty($validated['password'])) {
            $user->password = \Illuminate\Support\Facades\Hash::make($validated['password']);
        }
        $user->save();

        // 3. Update roles (with primary admin protection)
        $roleIds = !empty($validated['roles']) ? $validated['roles'] : ($request->filled('tma_role') ? [(int) $request->input('tma_role')] : []);

        if (!empty($roleIds)) {
            $adminRoleId = Role::where('name', 'system_admin')->value('id');
            $requesterRoleId = Role::where('name', 'requester')->value('id');
            $hodRoleId = Role::where('name', 'hod')->value('id');

            // Protect primary admin from losing system_admin role
            if (($user->id === 1 || $user->email === 'admin@cbvh.com.kh') && $adminRoleId && !in_array($adminRoleId, $roleIds)) {
                $roleIds[] = $adminRoleId;
            }

            // For TMA bank employees: if HOD role is selected, always include requester role as well
            if (!$isStaff && in_array($hodRoleId, $roleIds) && $requesterRoleId && !in_array($requesterRoleId, $roleIds)) {
                $roleIds[] = $requesterRoleId;
            }

            // For IT staff: strip requester role
            if ($isStaff && $requesterRoleId && in_array($requesterRoleId, $roleIds)) {
                $roleIds = array_diff($roleIds, [$requesterRoleId]);
            }

            $user->roles()->sync($roleIds);
        }

        return back()->with('status', "User '{$user->name}' updated successfully.");
    }

    /**
     * Update user role assignments.
     */
    public function updateUserRoles(Request $request, User $user): RedirectResponse
    {
        abort_unless(Auth::user()->hasRole(['system_admin', 'hod']), 403);

        $validated = $request->validate([
            'roles' => 'required|array|min:1',
            'roles.*' => 'exists:roles,id',
        ]);

        // Protect primary admin from losing system_admin role
        $adminRoleId = Role::where('name', 'system_admin')->value('id');
        if (($user->id === 1 || $user->email === 'admin@cbvh.com.kh') && $adminRoleId && !in_array($adminRoleId, $validated['roles'])) {
            $validated['roles'][] = $adminRoleId;
        }

        $user->roles()->sync($validated['roles']);
        $requesterRoleId = Role::where('name', 'requester')->value('id');
        if (!$user->fresh()->isStaff()) {
            $user->can_access_portal = false;
            $user->save();
        } elseif ($user->isItStaff() && $requesterRoleId && !in_array($requesterRoleId, $validated['roles'])) {
            $user->roles()->detach($requesterRoleId);
        }

        return back()->with('status', "Roles updated for {$user->name}.");
    }

    /**
     * Toggle user active/suspended status.
     */
    public function toggleUserActive(User $user): RedirectResponse
    {
        abort_unless(Auth::user()->hasRole(['system_admin', 'hod']), 403);

        if ($user->id === Auth::id()) {
            return back()->withErrors(['error' => 'You cannot deactivate your own account.']);
        }

        if ($user->id === 1 || $user->email === 'admin@cbvh.com.kh') {
            return back()->withErrors(['error' => 'The primary testing admin account cannot be deactivated.']);
        }

        $newStatus = !$user->is_active;
        $user->update(['is_active' => $newStatus]);

        if (!$newStatus) {
            try {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            } catch (\Throwable $e) {}
        }

        $statusStr = $newStatus ? 'activated' : 'suspended';
        return back()->with('status', "User {$user->name} {$statusStr}.");
    }

    /**
     * Quick toggle granting or revoking Web Portal access.
     */
    public function togglePortalAccess(User $user): RedirectResponse
    {
        abort_unless(Auth::user()->hasRole(['system_admin', 'hod']), 403);

        if (!$user->isStaff()) {
            return back()->withErrors(['error' => 'Web Portal access is only applicable to IT Technical Staff and Management. Regular employees use Telegram TMA.']);
        }

        if ($user->id === Auth::id()) {
            return back()->withErrors(['error' => 'You cannot revoke your own web portal access.']);
        }

        if ($user->id === 1 || $user->email === 'admin@cbvh.com.kh') {
            return back()->withErrors(['error' => 'Primary system admin portal access cannot be revoked.']);
        }

        $newAccess = !$user->can_access_portal;
        $user->can_access_portal = $newAccess;

        // If revoking portal access, terminate active portal sessions
        if (!$newAccess) {
            try {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            } catch (\Throwable $e) {}
        }

        $user->save();

        $actionText = $newAccess ? 'granted' : 'revoked';
        return back()->with('status', "Web Portal access {$actionText} for {$user->name}.");
    }

    /**
     * Unlink Telegram account from a user.
     */
    public function unlinkTelegram(User $user): RedirectResponse
    {
        abort_unless(Auth::user()->hasRole(['system_admin', 'hod']), 403);

        $user->update([
            'telegram_id' => null,
            'telegram_username' => null,
        ]);

        try {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        } catch (\Throwable $e) {}

        return back()->with('status', "Telegram account unlinked for {$user->name}. The employee can link again via Telegram.");
    }

    /**
     * Organization: Departments & Branches Management overview.
     */
    public function organization(): View
    {
        abort_unless(Auth::user()->hasRole(['system_admin', 'hod']), 403);

        $departments = Department::withCount('employees')
            ->orderBy('name')
            ->get();

        $branches = Branch::withCount(['employees', 'tickets'])
            ->orderBy('name')
            ->get();

        return view('portal.admin.organization', compact('departments', 'branches'));
    }

    /**
     * Store new Department.
     */
    public function storeDepartment(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()->hasRole(['system_admin', 'hod']), 403);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:20|unique:departments,code',
        ]);

        Department::create([
            'name' => trim($validated['name']),
            'code' => strtoupper(trim($validated['code'])),
            'is_active' => true,
        ]);

        return back()->with('status', "Department '{$validated['name']}' created successfully.");
    }

    /**
     * Update existing Department.
     */
    public function updateDepartment(Request $request, Department $department): RedirectResponse
    {
        abort_unless(Auth::user()->hasRole(['system_admin', 'hod']), 403);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:20|unique:departments,code,' . $department->id,
        ]);

        $department->update([
            'name' => trim($validated['name']),
            'code' => strtoupper(trim($validated['code'])),
        ]);

        return back()->with('status', "Department '{$department->name}' updated successfully.");
    }

    /**
     * Toggle Department active status.
     */
    public function toggleDepartment(Department $department): RedirectResponse
    {
        abort_unless(Auth::user()->hasRole(['system_admin', 'hod']), 403);

        $department->update(['is_active' => !$department->is_active]);

        $statusStr = $department->is_active ? 'activated' : 'suspended';
        return back()->with('status', "Department '{$department->name}' {$statusStr}.");
    }

    /**
     * Delete Department if not in use.
     */
    public function destroyDepartment(Department $department): RedirectResponse
    {
        abort_unless(Auth::user()->hasRole(['system_admin', 'hod']), 403);

        if ($department->employees()->count() > 0) {
            return back()->withErrors(['error' => "Cannot delete department '{$department->name}' because it has assigned employees. Suspend it instead."]);
        }

        $name = $department->name;
        $department->delete();

        return back()->with('status', "Department '{$name}' deleted successfully.");
    }

    /**
     * Store new Branch.
     */
    public function storeBranch(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()->hasRole(['system_admin', 'hod']), 403);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:20|unique:branches,code',
        ]);

        Branch::create([
            'name' => trim($validated['name']),
            'code' => strtoupper(trim($validated['code'])),
            'is_active' => true,
        ]);

        return back()->with('status', "Branch '{$validated['name']}' created successfully.");
    }

    /**
     * Update existing Branch.
     */
    public function updateBranch(Request $request, Branch $branch): RedirectResponse
    {
        abort_unless(Auth::user()->hasRole(['system_admin', 'hod']), 403);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:20|unique:branches,code,' . $branch->id,
        ]);

        $branch->update([
            'name' => trim($validated['name']),
            'code' => strtoupper(trim($validated['code'])),
        ]);

        return back()->with('status', "Branch '{$branch->name}' updated successfully.");
    }

    /**
     * Toggle Branch active status.
     */
    public function toggleBranch(Branch $branch): RedirectResponse
    {
        abort_unless(Auth::user()->hasRole(['system_admin', 'hod']), 403);

        $branch->update(['is_active' => !$branch->is_active]);

        $statusStr = $branch->is_active ? 'activated' : 'suspended';
        return back()->with('status', "Branch '{$branch->name}' {$statusStr}.");
    }

    /**
     * Delete Branch if not in use.
     */
    public function destroyBranch(Branch $branch): RedirectResponse
    {
        abort_unless(Auth::user()->hasRole(['system_admin', 'hod']), 403);

        if ($branch->employees()->count() > 0 || $branch->tickets()->count() > 0) {
            return back()->withErrors(['error' => "Cannot delete branch '{$branch->name}' because it has assigned employees or tickets. Suspend it instead."]);
        }

        $name = $branch->name;
        $branch->delete();

        return back()->with('status', "Branch '{$name}' deleted successfully.");
    }
}
