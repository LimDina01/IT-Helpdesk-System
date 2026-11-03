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
    manageApproversModal: false,
    editApproverCardModal: false,
    editingApprover: {},
    newApprLevel: 1,
    newApprTitle: 'Team Leader',
    targetDept: {}, 
    targetBranch: {},
    targetParentId: '',
    showWorkflowInfo: false
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
            <span>Departments & Approvers</span>
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
    <!-- DYNAMIC IT DIVISIONS & SPECIALIZED OFFICES SPOTLIGHT     -->
    <!-- ======================================================== -->
    <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-3">
            <div>
                <div class="flex items-center space-x-2">
                    <span class="text-lg">🛡️</span>
                    <h2 class="text-sm font-bold text-slate-900">Information Technology — Divisions & Specialized Offices</h2>
                    <span class="text-[10px] font-extrabold bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded-full border border-indigo-200/60">{{ $itUnits->count() }} Units</span>
                </div>
                <p class="text-[11px] text-slate-400 mt-0.5">Specialized sub-offices under Information Technology. Tickets requiring IT HOD approval (Slot 3) route dynamically to the designated head of that specific office.</p>
            </div>
            <div class="flex items-center space-x-2">
                <button type="button" 
                        @click="targetParentId = '{{ $itParent?->id }}'; createDeptModal = true"
                        class="text-xs font-bold text-indigo-600 hover:text-indigo-800 bg-indigo-50 hover:bg-indigo-100 px-3 py-1.5 rounded-xl transition flex items-center space-x-1">
                    <span>➕</span>
                    <span>Add IT Division / Office</span>
                </button>
            </div>
        </div>

        <!-- Dynamic Grid of All IT Units (IT-OPR, IT-PRD, IT-SOL, etc.) -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @forelse($itUnits as $unit)
                <div class="p-4 rounded-2xl border {{ $unit->head ? 'border-indigo-200 bg-indigo-50/20' : 'border-amber-200 bg-amber-50/20' }} flex flex-col justify-between space-y-3">
                    <div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-black font-mono bg-indigo-100 text-indigo-800 border border-indigo-200">{{ $unit->code }}</span>
                                <span class="text-xs font-bold text-slate-900 truncate" title="{{ $unit->name }}">{{ $unit->name }}</span>
                            </div>
                            <span class="text-[10px] font-extrabold uppercase px-1.5 py-0.5 rounded-full {{ $unit->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                {{ $unit->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>

                        <div class="mt-3 p-3 bg-white rounded-xl border border-slate-200/80 shadow-2xs flex items-center justify-between">
                            <div class="flex items-center space-x-3 truncate">
                                <div class="w-8 h-8 rounded-full bg-indigo-600 text-white font-bold text-xs flex items-center justify-center shrink-0">
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

                    <div class="pt-2 border-t border-slate-100 flex items-center justify-between">
                        <span class="text-[10px] text-slate-400 font-semibold">{{ $unit->categories()->count() }} Categories mapped</span>
                        <button type="button" 
                                @click="targetDept = {{ json_encode($unit) }}; assignDeptApproverModal = true"
                                class="text-xs font-bold text-indigo-600 hover:text-indigo-800 bg-indigo-50 hover:bg-indigo-100 px-3 py-1 rounded-xl transition flex items-center space-x-1">
                            <span>👤</span>
                            <span>Change Head</span>
                        </button>
                    </div>
                </div>
            @empty
                <div class="col-span-full py-4 text-center text-slate-400 text-xs">No IT divisions configured.</div>
            @endforelse
        </div>
    </div>

    <!-- ========================================== -->
    <!-- TWO-COLUMN LAYOUT: DEPARTMENTS VS BRANCHES  -->
    <!-- ========================================== -->
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
                            <h2 class="text-sm font-bold text-slate-900">Company Departments & Designated Approvers</h2>
                            <span class="text-[10px] font-extrabold bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded-full border border-indigo-200/60">{{ $departments->count() }}</span>
                        </div>
                        <p class="text-[11px] text-slate-400 mt-0.5">Top-level departments and specialized sub-offices with their assigned HODs.</p>
                    </div>
                    <button type="button" @click="targetParentId = ''; createDeptModal = true" class="text-xs text-indigo-600 hover:text-indigo-800 font-bold flex items-center space-x-1">
                        <span>➕ Add</span>
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 text-slate-500 font-bold uppercase tracking-wider text-[10px] border-b border-slate-200">
                            <tr>
                                <th class="py-3 px-4">Department & Code</th>
                                <th class="py-3 px-3">Designated HOD / Approver</th>
                                <th class="py-3 px-3 text-center">Staff</th>
                                <th class="py-3 px-3">Status</th>
                                <th class="py-3 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($departments as $dept)
                                <tr class="hover:bg-slate-50/70 transition {{ $dept->parent_id ? 'bg-slate-50/40' : '' }}">
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        <div class="flex items-center space-x-1.5">
                                            @if($dept->parent_id)
                                                <span class="text-slate-400 font-bold ml-2">↳</span>
                                                <span class="font-bold text-slate-800">{{ $dept->name }}</span>
                                            @else
                                                <span class="font-black text-slate-900">{{ $dept->name }}</span>
                                            @endif
                                        </div>
                                        <div class="flex items-center space-x-1.5 mt-0.5">
                                            <span class="font-mono text-indigo-700 font-bold text-[10px] bg-indigo-50 border border-indigo-100 px-1.5 py-0.5 rounded">
                                                {{ $dept->code }}
                                            </span>
                                            @if($dept->parent)
                                                <span class="text-[10px] text-slate-400 bg-slate-100 px-1.5 py-0.5 rounded">
                                                    Part of {{ $dept->parent->name }}
                                                </span>
                                            @endif
                                        </div>
                                    </td>

                                    <td class="py-3 px-3">
                                        <div class="space-y-1.5">
                                            @if($dept->approvers && $dept->approvers->count() > 0)
                                                <div class="flex flex-wrap gap-1 max-w-xs">
                                                    @foreach($dept->approvers as $appr)
                                                        <div class="inline-flex items-center space-x-1 text-[10px] font-semibold {{ $appr->level == 1 ? 'bg-blue-50 text-blue-700 border-blue-200' : ($appr->level == 2 ? 'bg-purple-50 text-purple-700 border-purple-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200') }} border px-2 py-0.5 rounded-lg shadow-2xs">
                                                            <span class="font-extrabold uppercase">L{{ $appr->level }}:</span>
                                                            <span class="truncate max-w-[90px]">{{ $appr->user ? $appr->user->name : 'Unknown' }}</span>
                                                            <span class="text-slate-400 font-normal">({{ $appr->title }})</span>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @elseif($dept->head)
                                                <div class="flex items-center space-x-2">
                                                    <div class="w-7 h-7 rounded-full bg-slate-800 text-white font-bold text-[10px] flex items-center justify-center shrink-0">
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
                                                        class="inline-flex items-center space-x-1 text-[11px] font-semibold text-amber-700 bg-amber-50 hover:bg-amber-100 border border-amber-200 px-2 py-0.5 rounded-full transition">
                                                    <span>⚡ Auto (Role Fallback)</span>
                                                </button>
                                            @endif
                                        </div>
                                    </td>

                                    <td class="py-3 px-3 text-center whitespace-nowrap">
                                        <span class="text-slate-600 font-semibold bg-slate-100 px-2 py-0.5 rounded-full text-[11px]">
                                            {{ $dept->employees_count }}
                                        </span>
                                    </td>

                                    <td class="py-3 px-3 whitespace-nowrap">
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full border {{ $dept->is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-rose-50 text-rose-700 border-rose-200' }}">
                                            {{ $dept->is_active ? 'Active' : 'Suspended' }}
                                        </span>
                                    </td>

                                    <td class="py-3 px-4 text-right whitespace-nowrap space-x-1.5">
                                        <button type="button" 
                                                @click="targetDept = {{ json_encode($dept) }}; manageApproversModal = true"
                                                class="text-[11px] text-indigo-700 hover:text-indigo-900 font-bold bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 px-2.5 py-1 rounded-lg transition"
                                                title="Manage Multi-Level Approver Cards">
                                            🗂️ Cards ({{ $dept->approvers->count() }})
                                        </button>
                                        <button type="button" 
                                                @click="targetDept = {{ json_encode($dept) }}; assignDeptApproverModal = true"
                                                class="text-[11px] text-slate-600 hover:text-slate-800 font-bold bg-slate-100 hover:bg-slate-200 px-2.5 py-1 rounded-lg transition"
                                                title="Designate Department Head">
                                            👤 Head
                                        </button>
                                        <button type="button" 
                                                @click="targetDept = {{ json_encode($dept) }}; editDeptModal = true"
                                                class="text-[11px] text-slate-600 hover:text-slate-900 font-semibold">
                                            Edit
                                        </button>
                                        <form method="POST" action="{{ route('portal.admin.departments.toggle', $dept->id) }}" class="inline-block">
                                            @csrf
                                            <button type="submit" class="text-[11px] {{ $dept->is_active ? 'text-amber-600 hover:underline' : 'text-emerald-600 hover:underline' }} font-semibold">
                                                {{ $dept->is_active ? 'Suspend' : 'Activate' }}
                                            </button>
                                        </form>
                                        @if($dept->employees_count === 0 && $dept->children()->count() === 0)
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
                        <p class="text-[11px] text-slate-400 mt-0.5">Physical branches for hardware routing & optional Slot 2 endorsement.</p>
                    </div>
                    <button type="button" @click="createBranchModal = true" class="text-xs text-emerald-600 hover:text-emerald-800 font-bold flex items-center space-x-1">
                        <span>➕ Add</span>
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 text-slate-500 font-bold uppercase tracking-wider text-[10px] border-b border-slate-200">
                            <tr>
                                <th class="py-3 px-4">Branch & Code</th>
                                <th class="py-3 px-3">Branch Manager</th>
                                <th class="py-3 px-3 text-center">Staff & Tickets</th>
                                <th class="py-3 px-3">Status</th>
                                <th class="py-3 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($branches as $branch)
                                <tr class="hover:bg-slate-50/70 transition">
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        <div class="font-bold text-slate-900">{{ $branch->name }}</div>
                                        <span class="inline-block mt-0.5 font-mono text-emerald-700 font-bold text-[10px] bg-emerald-50 border border-emerald-100 px-1.5 py-0.5 rounded">
                                            {{ $branch->code }}
                                        </span>
                                    </td>

                                    <td class="py-3 px-3 whitespace-nowrap">
                                        @if($branch->manager)
                                            <div class="flex items-center space-x-2">
                                                <div class="w-7 h-7 rounded-full bg-emerald-800 text-white font-bold text-[10px] flex items-center justify-center shrink-0">
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
                                                    class="inline-flex items-center space-x-1 text-[11px] font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 border border-slate-200 px-2 py-0.5 rounded-full transition">
                                                <span>➕ Assign Manager</span>
                                            </button>
                                        @endif
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

                                    <td class="py-3 px-4 text-right whitespace-nowrap space-x-1.5">
                                        <button type="button" 
                                                @click="targetBranch = {{ json_encode($branch) }}; assignBranchManagerModal = true"
                                                class="text-[11px] text-emerald-600 hover:text-emerald-800 font-bold bg-emerald-50 hover:bg-emerald-100 px-2.5 py-1 rounded-lg transition"
                                                title="Assign or Change Manager">
                                            👤 Manager
                                        </button>
                                        <button type="button" 
                                                @click="targetBranch = {{ json_encode($branch) }}; editBranchModal = true"
                                                class="text-[11px] text-slate-600 hover:text-slate-900 font-semibold">
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

    <!-- ======================================================= -->
    <!-- 7. MANAGE MULTI-LEVEL DEPARTMENT APPROVER CARDS MODAL    -->
    <!-- ======================================================= -->
    <div x-show="manageApproversModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto" x-cloak>
        <div @click.away="manageApproversModal = false" class="bg-white rounded-3xl max-w-2xl w-full shadow-2xl p-6 space-y-6 my-8">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-2xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-xl">
                        🗂️
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Department Approver Cards & Levels</h3>
                        <p class="text-[11px] text-slate-500">
                            Department: <span class="font-bold text-indigo-700" x-text="targetDept.name + ' (' + targetDept.code + ')'"></span>
                        </p>
                    </div>
                </div>
                <button type="button" @click="manageApproversModal = false" class="text-slate-400 hover:text-slate-600 text-sm font-bold">✕</button>
            </div>

            <!-- Existing Approver Cards in Department -->
            <div>
                <div class="flex items-center justify-between mb-2">
                    <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider">Configured Approver Cards</h4>
                    <span class="text-[10px] text-slate-400">Requesters map to Level 1, who can approve or escalate upwards</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-56 overflow-y-auto p-1">
                    <template x-for="appr in (targetDept.approvers || [])" :key="appr.id">
                        <div class="border border-slate-200 rounded-2xl p-3.5 bg-slate-50/60 hover:bg-white transition shadow-2xs relative flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] font-black px-2 py-0.5 rounded-full uppercase"
                                          :class="appr.level == 1 ? 'bg-blue-100 text-blue-800' : (appr.level == 2 ? 'bg-purple-100 text-purple-800' : (appr.level == 3 ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'))"
                                          x-text="'Level ' + appr.level">
                                    </span>
                                    <div class="flex items-center space-x-2">
                                        <button type="button" 
                                                @click="editingApprover = Object.assign({}, appr); editApproverCardModal = true"
                                                class="text-[11px] font-bold text-indigo-600 hover:text-indigo-800 hover:underline">
                                            ✏️ Edit
                                        </button>
                                        <form :action="'{{ url('portal/admin/departments') }}/' + targetDept.id + '/approvers/' + appr.id" method="POST" onsubmit="return confirm('Remove this approver card?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-[11px] font-bold text-rose-500 hover:text-rose-700 hover:underline">
                                                ✕
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                <div class="font-bold text-slate-900 text-xs mt-2" x-text="appr.title || ('Level ' + appr.level + ' Approver')"></div>
                                <div class="text-[11px] text-indigo-600 font-semibold mt-0.5" x-text="appr.user ? appr.user.name : 'Unknown User'"></div>
                                <div class="text-[10px] text-slate-400 truncate" x-text="appr.user ? appr.user.email : ''"></div>
                            </div>
                        </div>
                    </template>
                    <div x-show="!targetDept.approvers || targetDept.approvers.length === 0" class="col-span-2 text-center py-6 border-2 border-dashed border-slate-200 rounded-2xl text-slate-400 text-xs">
                        No approver cards configured yet. Fallbacks to designated HOD or traditional role.
                    </div>
                </div>
            </div>

            <!-- Add New Approver Card Form (Supports Any Custom Level & Title) -->
            <div class="border-t border-slate-100 pt-4 space-y-3">
                <div class="flex items-center justify-between">
                    <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider">➕ Add Approver Card</h4>
                    <span class="text-[10px] text-slate-400">Choose a preset or enter any custom level number</span>
                </div>

                <!-- Quick Presets -->
                <div class="flex items-center gap-1.5 flex-wrap">
                    <span class="text-[10px] text-slate-400 font-bold uppercase">Presets:</span>
                    <button type="button" @click="newApprLevel = 1; newApprTitle = 'Team Leader'" class="text-[10px] font-semibold bg-blue-50 hover:bg-blue-100 text-blue-700 px-2 py-0.5 rounded-md border border-blue-200 transition">L1: Team Leader</button>
                    <button type="button" @click="newApprLevel = 2; newApprTitle = 'Supervisor'" class="text-[10px] font-semibold bg-purple-50 hover:bg-purple-100 text-purple-700 px-2 py-0.5 rounded-md border border-purple-200 transition">L2: Supervisor</button>
                    <button type="button" @click="newApprLevel = 3; newApprTitle = 'Department Manager'" class="text-[10px] font-semibold bg-emerald-50 hover:bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-md border border-emerald-200 transition">L3: Manager</button>
                    <button type="button" @click="newApprLevel = 4; newApprTitle = 'General Manager'" class="text-[10px] font-semibold bg-amber-50 hover:bg-amber-100 text-amber-700 px-2 py-0.5 rounded-md border border-amber-200 transition">L4: GM</button>
                    <button type="button" @click="newApprLevel = 5; newApprTitle = 'Director / Executive'" class="text-[10px] font-semibold bg-rose-50 hover:bg-rose-100 text-rose-700 px-2 py-0.5 rounded-md border border-rose-200 transition">L5: Director</button>
                </div>

                <form :action="'{{ url('portal/admin/departments') }}/' + targetDept.id + '/approvers'" method="POST" class="space-y-3">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Approval Level #</label>
                            <input type="number" name="level" x-model="newApprLevel" min="1" max="20" required class="w-full text-xs px-3 py-2 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 font-bold text-center bg-white">
                        </div>
                        <div class="sm:col-span-3">
                            <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Custom Title / Designation</label>
                            <input type="text" name="title" x-model="newApprTitle" placeholder="e.g. Assistant Team Leader, Inventory Supervisor, Store Manager..." class="w-full text-xs px-3 py-2 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Designated Staff / User</label>
                        <select name="user_id" required class="w-full text-xs px-3 py-2 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                            <option value="">-- Select Staff Member --</option>
                            @foreach($approverCandidates as $candidate)
                                <option value="{{ $candidate->id }}">
                                    {{ $candidate->name }} ({{ $candidate->email }}) [{{ $candidate->roles->pluck('name')->join(', ') }}]
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex justify-end space-x-2 pt-2">
                        <button type="button" @click="manageApproversModal = false" class="px-4 py-2 text-xs text-slate-600 font-semibold hover:bg-slate-100 rounded-xl transition">Done</button>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-xs transition">+ Add Card</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ======================================================= -->
    <!-- 8. EDIT DEPARTMENT APPROVER CARD MODAL                   -->
    <!-- ======================================================= -->
    <div x-show="editApproverCardModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4" x-cloak>
        <div @click.away="editApproverCardModal = false" class="bg-white rounded-3xl max-w-md w-full shadow-2xl p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center space-x-2.5">
                    <span class="text-xl">✏️</span>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">Edit Approver Card</h3>
                        <p class="text-[11px] text-slate-500" x-text="targetDept.name"></p>
                    </div>
                </div>
                <button type="button" @click="editApproverCardModal = false" class="text-slate-400 hover:text-slate-600 font-bold text-sm">✕</button>
            </div>

            <form :action="'{{ url('portal/admin/departments') }}/' + targetDept.id + '/approvers/' + editingApprover.id" method="POST" class="space-y-3">
                @csrf
                @method('PUT')
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Level #</label>
                        <input type="number" name="level" x-model="editingApprover.level" min="1" max="20" required class="w-full text-xs px-3 py-2 rounded-xl border border-slate-300 font-bold text-center focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Title / Role</label>
                        <input type="text" name="title" x-model="editingApprover.title" required class="w-full text-xs px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Designated Staff Member</label>
                    <select name="user_id" x-model="editingApprover.user_id" required class="w-full text-xs px-3 py-2 rounded-xl border border-slate-300 bg-white focus:ring-2 focus:ring-indigo-500">
                        @foreach($approverCandidates as $candidate)
                            <option value="{{ $candidate->id }}" :selected="editingApprover.user_id == {{ $candidate->id }}">
                                {{ $candidate->name }} ({{ $candidate->email }}) [{{ $candidate->roles->pluck('name')->join(', ') }}]
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex justify-end space-x-2 pt-2 border-t border-slate-100">
                    <button type="button" @click="editApproverCardModal = false" class="px-3.5 py-1.5 text-xs text-slate-600 font-semibold hover:bg-slate-100 rounded-xl transition">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-xs transition">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
        </div>
    </div>

</div>
@endsection
