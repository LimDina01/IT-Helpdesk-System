@extends('portal.layout')

@section('title', 'System Administration - Departments & Branches')

@section('content')
<div x-data="{ 
    createDeptModal: false, 
    editDeptModal: false, 
    createBranchModal: false, 
    editBranchModal: false,
    targetDept: {}, 
    targetBranch: {} 
}" class="space-y-6">

    <!-- Top Admin Header -->
    <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="text-xs text-indigo-600 font-extrabold uppercase tracking-wider">System Administration</div>
            <h1 class="text-xl sm:text-2xl font-black text-slate-900 mt-1">Organization: Departments & Branches</h1>
            <p class="text-xs text-slate-500 mt-0.5">Manage bank departments for HOD service request approvals and branches for ticket asset routing.</p>
        </div>

        <div class="flex flex-wrap items-center gap-2 self-start sm:self-auto">
            <button type="button" @click="createDeptModal = true" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs px-3.5 py-2.5 rounded-xl shadow-xs transition flex items-center space-x-1.5">
                <span>➕</span>
                <span>Add Department</span>
            </button>
            <button type="button" @click="createBranchModal = true" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs px-3.5 py-2.5 rounded-xl shadow-xs transition flex items-center space-x-1.5">
                <span>➕</span>
                <span>Add Branch</span>
            </button>
        </div>
    </div>

    <!-- Admin Navigation Tabs -->
    <div class="flex items-center space-x-2 bg-slate-100 p-1 rounded-2xl text-xs font-semibold overflow-x-auto w-fit">
        <a href="{{ route('portal.admin.index') }}" class="text-slate-600 hover:text-slate-900 px-4 py-2 rounded-xl transition flex items-center space-x-1.5">
            <span>📁</span>
            <span>Categories & SLAs</span>
        </a>
        <a href="{{ route('portal.admin.holidays') }}" class="text-slate-600 hover:text-slate-900 px-4 py-2 rounded-xl transition flex items-center space-x-1.5">
            <span>📅</span>
            <span>Company Holidays</span>
        </a>
        <a href="{{ route('portal.admin.users') }}" class="text-slate-600 hover:text-slate-900 px-4 py-2 rounded-xl transition flex items-center space-x-1.5">
            <span>👥</span>
            <span>Staff & User Roles</span>
        </a>
        <a href="{{ route('portal.admin.organization') }}" class="bg-white text-indigo-600 shadow-xs px-4 py-2 rounded-xl transition flex items-center space-x-1.5">
            <span>🏢</span>
            <span>Departments & Branches</span>
        </a>
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

    <!-- Two-Column Organization Layout: Departments vs Branches -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- ========================================== -->
        <!-- 1. DEPARTMENTS CARD                        -->
        <!-- ========================================== -->
        <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden flex flex-col justify-between">
            <div>
                <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <div class="flex items-center space-x-2">
                            <span class="text-lg">🏛️</span>
                            <h2 class="text-sm font-bold text-slate-900">Company Departments</h2>
                            <span class="text-[10px] font-extrabold bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded-full border border-indigo-200/60">{{ $departments->count() }}</span>
                        </div>
                        <p class="text-[11px] text-slate-400 mt-0.5">Used for Department HOD service approvals in Telegram Mini App.</p>
                    </div>
                    <button type="button" @click="createDeptModal = true" class="text-xs text-indigo-600 hover:text-indigo-800 font-bold flex items-center space-x-1">
                        <span>➕ Add</span>
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 text-slate-500 font-bold uppercase tracking-wider text-[10px] border-b border-slate-200">
                            <tr>
                                <th class="py-3 px-5">Department Name</th>
                                <th class="py-3 px-3">Code</th>
                                <th class="py-3 px-3 text-center">Staff Count</th>
                                <th class="py-3 px-3">Status</th>
                                <th class="py-3 px-5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($departments as $dept)
                                <tr class="hover:bg-slate-50/70 transition">
                                    <td class="py-3 px-5 font-bold text-slate-800 whitespace-nowrap">
                                        {{ $dept->name }}
                                    </td>
                                    <td class="py-3 px-3 font-mono text-indigo-700 font-bold whitespace-nowrap">
                                        <span class="bg-indigo-50 border border-indigo-100 px-2 py-0.5 rounded-md text-[11px]">
                                            {{ $dept->code }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 text-center whitespace-nowrap">
                                        <span class="text-slate-600 font-semibold bg-slate-100 px-2 py-0.5 rounded-full text-[11px]">
                                            {{ $dept->employees_count }} staff
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 whitespace-nowrap">
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full border {{ $dept->is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-rose-50 text-rose-700 border-rose-200' }}">
                                            {{ $dept->is_active ? 'Active' : 'Suspended' }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-5 text-right whitespace-nowrap space-x-2">
                                        <button type="button" 
                                                @click="targetDept = {{ json_encode($dept) }}; editDeptModal = true"
                                                class="text-[11px] text-indigo-600 hover:underline font-semibold">
                                            Edit
                                        </button>
                                        <form method="POST" action="{{ route('portal.admin.departments.toggle', $dept->id) }}" class="inline-block">
                                            @csrf
                                            <button type="submit" class="text-[11px] {{ $dept->is_active ? 'text-amber-600 hover:underline' : 'text-emerald-600 hover:underline' }} font-semibold">
                                                {{ $dept->is_active ? 'Suspend' : 'Activate' }}
                                            </button>
                                        </form>
                                        @if($dept->employees_count === 0)
                                            <form method="POST" action="{{ route('portal.admin.departments.destroy', $dept->id) }}" class="inline-block" onsubmit="return confirm('Delete department {{ $dept->name }}?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-[11px] text-rose-600 hover:underline font-semibold">
                                                    Delete
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-6 text-center text-slate-400 text-xs">No departments found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- 2. BRANCHES CARD                           -->
        <!-- ========================================== -->
        <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden flex flex-col justify-between">
            <div>
                <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <div class="flex items-center space-x-2">
                            <span class="text-lg">📍</span>
                            <h2 class="text-sm font-bold text-slate-900">Branches & Offices</h2>
                            <span class="text-[10px] font-extrabold bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded-full border border-emerald-200/60">{{ $branches->count() }}</span>
                        </div>
                        <p class="text-[11px] text-slate-400 mt-0.5">Physical banking branch locations for on-site hardware and IT routing.</p>
                    </div>
                    <button type="button" @click="createBranchModal = true" class="text-xs text-emerald-600 hover:text-emerald-800 font-bold flex items-center space-x-1">
                        <span>➕ Add</span>
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 text-slate-500 font-bold uppercase tracking-wider text-[10px] border-b border-slate-200">
                            <tr>
                                <th class="py-3 px-5">Branch Name</th>
                                <th class="py-3 px-3">Code</th>
                                <th class="py-3 px-3 text-center">Staff & Tickets</th>
                                <th class="py-3 px-3">Status</th>
                                <th class="py-3 px-5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($branches as $branch)
                                <tr class="hover:bg-slate-50/70 transition">
                                    <td class="py-3 px-5 font-bold text-slate-800 whitespace-nowrap">
                                        {{ $branch->name }}
                                    </td>
                                    <td class="py-3 px-3 font-mono text-emerald-700 font-bold whitespace-nowrap">
                                        <span class="bg-emerald-50 border border-emerald-100 px-2 py-0.5 rounded-md text-[11px]">
                                            {{ $branch->code }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 text-center whitespace-nowrap">
                                        <span class="text-slate-600 font-semibold bg-slate-100 px-2 py-0.5 rounded-full text-[11px]">
                                            {{ $branch->employees_count }} staff &bull; {{ $branch->tickets_count }} tickets
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 whitespace-nowrap">
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full border {{ $branch->is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-rose-50 text-rose-700 border-rose-200' }}">
                                            {{ $branch->is_active ? 'Active' : 'Suspended' }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-5 text-right whitespace-nowrap space-x-2">
                                        <button type="button" 
                                                @click="targetBranch = {{ json_encode($branch) }}; editBranchModal = true"
                                                class="text-[11px] text-emerald-600 hover:underline font-semibold">
                                            Edit
                                        </button>
                                        <form method="POST" action="{{ route('portal.admin.branches.toggle', $branch->id) }}" class="inline-block">
                                            @csrf
                                            <button type="submit" class="text-[11px] {{ $branch->is_active ? 'text-amber-600 hover:underline' : 'text-emerald-600 hover:underline' }} font-semibold">
                                                {{ $branch->is_active ? 'Suspend' : 'Activate' }}
                                            </button>
                                        </form>
                                        @if($branch->employees_count === 0 && $branch->tickets_count === 0)
                                            <form method="POST" action="{{ route('portal.admin.branches.destroy', $branch->id) }}" class="inline-block" onsubmit="return confirm('Delete branch {{ $branch->name }}?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-[11px] text-rose-600 hover:underline font-semibold">
                                                    Delete
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-6 text-center text-slate-400 text-xs">No branches found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <!-- ========================================== -->
    <!-- MODAL 1: ADD DEPARTMENT                    -->
    <!-- ========================================== -->
    <div x-show="createDeptModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4" x-cloak>
        <div @click.away="createDeptModal = false" class="bg-white rounded-3xl max-w-md w-full shadow-2xl p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center space-x-2">
                    <span class="text-xl">🏛️</span>
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Add New Department</h3>
                        <p class="text-[11px] text-slate-500">Departments group employees for HOD approvals.</p>
                    </div>
                </div>
                <button type="button" @click="createDeptModal = false" class="text-slate-400 hover:text-slate-600 text-sm font-bold">✕</button>
            </div>
            
            <form method="POST" action="{{ route('portal.admin.departments.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Department Name</label>
                    <input type="text" name="name" required placeholder="e.g. Risk Management & Compliance" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Department Code</label>
                    <input type="text" name="code" required placeholder="e.g. RISK" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono uppercase">
                    <p class="text-[10px] text-slate-400 mt-0.5">Short unique identifier (e.g. OPS, FIN, IT, HR).</p>
                </div>

                <div class="flex justify-end space-x-2 pt-3 border-t border-slate-100">
                    <button type="button" @click="createDeptModal = false" class="px-4 py-2 text-xs text-slate-600 font-semibold hover:bg-slate-100 rounded-xl transition">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-xs transition">Create Department</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- MODAL 2: EDIT DEPARTMENT                   -->
    <!-- ========================================== -->
    <div x-show="editDeptModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4" x-cloak>
        <div @click.away="editDeptModal = false" class="bg-white rounded-3xl max-w-md w-full shadow-2xl p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center space-x-2">
                    <span class="text-xl">✏️</span>
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Edit Department</h3>
                        <p class="text-[11px] text-slate-500" x-text="targetDept.name"></p>
                    </div>
                </div>
                <button type="button" @click="editDeptModal = false" class="text-slate-400 hover:text-slate-600 text-sm font-bold">✕</button>
            </div>
            
            <form :action="'{{ url('portal/admin/departments') }}/' + targetDept.id" method="POST" class="space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Department Name</label>
                    <input type="text" name="name" required :value="targetDept.name" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Department Code</label>
                    <input type="text" name="code" required :value="targetDept.code" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono uppercase">
                </div>

                <div class="flex justify-end space-x-2 pt-3 border-t border-slate-100">
                    <button type="button" @click="editDeptModal = false" class="px-4 py-2 text-xs text-slate-600 font-semibold hover:bg-slate-100 rounded-xl transition">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-xs transition">Update Department</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- MODAL 3: ADD BRANCH                        -->
    <!-- ========================================== -->
    <div x-show="createBranchModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4" x-cloak>
        <div @click.away="createBranchModal = false" class="bg-white rounded-3xl max-w-md w-full shadow-2xl p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center space-x-2">
                    <span class="text-xl">📍</span>
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Add New Branch</h3>
                        <p class="text-[11px] text-slate-500">Banking branches for physical equipment and ticket support.</p>
                    </div>
                </div>
                <button type="button" @click="createBranchModal = false" class="text-slate-400 hover:text-slate-600 text-sm font-bold">✕</button>
            </div>
            
            <form method="POST" action="{{ route('portal.admin.branches.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Branch Name</label>
                    <input type="text" name="name" required placeholder="e.g. Battambang Regional Branch" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Branch Code</label>
                    <input type="text" name="code" required placeholder="e.g. BB-01" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-emerald-500 font-mono uppercase">
                    <p class="text-[10px] text-slate-400 mt-0.5">Short unique identifier (e.g. HQ-PP, TK-01, SR-01).</p>
                </div>

                <div class="flex justify-end space-x-2 pt-3 border-t border-slate-100">
                    <button type="button" @click="createBranchModal = false" class="px-4 py-2 text-xs text-slate-600 font-semibold hover:bg-slate-100 rounded-xl transition">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-xs transition">Create Branch</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- MODAL 4: EDIT BRANCH                       -->
    <!-- ========================================== -->
    <div x-show="editBranchModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4" x-cloak>
        <div @click.away="editBranchModal = false" class="bg-white rounded-3xl max-w-md w-full shadow-2xl p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center space-x-2">
                    <span class="text-xl">✏️</span>
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Edit Branch</h3>
                        <p class="text-[11px] text-slate-500" x-text="targetBranch.name"></p>
                    </div>
                </div>
                <button type="button" @click="editBranchModal = false" class="text-slate-400 hover:text-slate-600 text-sm font-bold">✕</button>
            </div>
            
            <form :action="'{{ url('portal/admin/branches') }}/' + targetBranch.id" method="POST" class="space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Branch Name</label>
                    <input type="text" name="name" required :value="targetBranch.name" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Branch Code</label>
                    <input type="text" name="code" required :value="targetBranch.code" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-emerald-500 font-mono uppercase">
                </div>

                <div class="flex justify-end space-x-2 pt-3 border-t border-slate-100">
                    <button type="button" @click="editBranchModal = false" class="px-4 py-2 text-xs text-slate-600 font-semibold hover:bg-slate-100 rounded-xl transition">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-xs transition">Update Branch</button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
