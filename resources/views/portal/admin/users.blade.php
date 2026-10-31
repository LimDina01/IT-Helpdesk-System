@extends('portal.layout')

@section('title', 'System Administration - Users & Roles')

@section('content')
<div x-data="{
    roleModal: false,
    createUserModal: false,
    createTmaModal: false,
    editUserModal: false,
    search: '',
    targetUser: {},
    userRoles: [],
    editForm: {
        id: '',
        name: '',
        email: '',
        employee_code: '',
        department_id: '',
        branch_id: '',
        phone: '',
        telegram_username: '',
        telegram_id: '',
        is_active: true,
        can_access_portal: false,
        roles: []
    },
    openEditModal(u) {
        this.targetUser = u;
        this.editForm = {
            id: u.id,
            name: u.name,
            email: u.email,
            employee_code: u.employee ? (u.employee.employee_code || '') : '',
            department_id: u.employee ? (u.employee.department_id || '') : '',
            branch_id: u.employee ? (u.employee.branch_id || '') : '',
            phone: u.employee ? (u.employee.phone || '') : '',
            telegram_username: u.telegram_username || '',
            telegram_id: u.telegram_id || '',
            is_active: u.is_active ? 1 : 0,
            can_access_portal: u.can_access_portal ? 1 : 0,
            roles: u.roles ? u.roles.map(r => r.id) : []
        };
        this.editUserModal = true;
    }
}" class="space-y-6">

    <!-- Top Admin Header -->
    <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="text-xs text-indigo-600 font-extrabold uppercase tracking-wider">System Administration</div>
            <h1 class="text-xl sm:text-2xl font-black text-slate-900 mt-1">Staff Roster & User Management</h1>
            <p class="text-xs text-slate-500 mt-0.5">Manage Web Portal IT technical staff as well as bank employees and department approvers for Telegram Mini App.</p>
        </div>

        <!-- 2 Distinct Creation Actions -->
        <div class="flex flex-wrap items-center gap-2 self-start sm:self-auto">
            <button type="button" @click="createUserModal = true" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs px-3.5 py-2.5 rounded-xl shadow-xs transition flex items-center space-x-1.5">
                <span>🛠️</span>
                <span>Add IT Staff User</span>
            </button>
            <button type="button" @click="createTmaModal = true" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-3.5 py-2.5 rounded-xl shadow-xs transition flex items-center space-x-1.5">
                <span>📱</span>
                <span>Add TMA Employee</span>
            </button>
        </div>
    </div>

    <!-- Admin Navigation Tabs & Filter Pills & Live Search -->
    <div class="space-y-3">
        <!-- Top Navigation -->
        <div class="flex items-center space-x-2 bg-slate-100 p-1 rounded-2xl text-xs font-semibold overflow-x-auto w-fit">
            <a href="{{ route('portal.admin.index') }}" class="text-slate-600 hover:text-slate-900 px-4 py-2 rounded-xl transition flex items-center space-x-1.5">
                <span>📁</span>
                <span>Categories & SLAs</span>
            </a>
            <a href="{{ route('portal.admin.holidays') }}" class="text-slate-600 hover:text-slate-900 px-4 py-2 rounded-xl transition flex items-center space-x-1.5">
                <span>📅</span>
                <span>Company Holidays</span>
            </a>
            <a href="{{ route('portal.admin.users') }}" class="bg-white text-indigo-600 shadow-xs px-4 py-2 rounded-xl transition flex items-center space-x-1.5">
                <span>👥</span>
                <span>Staff & User Roles</span>
            </a>
            <a href="{{ route('portal.admin.organization') }}" class="text-slate-600 hover:text-slate-900 px-4 py-2 rounded-xl transition flex items-center space-x-1.5">
                <span>🏢</span>
                <span>Departments & Branches</span>
            </a>
        </div>

        <!-- Filter Pills & Search -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-1">
            <div class="flex items-center space-x-1.5 bg-slate-100 p-1 rounded-2xl text-xs font-semibold overflow-x-auto">
                <a href="{{ route('portal.admin.users', ['filter' => 'all']) }}" class="px-3.5 py-1.5 rounded-xl transition flex items-center space-x-1.5 {{ $filter === 'all' ? 'bg-white text-indigo-600 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                    <span>👥</span>
                    <span>All Accounts</span>
                    <span class="text-[10px] px-1.5 py-0.2 rounded-full {{ $filter === 'all' ? 'bg-indigo-50 text-indigo-600' : 'bg-slate-200 text-slate-600' }}">{{ $totalCount }}</span>
                </a>
                <a href="{{ route('portal.admin.users', ['filter' => 'portal']) }}" class="px-3.5 py-1.5 rounded-xl transition flex items-center space-x-1.5 {{ $filter === 'portal' ? 'bg-white text-indigo-600 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                    <span>🛠️</span>
                    <span>IT Staff Portal</span>
                    <span class="text-[10px] px-1.5 py-0.2 rounded-full {{ $filter === 'portal' ? 'bg-indigo-50 text-indigo-600' : 'bg-slate-200 text-slate-600' }}">{{ $staffCount }}</span>
                </a>
                <a href="{{ route('portal.admin.users', ['filter' => 'tma']) }}" class="px-3.5 py-1.5 rounded-xl transition flex items-center space-x-1.5 {{ $filter === 'tma' ? 'bg-white text-indigo-600 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                    <span>📱</span>
                    <span>TMA Requesters</span>
                    <span class="text-[10px] px-1.5 py-0.2 rounded-full {{ $filter === 'tma' ? 'bg-indigo-50 text-indigo-600' : 'bg-slate-200 text-slate-600' }}">{{ $tmaCount }}</span>
                </a>
            </div>

            <!-- Live Instant Search Filter -->
            <div class="relative w-full sm:w-72">
                <input type="text"
                       x-model="search"
                       placeholder="Filter by name, email, code, dept..."
                       class="w-full text-xs pl-9 pr-3 py-2 rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 shadow-2xs">
                <span class="absolute left-3 top-2.5 text-slate-400 text-xs">🔍</span>
                <button x-show="search" @click="search = ''" class="absolute right-3 top-2 text-slate-400 hover:text-slate-600 text-xs">✕</button>
            </div>
        </div>
    </div>

    <!-- Alert Messages -->
    @if(session('status'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold px-4 py-3 rounded-2xl flex items-center space-x-2">
            <span>✅</span>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="bg-rose-50 border border-rose-200 text-rose-800 text-xs font-semibold px-4 py-3 rounded-2xl flex items-center space-x-2">
            <span>⚠️</span>
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    <!-- Users Table Card -->
    <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-500 font-bold uppercase tracking-wider text-[10px] border-b border-slate-200">
                    <tr>
                        <th class="py-3 px-6">User / Employee</th>
                        <th class="py-3 px-4 min-w-[200px]">Department & Branch</th>
                        <th class="py-3 px-4">Telegram Linked</th>
                        <th class="py-3 px-4">Assigned Roles</th>
                        <th class="py-3 px-4">Account Status</th>
                        <th class="py-3 px-4">Portal Access</th>
                        <th class="py-3 px-6 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($users as $user)
                        <tr class="hover:bg-slate-50/70 transition"
                            x-show="!search.trim() || '{{ strtolower(addslashes($user->name)) }}'.includes(search.toLowerCase().trim()) || '{{ strtolower(addslashes($user->email ?? '')) }}'.includes(search.toLowerCase().trim()) || '{{ strtolower(addslashes($user->telegram_username ?? '')) }}'.includes(search.toLowerCase().trim()) || '{{ strtolower(addslashes($user->employee?->employee_code ?? '')) }}'.includes(search.toLowerCase().trim()) || '{{ strtolower(addslashes($user->employee?->department?->name ?? '')) }}'.includes(search.toLowerCase().trim())">
                            <td class="py-3.5 px-6 whitespace-nowrap">
                                <div class="flex items-center space-x-2">
                                    <div class="font-bold text-slate-900">{{ $user->name }}</div>
                                    @if($user->id === 1 || $user->email === 'admin@cbvh.com.kh')
                                        <span class="text-[9px] font-extrabold uppercase px-1.5 py-0.2 rounded bg-amber-100 text-amber-800 border border-amber-200">Primary Admin</span>
                                    @endif
                                </div>
                                <div class="text-[10px] text-slate-400">
                                    {{ $user->email ?? 'No email' }}
                                    @if($user->employee)
                                        &bull; <span class="font-mono text-indigo-600 font-bold">{{ $user->employee->employee_code }}</span>
                                    @endif
                                </div>
                            </td>

                            <td class="py-3.5 px-4 whitespace-nowrap min-w-[200px]">
                                <div class="font-semibold text-slate-700">{{ $user->employee?->department?->name ?? 'Standalone IT Account' }}</div>
                                <div class="text-[10px] text-slate-400">{{ $user->employee?->branch?->name ?? 'Head Office' }}</div>
                            </td>

                            <td class="py-3.5 px-4 whitespace-nowrap">
                                @if($user->telegram_id)
                                    <div class="flex items-center space-x-2">
                                        <span class="text-blue-600 font-mono text-[11px] flex items-center space-x-1">
                                            <span>📱</span>
                                            <span>{{ $user->telegram_username ? '@' . $user->telegram_username : 'ID: ' . $user->telegram_id }}</span>
                                        </span>
                                        <form method="POST" action="{{ route('portal.admin.users.unlink-telegram', $user->id) }}" onsubmit="return confirm('Unlink Telegram account for {{ $user->name }}? The user will have to link again via Telegram.');" class="inline">
                                            @csrf
                                            <button type="submit" class="text-[10px] text-rose-500 hover:text-rose-700 underline font-semibold cursor-pointer" title="Unlink Telegram Account">Unlink</button>
                                        </form>
                                    </div>
                                @elseif($user->telegram_username)
                                    <span class="text-blue-600 font-mono text-[11px] flex items-center space-x-1">
                                        <span>📱</span>
                                        <span>@ {{ $user->telegram_username }}</span>
                                    </span>
                                @else
                                    <span class="text-slate-400 text-[11px]">Unlinked</span>
                                @endif
                            </td>

                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <div class="flex flex-wrap gap-1">
                                    @foreach($user->roles as $role)
                                        @if($role->name === 'system_admin')
                                            <span class="bg-red-100 text-red-800 font-extrabold text-[9px] uppercase px-1.5 py-0.2 rounded border border-red-200">Admin</span>
                                        @elseif($role->name === 'hod')
                                            <span class="bg-purple-100 text-purple-800 font-extrabold text-[9px] uppercase px-1.5 py-0.2 rounded border border-purple-200">HOD</span>
                                        @elseif($role->name === 'helpdesk')
                                            <span class="bg-blue-100 text-blue-800 font-extrabold text-[9px] uppercase px-1.5 py-0.2 rounded border border-blue-200">Help Desk</span>
                                        @elseif($role->name === 'technician')
                                            <span class="bg-emerald-100 text-emerald-800 font-extrabold text-[9px] uppercase px-1.5 py-0.2 rounded border border-emerald-200">Technician</span>
                                        @else
                                            <span class="bg-slate-100 text-slate-700 font-medium text-[9px] uppercase px-1.5 py-0.2 rounded border border-slate-200">Requester</span>
                                        @endif
                                    @endforeach
                                </div>
                            </td>

                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full border {{ $user->is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-rose-50 text-rose-700 border-rose-200' }}">
                                    {{ $user->is_active ? 'Active' : 'Suspended' }}
                                </span>
                            </td>

                            <td class="py-3.5 px-4 whitespace-nowrap">
                                @if($user->isStaff())
                                    @if($user->can_access_portal)
                                        <span class="inline-flex items-center space-x-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-200" title="User is authorized to log in via Web Portal">
                                            <span>🌐</span>
                                            <span>Granted</span>
                                        </span>
                                    @else
                                        <span class="inline-flex items-center space-x-1 text-[10px] font-medium px-2 py-0.5 rounded-full bg-slate-100 text-slate-500 border border-slate-200" title="Web Portal access is revoked">
                                            <span>🔒</span>
                                            <span>Not Granted</span>
                                        </span>
                                    @endif
                                @else
                                    <span class="inline-flex items-center space-x-1 text-[10px] font-medium px-2 py-0.5 rounded-full bg-slate-50 text-slate-400 border border-slate-200" title="Telegram Mini App only (TMA Requester)">
                                        <span>📱</span>
                                        <span>TMA Only</span>
                                    </span>
                                @endif
                            </td>

                            <td class="py-3.5 px-6 text-right whitespace-nowrap space-x-2">
                                <button type="button"
                                        @click="openEditModal({{ json_encode($user) }})"
                                        class="text-[11px] text-blue-600 hover:text-blue-800 font-bold hover:underline inline-flex items-center space-x-1 cursor-pointer">
                                    <span>✏️</span>
                                    <span>Edit</span>
                                </button>

                                @if($user->isStaff() && $user->can_access_portal)
                                    <button type="button"
                                            @click="targetUser = {{ json_encode($user) }}; userRoles = {{ json_encode($user->roles->pluck('id')) }}; roleModal = true"
                                            class="text-[11px] text-indigo-600 hover:underline font-semibold cursor-pointer">
                                        Roles
                                    </button>
                                @endif

                                @if($user->isStaff() && $user->id !== Auth::id() && $user->id !== 1 && $user->email !== 'admin@cbvh.com.kh')
                                    <!-- Toggle Portal Access Button -->
                                    <form method="POST" action="{{ route('portal.admin.users.toggle-portal', $user->id) }}" class="inline-block" onsubmit="return confirm('{{ $user->can_access_portal ? 'Revoke Web Portal login access for ' . addslashes($user->name) . '?' : 'Grant Web Portal login access to ' . addslashes($user->name) . '?' }}');">
                                        @csrf
                                        <button type="submit" class="text-[11px] {{ $user->can_access_portal ? 'text-amber-600 hover:text-amber-800' : 'text-blue-600 hover:text-blue-800' }} hover:underline font-semibold cursor-pointer">
                                            {{ $user->can_access_portal ? 'Revoke Portal' : 'Grant Portal' }}
                                        </button>
                                    </form>

                                    <!-- Suspend / Activate User Button -->
                                    <form method="POST" action="{{ route('portal.admin.users.toggle', $user->id) }}" class="inline-block">
                                        @csrf
                                        <button type="submit" class="text-[11px] {{ $user->is_active ? 'text-rose-600 hover:underline' : 'text-emerald-600 hover:underline' }} font-semibold cursor-pointer">
                                            {{ $user->is_active ? 'Suspend' : 'Activate' }}
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-slate-400 text-xs">No users found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($users->hasPages())
            <div class="p-4 border-t border-slate-200">
                {{ $users->links() }}
            </div>
        @endif
    </div>

    <!-- ========================================== -->
    <!-- 1. CREATE IT STAFF USER MODAL              -->
    <!-- ========================================== -->
    <div x-show="createUserModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4" x-cloak>
        <div @click.away="createUserModal = false" class="bg-white rounded-3xl max-w-lg w-full shadow-2xl p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center space-x-2">
                    <span class="text-xl">🛠️</span>
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Register / Promote IT Staff</h3>
                        <p class="text-[11px] text-slate-500">Assign operational roles to IT team members. AD users log in with their Windows domain password.</p>
                    </div>
                </div>
                <button type="button" @click="createUserModal = false" class="text-slate-400 hover:text-slate-600 text-sm font-bold">✕</button>
            </div>
            
            <form method="POST" action="{{ route('portal.admin.users.store') }}" class="space-y-4">
                @csrf

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Full Name</label>
                        <input type="text" name="name" required placeholder="e.g. Sem Boran" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Corporate Email / AD Login</label>
                        <input type="email" name="email" required placeholder="e.g. boran.sem@cbvh.com.kh" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Local Password (Optional)</label>
                        <input type="text" name="password" placeholder="Default: Password123!" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <p class="text-[10px] text-slate-400 mt-0.5">Optional for AD users. Default: Password123!</p>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Telegram Username (Optional)</label>
                        <input type="text" name="telegram_username" placeholder="e.g. boran_sem" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Employee ID Code (Optional)</label>
                    <input type="text" name="employee_code" placeholder="e.g. EMP-1005" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <p class="text-[10px] text-slate-400 mt-0.5">Links this staff member to company branch and employee records.</p>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1.5">Assign Operational IT Roles</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        @foreach($roles as $role)
                            <label class="flex items-center space-x-2.5 p-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer transition">
                                <input type="checkbox" name="roles[]" value="{{ $role->id }}" {{ $role->name === 'technician' ? 'checked' : '' }} class="rounded text-indigo-600 focus:ring-indigo-500">
                                <div>
                                    <div class="text-xs font-bold text-slate-800">{{ $role->label }}</div>
                                    <div class="text-[9px] text-slate-400 font-mono">{{ $role->name }}</div>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="flex justify-end space-x-2 pt-3 border-t border-slate-100">
                    <button type="button" @click="createUserModal = false" class="px-4 py-2 text-xs text-slate-600 font-semibold hover:bg-slate-100 rounded-xl transition">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-xs transition">Save IT Staff User</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- 2. CREATE TMA EMPLOYEE MODAL               -->
    <!-- ========================================== -->
    <div x-show="createTmaModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4" x-cloak>
        <div @click.away="createTmaModal = false" class="bg-white rounded-3xl max-w-xl w-full shadow-2xl p-6 sm:p-7 space-y-4 max-h-[92vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center space-x-2.5">
                    <span class="text-2xl">📱</span>
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Register TMA Employee / Requester</h3>
                        <p class="text-[11px] text-slate-500">Pre-register bank employee for Telegram Mini App & department approval routing.</p>
                    </div>
                </div>
                <button type="button" @click="createTmaModal = false" class="text-slate-400 hover:text-slate-600 text-sm font-bold">✕</button>
            </div>
            
            <form method="POST" action="{{ route('portal.admin.users.tma.store') }}" class="space-y-4">
                @csrf

                <!-- Row 1: Name & Email -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Full Name</label>
                        <input type="text" name="name" required placeholder="e.g. Sokha Chan" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Corporate Email</label>
                        <input type="email" name="email" required placeholder="e.g. sokha.chan@cbvh.com.kh" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <!-- Row 2: Employee Code & Phone -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Employee ID Code</label>
                        <input type="text" name="employee_code" required placeholder="e.g. EMP-1009" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono font-bold uppercase">
                        <p class="text-[10px] text-slate-400 mt-0.5">Used for Telegram Mini App account link matching.</p>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Phone Number (Optional)</label>
                        <input type="text" name="phone" placeholder="e.g. +855 12 333 444" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <!-- Row 3: Department & Branch (Wide 2-Column Section) -->
                <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200/80 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-extrabold text-slate-700 uppercase tracking-wider flex items-center space-x-1">
                            <span>🏢</span>
                            <span>Organization Placement</span>
                        </span>
                        <a href="{{ route('portal.admin.organization') }}" target="_blank" class="text-[10px] text-indigo-600 hover:text-indigo-800 font-bold hover:underline flex items-center space-x-0.5">
                            <span>⚙️ Manage Depts & Branches</span>
                            <span>↗</span>
                        </a>
                    </div>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 pt-1">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Department</label>
                            <select name="department_id" required class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}">{{ $dept->name }} ({{ $dept->code }})</option>
                                @endforeach
                            </select>
                            <p class="text-[10px] text-slate-400 mt-0.5">Directs HOD service request approvals.</p>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Branch / Location</label>
                            <select name="branch_id" required class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}">{{ $branch->name }} ({{ $branch->code }})</option>
                                @endforeach
                            </select>
                            <p class="text-[10px] text-slate-400 mt-0.5">Physical office for equipment support.</p>
                        </div>
                    </div>
                </div>

                <!-- Row 4: Role Selection in TMA -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1.5">TMA Account Role</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <label class="flex items-start space-x-2.5 p-3 rounded-2xl border border-slate-200 hover:bg-slate-50 cursor-pointer transition">
                            <input type="radio" name="role" value="requester" checked class="mt-0.5 text-blue-600 focus:ring-blue-500">
                            <div>
                                <div class="text-xs font-bold text-slate-900">👤 Requester (Employee)</div>
                                <div class="text-[10px] text-slate-500 mt-0.5">Standard staff member. Can submit tickets and chat with support.</div>
                            </div>
                        </label>

                        <label class="flex items-start space-x-2.5 p-3 rounded-2xl border border-slate-200 hover:bg-slate-50 cursor-pointer transition">
                            <input type="radio" name="role" value="hod" class="mt-0.5 text-blue-600 focus:ring-blue-500">
                            <div>
                                <div class="text-xs font-bold text-slate-900">👔 Department HOD</div>
                                <div class="text-[10px] text-slate-500 mt-0.5">Department Head. Approves Step 1 service requests for this department.</div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Row 5: Web Login Password & Telegram Username -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Web Login Password</label>
                        <input type="text" name="password" placeholder="Default: Password123!" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <p class="text-[10px] text-slate-400 mt-0.5">Used for TMA web login if Telegram is down.</p>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Telegram Username (Optional)</label>
                        <input type="text" name="telegram_username" placeholder="e.g. chan_sokha" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <p class="text-[10px] text-slate-400 mt-0.5">Pre-binds Telegram account.</p>
                    </div>
                </div>

                <div class="flex justify-end space-x-2 pt-3 border-t border-slate-100">
                    <button type="button" @click="createTmaModal = false" class="px-4 py-2 text-xs text-slate-600 font-semibold hover:bg-slate-100 rounded-xl transition">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-xs transition">Save TMA Employee</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- 3. EDIT ROLES MODAL                        -->
    <!-- ========================================== -->
    <div x-show="roleModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4" x-cloak>
        <div @click.away="roleModal = false" class="bg-white rounded-3xl max-w-md w-full shadow-2xl p-6 space-y-4">
            <h3 class="text-base font-bold text-slate-800">
                Assign Roles: <span class="text-indigo-600" x-text="targetUser.name"></span>
            </h3>
            <p class="text-xs text-slate-500">Users can hold multiple operational roles simultaneously (e.g. Technician + Help Desk, or Requester + HOD).</p>
            
            <form :action="'{{ url('portal/admin/users') }}/' + targetUser.id + '/roles'" method="POST" class="space-y-4">
                @csrf
                <div class="space-y-2">
                    @foreach($allRoles as $role)
                        <label class="flex items-center space-x-3 p-3 rounded-2xl border border-slate-200 hover:bg-slate-50 cursor-pointer transition">
                            <input type="checkbox" name="roles[]" value="{{ $role->id }}" :checked="userRoles.includes({{ $role->id }})" class="rounded text-indigo-600 focus:ring-indigo-500">
                            <div>
                                <div class="text-xs font-bold text-slate-800">{{ $role->label }}</div>
                                <div class="text-[10px] text-slate-400 font-mono">{{ $role->name }}</div>
                            </div>
                        </label>
                    @endforeach
                </div>

                <div class="flex justify-end space-x-2 pt-2">
                    <button type="button" @click="roleModal = false" class="px-3.5 py-2 text-xs text-slate-600 font-semibold">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-xs transition">Update Roles</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- 4. EDIT USER & EMPLOYEE MODAL              -->
    <!-- ========================================== -->
    <div x-show="editUserModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4" x-cloak>
        <div @click.away="editUserModal = false" class="bg-white rounded-3xl max-w-xl w-full shadow-2xl p-6 space-y-4 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center space-x-2">
                    <span class="text-xl">✏️</span>
                    <div>
                        <h3 class="text-base font-bold text-slate-900">
                            Edit User & Employee: <span class="text-indigo-600" x-text="editForm.name"></span>
                        </h3>
                        <p class="text-[11px] text-slate-500">Update employee profile, department approval routing, branch, contact, and assigned roles.</p>
                    </div>
                </div>
                <button type="button" @click="editUserModal = false" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg">✕</button>
            </div>

            <form :action="'{{ url('portal/admin/users') }}/' + editForm.id" method="POST" class="space-y-4">
                @csrf
                @method('PUT')

                <!-- Row 1: Name & Email -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Full Name</label>
                        <input type="text" name="name" x-model="editForm.name" required class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Corporate Email</label>
                        <input type="email" name="email" x-model="editForm.email" required class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>

                <!-- Row 2: Employee Code & Phone -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Employee Code / ID</label>
                        <input type="text" name="employee_code" x-model="editForm.employee_code" placeholder="e.g. EMP-1008" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 uppercase">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Phone Number (Optional)</label>
                        <input type="text" name="phone" x-model="editForm.phone" placeholder="e.g. +855 12 345 678" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>

                <!-- Row 3: Department & Branch -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Department</label>
                        <select name="department_id" x-model="editForm.department_id" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="">-- No Department Assigned --</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->name }} ({{ $dept->code }})</option>
                            @endforeach
                        </select>
                        <p class="text-[10px] text-slate-400 mt-0.5">Crucial: Step 1 HOD service requests route to this department's HOD.</p>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Branch / Location</label>
                        <select name="branch_id" x-model="editForm.branch_id" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="">-- No Branch Assigned --</option>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->name }} ({{ $branch->code }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Row 4: Telegram & Password Reset -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Telegram Username</label>
                        <div class="flex items-center">
                            <span class="text-xs text-slate-400 font-bold mr-1">@</span>
                            <input type="text" name="telegram_username" x-model="editForm.telegram_username" placeholder="telegram_handle" class="w-full text-xs px-3 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Reset Password (Optional)</label>
                        <input type="text" name="password" placeholder="Leave blank to keep unchanged" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>

                <!-- Telegram Account Binding Notice -->
                <div x-show="editForm.telegram_id" class="p-3 bg-blue-50/80 rounded-2xl border border-blue-200/80 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div>
                        <div class="text-[11px] font-bold text-blue-900">📱 Telegram Bound</div>
                        <div class="text-[10px] text-blue-700">Telegram User ID: <span class="font-mono font-bold" x-text="editForm.telegram_id"></span></div>
                    </div>
                    <label class="inline-flex items-center space-x-2 text-xs font-bold text-rose-600 hover:text-rose-800 cursor-pointer">
                        <input type="checkbox" name="unlink_telegram" value="1" class="rounded text-rose-600 focus:ring-rose-500">
                        <span>Unlink Telegram</span>
                    </label>
                </div>

                <!-- Account Status Checkbox -->
                <div class="p-3 bg-slate-50 rounded-2xl border border-slate-200">
                    <label class="flex items-center space-x-2.5 cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" :checked="editForm.is_active == 1" class="rounded text-indigo-600 focus:ring-indigo-500">
                        <span class="text-xs font-semibold text-slate-700">Account Active (Uncheck to suspend user access)</span>
                    </label>
                </div>

                <!-- Portal Access & Roles Configuration -->
                <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 space-y-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-xs font-bold text-slate-900 flex items-center space-x-1.5">
                                <span>🌐</span>
                                <span>Web Portal Access</span>
                            </div>
                            <p class="text-[10px] text-slate-500 mt-0.5">Allow this user to sign into the Web Portal with corporate credentials.</p>
                        </div>
                        <label class="flex items-center space-x-2 cursor-pointer bg-white px-3 py-1.5 rounded-xl border border-slate-200 shadow-2xs">
                            <input type="checkbox" name="can_access_portal" value="1" x-model="editForm.can_access_portal" class="rounded text-indigo-600 focus:ring-indigo-500">
                            <span class="text-xs font-bold text-indigo-700">Allow Portal Login</span>
                        </label>
                    </div>

                    <div class="border-t border-slate-200/80 pt-3 space-y-2">
                        <label class="block text-[11px] font-bold text-slate-700 uppercase">Assigned Roles</label>
                        <p class="text-[10px] text-slate-500">Check the roles this user holds across TMA and Web Portal:</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-1">
                            @foreach($allRoles as $role)
                                <label class="flex items-center space-x-2.5 p-2.5 bg-white rounded-xl border border-slate-200 hover:border-indigo-400 cursor-pointer transition shadow-2xs">
                                    <input type="checkbox" name="roles[]" value="{{ $role->id }}" :checked="editForm.roles.includes({{ $role->id }})" class="rounded text-indigo-600 focus:ring-indigo-500">
                                    <div>
                                        <div class="text-xs font-bold text-slate-800">{{ $role->label }}</div>
                                        <div class="text-[10px] text-slate-400 font-mono">{{ $role->name }}</div>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="flex justify-end space-x-2 pt-3 border-t border-slate-100">
                    <button type="button" @click="editUserModal = false" class="px-4 py-2 text-xs text-slate-600 font-semibold hover:bg-slate-100 rounded-xl transition">Cancel</button>
                    <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-xs transition">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
