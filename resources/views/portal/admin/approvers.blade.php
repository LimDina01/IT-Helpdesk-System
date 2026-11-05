@extends('portal.layout')

@section('title', 'System Administration - Approval Hierarchy & Levels')

@section('content')
<div x-data="{ 
    addApproverModal: false,
    editApproverModal: false,
    editingApprover: {},
    newBranchId: '{{ $selectedBranchId === 'hq' ? '' : ($selectedBranchId ?? '') }}',
    newDeptId: '{{ $selectedDeptId ?? '' }}',
    newLevel: 1,
    newTitle: 'Team Leader',
    showWorkflowInfo: false,
    
    // Live client-side search query for cards
    cardSearch: '',
    
    // User search combobox state (Add modal)
    candidates: {{ $candidatesJson->toJson() }},
    searchUserText: '',
    userDropdownOpen: false,
    selectedUser: null,
    
    // User search combobox state (Edit modal)
    editSearchUserText: '',
    editUserDropdownOpen: false,
    editSelectedUser: null,

    getFilteredCandidates(query, preferBranchId = null, preferDeptId = null) {
        let q = (query || '').toLowerCase().trim();
        let list = this.candidates;
        if (q) {
            return list.filter(c => 
                (c.name && c.name.toLowerCase().includes(q)) ||
                (c.email && c.email.toLowerCase().includes(q)) ||
                (c.dept_name && c.dept_name.toLowerCase().includes(q)) ||
                (c.dept_code && c.dept_code.toLowerCase().includes(q)) ||
                (c.branch_name && c.branch_name.toLowerCase().includes(q)) ||
                (c.roles && c.roles.toLowerCase().includes(q))
            );
        }
        if (preferBranchId || preferDeptId) {
            return [...list].sort((a, b) => {
                let scoreA = (preferBranchId && a.branch_id == preferBranchId ? 3 : 0) + (preferDeptId && a.dept_id == preferDeptId ? 1 : 0);
                let scoreB = (preferBranchId && b.branch_id == preferBranchId ? 3 : 0) + (preferDeptId && b.dept_id == preferDeptId ? 1 : 0);
                return scoreB - scoreA;
            });
        }
        return list;
    },

    openAddModal(presetBranchId = '', presetDeptId = '') {
        this.newBranchId = presetBranchId;
        this.newDeptId = presetDeptId;
        this.newLevel = 1;
        this.newTitle = 'Team Leader';
        this.selectedUser = null;
        this.searchUserText = '';
        this.userDropdownOpen = false;
        this.addApproverModal = true;
    },

    openEditModal(appr) {
        this.editingApprover = Object.assign({}, appr);
        this.editSelectedUser = this.candidates.find(c => c.id === appr.user_id) || (appr.user ? {
            id: appr.user.id,
            name: appr.user.name,
            email: appr.user.email,
            initials: (appr.user.name || '??').substring(0, 2).toUpperCase(),
            dept_code: appr.department ? appr.department.code : '',
            branch_name: appr.branch ? appr.branch.name : ''
        } : null);
        this.editSearchUserText = '';
        this.editUserDropdownOpen = false;
        this.editApproverModal = true;
    }
}" class="space-y-6">

    <!-- Top Admin Header -->
    <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="text-xs text-indigo-600 font-extrabold uppercase tracking-wider flex items-center space-x-1.5">
                <span>⚡</span>
                <span>System Administration &bull; Approval Routing</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-black text-slate-900 mt-1">Approval Hierarchy & Levels</h1>
            <p class="text-xs text-slate-500 mt-0.5">Connect <span class="font-bold text-slate-700">Branch ➔ Department ➔ Approval Level Cards</span> (Level 1 Team Leaders, Level 2 Supervisors, etc.)</p>
        </div>

        <div class="flex flex-wrap items-center gap-2 self-start sm:self-auto">
            <button type="button" @click="showWorkflowInfo = !showWorkflowInfo" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs px-3.5 py-2.5 rounded-xl shadow-xs transition flex items-center space-x-1.5">
                <span>ℹ️</span>
                <span x-text="showWorkflowInfo ? 'Hide Guide' : 'Approval Flow Guide'">Approval Flow Guide</span>
            </button>
            <button type="button" @click="openAddModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs px-4 py-2.5 rounded-xl shadow-xs transition flex items-center space-x-1.5 cursor-pointer">
                <span>➕</span>
                <span>Add Approver Card</span>
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
        <a href="{{ route('portal.admin.organization') }}" class="text-slate-600 hover:text-slate-900 px-4 py-2 rounded-xl transition flex items-center space-x-1.5">
            <span>🏢</span>
            <span>Departments & Branches</span>
        </a>
        <a href="{{ route('portal.admin.approvers') }}" class="bg-white text-indigo-600 shadow-xs px-4 py-2 rounded-xl transition flex items-center space-x-1.5">
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

    <!-- 4-Stat Overview Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-2xs">
            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Total Approver Cards</div>
            <div class="text-2xl font-black text-slate-900 mt-1">{{ $totalCardsCount }}</div>
            <div class="text-[10px] text-slate-400 mt-0.5">Across all locations & depts</div>
        </div>
        <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-2xs">
            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Company-Wide / HQ</div>
            <div class="text-2xl font-black text-indigo-600 mt-1">{{ $hqCardsCount }}</div>
            <div class="text-[10px] text-slate-400 mt-0.5">Accounting, HR, Finance defaults</div>
        </div>
        <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-2xs">
            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Branch-Specific</div>
            <div class="text-2xl font-black text-emerald-600 mt-1">{{ $branchCardsCount }}</div>
            <div class="text-[10px] text-slate-400 mt-0.5">Retail stores & workshops</div>
        </div>
        <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-2xs">
            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">IT Division Heads</div>
            <div class="text-2xl font-black text-purple-600 mt-1">{{ $itUnits->count() }}</div>
            <div class="text-[10px] text-slate-400 mt-0.5">IT-OPR, IT-PRD, IT-SOL (Slot 3)</div>
        </div>
    </div>

    <!-- Workflow Architecture Guide (Collapsible) -->
    <div x-show="showWorkflowInfo" x-transition class="bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 rounded-3xl p-6 text-white shadow-xl space-y-4">
        <div class="flex items-center justify-between border-b border-white/10 pb-3">
            <div class="flex items-center space-x-3">
                <span class="text-2xl">🏛️</span>
                <div>
                    <h3 class="text-sm font-bold text-white uppercase tracking-wider">Jewelry Company Approval Architecture</h3>
                    <p class="text-xs text-slate-300">How service requests route through branches, departments, and IT divisions</p>
                </div>
            </div>
            <button type="button" @click="showWorkflowInfo = false" class="text-slate-400 hover:text-white text-xs font-bold">✕ Close</button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2">
            <div class="p-4 bg-white/5 rounded-2xl border border-white/10 space-y-2">
                <div class="flex items-center space-x-2">
                    <span class="w-6 h-6 rounded-full bg-blue-500/20 text-blue-300 text-xs font-bold flex items-center justify-center">1</span>
                    <h4 class="text-xs font-bold text-blue-200 uppercase">Step 1: Branch Dept Review</h4>
                </div>
                <p class="text-[11px] text-slate-300 leading-relaxed">
                    Routes directly to the employee's <span class="font-bold text-white">Branch Department Team Leader (Level 1)</span> or designated Line Manager. Level 1 can approve, pass to a peer, or escalate to Level 2 (Supervisor).
                </p>
            </div>

            <div class="p-4 bg-white/5 rounded-2xl border border-white/10 space-y-2">
                <div class="flex items-center space-x-2">
                    <span class="w-6 h-6 rounded-full bg-emerald-500/20 text-emerald-300 text-xs font-bold flex items-center justify-center">2</span>
                    <h4 class="text-xs font-bold text-emerald-200 uppercase">Step 2: Store / Branch Manager</h4>
                </div>
                <p class="text-[11px] text-slate-300 leading-relaxed">
                    For physical stores or showroom requests requiring local store manager endorsement before centralized IT dispatch.
                </p>
            </div>

            <div class="p-4 bg-white/5 rounded-2xl border border-white/10 space-y-2">
                <div class="flex items-center space-x-2">
                    <span class="w-6 h-6 rounded-full bg-purple-500/20 text-purple-300 text-xs font-bold flex items-center justify-center">3</span>
                    <h4 class="text-xs font-bold text-purple-200 uppercase">Step 3: Central IT Division Head</h4>
                </div>
                <p class="text-[11px] text-slate-300 leading-relaxed">
                    Routes dynamically based on service category: <span class="font-bold text-white">IT-OPR</span> (Hardware/Helpdesk), <span class="font-bold text-white">IT-PRD</span> (Jewelry POS, ERP, Database), or <span class="font-bold text-white">IT-SOL</span> (Software Dev).
                </p>
            </div>
        </div>
    </div>

    <!-- Filter Bar & Search Controls -->
    <div class="bg-white rounded-3xl p-5 border border-slate-200/80 shadow-xs space-y-4">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <!-- Location Filter Pills -->
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="text-[11px] font-bold text-slate-400 uppercase mr-1">Location:</span>
                <a href="{{ route('portal.admin.approvers', array_merge(request()->except('branch_id'), ['branch_id' => ''])) }}" 
                   class="text-xs px-3 py-1.5 rounded-xl transition font-semibold {{ empty($selectedBranchId) ? 'bg-indigo-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                    All Locations
                </a>
                <a href="{{ route('portal.admin.approvers', array_merge(request()->except('branch_id'), ['branch_id' => 'hq'])) }}" 
                   class="text-xs px-3 py-1.5 rounded-xl transition font-semibold {{ $selectedBranchId === 'hq' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                    🌐 Company-wide / HQ
                </a>
                @foreach($branches as $b)
                    <a href="{{ route('portal.admin.approvers', array_merge(request()->except('branch_id'), ['branch_id' => $b->id])) }}" 
                       class="text-xs px-3 py-1.5 rounded-xl transition font-semibold {{ $selectedBranchId == $b->id ? 'bg-emerald-600 text-white shadow-xs' : 'bg-emerald-50 text-emerald-700 border border-emerald-200/70 hover:bg-emerald-100' }}">
                        📍 {{ $b->name }}
                    </a>
                @endforeach
            </div>

            <!-- Server Search Filter -->
            <form method="GET" action="{{ route('portal.admin.approvers') }}" class="flex items-center space-x-2 w-full lg:w-auto">
                @if(!empty($selectedBranchId))<input type="hidden" name="branch_id" value="{{ $selectedBranchId }}">@endif
                @if(!empty($selectedDeptId))<input type="hidden" name="department_id" value="{{ $selectedDeptId }}">@endif
                @if(!empty($selectedLevel))<input type="hidden" name="level" value="{{ $selectedLevel }}">@endif
                @if(isset($perPage) && $perPage != 10)<input type="hidden" name="per_page" value="{{ $perPage }}">@endif
                
                <div class="relative w-full lg:w-72">
                    <input type="text"
                           name="search"
                           value="{{ $search ?? '' }}"
                           placeholder="🔍 Search cards by name, dept, title... (Enter)"
                           class="w-full text-xs pl-8 pr-7 py-2 rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 shadow-2xs">
                    <span class="absolute left-2.5 top-2.5 text-slate-400 text-xs">🔍</span>
                    @if(!empty($search))
                        <a href="{{ route('portal.admin.approvers', array_filter(['branch_id' => $selectedBranchId, 'department_id' => $selectedDeptId, 'level' => $selectedLevel, 'per_page' => (isset($perPage) && $perPage != 10) ? $perPage : null])) }}" 
                           class="absolute right-2.5 top-2 text-xs text-slate-400 hover:text-slate-600 font-bold" title="Clear search">✕</a>
                    @endif
                </div>
                <button type="submit" class="bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold text-xs px-3 py-2 rounded-xl border border-indigo-200 transition cursor-pointer shrink-0">
                    Search
                </button>
            </form>
        </div>

        <!-- Secondary Filters: Department & Level -->
        <div class="flex items-center gap-3 pt-2 border-t border-slate-100 flex-wrap">
            <span class="text-[11px] font-bold text-slate-400 uppercase">Department:</span>
            <div class="flex items-center gap-1.5 flex-wrap">
                <a href="{{ route('portal.admin.approvers', array_merge(request()->except('department_id'), ['department_id' => ''])) }}" 
                   class="text-[11px] px-2.5 py-1 rounded-lg font-medium {{ empty($selectedDeptId) ? 'bg-slate-800 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                    All Depts
                </a>
                @foreach($departments as $dept)
                    <a href="{{ route('portal.admin.approvers', array_merge(request()->except('department_id'), ['department_id' => $dept->id])) }}" 
                       class="text-[11px] px-2.5 py-1 rounded-lg font-medium {{ $selectedDeptId == $dept->id ? 'bg-slate-800 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        {{ $dept->name }} ({{ $dept->code }})
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Approver Cards Matrix Grid (Grouped by Location & Department) -->
    @php
        $groupedCards = $approverCards->groupBy(function($card) {
            $loc = $card->branch ? $card->branch->name : '🌐 Company-Wide / Head Office (HQ)';
            $dept = $card->department ? $card->department->name : 'General';
            return $loc . ' — ' . $dept;
        });
    @endphp

    <div class="space-y-6">
        @forelse($groupedCards as $groupTitle => $cards)
            @php
                $firstCard = $cards->first();
                $groupBranch = $firstCard->branch;
                $groupDept = $firstCard->department;
            @endphp
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
                <!-- Group Header -->
                <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <div class="flex items-center space-x-3">
                        <span class="text-xl">{{ $groupBranch ? '📍' : '🌐' }}</span>
                        <div>
                            <div class="flex items-center space-x-2">
                                <h3 class="text-sm font-bold text-slate-900">{{ $groupTitle }}</h3>
                                @if($groupDept)
                                    <span class="font-mono text-[10px] font-bold px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-700 border border-indigo-200">
                                        {{ $groupDept->code }}
                                    </span>
                                @endif
                            </div>
                            <p class="text-[11px] text-slate-400 mt-0.5">
                                {{ $groupBranch ? ('Physical store / branch: ' . $groupBranch->name) : 'Company-wide default fallback for all branches' }}
                            </p>
                        </div>
                    </div>

                    <button type="button" 
                            @click="openAddModal('{{ $groupBranch ? $groupBranch->id : '' }}', '{{ $groupDept ? $groupDept->id : '' }}')"
                            class="text-xs text-indigo-600 hover:text-indigo-800 font-bold bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 px-3 py-1.5 rounded-xl transition flex items-center space-x-1 cursor-pointer">
                        <span>➕ Add Card</span>
                    </button>
                </div>

                <!-- Cards Grid -->
                <div class="p-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($cards as $card)
                            <div class="border border-slate-200 rounded-2xl p-4 bg-slate-50/40 hover:bg-white transition shadow-2xs relative flex flex-col justify-between space-y-3">
                                <div>
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center space-x-1.5">
                                            <span class="text-[10px] font-black px-2 py-0.5 rounded-full uppercase
                                                {{ $card->level == 1 ? 'bg-blue-100 text-blue-800' : ($card->level == 2 ? 'bg-purple-100 text-purple-800' : ($card->level == 3 ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800')) }}">
                                                Level {{ $card->level }}
                                            </span>
                                            <span class="text-[9px] font-bold px-1.5 py-0.5 rounded-full {{ $card->branch ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-600 border border-slate-200' }}">
                                                {{ $card->branch ? $card->branch->code : 'HQ' }}
                                            </span>
                                        </div>

                                        <div class="flex items-center space-x-2">
                                            <button type="button" 
                                                    @click="openEditModal({{ json_encode($card) }})"
                                                    class="text-[11px] font-bold text-indigo-600 hover:text-indigo-800 hover:underline cursor-pointer">
                                                ✏️ Edit
                                            </button>
                                            <form method="POST" action="{{ route('portal.admin.approvers.destroy', $card->id) }}" onsubmit="return confirm('Remove this approver card?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-[11px] font-bold text-rose-500 hover:text-rose-700 hover:underline cursor-pointer">
                                                    ✕
                                                </button>
                                            </form>
                                        </div>
                                    </div>

                                    <div class="font-bold text-slate-900 text-sm mt-2.5">{{ $card->title ?? "Level {$card->level} Approver" }}</div>
                                </div>

                                <div class="pt-3 border-t border-slate-100 flex items-center space-x-3">
                                    <div class="w-8 h-8 rounded-full bg-slate-800 text-white font-bold text-xs flex items-center justify-center shrink-0">
                                        {{ $card->user ? strtoupper(substr($card->user->name, 0, 2)) : '??' }}
                                    </div>
                                    <div class="truncate">
                                        <div class="text-xs font-bold text-slate-800 truncate">{{ $card->user?->name ?? 'Unassigned' }}</div>
                                        <div class="text-[10px] text-slate-400 truncate">{{ $card->user?->email }}</div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-3xl p-12 text-center border border-slate-200/80 shadow-xs space-y-3">
                <span class="text-4xl">🗂️</span>
                @if(!empty($search))
                    <h3 class="text-base font-bold text-slate-800">No Approver Cards Match "{{ $search }}"</h3>
                    <p class="text-xs text-slate-400 max-w-md mx-auto">
                        No approver cards matched your search term across departments, branches, or names.
                    </p>
                    <a href="{{ route('portal.admin.approvers', array_filter(['branch_id' => $selectedBranchId, 'department_id' => $selectedDeptId, 'level' => $selectedLevel, 'per_page' => (isset($perPage) && $perPage != 10) ? $perPage : null])) }}" 
                       class="px-4 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold text-xs rounded-xl border border-indigo-200 transition inline-flex items-center space-x-1.5 cursor-pointer">
                        <span>✕ Clear Search Filter</span>
                    </a>
                @else
                    <h3 class="text-base font-bold text-slate-800">No Approver Cards Configured</h3>
                    <p class="text-xs text-slate-400 max-w-md mx-auto">
                        Start by clicking <span class="font-bold text-indigo-600">+ Add Approver Card</span> to connect a Branch and Department to Level 1 Team Leaders and Level 2 Supervisors.
                    </p>
                    <button type="button" @click="openAddModal()" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-xs transition inline-flex items-center space-x-1.5 cursor-pointer">
                        <span>➕ Add First Approver Card</span>
                    </button>
                @endif
            </div>
        @endforelse

        <!-- Pagination & Results Summary Footer -->
        <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-2xs flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="text-xs text-slate-500 font-medium flex items-center space-x-1.5">
                <span>Showing</span>
                <span class="font-bold text-slate-700">{{ $approverCards->firstItem() ?? 0 }}</span>
                <span>to</span>
                <span class="font-bold text-slate-700">{{ $approverCards->lastItem() ?? 0 }}</span>
                <span>of</span>
                <span class="font-bold text-slate-700">{{ $approverCards->total() }}</span>
                <span>approver cards</span>
            </div>

            <div class="flex items-center space-x-4">
                <div class="flex items-center space-x-1.5 text-xs text-slate-500">
                    <span class="text-[11px] text-slate-400 font-semibold">Per page:</span>
                    @foreach([10, 15, 25, 50] as $size)
                        <a href="{{ request()->fullUrlWithQuery(['per_page' => $size, 'page' => 1]) }}"
                           class="px-2 py-0.5 rounded-md text-[11px] font-bold transition {{ ($perPage ?? 10) == $size ? 'bg-indigo-600 text-white' : 'bg-slate-200/70 hover:bg-slate-200 text-slate-600' }}">
                            {{ $size }}
                        </a>
                    @endforeach
                </div>

                @if($approverCards->hasPages())
                    <div class="shrink-0">
                        {{ $approverCards->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- ======================================================= -->
    <!-- MODAL 1: ADD APPROVER CARD                              -->
    <!-- ======================================================= -->
    <div x-show="addApproverModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto" x-cloak>
        <div @click.away="addApproverModal = false" class="bg-white rounded-3xl max-w-lg w-full shadow-2xl p-6 space-y-4 my-8">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-2xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-xl">
                        ➕
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Add Approver Card</h3>
                        <p class="text-[11px] text-slate-500">Connect Branch ➔ Department ➔ Level ➔ Staff</p>
                    </div>
                </div>
                <button type="button" @click="addApproverModal = false" class="text-slate-400 hover:text-slate-600 text-sm font-bold cursor-pointer">✕</button>
            </div>

            <!-- Quick Presets -->
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="text-[10px] text-slate-400 font-bold uppercase">Presets:</span>
                <button type="button" @click="newLevel = 1; newTitle = 'Team Leader'" class="text-[10px] font-semibold bg-blue-50 hover:bg-blue-100 text-blue-700 px-2 py-0.5 rounded-md border border-blue-200 transition">L1: Team Leader</button>
                <button type="button" @click="newLevel = 2; newTitle = 'Supervisor'" class="text-[10px] font-semibold bg-purple-50 hover:bg-purple-100 text-purple-700 px-2 py-0.5 rounded-md border border-purple-200 transition">L2: Supervisor</button>
                <button type="button" @click="newLevel = 3; newTitle = 'Department Manager'" class="text-[10px] font-semibold bg-emerald-50 hover:bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-md border border-emerald-200 transition">L3: Manager</button>
                <button type="button" @click="newLevel = 4; newTitle = 'General Manager'" class="text-[10px] font-semibold bg-amber-50 hover:bg-amber-100 text-amber-700 px-2 py-0.5 rounded-md border border-amber-200 transition">L4: GM</button>
            </div>

            <form method="POST" action="{{ route('portal.admin.approvers.store') }}" class="space-y-4">
                @csrf
                
                <!-- 1. Branch Scope -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Branch / Store Location Scope</label>
                    <select name="branch_id" x-model="newBranchId" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white font-medium">
                        <option value="">🌐 All Branches / Head Office (HQ Default)</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}">📍 {{ $branch->name }} ({{ $branch->code }})</option>
                        @endforeach
                    </select>
                </div>

                <!-- 2. Target Department -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Target Department <span class="text-rose-500">*</span></label>
                    <select name="department_id" x-model="newDeptId" required class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white font-medium">
                        <option value="">-- Choose Department (e.g. Sales, Cashier, Workshop) --</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}">{{ $dept->name }} ({{ $dept->code }})</option>
                        @endforeach
                    </select>
                </div>

                <!-- 3. Level & Custom Title -->
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Level # <span class="text-rose-500">*</span></label>
                        <input type="number" name="level" x-model="newLevel" min="1" max="20" required class="w-full text-xs px-3 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 font-bold text-center bg-white">
                    </div>
                    <div class="sm:col-span-3">
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Custom Title / Designation</label>
                        <input type="text" name="title" x-model="newTitle" placeholder="e.g. Store Sales Leader, Cashier Supervisor..." class="w-full text-xs px-3 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>

                <!-- 4. Searchable User Combobox -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Designated Staff Member <span class="text-rose-500">*</span></label>
                    
                    <div x-show="selectedUser" class="flex items-center justify-between p-2.5 bg-indigo-50/70 border border-indigo-200 rounded-xl mb-1">
                        <div class="flex items-center space-x-2.5 truncate">
                            <div class="w-8 h-8 rounded-full bg-indigo-600 text-white font-bold text-xs flex items-center justify-center shrink-0" x-text="selectedUser ? selectedUser.initials : ''"></div>
                            <div class="truncate">
                                <div class="text-xs font-bold text-slate-900 truncate" x-text="selectedUser ? selectedUser.name : ''"></div>
                                <div class="text-[10px] text-slate-500 truncate" x-text="(selectedUser ? selectedUser.email : '') + (selectedUser && selectedUser.dept_code ? (' • ' + selectedUser.dept_code) : '') + (selectedUser && selectedUser.branch_name ? (' • ' + selectedUser.branch_name) : '')"></div>
                            </div>
                        </div>
                        <button type="button" @click="selectedUser = null; searchUserText = ''" class="text-xs text-rose-500 hover:text-rose-700 font-bold px-2 py-1 shrink-0 cursor-pointer">✕ Change</button>
                    </div>
                    <input type="hidden" name="user_id" :value="selectedUser ? selectedUser.id : ''" required>

                    <div x-show="!selectedUser" class="relative">
                        <div class="relative">
                            <input type="text" 
                                   x-model="searchUserText" 
                                   @focus="userDropdownOpen = true"
                                   @input="userDropdownOpen = true"
                                   placeholder="🔍 Type name or email to search staff..." 
                                   class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                            <button type="button" x-show="searchUserText" @click="searchUserText = ''" class="absolute right-3 top-2.5 text-slate-400 hover:text-slate-600 text-xs font-bold">✕</button>
                        </div>

                        <!-- Dropdown Results -->
                        <div x-show="userDropdownOpen" 
                             @click.away="userDropdownOpen = false"
                             class="absolute z-50 mt-1 w-full bg-white rounded-2xl shadow-2xl border border-slate-200 max-h-52 overflow-y-auto divide-y divide-slate-100 p-1">
                            <template x-for="candidate in getFilteredCandidates(searchUserText, newBranchId, newDeptId)" :key="candidate.id">
                                <div @click="selectedUser = candidate; userDropdownOpen = false; searchUserText = ''"
                                     class="p-2 hover:bg-indigo-50/80 rounded-xl cursor-pointer flex items-center justify-between transition">
                                    <div class="flex items-center space-x-2.5 truncate">
                                        <div class="w-7 h-7 rounded-full bg-slate-800 text-white font-bold text-[10px] flex items-center justify-center shrink-0" x-text="candidate.initials"></div>
                                        <div class="truncate">
                                            <div class="text-xs font-bold text-slate-900 truncate" x-text="candidate.name"></div>
                                            <div class="text-[10px] text-slate-400 truncate" x-text="candidate.email"></div>
                                        </div>
                                    </div>
                                    <div class="text-right shrink-0 ml-2">
                                        <span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 border border-slate-200" x-text="candidate.dept_code || 'STAFF'"></span>
                                        <span x-show="candidate.branch_name" class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200 ml-1" x-text="candidate.branch_name"></span>
                                    </div>
                                </div>
                            </template>
                            <div x-show="getFilteredCandidates(searchUserText, newBranchId, newDeptId).length === 0" class="py-4 text-center text-xs text-slate-400">
                                No staff member matches your search.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end space-x-2 pt-3 border-t border-slate-100">
                    <button type="button" @click="addApproverModal = false" class="px-4 py-2 text-xs text-slate-600 font-semibold hover:bg-slate-100 rounded-xl transition cursor-pointer">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-xs transition cursor-pointer">+ Save Approver Card</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ======================================================= -->
    <!-- MODAL 2: EDIT APPROVER CARD                             -->
    <!-- ======================================================= -->
    <div x-show="editApproverModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto" x-cloak>
        <div @click.away="editApproverModal = false" class="bg-white rounded-3xl max-w-lg w-full shadow-2xl p-6 space-y-4 my-8">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-2xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-xl">
                        ✏️
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Edit Approver Card</h3>
                        <p class="text-[11px] text-slate-500">Update Level, Title, Branch Scope, or Assigned Staff</p>
                    </div>
                </div>
                <button type="button" @click="editApproverModal = false" class="text-slate-400 hover:text-slate-600 text-sm font-bold cursor-pointer">✕</button>
            </div>

            <form :action="'{{ url('portal/admin/approvers') }}/' + editingApprover.id" method="POST" class="space-y-4">
                @csrf
                @method('PUT')
                
                <!-- 1. Branch Scope -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Branch / Location Scope</label>
                    <select name="branch_id" x-model="editingApprover.branch_id" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white font-medium">
                        <option value="">🌐 All Branches / Head Office (HQ Default)</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}" :selected="editingApprover.branch_id == {{ $branch->id }}">📍 {{ $branch->name }} ({{ $branch->code }})</option>
                        @endforeach
                    </select>
                </div>

                <!-- 2. Target Department -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Target Department <span class="text-rose-500">*</span></label>
                    <select name="department_id" x-model="editingApprover.department_id" required class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white font-medium">
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}" :selected="editingApprover.department_id == {{ $dept->id }}">{{ $dept->name }} ({{ $dept->code }})</option>
                        @endforeach
                    </select>
                </div>

                <!-- 3. Level & Custom Title -->
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Level # <span class="text-rose-500">*</span></label>
                        <input type="number" name="level" x-model="editingApprover.level" min="1" max="20" required class="w-full text-xs px-3 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 font-bold text-center bg-white">
                    </div>
                    <div class="sm:col-span-3">
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Custom Title / Designation</label>
                        <input type="text" name="title" x-model="editingApprover.title" required class="w-full text-xs px-3 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>

                <!-- 4. Searchable User Combobox for Edit -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Designated Staff Member <span class="text-rose-500">*</span></label>
                    
                    <div x-show="editSelectedUser" class="flex items-center justify-between p-2.5 bg-indigo-50/70 border border-indigo-200 rounded-xl mb-1">
                        <div class="flex items-center space-x-2.5 truncate">
                            <div class="w-8 h-8 rounded-full bg-indigo-600 text-white font-bold text-xs flex items-center justify-center shrink-0" x-text="editSelectedUser ? editSelectedUser.initials : ''"></div>
                            <div class="truncate">
                                <div class="text-xs font-bold text-slate-900 truncate" x-text="editSelectedUser ? editSelectedUser.name : ''"></div>
                                <div class="text-[10px] text-slate-500 truncate" x-text="editSelectedUser ? editSelectedUser.email : ''"></div>
                            </div>
                        </div>
                        <button type="button" @click="editSelectedUser = null; editSearchUserText = ''" class="text-xs text-rose-500 hover:text-rose-700 font-bold px-2 py-1 shrink-0 cursor-pointer">✕ Change</button>
                    </div>
                    <input type="hidden" name="user_id" :value="editSelectedUser ? editSelectedUser.id : ''" required>

                    <div x-show="!editSelectedUser" class="relative">
                        <input type="text" 
                               x-model="editSearchUserText" 
                               @focus="editUserDropdownOpen = true"
                               @input="editUserDropdownOpen = true"
                               placeholder="🔍 Search staff to reassign..." 
                               class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                        
                        <div x-show="editUserDropdownOpen" 
                             @click.away="editUserDropdownOpen = false"
                             class="absolute z-50 mt-1 w-full bg-white rounded-2xl shadow-2xl border border-slate-200 max-h-48 overflow-y-auto divide-y divide-slate-100 p-1">
                            <template x-for="candidate in getFilteredCandidates(editSearchUserText, editingApprover.branch_id, editingApprover.department_id)" :key="candidate.id">
                                <div @click="editSelectedUser = candidate; editUserDropdownOpen = false; editSearchUserText = ''"
                                     class="p-2 hover:bg-indigo-50/80 rounded-xl cursor-pointer flex items-center justify-between transition">
                                    <div class="flex items-center space-x-2 truncate">
                                        <div class="w-7 h-7 rounded-full bg-slate-800 text-white font-bold text-[10px] flex items-center justify-center shrink-0" x-text="candidate.initials"></div>
                                        <div class="truncate">
                                            <div class="text-xs font-bold text-slate-900 truncate" x-text="candidate.name"></div>
                                            <div class="text-[10px] text-slate-400 truncate" x-text="candidate.email"></div>
                                        </div>
                                    </div>
                                    <span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-slate-100 text-slate-600" x-text="candidate.dept_code || 'STAFF'"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end space-x-2 pt-3 border-t border-slate-100">
                    <button type="button" @click="editApproverModal = false" class="px-4 py-2 text-xs text-slate-600 font-semibold hover:bg-slate-100 rounded-xl transition cursor-pointer">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-xs transition cursor-pointer">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection

