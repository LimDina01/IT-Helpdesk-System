@extends('portal.layout')

@section('title', 'System Administration - Departments, Branches & Approver Management')

@section('content')
<div x-data="{ 
    createDeptModal: false, 
    editDeptModal: false, 
    assignDeptApproverModal: false,
    createBranchModal: false, 
    editBranchModal: false,
    assignBranchManagerModal: false,
    targetDept: {}, 
    targetBranch: {},
    targetParentId: '',
    showWorkflowInfo: false,
    orgTab: new URLSearchParams(window.location.search).get('tab') || 'departments',
    deptSearch: '',
    branchSearch: ''
}" class="space-y-6">

    <!-- Top Admin Header -->
    <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="text-xs text-indigo-600 font-extrabold uppercase tracking-wider flex items-center space-x-1.5">
                <span>⚡</span>
                <span>System Administration &bull; Approval Routing</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-black text-slate-900 mt-1">Organization: Departments, Branches & Approvers</h1>
            <p class="text-xs text-slate-500 mt-0.5">Hierarchical structure: Assign designated HODs for Slot 1 approvals, branch managers, and IT division/office routing (IT-OPR, IT-PRD, IT-SOL).</p>
        </div>

        <div class="flex flex-wrap items-center gap-2 self-start sm:self-auto">
            <button type="button" @click="showWorkflowInfo = !showWorkflowInfo" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs px-3.5 py-2.5 rounded-xl shadow-xs transition flex items-center space-x-1.5">
                <span>ℹ️</span>
                <span x-text="showWorkflowInfo ? 'Hide Workflow' : 'Approval Rules'">Approval Rules</span>
            </button>
            <button type="button" @click="targetParentId = '{{ $itParent?->id }}'; createDeptModal = true" class="bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold text-xs px-3.5 py-2.5 rounded-xl border border-indigo-200 transition flex items-center space-x-1.5">
                <span>➕</span>
                <span>Add IT Office</span>
            </button>
            <button type="button" @click="targetParentId = ''; createDeptModal = true" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs px-3.5 py-2.5 rounded-xl shadow-xs transition flex items-center space-x-1.5">
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
        <a href="{{ route('portal.admin.approvers') }}" class="text-slate-600 hover:text-slate-900 px-4 py-2 rounded-xl transition flex items-center space-x-1.5">
            <span>🗂️</span>
            <span>Approvers & Levels</span>
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

    <!-- Workflow Architecture Guide (Collapsible) -->
    <div x-show="showWorkflowInfo" x-transition class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 rounded-3xl p-6 text-white shadow-xl space-y-4">
        <div class="flex items-center justify-between border-b border-white/10 pb-3">
            <div class="flex items-center space-x-2">
                <span class="text-xl">🔀</span>
                <div>
                    <h3 class="text-sm font-bold text-white uppercase tracking-wider">3-Slot Approval Architecture Overview</h3>
                    <p class="text-xs text-slate-300">How service requests and approval-required tickets are routed through the company hierarchy</p>
                </div>
            </div>
            <button type="button" @click="showWorkflowInfo = false" class="text-slate-400 hover:text-white text-xs font-bold">✕ Close</button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Slot 1 -->
            <div class="bg-white/10 rounded-2xl p-4 border border-white/10 space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-full bg-indigo-500/30 text-indigo-200 border border-indigo-400/30">Slot 1</span>
                    <span class="text-xs">👔</span>
                </div>
                <h4 class="text-xs font-black text-white">Requester Department HOD</h4>
                <p class="text-[11px] text-slate-300 leading-relaxed">
                    Evaluates business justification. Routes to the <strong>Designated HOD</strong> configured on the department. If requester is in IT, this step is automatically bypassed.
                </p>
            </div>

            <!-- Slot 2 -->
            <div class="bg-white/10 rounded-2xl p-4 border border-white/10 space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-full bg-amber-500/30 text-amber-200 border border-amber-400/30">Slot 2</span>
                    <span class="text-xs">🏢</span>
                </div>
                <h4 class="text-xs font-black text-white">Branch Manager (Intermediate)</h4>
                <p class="text-[11px] text-slate-300 leading-relaxed">
                    Branch-level endorsement. Routes to the <strong>Branch Manager</strong> configured on the branch. Auto-skipped by default unless branch endorsement is required.
                </p>
            </div>

            <!-- Slot 3 -->
            <div class="bg-white/10 rounded-2xl p-4 border border-white/10 space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-full bg-emerald-500/30 text-emerald-200 border border-emerald-400/30">Slot 3</span>
                    <span class="text-xs">🛡️</span>
                </div>
                <h4 class="text-xs font-black text-white">IT Final Approver (Dynamic Division)</h4>
                <p class="text-[11px] text-slate-300 leading-relaxed">
                    Technical signoff routed directly to the designated head of the category's assigned IT unit:
                    <br>&bull; <strong>IT-OPR</strong>: Operations Head (Hardware, Mail, Network)
                    <br>&bull; <strong>IT-PRD</strong>: Production Head (ERP, POS, Database, Server)
                    <br>&bull; <strong>IT-SOL</strong>: Solution Manager (ERP, Software Development)
                </p>
            </div>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- SEGMENTED TABS: FULL-WIDTH ORGANIZATIONAL VIEWS          -->
    <!-- ======================================================== -->
    <div class="bg-white p-2.5 rounded-3xl border border-slate-200/80 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="flex items-center space-x-2 overflow-x-auto pb-1 sm:pb-0">
            <button type="button" 
                    @click="orgTab = 'departments'"
                    :class="orgTab === 'departments' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'"
                    class="px-4 py-2.5 rounded-2xl text-xs font-bold transition flex items-center space-x-2 shrink-0 cursor-pointer">
                <span>🏛️</span>
                <span>Company Departments</span>
                <span class="text-[10px] px-2 py-0.5 rounded-full font-black" :class="orgTab === 'departments' ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700'">
                    {{ $departments->total() }}
                </span>
            </button>

            <button type="button" 
                    @click="orgTab = 'branches'"
                    :class="orgTab === 'branches' ? 'bg-emerald-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'"
                    class="px-4 py-2.5 rounded-2xl text-xs font-bold transition flex items-center space-x-2 shrink-0 cursor-pointer">
                <span>📍</span>
                <span>Branches & Offices</span>
                <span class="text-[10px] px-2 py-0.5 rounded-full font-black" :class="orgTab === 'branches' ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700'">
                    {{ $branches->total() }}
                </span>
            </button>

            <button type="button" 
                    @click="orgTab = 'it_units'"
                    :class="orgTab === 'it_units' ? 'bg-indigo-950 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'"
                    class="px-4 py-2.5 rounded-2xl text-xs font-bold transition flex items-center space-x-2 shrink-0 cursor-pointer">
                <span>🛡️</span>
                <span>IT Divisions &amp; Specialized Offices</span>
                <span class="text-[10px] px-2 py-0.5 rounded-full font-black" :class="orgTab === 'it_units' ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700'">
                    {{ $itUnits->count() }}
                </span>
            </button>
        </div>

        <div class="flex items-center space-x-2 self-end sm:self-auto shrink-0">
            <template x-if="orgTab === 'departments'">
                <button type="button" @click="targetParentId = ''; createDeptModal = true" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs px-3.5 py-2 rounded-xl shadow-xs transition flex items-center space-x-1.5 cursor-pointer">
                    <span>➕</span>
                    <span>Add Department</span>
                </button>
            </template>
            <template x-if="orgTab === 'branches'">
                <button type="button" @click="createBranchModal = true" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs px-3.5 py-2 rounded-xl shadow-xs transition flex items-center space-x-1.5 cursor-pointer">
                    <span>➕</span>
                    <span>Add Branch</span>
                </button>
            </template>
            <template x-if="orgTab === 'it_units'">
                <button type="button" @click="targetParentId = '{{ $itParent?->id }}'; createDeptModal = true" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs px-3.5 py-2 rounded-xl shadow-xs transition flex items-center space-x-1.5 cursor-pointer">
                    <span>➕</span>
                    <span>Add IT Division</span>
                </button>
            </template>
        </div>
    </div>

    <!-- TAB 1: COMPANY DEPARTMENTS (100% FULL-WIDTH) -->
    <div x-show="orgTab === 'departments'" class="space-y-4">
        <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
            <div class="p-5 border-b border-slate-100 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <div class="flex items-center space-x-2">
                        <span class="text-lg">🏛️</span>
                        <h2 class="text-base font-bold text-slate-900">Company Departments</h2>
                        <span class="text-[10px] font-extrabold bg-indigo-50 text-indigo-700 px-2.5 py-0.5 rounded-full border border-indigo-200/60">{{ $departments->total() }} Total</span>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-0.5">Top-level operational departments and specialized sub-units across the company.</p>
                </div>
                
                <div class="flex items-center space-x-3 w-full md:w-auto">
                    <div class="relative w-full md:w-72">
                        <input type="text" 
                               x-model="deptSearch" 
                               placeholder="🔍 Search department name or code..." 
                               class="w-full text-xs pl-3.5 pr-8 py-2 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-slate-50 focus:bg-white transition">
                        <button type="button" x-show="deptSearch" @click="deptSearch = ''" class="absolute right-2.5 top-2 text-xs text-slate-400 hover:text-slate-600">✕</button>
                    </div>
                    <button type="button" @click="targetParentId = ''; createDeptModal = true" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs px-3.5 py-2 rounded-xl shadow-xs transition flex items-center space-x-1.5 shrink-0 cursor-pointer">
                        <span>➕ Add</span>
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-500 font-bold uppercase tracking-wider text-[10px] border-b border-slate-200">
                        <tr>
                            <th class="py-3 px-5">Department &amp; Code</th>
                            <th class="py-3 px-4">Designated Head / Approver</th>
                            <th class="py-3 px-4 text-center">Staff Count</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($departments as $dept)
                            <tr class="hover:bg-slate-50/70 transition {{ $dept->parent_id ? 'bg-slate-50/30' : '' }}"
                                x-show="!deptSearch || ('{{ strtolower($dept->name . ' ' . $dept->code) }}').includes(deptSearch.toLowerCase())">
                                <td class="py-3.5 px-5 whitespace-nowrap">
                                    <div class="flex items-center space-x-2">
                                        @if($dept->parent_id)
                                            <span class="text-slate-400 font-bold ml-3 text-sm">↳</span>
                                            <span class="font-bold text-slate-800">{{ $dept->name }}</span>
                                        @else
                                            <span class="font-black text-slate-900 text-sm">{{ $dept->name }}</span>
                                        @endif
                                    </div>
                                    <div class="flex items-center space-x-1.5 mt-1 {{ $dept->parent_id ? 'ml-6' : '' }}">
                                        <span class="font-mono text-indigo-700 font-bold text-[10px] bg-indigo-50 border border-indigo-100 px-2 py-0.5 rounded">
                                            {{ $dept->code }}
                                        </span>
                                        @if($dept->parent)
                                            <span class="text-[10px] text-slate-500 bg-slate-100 px-2 py-0.5 rounded border border-slate-200/60 font-medium">
                                                Part of {{ $dept->parent->name }}
                                            </span>
                                        @endif
                                    </div>
                                </td>

                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    @if($dept->head)
                                        <div class="flex items-center space-x-2.5">
                                            <div class="w-7 h-7 rounded-full bg-indigo-600 text-white font-bold text-[10px] flex items-center justify-center shrink-0">
                                                {{ strtoupper(substr($dept->head->name, 0, 2)) }}
                                            </div>
                                            <div>
                                                <div class="font-bold text-slate-800 text-xs">{{ $dept->head->name }}</div>
                                                <div class="text-[10px] text-slate-400">{{ $dept->head->email }}</div>
                                            </div>
                                        </div>
                                    @else
                                        <button type="button" 
                                                @click="targetDept = {{ json_encode($dept) }}; assignDeptApproverModal = true"
                                                class="inline-flex items-center space-x-1 text-[11px] font-semibold text-slate-500 bg-slate-100 hover:bg-slate-200 border border-slate-200 px-2.5 py-1 rounded-full transition cursor-pointer">
                                            <span>➕ Assign Head</span>
                                        </button>
                                    @endif
                                </td>

                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <span class="text-slate-700 font-semibold bg-slate-100 border border-slate-200/80 px-3 py-1 rounded-full text-[11px]">
                                        {{ $dept->employees_count }} staff
                                    </span>
                                </td>

                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <span class="text-[10px] font-bold px-2.5 py-0.5 rounded-full border {{ $dept->is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-rose-50 text-rose-700 border-rose-200' }}">
                                        {{ $dept->is_active ? 'Active' : 'Suspended' }}
                                    </span>
                                </td>

                                <td class="py-3.5 px-5 text-right whitespace-nowrap space-x-1.5">
                                    <button type="button" 
                                            @click="targetDept = {{ json_encode($dept) }}; assignDeptApproverModal = true"
                                            class="text-xs text-indigo-600 hover:text-indigo-800 font-bold bg-indigo-50 hover:bg-indigo-100 px-2.5 py-1 rounded-lg transition cursor-pointer">
                                        Head
                                    </button>
                                    <button type="button" 
                                            @click="targetDept = {{ json_encode($dept) }}; editDeptModal = true"
                                            class="text-xs text-slate-600 hover:text-slate-800 font-bold bg-slate-100 hover:bg-slate-200 px-2.5 py-1 rounded-lg transition cursor-pointer">
                                        Edit
                                    </button>
                                    <form method="POST" action="{{ route('portal.admin.departments.toggle', $dept->id) }}" class="inline-block">
                                        @csrf
                                        <button type="submit" class="text-xs {{ $dept->is_active ? 'text-amber-600 hover:text-amber-800' : 'text-emerald-600 hover:text-emerald-800' }} font-bold px-2 py-1 transition cursor-pointer">
                                            {{ $dept->is_active ? 'Suspend' : 'Activate' }}
                                        </button>
                                    </form>
                                    @if($dept->employees_count === 0 && $dept->children()->count() === 0)
                                        <form method="POST" action="{{ route('portal.admin.departments.destroy', $dept->id) }}" class="inline-block" onsubmit="return confirm('Delete department {{ $dept->name }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs text-rose-600 hover:text-rose-800 font-bold bg-rose-50 hover:bg-rose-100 px-2.5 py-1 rounded-lg transition cursor-pointer">
                                                Delete
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center text-slate-400 text-xs">No departments found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination & Results Summary Footer -->
            <div class="px-5 py-3.5 border-t border-slate-100 bg-slate-50/60 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="text-xs text-slate-500 font-medium flex items-center space-x-1.5">
                    <span>Showing</span>
                    <span class="font-bold text-slate-700">{{ $departments->firstItem() ?? 0 }}</span>
                    <span>to</span>
                    <span class="font-bold text-slate-700">{{ $departments->lastItem() ?? 0 }}</span>
                    <span>of</span>
                    <span class="font-bold text-slate-700">{{ $departments->total() }}</span>
                    <span>departments</span>
                </div>

                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-1.5 text-xs text-slate-500">
                        <span class="text-[11px] text-slate-400 font-semibold">Per page:</span>
                        @foreach([10, 25, 50] as $size)
                            <a href="{{ request()->fullUrlWithQuery(['dept_per_page' => $size, 'depts_page' => 1, 'tab' => 'departments']) }}"
                               class="px-2 py-0.5 rounded-md text-[11px] font-bold transition {{ ($deptPerPage ?? 10) == $size ? 'bg-indigo-600 text-white' : 'bg-slate-200/70 hover:bg-slate-200 text-slate-600' }}">
                                {{ $size }}
                            </a>
                        @endforeach
                    </div>

                    @if($departments->hasPages())
                        <div class="shrink-0">
                            {{ $departments->appends(['tab' => 'departments', 'dept_per_page' => $deptPerPage ?? 10])->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 2: BRANCHES & STORE LOCATIONS (100% FULL-WIDTH) -->
    <div x-show="orgTab === 'branches'" class="space-y-4">
        <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
            <div class="p-5 border-b border-slate-100 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <div class="flex items-center space-x-2">
                        <span class="text-lg">📍</span>
                        <h2 class="text-base font-bold text-slate-900">Branches & Offices</h2>
                        <span class="text-[10px] font-extrabold bg-emerald-50 text-emerald-700 px-2.5 py-0.5 rounded-full border border-emerald-200/60">{{ $branches->total() }} Locations</span>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-0.5">Physical luxury retail boutiques, corporate head offices, and jewelry workshops.</p>
                </div>
                
                <div class="flex items-center space-x-3 w-full md:w-auto">
                    <div class="relative w-full md:w-72">
                        <input type="text" 
                               x-model="branchSearch" 
                               placeholder="🔍 Search branch name or code..." 
                               class="w-full text-xs pl-3.5 pr-8 py-2 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 bg-slate-50 focus:bg-white transition">
                        <button type="button" x-show="branchSearch" @click="branchSearch = ''" class="absolute right-2.5 top-2 text-xs text-slate-400 hover:text-slate-600">✕</button>
                    </div>
                    <button type="button" @click="createBranchModal = true" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs px-3.5 py-2 rounded-xl shadow-xs transition flex items-center space-x-1.5 shrink-0 cursor-pointer">
                        <span>➕ Add Branch</span>
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-500 font-bold uppercase tracking-wider text-[10px] border-b border-slate-200">
                        <tr>
                            <th class="py-3 px-5">Branch / Boutique &amp; Code</th>
                            <th class="py-3 px-4">Branch Manager</th>
                            <th class="py-3 px-4 text-center">Staff &amp; Active Tickets</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($branches as $branch)
                            <tr class="hover:bg-slate-50/70 transition"
                                x-show="!branchSearch || ('{{ strtolower($branch->name . ' ' . $branch->code) }}').includes(branchSearch.toLowerCase())">
                                <td class="py-4 px-5 whitespace-nowrap">
                                    <div class="font-bold text-slate-900 text-sm">{{ $branch->name }}</div>
                                    <span class="inline-block mt-1 font-mono text-emerald-700 font-bold text-[10px] bg-emerald-50 border border-emerald-100 px-2 py-0.5 rounded">
                                        {{ $branch->code }}
                                    </span>
                                </td>

                                <td class="py-4 px-4 whitespace-nowrap">
                                    @if($branch->manager)
                                        <div class="flex items-center space-x-2.5">
                                            <div class="w-8 h-8 rounded-full bg-emerald-700 text-white font-bold text-xs flex items-center justify-center shrink-0">
                                                {{ strtoupper(substr($branch->manager->name, 0, 2)) }}
                                            </div>
                                            <div>
                                                <div class="font-bold text-slate-800 text-xs">{{ $branch->manager->name }}</div>
                                                <div class="text-[10px] text-slate-400">{{ $branch->manager->email }}</div>
                                            </div>
                                        </div>
                                    @else
                                        <button type="button" 
                                                @click="targetBranch = {{ json_encode($branch) }}; assignBranchManagerModal = true"
                                                class="inline-flex items-center space-x-1 text-[11px] font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 border border-slate-200 px-2.5 py-1 rounded-full transition cursor-pointer">
                                            <span>➕ Assign Manager</span>
                                        </button>
                                    @endif
                                </td>

                                <td class="py-4 px-4 text-center whitespace-nowrap">
                                    <span class="text-slate-700 font-semibold bg-slate-100 border border-slate-200 px-3 py-1 rounded-full text-[11px]">
                                        {{ $branch->employees_count }} staff &bull; {{ $branch->tickets_count }} tickets
                                    </span>
                                </td>

                                <td class="py-4 px-4 whitespace-nowrap">
                                    <span class="text-[10px] font-bold px-2.5 py-0.5 rounded-full border {{ $branch->is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-rose-50 text-rose-700 border-rose-200' }}">
                                        {{ $branch->is_active ? 'Active' : 'Suspended' }}
                                    </span>
                                </td>

                                <td class="py-4 px-5 text-right whitespace-nowrap space-x-1.5">
                                    <button type="button" 
                                            @click="targetBranch = {{ json_encode($branch) }}; assignBranchManagerModal = true"
                                            class="text-xs text-emerald-600 hover:text-emerald-800 font-bold bg-emerald-50 hover:bg-emerald-100 px-2.5 py-1 rounded-lg transition cursor-pointer">
                                        Manager
                                    </button>
                                    <button type="button" 
                                            @click="targetBranch = {{ json_encode($branch) }}; editBranchModal = true"
                                            class="text-xs text-slate-600 hover:text-slate-900 font-bold bg-slate-100 hover:bg-slate-200 px-2.5 py-1 rounded-lg transition cursor-pointer">
                                        Edit
                                    </button>
                                    <form method="POST" action="{{ route('portal.admin.branches.toggle', $branch->id) }}" class="inline-block">
                                        @csrf
                                        <button type="submit" class="text-xs {{ $branch->is_active ? 'text-amber-600 hover:text-amber-800' : 'text-emerald-600 hover:text-emerald-800' }} font-bold px-2 py-1 transition cursor-pointer">
                                            {{ $branch->is_active ? 'Suspend' : 'Activate' }}
                                        </button>
                                    </form>
                                    @if($branch->employees_count === 0 && $branch->tickets_count === 0)
                                        <form method="POST" action="{{ route('portal.admin.branches.destroy', $branch->id) }}" class="inline-block" onsubmit="return confirm('Delete branch {{ $branch->name }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs text-rose-600 hover:text-rose-800 font-bold bg-rose-50 hover:bg-rose-100 px-2.5 py-1 rounded-lg transition cursor-pointer">
                                                Delete
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center text-slate-400 text-xs">No branches found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination & Results Summary Footer -->
            <div class="px-5 py-3.5 border-t border-slate-100 bg-slate-50/60 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="text-xs text-slate-500 font-medium flex items-center space-x-1.5">
                    <span>Showing</span>
                    <span class="font-bold text-slate-700">{{ $branches->firstItem() ?? 0 }}</span>
                    <span>to</span>
                    <span class="font-bold text-slate-700">{{ $branches->lastItem() ?? 0 }}</span>
                    <span>of</span>
                    <span class="font-bold text-slate-700">{{ $branches->total() }}</span>
                    <span>branch locations</span>
                </div>

                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-1.5 text-xs text-slate-500">
                        <span class="text-[11px] text-slate-400 font-semibold">Per page:</span>
                        @foreach([10, 25, 50] as $size)
                            <a href="{{ request()->fullUrlWithQuery(['branch_per_page' => $size, 'branches_page' => 1, 'tab' => 'branches']) }}"
                               class="px-2 py-0.5 rounded-md text-[11px] font-bold transition {{ ($branchPerPage ?? 10) == $size ? 'bg-emerald-600 text-white' : 'bg-slate-200/70 hover:bg-slate-200 text-slate-600' }}">
                                {{ $size }}
                            </a>
                        @endforeach
                    </div>

                    @if($branches->hasPages())
                        <div class="shrink-0">
                            {{ $branches->appends(['tab' => 'branches', 'branch_per_page' => $branchPerPage ?? 10])->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 3: IT DIVISIONS & SPECIALIZED OFFICES (100% FULL-WIDTH) -->
    <div x-show="orgTab === 'it_units'" class="space-y-4">
        <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-xs space-y-5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-4">
                <div>
                    <div class="flex items-center space-x-2">
                        <span class="text-xl">🛡️</span>
                        <h2 class="text-base font-bold text-slate-900">Information Technology — Divisions &amp; Specialized Offices</h2>
                        <span class="text-[10px] font-extrabold bg-indigo-50 text-indigo-700 px-2.5 py-0.5 rounded-full border border-indigo-200/60">{{ $itUnits->count() }} Units</span>
                    </div>
                    <p class="text-xs text-slate-500 mt-1">Specialized sub-offices under Information Technology. Tickets requiring IT HOD approval (Slot 3) route dynamically to the designated head of that specific office.</p>
                </div>
                <div class="flex items-center space-x-2">
                    <button type="button" 
                            @click="targetParentId = '{{ $itParent?->id }}'; createDeptModal = true"
                            class="text-xs font-bold text-indigo-600 hover:text-indigo-800 bg-indigo-50 hover:bg-indigo-100 px-3.5 py-2 rounded-xl transition flex items-center space-x-1.5 cursor-pointer">
                        <span>➕</span>
                        <span>Add IT Division / Office</span>
                    </button>
                </div>
            </div>

            <!-- Dynamic Grid of All IT Units (IT-OPR, IT-PRD, IT-SOL, etc.) -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                @forelse($itUnits as $unit)
                    <div class="p-5 rounded-3xl border {{ $unit->head ? 'border-indigo-200 bg-indigo-50/20' : 'border-amber-200 bg-amber-50/20' }} flex flex-col justify-between space-y-4 shadow-2xs">
                        <div>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-2">
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-black font-mono bg-indigo-100 text-indigo-800 border border-indigo-200">{{ $unit->code }}</span>
                                    <span class="text-xs font-bold text-slate-900 truncate" title="{{ $unit->name }}">{{ $unit->name }}</span>
                                </div>
                                <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-full {{ $unit->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                    {{ $unit->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>

                            <div class="mt-3.5 p-3.5 bg-white rounded-2xl border border-slate-200/80 shadow-2xs flex items-center justify-between">
                                <div class="flex items-center space-x-3 truncate">
                                    <div class="w-9 h-9 rounded-full bg-indigo-600 text-white font-bold text-xs flex items-center justify-center shrink-0">
                                        {{ $unit->head ? strtoupper(substr($unit->head->name, 0, 2)) : '??' }}
                                    </div>
                                    <div class="truncate">
                                        <div class="text-xs font-bold text-slate-900 truncate">
                                            {{ $unit->head ? $unit->head->name : 'No Designated Approver' }}
                                        </div>
                                        <div class="text-[11px] text-slate-400 truncate">
                                            {{ $unit->head ? $unit->head->email : 'Fallback to parent IT manager' }}
                                        </div>
                                    </div>
                                </div>
                                @if($unit->head && $unit->head->telegram_id)
                                    <span class="text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 px-2 py-0.5 rounded-full flex items-center space-x-1 shrink-0">
                                        <span>📱</span>
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="pt-3 border-t border-slate-200/60 flex items-center justify-between gap-2">
                            <span class="text-[10px] text-slate-400 font-semibold">{{ $unit->categories_count }} Categories mapped</span>
                            
                            <div class="flex items-center space-x-1.5">
                                <button type="button" 
                                        @click="targetDept = {{ json_encode($unit) }}; assignDeptApproverModal = true"
                                        class="text-xs font-bold text-indigo-600 hover:text-indigo-800 bg-indigo-50 hover:bg-indigo-100 px-2.5 py-1.5 rounded-xl transition flex items-center space-x-1 cursor-pointer">
                                    <span>👤 Head</span>
                                </button>
                                <button type="button" 
                                        @click="targetDept = {{ json_encode($unit) }}; editDeptModal = true"
                                        class="text-xs font-bold text-slate-600 hover:text-slate-800 bg-slate-100 hover:bg-slate-200 px-2.5 py-1.5 rounded-xl transition flex items-center space-x-1 cursor-pointer">
                                    <span>✏️ Edit</span>
                                </button>
                                @if(($unit->employees_count ?? 0) === 0 && ($unit->categories_count ?? 0) === 0)
                                    <form method="POST" action="{{ route('portal.admin.departments.destroy', $unit->id) }}" class="inline-block" onsubmit="return confirm('Permanently delete IT Division \'{{ $unit->name }}\'?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-bold text-rose-600 hover:text-rose-800 bg-rose-50 hover:bg-rose-100 px-2.5 py-1.5 rounded-xl transition flex items-center space-x-1 cursor-pointer">
                                            <span>🗑️ Delete</span>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full py-6 text-center text-slate-400 text-xs">No IT divisions configured.</div>
                @endforelse
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
                        <h3 class="text-base font-bold text-slate-900">Add New Department / Unit</h3>
                        <p class="text-[11px] text-slate-500">Create a top-level department or specialized office under IT.</p>
                    </div>
                </div>
                <button type="button" @click="createDeptModal = false" class="text-slate-400 hover:text-slate-600 text-sm font-bold">✕</button>
            </div>
            
            <form method="POST" action="{{ route('portal.admin.departments.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Parent Department / Hierarchy</label>
                    <select name="parent_id" x-model="targetParentId" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                        <option value="">-- None (Top-Level Department) --</option>
                        @foreach($parentDepartments as $pDept)
                            <option value="{{ $pDept->id }}">
                                Sub-unit of: {{ $pDept->name }} ({{ $pDept->code }})
                            </option>
                        @endforeach
                    </select>
                    <p class="text-[10px] text-slate-400 mt-0.5">Select a parent if this is a specialized division (e.g. under Information Technology).</p>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Department / Office Name</label>
                    <input type="text" name="name" required placeholder="e.g. IT Solutions &amp; Development" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Department Code</label>
                    <input type="text" name="code" required placeholder="e.g. IT-SOL" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono uppercase">
                    <p class="text-[10px] text-slate-400 mt-0.5">Short unique identifier (e.g. IT-OPR, IT-PRD, IT-SOL, RISK).</p>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Designated Approver (HOD / Manager)</label>
                    <select name="head_user_id" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                        <option value="">-- Auto / Role Fallback (No Designated User) --</option>
                        @foreach($approverCandidates as $candidate)
                            <option value="{{ $candidate->id }}">
                                {{ $candidate->name }} ({{ $candidate->email }}) [{{ $candidate->roles->pluck('name')->join(', ') }}]
                            </option>
                        @endforeach
                    </select>
                    <p class="text-[10px] text-slate-400 mt-0.5">Primary approver for service requests routed to this department/unit.</p>
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
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Parent Department / Hierarchy</label>
                    <select name="parent_id" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                        <option value="">-- None (Top-Level Department) --</option>
                        @foreach($parentDepartments as $pDept)
                            <option value="{{ $pDept->id }}" :selected="targetDept.parent_id == {{ $pDept->id }}">
                                Sub-unit of: {{ $pDept->name }} ({{ $pDept->code }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Department Name</label>
                    <input type="text" name="name" required :value="targetDept.name" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Department Code</label>
                    <input type="text" name="code" required :value="targetDept.code" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono uppercase">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Designated Approver (HOD / Manager)</label>
                    <select name="head_user_id" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                        <option value="">-- Auto / Role Fallback (No Designated User) --</option>
                        @foreach($approverCandidates as $candidate)
                            <option value="{{ $candidate->id }}" :selected="targetDept.head_user_id == {{ $candidate->id }}">
                                {{ $candidate->name }} ({{ $candidate->email }}) [{{ $candidate->roles->pluck('name')->join(', ') }}]
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex justify-end space-x-2 pt-3 border-t border-slate-100">
                    <button type="button" @click="editDeptModal = false" class="px-4 py-2 text-xs text-slate-600 font-semibold hover:bg-slate-100 rounded-xl transition">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-xs transition">Update Department</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- MODAL 3: QUICK ASSIGN DEPARTMENT APPROVER  -->
    <!-- ========================================== -->
    <div x-show="assignDeptApproverModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4" x-cloak>
        <div @click.away="assignDeptApproverModal = false" class="bg-white rounded-3xl max-w-md w-full shadow-2xl p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center space-x-2">
                    <span class="text-xl">👤</span>
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Assign Designated Approver (HOD / Manager)</h3>
                        <p class="text-[11px] text-slate-500" x-text="targetDept.name + ' (' + targetDept.code + ')'"></p>
                    </div>
                </div>
                <button type="button" @click="assignDeptApproverModal = false" class="text-slate-400 hover:text-slate-600 text-sm font-bold">✕</button>
            </div>
            
            <form :action="'{{ url('portal/admin/departments') }}/' + targetDept.id + '/approver'" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Designated Head of Department</label>
                    <select name="head_user_id" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                        <option value="">-- Remove Designated Approver (Use Role Fallback) --</option>
                        @foreach($approverCandidates as $candidate)
                            <option value="{{ $candidate->id }}" :selected="targetDept.head_user_id == {{ $candidate->id }}">
                                {{ $candidate->name }} ({{ $candidate->email }}) [{{ $candidate->roles->pluck('name')->join(', ') }}]
                            </option>
                        @endforeach
                    </select>
                    <p class="text-[10px] text-slate-400 mt-1">
                        When set, tickets routed to this department/unit will route directly to this user for approval.
                    </p>
                </div>

                <div class="flex justify-end space-x-2 pt-3 border-t border-slate-100">
                    <button type="button" @click="assignDeptApproverModal = false" class="px-4 py-2 text-xs text-slate-600 font-semibold hover:bg-slate-100 rounded-xl transition">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-xs transition">Save Approver</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- MODAL 4: ADD BRANCH                        -->
    <!-- ========================================== -->
    <div x-show="createBranchModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4" x-cloak>
        <div @click.away="createBranchModal = false" class="bg-white rounded-3xl max-w-md w-full shadow-2xl p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center space-x-2">
                    <span class="text-xl">📍</span>
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Add New Branch</h3>
                        <p class="text-[11px] text-slate-500">Company branches and retail stores for physical equipment and ticket support.</p>
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
                    <input type="text" name="code" required placeholder="e.g. BTB-01" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-emerald-500 font-mono uppercase">
                    <p class="text-[10px] text-slate-400 mt-0.5">Short unique identifier (e.g. HQ-PP, BT-PP, BTB-01, VH-SR).</p>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Branch Manager / Approver</label>
                    <select name="manager_user_id" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-emerald-500 bg-white">
                        <option value="">-- Unassigned (No Manager) --</option>
                        @foreach($approverCandidates as $candidate)
                            <option value="{{ $candidate->id }}">
                                {{ $candidate->name }} ({{ $candidate->email }}) [{{ $candidate->roles->pluck('name')->join(', ') }}]
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex justify-end space-x-2 pt-3 border-t border-slate-100">
                    <button type="button" @click="createBranchModal = false" class="px-4 py-2 text-xs text-slate-600 font-semibold hover:bg-slate-100 rounded-xl transition">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-xs transition">Create Branch</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- MODAL 5: EDIT BRANCH                       -->
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

                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Branch Manager / Approver</label>
                    <select name="manager_user_id" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-emerald-500 bg-white">
                        <option value="">-- Unassigned (No Manager) --</option>
                        @foreach($approverCandidates as $candidate)
                            <option value="{{ $candidate->id }}" :selected="targetBranch.manager_user_id == {{ $candidate->id }}">
                                {{ $candidate->name }} ({{ $candidate->email }}) [{{ $candidate->roles->pluck('name')->join(', ') }}]
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex justify-end space-x-2 pt-3 border-t border-slate-100">
                    <button type="button" @click="editBranchModal = false" class="px-4 py-2 text-xs text-slate-600 font-semibold hover:bg-slate-100 rounded-xl transition">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-xs transition">Update Branch</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- MODAL 6: QUICK ASSIGN BRANCH MANAGER       -->
    <!-- ========================================== -->
    <div x-show="assignBranchManagerModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4" x-cloak>
        <div @click.away="assignBranchManagerModal = false" class="bg-white rounded-3xl max-w-md w-full shadow-2xl p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center space-x-2">
                    <span class="text-xl">👤</span>
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Assign Branch Manager</h3>
                        <p class="text-[11px] text-slate-500" x-text="targetBranch.name + ' (' + targetBranch.code + ')'"></p>
                    </div>
                </div>
                <button type="button" @click="assignBranchManagerModal = false" class="text-slate-400 hover:text-slate-600 text-sm font-bold">✕</button>
            </div>
            
            <form :action="'{{ url('portal/admin/branches') }}/' + targetBranch.id + '/manager'" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Designated Branch Manager</label>
                    <select name="manager_user_id" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-emerald-500 bg-white">
                        <option value="">-- Remove Manager (Unassigned) --</option>
                        @foreach($approverCandidates as $candidate)
                            <option value="{{ $candidate->id }}" :selected="targetBranch.manager_user_id == {{ $candidate->id }}">
                                {{ $candidate->name }} ({{ $candidate->email }}) [{{ $candidate->roles->pluck('name')->join(', ') }}]
                            </option>
                        @endforeach
                    </select>
                    <p class="text-[10px] text-slate-400 mt-1">
                        Assigned manager can approve Slot 2 intermediate branch endorsement steps.
                    </p>
                </div>

                <div class="flex justify-end space-x-2 pt-3 border-t border-slate-100">
                    <button type="button" @click="assignBranchManagerModal = false" class="px-4 py-2 text-xs text-slate-600 font-semibold hover:bg-slate-100 rounded-xl transition">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-xs transition">Save Manager</button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
