@extends('portal.layout')

@section('title', 'System Administration - Users & Roles')

@section('content')
<div x-data="{ roleModal: false, createUserModal: false, search: '', targetUser: {}, userRoles: [] }" class="space-y-6">

    <!-- Top Admin Header -->
    <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="text-xs text-indigo-600 font-extrabold uppercase tracking-wider">System Administration</div>
            <h1 class="text-xl sm:text-2xl font-black text-slate-900 mt-1">Staff Roster & Role Permissions</h1>
            <p class="text-xs text-slate-500 mt-0.5">Manage Help Desk specialists, field technicians, HOD department executives, and system administrators.</p>
        </div>

        <button type="button" @click="createUserModal = true" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs px-4 py-2.5 rounded-xl shadow-xs transition flex items-center space-x-1.5 self-start sm:self-auto">
            <span>➕</span>
            <span>Add Staff User</span>
        </button>
    </div>

    <!-- Admin Navigation Tabs & Live Search -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
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
        </div>

        <!-- Live Instant Search Filter -->
        <div class="relative w-full sm:w-72">
            <input type="text"
                   x-model="search"
                   placeholder="Filter users by name, email, code..."
                   class="w-full text-xs pl-9 pr-3 py-2 rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 shadow-2xs">
            <span class="absolute left-3 top-2.5 text-slate-400 text-xs">🔍</span>
            <button x-show="search" @click="search = ''" class="absolute right-3 top-2 text-slate-400 hover:text-slate-600 text-xs">✕</button>
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
                        <th class="py-3 px-4">Department & Branch</th>
                        <th class="py-3 px-4">Telegram Linked</th>
                        <th class="py-3 px-4">Assigned Roles</th>
                        <th class="py-3 px-4">Account Status</th>
                        <th class="py-3 px-6 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($users as $user)
                        <tr class="hover:bg-slate-50/70 transition"
                            x-show="!search.trim() || '{{ strtolower(addslashes($user->name)) }}'.includes(search.toLowerCase().trim()) || '{{ strtolower(addslashes($user->email ?? '')) }}'.includes(search.toLowerCase().trim()) || '{{ strtolower(addslashes($user->telegram_username ?? '')) }}'.includes(search.toLowerCase().trim()) || '{{ strtolower(addslashes($user->employee?->employee_code ?? '')) }}'.includes(search.toLowerCase().trim())">
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
                                        &bull; <span class="font-mono text-indigo-600">{{ $user->employee->employee_code }}</span>
                                    @endif
                                </div>
                            </td>

                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <div class="font-semibold text-slate-700">{{ $user->employee?->department?->name ?? 'Standalone Account' }}</div>
                                <div class="text-[10px] text-slate-400">{{ $user->employee?->branch?->name ?? 'HQ' }}</div>
                            </td>

                            <td class="py-3.5 px-4 whitespace-nowrap">
                                @if($user->telegram_id)
                                    <span class="text-blue-600 font-mono text-[11px] flex items-center space-x-1">
                                        <span>📱</span>
                                        <span>{{ $user->telegram_username ? '@' . $user->telegram_username : 'ID: ' . $user->telegram_id }}</span>
                                    </span>
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

                            <td class="py-3.5 px-6 text-right whitespace-nowrap space-x-2">
                                <button type="button"
                                        @click="targetUser = {{ json_encode($user) }}; userRoles = {{ json_encode($user->roles->pluck('id')) }}; roleModal = true"
                                        class="text-[11px] text-indigo-600 hover:underline font-semibold">
                                    Edit Roles
                                </button>

                                @if($user->id !== Auth::id() && $user->id !== 1 && $user->email !== 'admin@cbvh.com.kh')
                                    <form method="POST" action="{{ route('portal.admin.users.toggle', $user->id) }}" class="inline-block">
                                        @csrf
                                        <button type="submit" class="text-[11px] {{ $user->is_active ? 'text-rose-600 hover:underline' : 'text-emerald-600 hover:underline' }} font-semibold">
                                            {{ $user->is_active ? 'Suspend' : 'Activate' }}
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-400 text-xs">No users found.</td>
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
    <!-- 1. CREATE STAFF USER MODAL                 -->
    <!-- ========================================== -->
    <div x-show="createUserModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4" x-cloak>
        <div @click.away="createUserModal = false" class="bg-white rounded-3xl max-w-lg w-full shadow-2xl p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center space-x-2">
                    <span class="text-xl">👤</span>
                    <h3 class="text-base font-bold text-slate-900">Add Staff / Admin User</h3>
                </div>
                <button type="button" @click="createUserModal = false" class="text-slate-400 hover:text-slate-600 text-sm font-bold">✕</button>
            </div>
            
            <form method="POST" action="{{ route('portal.admin.users.store') }}" class="space-y-4">
                @csrf

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Full Name</label>
                        <input type="text" name="name" required placeholder="e.g. Dina Lim" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Corporate Email</label>
                        <input type="email" name="email" required placeholder="e.g. dina.lim@cbvh.com.kh" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Login Password</label>
                        <input type="text" name="password" placeholder="Default: Password123!" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <p class="text-[10px] text-slate-400 mt-0.5">Leave blank for default: Password123!</p>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Telegram Username (Optional)</label>
                        <input type="text" name="telegram_username" placeholder="e.g. Lim_Dina01" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Employee ID Code (Optional)</label>
                    <input type="text" name="employee_code" placeholder="e.g. EMP-1002" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <p class="text-[10px] text-slate-400 mt-0.5">Links this user account to employee department and branch records.</p>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1.5">Assign Roles</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        @foreach($roles as $role)
                            <label class="flex items-center space-x-2.5 p-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer transition">
                                <input type="checkbox" name="roles[]" value="{{ $role->id }}" {{ $role->name === 'system_admin' ? 'checked' : '' }} class="rounded text-indigo-600 focus:ring-indigo-500">
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
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-xs transition">Create Staff User</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- 2. EDIT ROLES MODAL                        -->
    <!-- ========================================== -->
    <div x-show="roleModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4" x-cloak>
        <div @click.away="roleModal = false" class="bg-white rounded-3xl max-w-md w-full shadow-2xl p-6 space-y-4">
            <h3 class="text-base font-bold text-slate-800">
                Assign Roles: <span class="text-indigo-600" x-text="targetUser.name"></span>
            </h3>
            <p class="text-xs text-slate-500">Users can hold multiple operational roles simultaneously (e.g. Technician + Help Desk).</p>
            
            <form :action="'{{ url('portal/admin/users') }}/' + targetUser.id + '/roles'" method="POST" class="space-y-4">
                @csrf
                <div class="space-y-2">
                    @foreach($roles as $role)
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

</div>
@endsection
