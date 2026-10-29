@extends('portal.layout')

@section('title', 'System Administration - Categories & SLAs')

@section('content')
<div x-data="{
    search: '',
    createGroupModal: false,
    createSubModal: false,
    editModal: false,
    deleteModal: false,
    isEditingParent: false,
    selectedParentId: '',
    selectedParentName: '',
    editCat: {},
    catToDelete: null,

    // Preset Icons Reference
    presetIcons: [
        { id: 'wifi', label: 'Wi-Fi / Network', emoji: '📶' },
        { id: 'laptop', label: 'Computer / Hardware', emoji: '💻' },
        { id: 'apps', label: 'Software / Apps', emoji: '🪟' },
        { id: 'database', label: 'Database / ERP', emoji: '🗄️' },
        { id: 'pos', label: 'POS / Cashier', emoji: '💳' },
        { id: 'facility', label: 'Building / Office', emoji: '🏢' },
        { id: 'cctv', label: 'CCTV / Camera', emoji: '📹' },
        { id: 'phone', label: 'Phone / Intercom', emoji: '📞' },
        { id: 'lock', label: 'Access / Account', emoji: '🔐' },
        { id: 'email', label: 'Email / 365', emoji: '✉️' },
        { id: 'cart', label: 'Procurement / Buying', emoji: '🛒' },
        { id: 'other', label: 'Other / Inquiries', emoji: '🏷️' },
        { id: 'help', label: 'Help / Support', emoji: '❓' }
    ],

    // Color Swatches Reference
    colorPalette: [
        { id: 'emerald', name: 'Emerald', bgClass: 'bg-emerald-500' },
        { id: 'blue', name: 'Blue', bgClass: 'bg-blue-600' },
        { id: 'sky', name: 'Sky', bgClass: 'bg-sky-600' },
        { id: 'indigo', name: 'Indigo', bgClass: 'bg-indigo-600' },
        { id: 'purple', name: 'Purple', bgClass: 'bg-purple-600' },
        { id: 'amber', name: 'Amber', bgClass: 'bg-amber-500' },
        { id: 'rose', name: 'Rose', bgClass: 'bg-rose-500' },
        { id: 'slate', name: 'Slate', bgClass: 'bg-slate-600' }
    ],

    newGroup: {
        name: '',
        name_km: '',
        description: '',
        description_km: '',
        icon: 'other',
        icon_color: 'blue',
        icon_tab: 'upload',
        iconPreview: null,
        previewLang: 'en'
    },

    openCreateGroup() {
        this.newGroup = {
            name: '',
            name_km: '',
            description: '',
            description_km: '',
            icon: 'other',
            icon_color: 'blue',
            icon_tab: 'upload',
            iconPreview: null,
            previewLang: 'en'
        };
        this.createGroupModal = true;
    },
    
    openAddSub(parentId = '', parentName = '') {
        this.selectedParentId = parentId ? String(parentId) : '';
        this.selectedParentName = parentName || '';
        this.createSubModal = true;
    },
    openEdit(cat, isParent = false) {
        this.editCat = JSON.parse(JSON.stringify(cat));
        this.isEditingParent = isParent;
        if (this.editCat.parent_id) {
            this.editCat.parent_id = String(this.editCat.parent_id);
        } else {
            this.editCat.parent_id = '';
        }
        this.editCat.icon = this.editCat.icon || 'other';
        this.editCat.icon_color = this.editCat.icon_color || 'blue';
        this.editCat.icon_tab = this.editCat.icon_url ? 'upload' : 'preset';
        this.editCat.iconPreview = this.editCat.icon_url || null;
        this.editCat.remove_icon_file = 0;
        this.editCat.previewLang = 'en';
        this.editModal = true;
    },
    getColorClass(colorId) {
        const found = this.colorPalette.find(c => c.id === colorId);
        return found ? found.bgClass : 'bg-blue-600';
    },
    getEmojiForIcon(iconId) {
        const found = this.presetIcons.find(i => i.id === iconId);
        return found ? found.emoji : '📁';
    },
    confirmDelete(cat, isParent = false) {
        this.catToDelete = cat;
        this.isEditingParent = isParent;
        this.deleteModal = true;
    },
    matchesSearch(parentName, parentNameKm, children = []) {
        if (!this.search.trim()) return true;
        const q = this.search.toLowerCase().trim();
        if ((parentName || '').toLowerCase().includes(q)) return true;
        if ((parentNameKm || '').toLowerCase().includes(q)) return true;
        return children.some(c => (c.name || '').toLowerCase().includes(q) || (c.name_km || '').toLowerCase().includes(q));
    }
}" class="space-y-6">

    <!-- Top Admin Header -->
    <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-xs flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div>
            <div class="text-xs text-indigo-600 font-extrabold uppercase tracking-wider">System Administration</div>
            <h1 class="text-xl sm:text-2xl font-black text-slate-900 mt-1">Categories, Workflows & SLA Targets</h1>
            <p class="text-xs text-slate-500 mt-0.5">Configure incident routing, service request multi-step approvals, and response time commitments.</p>
        </div>

        <div class="flex items-center flex-wrap gap-2.5 self-start lg:self-auto">
            <!-- Create Parent Group Button -->
            <button type="button" @click="openCreateGroup()" class="bg-slate-800 hover:bg-slate-900 text-white font-bold text-xs px-3.5 py-2.5 rounded-xl shadow-xs transition flex items-center space-x-1.5">
                <span>📁</span>
                <span>New Parent Group</span>
            </button>

            <!-- Create Subcategory Button -->
            <button type="button" @click="openAddSub('', '')" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs px-4 py-2.5 rounded-xl shadow-xs transition flex items-center space-x-1.5">
                <span>➕</span>
                <span>New Subcategory</span>
            </button>
        </div>
    </div>

    <!-- Admin Navigation Tabs & Live Search Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="flex items-center space-x-2 bg-slate-100 p-1 rounded-2xl text-xs font-semibold overflow-x-auto w-fit">
            <a href="{{ route('portal.admin.index') }}" class="bg-white text-indigo-600 shadow-xs px-4 py-2 rounded-xl transition flex items-center space-x-1.5">
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
        </div>

        <!-- Live Instant Search Filter -->
        <div class="relative w-full sm:w-72">
            <input type="text"
                   x-model="search"
                   placeholder="Filter categories or subcategories..."
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
        <div class="bg-rose-50 border border-rose-200 text-rose-800 text-xs font-semibold px-4 py-3 rounded-2xl space-y-1">
            @foreach($errors->all() as $err)
                <div class="flex items-center space-x-2">
                    <span>⚠️</span>
                    <span>{{ $err }}</span>
                </div>
            @endforeach
        </div>
    @endif

    <!-- Categories List (Grouped by Parent) -->
    <div class="space-y-6">
        @php
            $totalSubcategories = 0;
            foreach($categories as $p) {
                $totalSubcategories += $p->children->count();
            }
        @endphp

        @forelse($categories as $parent)
            <div x-show="matchesSearch('{{ addslashes($parent->name) }}', '{{ addslashes($parent->name_km ?? '') }}', {{ json_encode($parent->children->map(fn($c) => ['name' => $c->name, 'name_km' => $c->name_km])) }})"
                 class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden transition">
                
                <!-- Parent Header -->
                <div class="bg-slate-50/90 px-6 py-4 border-b border-slate-200/80 flex flex-col md:flex-row md:items-center justify-between gap-3">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-xl {{ $parent->icon_color ? 'bg-' . $parent->icon_color . '-500 text-white' : ($parent->type === 'service_request' ? 'bg-indigo-100 text-indigo-700' : 'bg-rose-100 text-rose-700') }} flex items-center justify-center font-bold text-sm shadow-2xs overflow-hidden shrink-0">
                            @if($parent->icon_url)
                                <img src="{{ $parent->icon_url }}" alt="" class="w-6 h-6 object-contain" />
                            @else
                                <span class="text-base">
                                    @switch($parent->icon)
                                        @case('wifi') 📶 @break
                                        @case('laptop') 💻 @break
                                        @case('apps') 🪟 @break
                                        @case('database') 🗄️ @break
                                        @case('pos') 💳 @break
                                        @case('facility') 🏢 @break
                                        @case('cctv') 📹 @break
                                        @case('phone') 📞 @break
                                        @case('lock') 🔐 @break
                                        @case('email') ✉️ @break
                                        @case('cart') 🛒 @break
                                        @case('other') 🏷️ @break
                                        @case('help') ❓ @break
                                        @default {{ $parent->type === 'service_request' ? '📦' : '⚡' }}
                                    @endswitch
                                </span>
                            @endif
                        </div>
                        <div>
                            <div class="flex items-center space-x-2">
                                <span class="font-extrabold text-sm text-slate-900">{{ $parent->name }}</span>
                                @if($parent->name_km)
                                    <span class="text-xs font-semibold text-slate-600 bg-slate-200/80 px-2 py-0.5 rounded-lg">{{ $parent->name_km }}</span>
                                @endif
                                @if(!$parent->is_active)
                                    <span class="text-[9px] font-bold uppercase px-1.5 py-0.2 rounded bg-slate-200 text-slate-600">Disabled</span>
                                @endif
                            </div>
                            @if($parent->description || $parent->description_km)
                                <div class="text-[11px] text-slate-600 font-medium mt-0.5">
                                    <span>{{ $parent->description }}</span>
                                    @if($parent->description_km)
                                        <span class="text-slate-400 font-normal">({{ $parent->description_km }})</span>
                                    @endif
                                </div>
                            @endif
                            <div class="text-[11px] text-slate-400 mt-0.5 flex items-center space-x-1.5">
                                @if($parent->type === 'service_request')
                                    <span class="text-indigo-600 font-semibold">🔵 Service Request Group</span>
                                @else
                                    <span class="text-rose-600 font-semibold">🔴 Incident / Fault Group</span>
                                @endif
                                <span>&bull;</span>
                                <span>{{ $parent->children->count() }} subcategories</span>
                                <span>&bull;</span>
                                <span>{{ $parent->children->sum('tickets_count') + $parent->tickets_count }} total tickets</span>
                            </div>
                        </div>
                    </div>

                    <!-- Parent Group Actions -->
                    <div class="flex items-center flex-wrap gap-2">
                        <!-- Direct Add Subcategory Button -->
                        <button type="button"
                                @click="openAddSub({{ $parent->id }}, '{{ addslashes($parent->name) }}')"
                                class="bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 font-bold text-[11px] px-2.5 py-1.5 rounded-xl transition flex items-center space-x-1">
                            <span>➕</span>
                            <span>Add Subcategory</span>
                        </button>

                        <!-- Edit Parent Group Button -->
                        <button type="button"
                                @click="openEdit({{ json_encode($parent) }}, true)"
                                class="bg-white hover:bg-slate-100 text-slate-700 border border-slate-200 font-semibold text-[11px] px-2.5 py-1.5 rounded-xl transition flex items-center space-x-1 shadow-2xs">
                            <span>✏️</span>
                            <span>Edit Group</span>
                        </button>

                        <!-- Toggle Active/Disabled -->
                        <form method="POST" action="{{ route('portal.admin.categories.toggle', $parent->id) }}" class="inline-block">
                            @csrf
                            <button type="submit" class="text-[10px] font-bold px-2 py-1.5 rounded-xl border transition {{ $parent->is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100' : 'bg-slate-100 text-slate-500 border-slate-200 hover:bg-slate-200' }}">
                                {{ $parent->is_active ? 'Active' : 'Disabled' }}
                            </button>
                        </form>

                        <!-- Delete Parent Group (if empty) -->
                        @if($parent->children->count() === 0 && $parent->tickets_count === 0)
                            <button type="button"
                                    @click="confirmDelete({{ json_encode($parent) }}, true)"
                                    class="text-rose-600 hover:text-rose-800 hover:bg-rose-50 text-[11px] font-semibold px-2 py-1.5 rounded-xl transition border border-rose-200"
                                    title="Delete empty parent group">
                                🗑️
                            </button>
                        @endif
                    </div>
                </div>

                <!-- Subcategories Table -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50/40 text-slate-400 font-bold uppercase tracking-wider text-[10px] border-b border-slate-100">
                            <tr>
                                <th class="py-2.5 px-6">Subcategory Name</th>
                                <th class="py-2.5 px-4">Workflow Type</th>
                                <th class="py-2.5 px-4">Approval Required</th>
                                <th class="py-2.5 px-4">Default Priority</th>
                                <th class="py-2.5 px-4">Ack SLA</th>
                                <th class="py-2.5 px-4">Resolve SLA</th>
                                <th class="py-2.5 px-4">Total Tickets</th>
                                <th class="py-2.5 px-6 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($parent->children as $child)
                                <tr class="hover:bg-slate-50/70 transition"
                                    x-show="!search.trim() || '{{ strtolower(addslashes($child->name)) }}'.includes(search.toLowerCase().trim()) || '{{ strtolower(addslashes($child->name_km ?? '')) }}'.includes(search.toLowerCase().trim()) || '{{ strtolower(addslashes($parent->name)) }}'.includes(search.toLowerCase().trim()) || '{{ strtolower(addslashes($parent->name_km ?? '')) }}'.includes(search.toLowerCase().trim())">
                                    <td class="py-3 px-6 font-bold text-slate-800 whitespace-nowrap">
                                        <div class="flex items-center space-x-1.5">
                                            <span>{{ $child->name }}</span>
                                            @if($child->name_km)
                                                <span class="text-[11px] font-medium text-slate-500">({{ $child->name_km }})</span>
                                            @endif
                                            @if(!$child->is_active)
                                                <span class="text-[9px] font-bold uppercase px-1.5 py-0.2 rounded bg-slate-200 text-slate-600">Disabled</span>
                                            @endif
                                        </div>
                                    </td>

                                    <td class="py-3 px-4 whitespace-nowrap">
                                        @if($child->type === 'service_request')
                                            <span class="bg-indigo-50 text-indigo-700 border border-indigo-200 font-bold text-[9px] uppercase px-2 py-0.5 rounded">
                                                Service Request
                                            </span>
                                        @else
                                            <span class="bg-rose-50 text-rose-700 border border-rose-200 font-bold text-[9px] uppercase px-2 py-0.5 rounded">
                                                Incident / Fault
                                            </span>
                                        @endif
                                    </td>

                                    <td class="py-3 px-4 whitespace-nowrap">
                                        @if($child->requires_approval)
                                            <span class="bg-purple-100 text-purple-800 font-bold text-[10px] px-2 py-0.5 rounded-full border border-purple-200">
                                                ⏳ HOD & IT Chain
                                            </span>
                                        @else
                                            <span class="text-slate-400 text-[11px] font-medium">None (Direct Triage)</span>
                                        @endif
                                    </td>

                                    <td class="py-3 px-4 whitespace-nowrap">
                                        <span class="font-bold text-[10px] uppercase px-1.5 py-0.2 rounded {{ $child->default_priority === 'critical' ? 'bg-red-100 text-red-700' : ($child->default_priority === 'high' ? 'bg-orange-100 text-orange-700' : 'bg-slate-100 text-slate-700') }}">
                                            {{ $child->default_priority }}
                                        </span>
                                    </td>

                                    <td class="py-3 px-4 whitespace-nowrap font-mono text-slate-600">
                                        {{ $child->target_acknowledge_minutes }} mins
                                    </td>

                                    <td class="py-3 px-4 whitespace-nowrap font-mono text-slate-600">
                                        {{ $child->target_resolve_hours }} hrs
                                    </td>

                                    <td class="py-3 px-4 whitespace-nowrap font-mono font-bold text-blue-600">
                                        {{ $child->tickets_count }}
                                    </td>

                                    <td class="py-3 px-6 text-right whitespace-nowrap space-x-1">
                                        <!-- Edit Subcategory -->
                                        <button type="button"
                                                @click="openEdit({{ json_encode($child) }}, false)"
                                                class="text-[11px] text-blue-600 hover:text-blue-800 hover:underline font-semibold px-2 py-1">
                                            Edit
                                        </button>
                                        
                                        <!-- Active / Disabled Toggle -->
                                        <form method="POST" action="{{ route('portal.admin.categories.toggle', $child->id) }}" class="inline-block">
                                            @csrf
                                            <button type="submit" class="text-[10px] font-bold px-2 py-0.5 rounded border transition {{ $child->is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100' : 'bg-slate-100 text-slate-500 border-slate-200 hover:bg-slate-200' }}">
                                                {{ $child->is_active ? 'Active' : 'Disabled' }}
                                            </button>
                                        </form>

                                        <!-- Delete Subcategory (Safe: only if 0 tickets) -->
                                        @if($child->tickets_count === 0)
                                            <button type="button"
                                                    @click="confirmDelete({{ json_encode($child) }}, false)"
                                                    class="text-rose-500 hover:text-rose-700 text-xs px-1.5 py-0.5 font-bold transition ml-1"
                                                    title="Delete empty subcategory">
                                                🗑️
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="py-6 px-6 text-center text-slate-400 text-xs italic">
                                        No subcategories in this group yet. 
                                        <button type="button" @click="openAddSub({{ $parent->id }}, '{{ addslashes($parent->name) }}')" class="text-indigo-600 font-bold hover:underline ml-1">
                                            + Add first subcategory
                                        </button>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-3xl p-12 text-center text-slate-400 border border-slate-200">
                <div class="text-3xl mb-2">📁</div>
                <div class="font-bold text-slate-700 text-sm">No Category Groups Created Yet</div>
                <p class="text-xs text-slate-400 mt-1">Get started by creating your first parent category group.</p>
                <button type="button" @click="openCreateGroup()" class="mt-4 bg-indigo-600 text-white font-bold text-xs px-4 py-2 rounded-xl">
                    + Create Parent Group
                </button>
            </div>
        @endforelse
    </div>

    <!-- ========================================== -->
    <!-- 1. CREATE PARENT GROUP MODAL               -->
    <!-- ========================================== -->
    <div x-show="createGroupModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4" x-cloak>
        <div @click.away="createGroupModal = false" class="bg-white rounded-3xl max-w-xl w-full shadow-2xl p-6 space-y-4 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center space-x-2">
                    <span class="text-xl">📁</span>
                    <h3 class="text-base font-bold text-slate-900">Create Parent Category Group</h3>
                </div>
                <button type="button" @click="createGroupModal = false" class="text-slate-400 hover:text-slate-600 text-sm font-bold">✕</button>
            </div>
            
            <form method="POST" action="{{ route('portal.admin.categories.store') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <input type="hidden" name="parent_id" value="">
                <input type="hidden" name="icon" :value="newGroup.icon">
                <input type="hidden" name="icon_color" :value="newGroup.icon_color">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Group Name (EN) <span class="text-rose-500">*</span></label>
                        <input type="text" name="name" x-model="newGroup.name" required placeholder="e.g. Hardware & Devices" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 font-medium">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Khmer Name (ឈ្មោះជាភាសាខ្មែរ)</label>
                        <input type="text" name="name_km" x-model="newGroup.name_km" placeholder="ឧ. កុំព្យូទ័រ & ឧបករណ៍បច្ចេកវិទ្យា" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 font-sans">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">
                            Detail Subtitle (EN)
                            <span class="text-slate-400 font-normal lowercase">(shows in Mini App)</span>
                        </label>
                        <input type="text" name="description" x-model="newGroup.description" placeholder="e.g. Laptop, desktop, printer, accessories" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">
                            Detail Subtitle (KM ភាសាខ្មែរ)
                            <span class="text-slate-400 font-normal lowercase">(ការបកប្រែ)</span>
                        </label>
                        <input type="text" name="description_km" x-model="newGroup.description_km" placeholder="ឧ. កុំព្យូទ័រយួរដៃ លើតុ ម៉ាស៊ីនព្រីន..." class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 font-sans">
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Group Workflow Type</label>
                    <select name="type" required class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="incident_issue">Incident / Fault (Something Broken)</option>
                        <option value="service_request">Service Request (Enhancement / Access / Buying)</option>
                    </select>
                    <p class="text-[10px] text-slate-400 mt-1">Sets the default classification for subcategories inside this group.</p>
                </div>

                <!-- Custom Icon & Styling Box -->
                <div class="p-4 bg-slate-50/90 rounded-2xl border border-slate-200/80 space-y-3">
                    <div class="flex items-center justify-between">
                        <label class="block text-[11px] font-bold text-slate-700 uppercase tracking-wide">Category Icon & Style</label>
                        <!-- Tab switcher -->
                        <div class="inline-flex items-center bg-slate-200/80 p-0.5 rounded-xl text-[11px]">
                            <button type="button" @click="newGroup.icon_tab = 'upload'" :class="newGroup.icon_tab === 'upload' ? 'bg-white text-indigo-700 font-bold shadow-2xs' : 'text-slate-600 font-medium'" class="px-2.5 py-1 rounded-lg transition">
                                📤 Upload Custom
                            </button>
                            <button type="button" @click="newGroup.icon_tab = 'preset'" :class="newGroup.icon_tab === 'preset' ? 'bg-white text-indigo-700 font-bold shadow-2xs' : 'text-slate-600 font-medium'" class="px-2.5 py-1 rounded-lg transition">
                                🎨 Presets
                            </button>
                        </div>
                    </div>

                    <!-- TAB 1: Custom Icon File Upload -->
                    <div x-show="newGroup.icon_tab === 'upload'" class="space-y-2">
                        <div class="flex items-center space-x-3">
                            <label class="flex-1 cursor-pointer border-2 border-dashed border-indigo-200 hover:border-indigo-400 bg-white hover:bg-indigo-50/30 rounded-2xl p-3 text-center transition group">
                                <input type="file" name="icon_file" accept="image/png,image/svg+xml,image/jpeg,image/webp" class="hidden"
                                       @change="const file = $event.target.files[0]; if (file) { const reader = new FileReader(); reader.onload = (e) => newGroup.iconPreview = e.target.result; reader.readAsDataURL(file); }">
                                <div class="flex items-center justify-center space-x-2 text-indigo-600 font-bold text-xs">
                                    <span>📁</span>
                                    <span>Upload Custom PNG / SVG / JPG</span>
                                </div>
                                <p class="text-[10px] text-slate-400 mt-0.5">Transparent PNG or SVG recommended (Square ratio, max 2MB)</p>
                            </label>

                            <template x-if="newGroup.iconPreview">
                                <div class="flex items-center space-x-2 bg-white p-2 rounded-2xl border border-slate-200 shrink-0">
                                    <img :src="newGroup.iconPreview" class="w-8 h-8 object-contain rounded-lg">
                                    <button type="button" @click="newGroup.iconPreview = null" class="text-rose-500 hover:text-rose-700 text-xs font-bold px-1.5 py-1 rounded hover:bg-rose-50" title="Remove custom file">✕</button>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- TAB 2: Preset Icon Picker -->
                    <div x-show="newGroup.icon_tab === 'preset'" class="space-y-2">
                        <div class="grid grid-cols-4 sm:grid-cols-7 gap-1.5 max-h-32 overflow-y-auto p-1.5 bg-white rounded-xl border border-slate-200">
                            <template x-for="item in presetIcons" :key="item.id">
                                <button type="button" @click="newGroup.icon = item.id; newGroup.iconPreview = null"
                                        :class="newGroup.icon === item.id && !newGroup.iconPreview ? 'border-indigo-600 bg-indigo-50 ring-2 ring-indigo-500/20' : 'border-slate-200 hover:bg-slate-50'"
                                        class="flex flex-col items-center justify-center p-2 rounded-xl border text-center transition"
                                        :title="item.label">
                                    <span class="text-lg" x-text="item.emoji"></span>
                                    <span class="text-[9px] font-semibold text-slate-700 truncate w-full mt-0.5" x-text="item.id"></span>
                                </button>
                            </template>
                        </div>
                    </div>

                    <!-- Color Swatches -->
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5">Tile Background Color</label>
                        <div class="flex items-center flex-wrap gap-2">
                            <template x-for="c in colorPalette" :key="c.id">
                                <button type="button" @click="newGroup.icon_color = c.id"
                                        :class="[c.bgClass, newGroup.icon_color === c.id ? 'ring-2 ring-offset-2 ring-indigo-600 scale-110' : 'opacity-80 hover:opacity-100']"
                                        class="w-6 h-6 rounded-full transition-all duration-150 shadow-2xs"
                                        :title="c.name">
                                </button>
                            </template>
                        </div>
                    </div>

                    <!-- Live Mini App Preview Box -->
                    <div class="p-3 bg-white border border-slate-200/90 rounded-2xl space-y-1.5 shadow-2xs">
                        <div class="flex items-center justify-between">
                            <span class="text-[9px] font-bold uppercase tracking-wider text-slate-400">Live Telegram Mini App Preview</span>
                            <div class="inline-flex items-center bg-slate-100 p-0.5 rounded-full text-[9px]">
                                <button type="button" @click="newGroup.previewLang = 'en'" :class="newGroup.previewLang === 'en' ? 'bg-blue-600 text-white font-bold' : 'text-slate-500'" class="px-2 py-0.5 rounded-full transition">EN</button>
                                <button type="button" @click="newGroup.previewLang = 'km'" :class="newGroup.previewLang === 'km' ? 'bg-blue-600 text-white font-bold' : 'text-slate-500'" class="px-2 py-0.5 rounded-full transition">ខ្មែរ</button>
                            </div>
                        </div>

                        <div class="bg-white border border-slate-200 rounded-2xl p-3 flex items-center space-x-3">
                            <div :class="getColorClass(newGroup.icon_color)" class="w-12 h-12 rounded-2xl text-white flex items-center justify-center shrink-0 shadow-xs overflow-hidden">
                                <template x-if="newGroup.iconPreview">
                                    <img :src="newGroup.iconPreview" class="w-7 h-7 object-contain">
                                </template>
                                <template x-if="!newGroup.iconPreview">
                                    <span class="text-xl" x-text="getEmojiForIcon(newGroup.icon)"></span>
                                </template>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-xs font-bold text-slate-800 leading-tight" x-text="newGroup.previewLang === 'km' ? (newGroup.name_km || newGroup.name || 'ឈ្មោះក្រុម') : (newGroup.name || 'Group Name')"></div>
                                <div class="text-[11px] text-slate-500 mt-0.5 leading-snug line-clamp-1" x-text="newGroup.previewLang === 'km' ? (newGroup.description_km || newGroup.description || 'ការពន្យល់លម្អិត...') : (newGroup.description || 'Detail subtitle description...')"></div>
                            </div>
                            <div class="text-slate-300">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end space-x-2 pt-3 border-t border-slate-100">
                    <button type="button" @click="createGroupModal = false" class="px-4 py-2 text-xs text-slate-600 font-semibold hover:bg-slate-100 rounded-xl transition">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-xs transition">Create Parent Group</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- 2. CREATE SUBCATEGORY MODAL               -->
    <!-- ========================================== -->
    <div x-show="createSubModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4" x-cloak>
        <div @click.away="createSubModal = false" class="bg-white rounded-3xl max-w-lg w-full shadow-2xl p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center space-x-2">
                    <span class="text-xl">➕</span>
                    <h3 class="text-base font-bold text-slate-900">
                        <span x-show="selectedParentName">Add Subcategory to <strong class="text-indigo-600" x-text="selectedParentName"></strong></span>
                        <span x-show="!selectedParentName">Create New Subcategory</span>
                    </h3>
                </div>
                <button type="button" @click="createSubModal = false" class="text-slate-400 hover:text-slate-600 text-sm font-bold">✕</button>
            </div>
            
            <form method="POST" action="{{ route('portal.admin.categories.store') }}" class="space-y-4">
                @csrf
                
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Parent Group</label>
                    <select name="parent_id" x-model="selectedParentId" required class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="" disabled>-- Select Parent Category Group --</option>
                        @foreach($categories as $p)
                            <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->type === 'service_request' ? 'Service Request' : 'Incident' }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Subcategory Name (EN)</label>
                        <input type="text" name="name" required placeholder="e.g. Dual Monitor Display Request" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Khmer Name (ឈ្មោះជាភាសាខ្មែរ)</label>
                        <input type="text" name="name_km" placeholder="ឧ. ស្នើសុំបន្ថែមអេក្រង់កុំព្យូទ័រ" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 font-sans">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Workflow Type</label>
                        <select name="type" required class="w-full text-xs px-3 py-2 rounded-xl border border-slate-300 bg-white">
                            <option value="incident_issue">Incident / Fault (Broken)</option>
                            <option value="service_request">Service Request (Request)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Default Priority</label>
                        <select name="default_priority" required class="w-full text-xs px-3 py-2 rounded-xl border border-slate-300 bg-white">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>
                </div>

                <div class="p-3 bg-purple-50 rounded-2xl border border-purple-200">
                    <label class="flex items-center space-x-2 cursor-pointer">
                        <input type="checkbox" name="requires_approval" value="1" class="rounded text-purple-600 focus:ring-purple-500">
                        <span class="text-xs font-bold text-purple-900">Requires Multi-Step Approval (Dept HOD + IT Manager)</span>
                    </label>
                    <p class="text-[10px] text-purple-700 mt-1">If checked, requests will enter 'pending_approval' before dispatching to Help Desk.</p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Target Ack SLA (Minutes)</label>
                        <input type="number" name="target_acknowledge_minutes" value="30" min="5" required class="w-full text-xs px-3 py-2 rounded-xl border border-slate-300">
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Target Resolve SLA (Hours)</label>
                        <input type="number" name="target_resolve_hours" value="24" min="1" required class="w-full text-xs px-3 py-2 rounded-xl border border-slate-300">
                    </div>
                </div>

                <div class="flex justify-end space-x-2 pt-3 border-t border-slate-100">
                    <button type="button" @click="createSubModal = false" class="px-4 py-2 text-xs text-slate-600 font-semibold hover:bg-slate-100 rounded-xl transition">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-xs transition">Create Subcategory</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- 3. EDIT CATEGORY / GROUP MODAL             -->
    <!-- ========================================== -->
    <div x-show="editModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4" x-cloak>
        <div @click.away="editModal = false" class="bg-white rounded-3xl max-w-xl w-full shadow-2xl p-6 space-y-4 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center space-x-2">
                    <span class="text-xl" x-text="isEditingParent ? '📁' : '✏️'"></span>
                    <h3 class="text-base font-bold text-slate-900">
                        <span x-text="isEditingParent ? 'Edit Parent Group: ' : 'Edit Subcategory: '"></span>
                        <strong class="text-indigo-600" x-text="editCat.name"></strong>
                    </h3>
                </div>
                <button type="button" @click="editModal = false" class="text-slate-400 hover:text-slate-600 text-sm font-bold">✕</button>
            </div>
            
            <form :action="'{{ url('portal/admin/categories') }}/' + editCat.id" method="POST" enctype="multipart/form-data" class="space-y-4">
                @csrf
                @method('PUT')
                <input type="hidden" name="icon" :value="editCat.icon">
                <input type="hidden" name="icon_color" :value="editCat.icon_color">

                <!-- Parent Group Reassignment (For Subcategories) -->
                <div x-show="!isEditingParent">
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Parent Category Group</label>
                    <select name="parent_id" x-model="editCat.parent_id" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white">
                        <option value="">-- Root Parent Group (Promote to Parent) --</option>
                        @foreach($categories as $p)
                            <option value="{{ $p->id }}">{{ $p->name }}</option>
                        @endforeach
                    </select>
                    <p class="text-[10px] text-slate-400 mt-1">You can move this subcategory to another parent group anytime.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Name (EN) <span class="text-rose-500">*</span></label>
                        <input type="text" name="name" x-model="editCat.name" required class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 font-medium">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Khmer Name (ឈ្មោះជាភាសាខ្មែរ)</label>
                        <input type="text" name="name_km" x-model="editCat.name_km" placeholder="ឈ្មោះជាភាសាខ្មែរ..." class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 font-sans">
                    </div>
                </div>

                <!-- Detail / Subtitle (For Parent Category Groups) -->
                <div x-show="isEditingParent" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">
                            Detail Subtitle (EN)
                            <span class="text-slate-400 font-normal lowercase">(shows in Mini App)</span>
                        </label>
                        <input type="text" name="description" x-model="editCat.description" placeholder="e.g. Receipt printer, barcode scanner, POS" class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">
                            Detail Subtitle (KM ភាសាខ្មែរ)
                            <span class="text-slate-400 font-normal lowercase">(ការបកប្រែ)</span>
                        </label>
                        <input type="text" name="description_km" x-model="editCat.description_km" placeholder="ឧ. ម៉ាស៊ីនព្រីនវិក្កយបត្រ ស្កេនបាកូដ..." class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 font-sans">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Workflow Type</label>
                        <select name="type" x-model="editCat.type" required class="w-full text-xs px-3 py-2 rounded-xl border border-slate-300 bg-white">
                            <option value="incident_issue">Incident / Fault</option>
                            <option value="service_request">Service Request</option>
                        </select>
                    </div>

                    <div x-show="!isEditingParent">
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Default Priority</label>
                        <select name="default_priority" x-model="editCat.default_priority" class="w-full text-xs px-3 py-2 rounded-xl border border-slate-300 bg-white">
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>
                </div>

                <!-- Custom Icon & Styling Box (For Parent Category Groups) -->
                <div x-show="isEditingParent" class="p-4 bg-slate-50/90 rounded-2xl border border-slate-200/80 space-y-3">
                    <div class="flex items-center justify-between">
                        <label class="block text-[11px] font-bold text-slate-700 uppercase tracking-wide">Category Icon & Style</label>
                        <div class="inline-flex items-center bg-slate-200/80 p-0.5 rounded-xl text-[11px]">
                            <button type="button" @click="editCat.icon_tab = 'upload'" :class="editCat.icon_tab === 'upload' ? 'bg-white text-indigo-700 font-bold shadow-2xs' : 'text-slate-600 font-medium'" class="px-2.5 py-1 rounded-lg transition">
                                📤 Upload Custom
                            </button>
                            <button type="button" @click="editCat.icon_tab = 'preset'" :class="editCat.icon_tab === 'preset' ? 'bg-white text-indigo-700 font-bold shadow-2xs' : 'text-slate-600 font-medium'" class="px-2.5 py-1 rounded-lg transition">
                                🎨 Presets
                            </button>
                        </div>
                    </div>

                    <!-- TAB 1: Custom Icon File Upload -->
                    <div x-show="editCat.icon_tab === 'upload'" class="space-y-2">
                        <div class="flex items-center space-x-3">
                            <label class="flex-1 cursor-pointer border-2 border-dashed border-indigo-200 hover:border-indigo-400 bg-white hover:bg-indigo-50/30 rounded-2xl p-3 text-center transition group">
                                <input type="file" name="icon_file" accept="image/png,image/svg+xml,image/jpeg,image/webp" class="hidden"
                                       @change="const file = $event.target.files[0]; if (file) { const reader = new FileReader(); reader.onload = (e) => editCat.iconPreview = e.target.result; reader.readAsDataURL(file); editCat.remove_icon_file = 0; }">
                                <div class="flex items-center justify-center space-x-2 text-indigo-600 font-bold text-xs">
                                    <span>📁</span>
                                    <span>Upload New PNG / SVG / JPG</span>
                                </div>
                                <p class="text-[10px] text-slate-400 mt-0.5">Transparent PNG or SVG recommended (Square ratio, max 2MB)</p>
                            </label>

                            <template x-if="editCat.iconPreview">
                                <div class="flex items-center space-x-2 bg-white p-2 rounded-2xl border border-slate-200 shrink-0">
                                    <img :src="editCat.iconPreview" class="w-8 h-8 object-contain rounded-lg">
                                    <button type="button" @click="editCat.iconPreview = null; editCat.remove_icon_file = 1" class="text-rose-500 hover:text-rose-700 text-xs font-bold px-1.5 py-1 rounded hover:bg-rose-50" title="Remove custom file">✕</button>
                                </div>
                            </template>
                        </div>

                        <!-- Checkbox for removing uploaded icon -->
                        <template x-if="editCat.icon_url && !editCat.iconPreview">
                            <input type="hidden" name="remove_icon_file" value="1">
                        </template>
                    </div>

                    <!-- TAB 2: Preset Icon Picker -->
                    <div x-show="editCat.icon_tab === 'preset'" class="space-y-2">
                        <div class="grid grid-cols-4 sm:grid-cols-7 gap-1.5 max-h-32 overflow-y-auto p-1.5 bg-white rounded-xl border border-slate-200">
                            <template x-for="item in presetIcons" :key="item.id">
                                <button type="button" @click="editCat.icon = item.id; editCat.iconPreview = null; editCat.remove_icon_file = 1"
                                        :class="editCat.icon === item.id && !editCat.iconPreview ? 'border-indigo-600 bg-indigo-50 ring-2 ring-indigo-500/20' : 'border-slate-200 hover:bg-slate-50'"
                                        class="flex flex-col items-center justify-center p-2 rounded-xl border text-center transition"
                                        :title="item.label">
                                    <span class="text-lg" x-text="item.emoji"></span>
                                    <span class="text-[9px] font-semibold text-slate-700 truncate w-full mt-0.5" x-text="item.id"></span>
                                </button>
                            </template>
                        </div>
                    </div>

                    <!-- Color Swatches -->
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5">Tile Background Color</label>
                        <div class="flex items-center flex-wrap gap-2">
                            <template x-for="c in colorPalette" :key="c.id">
                                <button type="button" @click="editCat.icon_color = c.id"
                                        :class="[c.bgClass, editCat.icon_color === c.id ? 'ring-2 ring-offset-2 ring-indigo-600 scale-110' : 'opacity-80 hover:opacity-100']"
                                        class="w-6 h-6 rounded-full transition-all duration-150 shadow-2xs"
                                        :title="c.name">
                                </button>
                            </template>
                        </div>
                    </div>

                    <!-- Live Mini App Preview Box -->
                    <div class="p-3 bg-white border border-slate-200/90 rounded-2xl space-y-1.5 shadow-2xs">
                        <div class="flex items-center justify-between">
                            <span class="text-[9px] font-bold uppercase tracking-wider text-slate-400">Live Telegram Mini App Preview</span>
                            <div class="inline-flex items-center bg-slate-100 p-0.5 rounded-full text-[9px]">
                                <button type="button" @click="editCat.previewLang = 'en'" :class="editCat.previewLang === 'en' ? 'bg-blue-600 text-white font-bold' : 'text-slate-500'" class="px-2 py-0.5 rounded-full transition">EN</button>
                                <button type="button" @click="editCat.previewLang = 'km'" :class="editCat.previewLang === 'km' ? 'bg-blue-600 text-white font-bold' : 'text-slate-500'" class="px-2 py-0.5 rounded-full transition">ខ្មែរ</button>
                            </div>
                        </div>

                        <div class="bg-white border border-slate-200 rounded-2xl p-3 flex items-center space-x-3">
                            <div :class="getColorClass(editCat.icon_color)" class="w-12 h-12 rounded-2xl text-white flex items-center justify-center shrink-0 shadow-xs overflow-hidden">
                                <template x-if="editCat.iconPreview">
                                    <img :src="editCat.iconPreview" class="w-7 h-7 object-contain">
                                </template>
                                <template x-if="!editCat.iconPreview">
                                    <span class="text-xl" x-text="getEmojiForIcon(editCat.icon)"></span>
                                </template>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-xs font-bold text-slate-800 leading-tight" x-text="editCat.previewLang === 'km' ? (editCat.name_km || editCat.name || 'ឈ្មោះក្រុម') : (editCat.name || 'Group Name')"></div>
                                <div class="text-[11px] text-slate-500 mt-0.5 leading-snug line-clamp-1" x-text="editCat.previewLang === 'km' ? (editCat.description_km || editCat.description || 'ការពន្យល់លម្អិត...') : (editCat.description || 'Detail subtitle description...')"></div>
                            </div>
                            <div class="text-slate-300">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Subcategory Specific Settings -->
                <template x-if="!isEditingParent">
                    <div class="space-y-4">
                        <div class="p-3 bg-purple-50 rounded-2xl border border-purple-200">
                            <label class="flex items-center space-x-2 cursor-pointer">
                                <input type="checkbox" name="requires_approval" value="1" :checked="editCat.requires_approval" class="rounded text-purple-600 focus:ring-purple-500">
                                <span class="text-xs font-bold text-purple-900">Requires Multi-Step Approval (Dept HOD + IT Manager)</span>
                            </label>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Target Ack SLA (Minutes)</label>
                                <input type="number" name="target_acknowledge_minutes" x-model="editCat.target_acknowledge_minutes" min="5" class="w-full text-xs px-3 py-2 rounded-xl border border-slate-300">
                            </div>

                            <div>
                                <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Target Resolve SLA (Hours)</label>
                                <input type="number" name="target_resolve_hours" x-model="editCat.target_resolve_hours" min="1" class="w-full text-xs px-3 py-2 rounded-xl border border-slate-300">
                            </div>
                        </div>
                    </div>
                </template>

                <div class="flex justify-end space-x-2 pt-3 border-t border-slate-100">
                    <button type="button" @click="editModal = false" class="px-4 py-2 text-xs text-slate-600 font-semibold hover:bg-slate-100 rounded-xl transition">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-xs transition">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- 4. PERMANENT DELETE CONFIRMATION MODAL    -->
    <!-- ========================================== -->
    <div x-show="deleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4" x-cloak>
        <div @click.away="deleteModal = false" class="bg-white rounded-3xl max-w-sm w-full shadow-2xl p-6 text-center space-y-4">
            <div class="w-12 h-12 rounded-2xl bg-rose-100 text-rose-600 flex items-center justify-center text-xl mx-auto">
                🗑️
            </div>
            
            <div>
                <h3 class="text-base font-bold text-slate-900">Delete Category?</h3>
                <p class="text-xs text-slate-500 mt-1">
                    Are you sure you want to permanently delete <strong class="text-slate-800" x-text="catToDelete?.name"></strong>?
                </p>
                <p class="text-[10px] text-rose-600 font-medium mt-1">
                    This action is permanent and cannot be undone. Only categories with 0 tickets can be deleted.
                </p>
            </div>

            <form :action="'{{ url('portal/admin/categories') }}/' + (catToDelete ? catToDelete.id : '')" method="POST" class="flex justify-center space-x-2 pt-2">
                @csrf
                @method('DELETE')
                <button type="button" @click="deleteModal = false" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-xl transition">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs rounded-xl shadow-xs transition">Permanently Delete</button>
            </form>
        </div>
    </div>

</div>
@endsection
