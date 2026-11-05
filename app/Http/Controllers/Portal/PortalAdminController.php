<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\CompanyHoliday;
use App\Models\Department;
use App\Models\DepartmentApprover;
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
        $itUnits = Department::itUnits()->where('is_active', true)->orderBy('name')->get();

        return view('portal.admin.index', compact('categories', 'branches', 'departments', 'itUnits'));
    }

    /**
     * Create a new category or subcategory.
     */
    public function storeCategory(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()->hasRole(['system_admin', 'hod']), 403);

        $validated = $request->validate([
            'parent_id' => 'nullable|exists:ticket_categories,id',
            'code' => 'nullable|string|max:20',
            'it_division' => 'nullable|in:opr,prd',
            'it_department_id' => 'nullable|exists:departments,id',
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

        if (!empty($validated['it_department_id'])) {
            $dept = Department::find($validated['it_department_id']);
            if ($dept) {
                $clean = strtolower(str_replace('IT-', '', $dept->code));
                $validated['it_division'] = in_array($clean, ['opr', 'prd']) ? $clean : ($validated['it_division'] ?? 'opr');
            }
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
            'code' => 'nullable|string|max:20',
            'it_division' => 'nullable|in:opr,prd',
            'it_department_id' => 'nullable|exists:departments,id',
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

        if (!empty($validated['it_department_id'])) {
            $dept = Department::find($validated['it_department_id']);
            if ($dept) {
                $clean = strtolower(str_replace('IT-', '', $dept->code));
                $validated['it_division'] = in_array($clean, ['opr', 'prd']) ? $clean : ($validated['it_division'] ?? 'opr');
            }
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

        $holidays = CompanyHoliday::orderBy('holiday_date')->paginate(20)->withQueryString();

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
        $search = trim((string) $request->input('search', ''));
        $perPage = (int) $request->input('per_page', 25);
        if (!in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 25;
        }

        $query = User::with([
            'roles',
            'employee.department',
            'employee.branch',
            'employee.lineManager',
            'departmentApproverRoles.department',
            'departmentApproverRoles.branch',
        ])->orderBy('name');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('telegram_username', 'like', "%{$search}%")
                  ->orWhereHas('employee', function ($eq) use ($search) {
                      $eq->where('employee_code', 'like', "%{$search}%")
                         ->orWhere('full_name', 'like', "%{$search}%")
                         ->orWhere('phone', 'like', "%{$search}%")
                         ->orWhereHas('department', fn($dq) => $dq->where('name', 'like', "%{$search}%"))
                         ->orWhereHas('branch', fn($bq) => $bq->where('name', 'like', "%{$search}%"));
                  });
            });
        }

        $staffFilterClosure = function ($q) {
            $q->whereNull('employee_id')
              ->whereHas('roles', fn($r) => $r->whereIn('name', ['technician', 'helpdesk', 'system_admin', 'hod']));
        };

        $tmaFilterClosure = function ($q) {
            $q->whereNotNull('employee_id');
        };

        if ($filter === 'portal') {
            $query->where($staffFilterClosure);
        } elseif ($filter === 'tma') {
            $query->where($tmaFilterClosure);
        }

        $users = $query->paginate($perPage)->withQueryString();

        $totalCount = User::count();
        $staffCount = User::where($staffFilterClosure)->count();
        $tmaCount = User::where($tmaFilterClosure)->count();

        // Operational IT Staff roles (for staff modal)
        $roles = Role::whereIn('name', ['technician', 'helpdesk', 'system_admin', 'hod'])->get();
        // All roles (for role assignment modal)
        $allRoles = Role::orderBy('id')->get();
        $departments = Department::where('is_active', true)->orderBy('name')->get();
        $branches = Branch::where('is_active', true)->orderBy('name')->get();
        $lineManagerCandidates = User::where('is_active', true)
            ->whereHas('roles', fn($r) => $r->whereIn('name', ['hod', 'requester', 'system_admin']))
            ->with(['employee.department', 'employee.branch', 'roles'])
            ->orderBy('name')
            ->get();

        $candidatesJson = $lineManagerCandidates->map(function ($u) {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'employee_code' => $u->employee?->employee_code,
                'initials' => strtoupper(substr($u->name, 0, 2)),
                'dept_id' => $u->employee?->department_id,
                'dept_name' => $u->employee?->department?->name,
                'dept_code' => $u->employee?->department?->code,
                'branch_id' => $u->employee?->branch_id,
                'branch_name' => $u->employee?->branch?->name,
                'branch_code' => $u->employee?->branch?->code,
                'roles' => $u->roles->pluck('name')->join(', '),
            ];
        })->values();

        return view('portal.admin.users', compact(
            'users',
            'roles',
            'allRoles',
            'departments',
            'branches',
            'lineManagerCandidates',
            'candidatesJson',
            'totalCount',
            'staffCount',
            'tmaCount',
            'filter',
            'perPage',
            'search'
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
            'line_manager_user_id' => 'nullable|exists:users,id',
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
        $employee->line_manager_user_id = !empty($validated['line_manager_user_id']) ? $validated['line_manager_user_id'] : null;
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

        // 3. Assign roles (defaults to Requester; supports HOD if explicitly provided)
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
            'line_manager_user_id' => 'nullable|exists:users,id',
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

        $isStaff = $request->has('is_it_staff')
            ? $request->boolean('is_it_staff')
            : ($user->isItStaff() && empty($user->employee_id));

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
                if (array_key_exists('line_manager_user_id', $validated)) {
                    $employee->line_manager_user_id = $validated['line_manager_user_id'] ?: null;
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
        $roleIds = !empty($validated['roles']) ? array_map('intval', $validated['roles']) : [];

        $adminRoleId = Role::where('name', 'system_admin')->value('id');
        $requesterRoleId = Role::where('name', 'requester')->value('id');
        $hodRoleId = Role::where('name', 'hod')->value('id');

        if (!$isStaff) {
            // For TMA employees: must always have at least requester role
            if ($requesterRoleId && !in_array($requesterRoleId, $roleIds)) {
                $roleIds[] = $requesterRoleId;
            }
            // Strip any IT technical roles from TMA employees
            $itRoleIds = Role::whereIn('name', ['technician', 'helpdesk', 'system_admin'])->pluck('id')->toArray();
            $roleIds = array_values(array_diff($roleIds, $itRoleIds));
        } else {
            // Protect primary admin from losing system_admin role
            if (($user->id === 1 || $user->email === 'admin@cbvh.com.kh') && $adminRoleId && !in_array($adminRoleId, $roleIds)) {
                $roleIds[] = $adminRoleId;
            }
            // For IT staff: strip requester role
            if ($requesterRoleId && in_array($requesterRoleId, $roleIds)) {
                $roleIds = array_values(array_diff($roleIds, [$requesterRoleId]));
            }
        }

        if (!empty($roleIds)) {
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
    public function organization(Request $request): View
    {
        abort_unless(Auth::user()->hasRole(['system_admin', 'hod']), 403);

        $deptPerPage = (int) $request->input('dept_per_page', 10);
        if (!in_array($deptPerPage, [10, 25, 50], true)) {
            $deptPerPage = 10;
        }

        $branchPerPage = (int) $request->input('branch_per_page', 10);
        if (!in_array($branchPerPage, [10, 25, 50], true)) {
            $branchPerPage = 10;
        }

        $deptSearch = trim((string) $request->input('dept_search', ''));
        $branchSearch = trim((string) $request->input('branch_search', ''));

        $deptQuery = Department::with(['head', 'employees', 'parent', 'children.head'])
            ->withCount('employees');

        if ($deptSearch !== '') {
            $deptQuery->where(function ($q) use ($deptSearch) {
                $q->where('name', 'like', "%{$deptSearch}%")
                  ->orWhere('code', 'like', "%{$deptSearch}%");
            });
        }

        $departments = $deptQuery->orderByRaw("CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END, name ASC")
            ->paginate($deptPerPage, ['*'], 'depts_page')
            ->withQueryString();

        $branchQuery = Branch::with(['manager', 'employees'])
            ->withCount(['employees', 'tickets']);

        if ($branchSearch !== '') {
            $branchQuery->where(function ($q) use ($branchSearch) {
                $q->where('name', 'like', "%{$branchSearch}%")
                  ->orWhere('code', 'like', "%{$branchSearch}%");
            });
        }

        $branches = $branchQuery->orderBy('name')
            ->paginate($branchPerPage, ['*'], 'branches_page')
            ->withQueryString();

        // Potential parent departments (top-level only)
        $parentDepartments = Department::whereNull('parent_id')->orderBy('name')->get();

        // IT parent department specifically
        $itParent = Department::where('code', 'IT')->with(['children.head', 'head'])->first();

        // Dynamic IT sub-units (IT-OPR, IT-PRD, IT-SOL, etc.)
        $itUnits = Department::itUnits()->with('head')->withCount(['employees', 'categories'])->orderBy('name')->get();

        // Eligible candidates to be assigned as HOD or Branch Manager
        $approverCandidates = User::where('is_active', true)
            ->with(['roles', 'employee.department', 'employee.branch'])
            ->orderBy('name')
            ->get();

        $candidatesJson = $approverCandidates->map(function ($u) {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'initials' => strtoupper(substr($u->name, 0, 2)),
                'dept_id' => $u->employee?->department_id,
                'dept_name' => $u->employee?->department?->name,
                'dept_code' => $u->employee?->department?->code,
                'branch_id' => $u->employee?->branch_id,
                'branch_name' => $u->employee?->branch?->name,
                'branch_code' => $u->employee?->branch?->code,
                'roles' => $u->roles->pluck('name')->join(', '),
            ];
        })->values();

        return view('portal.admin.organization', compact(
            'departments', 
            'branches', 
            'parentDepartments', 
            'itParent', 
            'itUnits', 
            'approverCandidates',
            'candidatesJson',
            'deptPerPage',
            'branchPerPage',
            'deptSearch',
            'branchSearch'
        ));
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
            'parent_id' => 'nullable|exists:departments,id',
            'head_user_id' => 'nullable|exists:users,id',
        ]);

        $dept = Department::create([
            'name' => trim($validated['name']),
            'code' => strtoupper(trim($validated['code'])),
            'parent_id' => $validated['parent_id'] ?? null,
            'head_user_id' => $validated['head_user_id'] ?? null,
            'is_active' => true,
        ]);

        return back()->with('status', "Department '{$dept->name}' created successfully.");
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
            'parent_id' => 'nullable|exists:departments,id|not_in:' . $department->id,
            'head_user_id' => 'nullable|exists:users,id',
        ]);

        $department->update([
            'name' => trim($validated['name']),
            'code' => strtoupper(trim($validated['code'])),
            'parent_id' => $validated['parent_id'] ?? null,
            'head_user_id' => $validated['head_user_id'] ?? null,
        ]);

        return back()->with('status', "Department '{$department->name}' updated successfully.");
    }

    /**
     * Quick-assign or update Department Designated Approver (HOD).
     */
    public function updateDepartmentApprover(Request $request, Department $department): RedirectResponse
    {
        abort_unless(Auth::user()->hasRole(['system_admin', 'hod']), 403);

        $validated = $request->validate([
            'head_user_id' => 'nullable|exists:users,id',
        ]);

        $department->update([
            'head_user_id' => $validated['head_user_id'] ?? null,
        ]);

        $approverName = $department->head ? $department->head->name : 'Auto / Role Fallback';
        return back()->with('status', "Department '{$department->name}' approver updated to {$approverName}.");
    }

    /**
     * Add a Department Approver Card (Level 1, Level 2, etc.) optionally scoped to a Branch.
     */
    public function storeDepartmentApprover(Request $request, Department $department): RedirectResponse
    {
        abort_unless(Auth::user()->hasRole(['system_admin', 'hod']), 403);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'branch_id' => 'nullable|exists:branches,id',
            'level' => 'required|integer|min:1|max:20',
            'title' => 'nullable|string|max:100',
        ]);

        $defaultTitle = match ((int) $validated['level']) {
            1 => 'Team Leader',
            2 => 'Supervisor',
            3 => 'Department Manager',
            4 => 'General Manager',
            5 => 'Director / Executive',
            default => "Level {$validated['level']} Approver",
        };

        DepartmentApprover::create([
            'department_id' => $department->id,
            'branch_id' => !empty($validated['branch_id']) ? $validated['branch_id'] : null,
            'user_id' => $validated['user_id'],
            'level' => $validated['level'],
            'title' => !empty($validated['title']) ? $validated['title'] : $defaultTitle,
            'is_active' => true,
        ]);

        $scopeDesc = !empty($validated['branch_id']) ? ' (Branch scoped)' : ' (Company-wide / HQ)';
        return back()->with('status', "Approver card added successfully to '{$department->name}'{$scopeDesc}.");
    }

    /**
     * Add an Approver Card from Branch scope for a Department.
     */
    public function storeBranchDepartmentApprover(Request $request, Branch $branch): RedirectResponse
    {
        abort_unless(Auth::user()->hasRole(['system_admin', 'hod']), 403);

        $validated = $request->validate([
            'department_id' => 'required|exists:departments,id',
            'user_id' => 'required|exists:users,id',
            'level' => 'required|integer|min:1|max:20',
            'title' => 'nullable|string|max:100',
        ]);

        $dept = Department::findOrFail($validated['department_id']);

        $defaultTitle = match ((int) $validated['level']) {
            1 => 'Team Leader',
            2 => 'Supervisor',
            3 => 'Department Manager',
            4 => 'General Manager',
            5 => 'Director / Executive',
            default => "Level {$validated['level']} Approver",
        };

        DepartmentApprover::create([
            'department_id' => $dept->id,
            'branch_id' => $branch->id,
            'user_id' => $validated['user_id'],
            'level' => $validated['level'],
            'title' => !empty($validated['title']) ? $validated['title'] : $defaultTitle,
            'is_active' => true,
        ]);

        return back()->with('status', "Approver card added successfully to '{$branch->name}' for {$dept->name}.");
    }

    /**
     * Edit / Update an existing Department Approver Card (Level, Title, Assigned User, Branch Scope).
     */
    public function updateDepartmentApproverCard(Request $request, Department $department, DepartmentApprover $approver): RedirectResponse
    {
        abort_unless(Auth::user()->hasRole(['system_admin', 'hod']), 403);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'branch_id' => 'nullable|exists:branches,id',
            'level' => 'required|integer|min:1|max:20',
            'title' => 'nullable|string|max:100',
        ]);

        if ($approver->department_id === $department->id) {
            $defaultTitle = match ((int) $validated['level']) {
                1 => 'Team Leader',
                2 => 'Supervisor',
                3 => 'Department Manager',
                4 => 'General Manager',
                5 => 'Director / Executive',
                default => "Level {$validated['level']} Approver",
            };

            $approver->update([
                'user_id' => $validated['user_id'],
                'branch_id' => array_key_exists('branch_id', $validated) ? ($validated['branch_id'] ?: null) : $approver->branch_id,
                'level' => $validated['level'],
                'title' => !empty($validated['title']) ? $validated['title'] : $defaultTitle,
            ]);
        }

        return back()->with('status', "Approver card updated successfully in '{$department->name}'.");
    }

    /**
     * Delete a Department Approver Card.
     */
    public function deleteDepartmentApprover(Department $department, DepartmentApprover $approver): RedirectResponse
    {
        abort_unless(Auth::user()->hasRole(['system_admin', 'hod']), 403);

        if ($approver->department_id === $department->id) {
            $approver->delete();
        }

        return back()->with('status', "Approver card removed from '{$department->name}'.");
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

        if ($department->children()->count() > 0) {
            return back()->withErrors(['error' => "Cannot delete department '{$department->name}' because it has sub-departments. Delete or move them first."]);
        }

        // Clean up categories and approvers linked to this department/unit
        $department->categories()->update(['it_department_id' => null]);
        $department->approvers()->delete();

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
            'manager_user_id' => 'nullable|exists:users,id',
        ]);

        $branch = Branch::create([
            'name' => trim($validated['name']),
            'code' => strtoupper(trim($validated['code'])),
            'manager_user_id' => $validated['manager_user_id'] ?? null,
            'is_active' => true,
        ]);

        return back()->with('status', "Branch '{$branch->name}' created successfully.");
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
            'manager_user_id' => 'nullable|exists:users,id',
        ]);

        $branch->update([
            'name' => trim($validated['name']),
            'code' => strtoupper(trim($validated['code'])),
            'manager_user_id' => $validated['manager_user_id'] ?? null,
        ]);

        return back()->with('status', "Branch '{$branch->name}' updated successfully.");
    }

    /**
     * Quick-assign or update Branch Manager / Approver.
     */
    public function updateBranchManager(Request $request, Branch $branch): RedirectResponse
    {
        abort_unless(Auth::user()->hasRole(['system_admin', 'hod']), 403);

        $validated = $request->validate([
            'manager_user_id' => 'nullable|exists:users,id',
        ]);

        $branch->update([
            'manager_user_id' => $validated['manager_user_id'] ?? null,
        ]);

        $managerName = $branch->manager ? $branch->manager->name : 'Unassigned';
        return back()->with('status', "Branch '{$branch->name}' manager updated to {$managerName}.");
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

    /**
     * Approvers: Dedicated tab for Multi-Level Approval Hierarchy & Matrix.
     */
    public function approvers(Request $request): View
    {
        abort_unless(Auth::user()->hasRole(['system_admin', 'hod']), 403);

        $selectedBranchId = $request->query('branch_id');
        $selectedDeptId = $request->query('department_id');
        $selectedLevel = $request->query('level');

        $approversQuery = DepartmentApprover::with(['department', 'branch', 'user.roles', 'user.employee'])
            ->orderByRaw('CASE WHEN branch_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('department_id')
            ->orderBy('level')
            ->orderBy('id');

        if ($selectedBranchId === 'hq') {
            $approversQuery->whereNull('branch_id');
        } elseif (!empty($selectedBranchId)) {
            $approversQuery->where('branch_id', $selectedBranchId);
        }

        if (!empty($selectedDeptId)) {
            $approversQuery->where('department_id', $selectedDeptId);
        }

        if (!empty($selectedLevel)) {
            $approversQuery->where('level', (int) $selectedLevel);
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $approversQuery->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  })
                  ->orWhereHas('department', fn($dq) => $dq->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))
                  ->orWhereHas('branch', fn($bq) => $bq->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
            });
        }

        $perPage = (int) $request->input('per_page', 10);
        if (!in_array($perPage, [10, 15, 25, 50], true)) {
            $perPage = 10;
        }

        $approverCards = $approversQuery->paginate($perPage)->withQueryString();

        $departments = Department::where('is_active', true)
            ->where(function ($q) {
                $itId = Department::where('code', 'IT')->value('id') ?? 0;
                $q->whereNull('parent_id')->orWhere('parent_id', '!=', $itId);
            })
            ->orderBy('name')
            ->get();

        $branches = Branch::where('is_active', true)->orderBy('name')->get();

        $itUnits = Department::itUnits()->with('head')->orderBy('name')->get();

        $approverCandidates = User::where('is_active', true)
            ->with(['roles', 'employee.department', 'employee.branch'])
            ->orderBy('name')
            ->get();

        $candidatesJson = $approverCandidates->map(function ($u) {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'initials' => strtoupper(substr($u->name, 0, 2)),
                'dept_id' => $u->employee?->department_id,
                'dept_name' => $u->employee?->department?->name,
                'dept_code' => $u->employee?->department?->code,
                'branch_id' => $u->employee?->branch_id,
                'branch_name' => $u->employee?->branch?->name,
                'branch_code' => $u->employee?->branch?->code,
                'roles' => $u->roles->pluck('name')->join(', '),
            ];
        })->values();

        $totalCardsCount = DepartmentApprover::count();
        $hqCardsCount = DepartmentApprover::whereNull('branch_id')->count();
        $branchCardsCount = DepartmentApprover::whereNotNull('branch_id')->count();

        return view('portal.admin.approvers', compact(
            'approverCards',
            'departments',
            'branches',
            'itUnits',
            'approverCandidates',
            'candidatesJson',
            'selectedBranchId',
            'selectedDeptId',
            'selectedLevel',
            'totalCardsCount',
            'hqCardsCount',
            'branchCardsCount',
            'perPage',
            'search'
        ));
    }

    /**
     * Store new Approver Card from the Dedicated Approvers Tab.
     */
    public function storeApprover(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()->hasRole(['system_admin', 'hod']), 403);

        $validated = $request->validate([
            'department_id' => 'required|exists:departments,id',
            'branch_id' => 'nullable|exists:branches,id',
            'user_id' => 'required|exists:users,id',
            'level' => 'required|integer|min:1|max:20',
            'title' => 'nullable|string|max:100',
        ]);

        $defaultTitle = match ((int) $validated['level']) {
            1 => 'Team Leader',
            2 => 'Supervisor',
            3 => 'Department Manager',
            4 => 'General Manager',
            5 => 'Director / Executive',
            default => "Level {$validated['level']} Approver",
        };

        $dept = Department::findOrFail($validated['department_id']);

        DepartmentApprover::create([
            'department_id' => $dept->id,
            'branch_id' => !empty($validated['branch_id']) ? $validated['branch_id'] : null,
            'user_id' => $validated['user_id'],
            'level' => $validated['level'],
            'title' => !empty($validated['title']) ? $validated['title'] : $defaultTitle,
            'is_active' => true,
        ]);

        return back()->with('status', "Approver card added successfully.");
    }

    /**
     * Update Approver Card from the Dedicated Approvers Tab.
     */
    public function updateApprover(Request $request, DepartmentApprover $approver): RedirectResponse
    {
        abort_unless(Auth::user()->hasRole(['system_admin', 'hod']), 403);

        $validated = $request->validate([
            'department_id' => 'required|exists:departments,id',
            'branch_id' => 'nullable|exists:branches,id',
            'user_id' => 'required|exists:users,id',
            'level' => 'required|integer|min:1|max:20',
            'title' => 'nullable|string|max:100',
        ]);

        $defaultTitle = match ((int) $validated['level']) {
            1 => 'Team Leader',
            2 => 'Supervisor',
            3 => 'Department Manager',
            4 => 'General Manager',
            5 => 'Director / Executive',
            default => "Level {$validated['level']} Approver",
        };

        $approver->update([
            'department_id' => $validated['department_id'],
            'branch_id' => !empty($validated['branch_id']) ? $validated['branch_id'] : null,
            'user_id' => $validated['user_id'],
            'level' => $validated['level'],
            'title' => !empty($validated['title']) ? $validated['title'] : $defaultTitle,
        ]);

        return back()->with('status', "Approver card updated successfully.");
    }

    /**
     * Delete Approver Card from the Dedicated Approvers Tab.
     */
    public function destroyApprover(DepartmentApprover $approver): RedirectResponse
    {
        abort_unless(Auth::user()->hasRole(['system_admin', 'hod']), 403);

        $approver->delete();

        return back()->with('status', "Approver card removed successfully.");
    }
}
