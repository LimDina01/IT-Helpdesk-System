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
        // CBVH Jewelry & Luxury
        { id: '#A16B54', name: 'CBVH Brown', hex: '#A16B54' },
        { id: '#8C6239', name: 'Deep Bronze', hex: '#8C6239' },
        { id: '#B76E79', name: 'Rose Gold', hex: '#B76E79' },
        { id: '#D4AF37', name: 'Luxury Gold', hex: '#D4AF37' },
        { id: '#C5A059', name: 'Champagne Gold', hex: '#C5A059' },
        { id: '#FAF4E3', name: 'Silk Champagne', hex: '#FAF4E3' },
        { id: '#FCF8ED', name: 'Cream Pearl', hex: '#FCF8ED' },

        // Blues & Cyans
        { id: '#2563EB', name: 'Royal Blue', hex: '#2563EB' },
        { id: '#1D4ED8', name: 'Navy Blue', hex: '#1D4ED8' },
        { id: '#0284C7', name: 'Sky Blue', hex: '#0284C7' },
        { id: '#06B6D4', name: 'Cyan Ocean', hex: '#06B6D4' },
        { id: '#0891B2', name: 'Deep Cyan', hex: '#0891B2' },

        // Emeralds & Greens
        { id: '#10B981', name: 'Emerald', hex: '#10B981' },
        { id: '#059669', name: 'Forest Green', hex: '#059669' },
        { id: '#16A34A', name: 'Vibrant Green', hex: '#16A34A' },
        { id: '#14B8A6', name: 'Teal Jade', hex: '#14B8A6' },
        { id: '#84CC16', name: 'Lime Olive', hex: '#84CC16' },

        // Purples & Violets
        { id: '#4F46E5', name: 'Royal Indigo', hex: '#4F46E5' },
        { id: '#7C3AED', name: 'Violet', hex: '#7C3AED' },
        { id: '#9333EA', name: 'Amethyst Purple', hex: '#9333EA' },
        { id: '#C026D3', name: 'Fuchsia', hex: '#C026D3' },

        // Reds, Pinks, Oranges & Yellows
        { id: '#EC4899', name: 'Ruby Pink', hex: '#EC4899' },
        { id: '#F43F5E', name: 'Rose', hex: '#F43F5E' },
        { id: '#DC2626', name: 'Crimson Red', hex: '#DC2626' },
        { id: '#B91C1C', name: 'Garnet Red', hex: '#B91C1C' },
        { id: '#EA580C', name: 'Burnt Orange', hex: '#EA580C' },
        { id: '#F97316', name: 'Vivid Orange', hex: '#F97316' },
        { id: '#F59E0B', name: 'Amber Topaz', hex: '#F59E0B' },
        { id: '#EAB308', name: 'Yellow Gold', hex: '#EAB308' },

        // Neutrals & Darks
        { id: '#475569', name: 'Slate Grey', hex: '#475569' },
        { id: '#334155', name: 'Steel Blue', hex: '#334155' },
        { id: '#1E293B', name: 'Dark Slate', hex: '#1E293B' },
        { id: '#0F172A', name: 'Midnight Navy', hex: '#0F172A' },
        { id: '#27272A', name: 'Charcoal Zinc', hex: '#27272A' },
        { id: '#18181B', name: 'Pitch Black', hex: '#18181B' },
        { id: '#FFFFFF', name: 'Pure White', hex: '#FFFFFF' }
    ],

    newGroup: {
        name: '',
        name_km: '',
        description: '',
        description_km: '',
        icon: 'other',
        icon_color: '#2563EB',
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
            icon_color: '#2563EB',
            icon_tab: 'upload',
            iconPreview: null,
            previewLang: 'en'
        };
        this.createGroupModal = true;
    },
    
    subForm: {
        sla_critical_hours: 4,
        sla_high_hours: 8,
        sla_medium_hours: 24,
        sla_low_hours: 48,
        default_priority: 'medium',
        target_acknowledge_minutes: 30
    },

    openAddSub(parentId = '', parentName = '') {
        this.selectedParentId = parentId ? String(parentId) : '';
        this.selectedParentName = parentName || '';
        this.subForm = {
            sla_critical_hours: 4,
            sla_high_hours: 8,
            sla_medium_hours: 24,
            sla_low_hours: 48,
            default_priority: 'medium',
            target_acknowledge_minutes: 30
        };
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
        this.editCat.icon_color = this.editCat.icon_color || '#2563EB';
        this.editCat.icon_tab = this.editCat.icon_url ? 'upload' : 'preset';
        this.editCat.iconPreview = this.editCat.icon_url || null;
        this.editCat.remove_icon_file = 0;
        this.editCat.previewLang = 'en';
        this.editCat.default_priority = this.editCat.default_priority || 'medium';
        this.editCat.sla_critical_hours = this.editCat.sla_critical_hours || 4;
        this.editCat.sla_high_hours = this.editCat.sla_high_hours || 8;
        this.editCat.sla_medium_hours = this.editCat.sla_medium_hours || 24;
        this.editCat.sla_low_hours = this.editCat.sla_low_hours || 48;
        this.editCat.target_acknowledge_minutes = this.editCat.target_acknowledge_minutes || 30;
        this.editModal = true;
    },
    getColorHex(colorVal) {
        if (!colorVal) return '#2563EB';
        if (colorVal.startsWith('#')) return colorVal;
        const nameMap = {
            'cbvh-brown': '#A16B54',
            'gold': '#D4AF37',
            'champagne': '#FAF4E3',
            'cream': '#FCF8ED',
            'rose-gold': '#B76E79',
            'bronze': '#8C6239',
            'emerald': '#10B981',
            'teal': '#14B8A6',
            'cyan': '#06B6D4',
            'sky': '#0284C7',
            'blue': '#2563EB',
            'indigo': '#4F46E5',
            'violet': '#7C3AED',
            'purple': '#9333EA',
            'fuchsia': '#C026D3',
            'pink': '#EC4899',
            'rose': '#F43F5E',
            'red': '#DC2626',
            'orange': '#EA580C',
            'amber': '#F59E0B',
            'yellow': '#EAB308',
            'lime': '#84CC16',
            'green': '#16A34A',
            'slate': '#475569',
            'zinc': '#27272A',
            'dark-navy': '#0F172A',
            'white': '#FFFFFF',
        };
        return nameMap[colorVal.toLowerCase()] || '#2563EB';
    },
    getColorStyle(colorVal) {
        const hex = this.getColorHex(colorVal);
        return `background-color: ${hex} !important;`;
    },
    isLightColor(colorVal) {
        const hex = this.getColorHex(colorVal).replace('#', '');
        if (hex.length === 6) {
            const r = parseInt(hex.substring(0, 2), 16);
            const g = parseInt(hex.substring(2, 4), 16);
            const b = parseInt(hex.substring(4, 6), 16);
            const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
            return luminance > 0.65;
        }
        return false;
    },
    getColorClass(colorId) {
        return '';
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
                        @php
                            $cVal = $parent->icon_color;
                            $hexMap = [
                                'cbvh-brown' => '#A16B54',
                                'gold' => '#D4AF37',
                                'champagne' => '#FAF4E3',
                                'cream' => '#FCF8ED',
                                'rose-gold' => '#B76E79',
                                'bronze' => '#8C6239',
                                'emerald' => '#10B981',
                                'teal' => '#14B8A6',
                                'cyan' => '#06B6D4',
                                'sky' => '#0284C7',
                                'blue' => '#2563EB',
                                'indigo' => '#4F46E5',
                                'violet' => '#7C3AED',
                                'purple' => '#9333EA',
                                'fuchsia' => '#C026D3',
                                'pink' => '#EC4899',
                                'rose' => '#F43F5E',
                                'red' => '#DC2626',
                                'orange' => '#EA580C',
                                'amber' => '#F59E0B',
                                'yellow' => '#EAB308',
                                'lime' => '#84CC16',
                                'green' => '#16A34A',
                                'slate' => '#475569',
                                'zinc' => '#27272A',
                                'dark-navy' => '#0F172A',
                                'white' => '#FFFFFF',
                            ];
                            $tileHex = $hexMap[strtolower($cVal ?? '')] ?? ($cVal && str_starts_with($cVal, '#') ? $cVal : ($parent->type === 'service_request' ? '#4F46E5' : '#2563EB'));
                            $isLightTile = in_array(strtolower($tileHex), ['#ffffff', '#faf4e3', '#fcf8ed', '#f1f5f9', '#eab308']);
                        @endphp
                        <div style="background-color: {{ $tileHex }} !important;"
                             class="w-10 h-10 rounded-xl {{ $isLightTile ? 'text-slate-800 border border-slate-200' : 'text-white' }} flex items-center justify-center font-bold text-sm shadow-2xs overflow-hidden shrink-0">
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

                                    <td class="py-3 px-4 whitespace-nowrap">
                                        <div class="flex items-center space-x-1 text-[10px] font-mono">
                                            <span class="px-1.5 py-0.5 rounded bg-red-50 text-red-700 font-bold border border-red-200/60" title="Critical Priority SLA">Crit: {{ $child->sla_critical_hours ?? 4 }}h</span>
                                            <span class="px-1.5 py-0.5 rounded bg-orange-50 text-orange-700 font-bold border border-orange-200/60" title="High Priority SLA">High: {{ $child->sla_high_hours ?? 8 }}h</span>
                                            <span class="px-1.5 py-0.5 rounded bg-blue-50 text-blue-700 font-bold border border-blue-200/60" title="Medium Priority SLA">Med: {{ $child->sla_medium_hours ?? 24 }}h</span>
                                            <span class="px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 font-bold border border-emerald-200/60" title="Low Priority SLA">Low: {{ $child->sla_low_hours ?? 48 }}h</span>
                                        </div>
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

                    <!-- Color Swatches & Custom Color Picker -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-[10px] font-bold text-slate-500 uppercase">Tile Background Color</label>
                            <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded-md bg-slate-100 text-slate-700" x-text="newGroup.icon_color"></span>
                        </div>

                        <div class="p-2.5 bg-slate-50/80 rounded-2xl border border-slate-200/80 space-y-2">
                            <!-- Curated Palette Swatches (36 Shades) -->
                            <div class="flex items-center flex-wrap gap-1.5 max-h-36 overflow-y-auto p-1 bg-white rounded-xl border border-slate-200/60">
                                <template x-for="c in colorPalette" :key="c.id">
                                    <button type="button" @click="newGroup.icon_color = c.hex"
                                            :style="`background-color: ${c.hex};`"
                                            :class="(newGroup.icon_color && (newGroup.icon_color.toLowerCase() === c.hex.toLowerCase() || newGroup.icon_color.toLowerCase() === c.id.toLowerCase())) ? 'ring-2 ring-offset-2 ring-indigo-600 scale-110 z-10' : 'opacity-85 hover:opacity-100 hover:scale-105'"
                                            class="w-6 h-6 rounded-full transition-all duration-150 shadow-2xs shrink-0 border border-black/10 cursor-pointer"
                                            :title="c.name + ' (' + c.hex + ')'">
                                    </button>
                                </template>
                            </div>

                            <!-- Custom Color Picker + Hex Input Row -->
                            <div class="flex items-center space-x-2 pt-1 border-t border-slate-200/60">
                                <label class="flex items-center space-x-1.5 cursor-pointer bg-white px-2 py-1 rounded-lg border border-slate-200 hover:bg-slate-50 transition shrink-0">
                                    <input type="color"
                                           :value="getColorHex(newGroup.icon_color)"
                                           @input="newGroup.icon_color = $event.target.value"
                                           class="w-5 h-5 rounded cursor-pointer p-0 border-0 bg-transparent" />
                                    <span class="text-[11px] font-semibold text-slate-600">Custom Picker</span>
                                </label>
                                <div class="flex-1 flex items-center bg-white border border-slate-200 rounded-lg px-2 py-1">
                                    <span class="text-xs text-slate-400 font-mono font-bold mr-1">#</span>
                                    <input type="text"
                                           :value="(newGroup.icon_color || '').replace('#', '')"
                                           @input="newGroup.icon_color = '#' + $event.target.value.replace(/[^0-9A-Fa-f]/g, '')"
                                           placeholder="A16B54"
                                           maxlength="6"
                                           class="w-full text-xs font-mono font-semibold text-slate-800 focus:outline-none uppercase" />
                                </div>
                            </div>
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
                            <div :style="getColorStyle(newGroup.icon_color)"
                                 :class="isLightColor(newGroup.icon_color) ? 'text-slate-800 border border-slate-200' : 'text-white'"
                                 class="w-12 h-12 rounded-2xl flex items-center justify-center shrink-0 shadow-xs overflow-hidden transition-colors">
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

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Workflow Type</label>
                        <select name="type" required class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white">
                            <option value="incident_issue">Incident / Fault (Broken)</option>
                            <option value="service_request">Service Request (Request)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Target Ack SLA (Minutes)</label>
                        <input type="number" name="target_acknowledge_minutes" x-model="subForm.target_acknowledge_minutes" value="30" min="5" required class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-slate-300">
                    </div>
                </div>

                <div class="p-3 bg-purple-50 rounded-2xl border border-purple-200">
                    <label class="flex items-center space-x-2 cursor-pointer">
                        <input type="checkbox" name="requires_approval" value="1" class="rounded text-purple-600 focus:ring-purple-500">
                        <span class="text-xs font-bold text-purple-900">Requires Multi-Step Approval (Dept HOD + IT Manager)</span>
                    </label>
                    <p class="text-[10px] text-purple-700 mt-1">If checked, requests will enter 'pending_approval' before dispatching to Help Desk.</p>
                </div>

                <!-- Resolution SLA Hours by Priority Card -->
                <div class="p-3.5 bg-slate-50/90 rounded-2xl border border-slate-200/90 space-y-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 uppercase tracking-wide">
                                Resolution SLAs by Priority
                            </label>
                            <p class="text-[10px] text-slate-500">Set target hours to complete for every priority tier</p>
                        </div>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 font-mono">Multi-Tier SLA</span>
                    </div>

                    <!-- 4 Priority SLA Inputs Grid -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                        <!-- Critical Tier -->
                        <div class="p-2.5 bg-red-50/70 rounded-xl border border-red-200/80">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-[10px] font-extrabold uppercase text-red-700 flex items-center space-x-1">
                                    <span>🔴</span>
                                    <span>Critical</span>
                                </span>
                                <span class="text-[9px] text-red-500 font-medium">Emergency</span>
                            </div>
                            <div class="flex items-center bg-white rounded-lg border border-red-200 px-2 py-1">
                                <input type="number" name="sla_critical_hours" x-model="subForm.sla_critical_hours" min="1" max="720" required class="w-full text-xs font-bold text-slate-800 focus:outline-none text-right">
                                <span class="text-[11px] font-semibold text-slate-500 ml-1">hrs</span>
                            </div>
                        </div>

                        <!-- High Tier -->
                        <div class="p-2.5 bg-orange-50/70 rounded-xl border border-orange-200/80">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-[10px] font-extrabold uppercase text-orange-700 flex items-center space-x-1">
                                    <span>🟠</span>
                                    <span>High</span>
                                </span>
                                <span class="text-[9px] text-orange-500 font-medium">Urgent</span>
                            </div>
                            <div class="flex items-center bg-white rounded-lg border border-orange-200 px-2 py-1">
                                <input type="number" name="sla_high_hours" x-model="subForm.sla_high_hours" min="1" max="720" required class="w-full text-xs font-bold text-slate-800 focus:outline-none text-right">
                                <span class="text-[11px] font-semibold text-slate-500 ml-1">hrs</span>
                            </div>
                        </div>

                        <!-- Medium Tier -->
                        <div class="p-2.5 bg-blue-50/70 rounded-xl border border-blue-200/80">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-[10px] font-extrabold uppercase text-blue-700 flex items-center space-x-1">
                                    <span>🔵</span>
                                    <span>Medium</span>
                                </span>
                                <span class="text-[9px] text-blue-500 font-medium">Standard</span>
                            </div>
                            <div class="flex items-center bg-white rounded-lg border border-blue-200 px-2 py-1">
                                <input type="number" name="sla_medium_hours" x-model="subForm.sla_medium_hours" min="1" max="720" required class="w-full text-xs font-bold text-slate-800 focus:outline-none text-right">
                                <span class="text-[11px] font-semibold text-slate-500 ml-1">hrs</span>
                            </div>
                        </div>

                        <!-- Low Tier -->
                        <div class="p-2.5 bg-emerald-50/70 rounded-xl border border-emerald-200/80">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-[10px] font-extrabold uppercase text-emerald-700 flex items-center space-x-1">
                                    <span>🟢</span>
                                    <span>Low</span>
                                </span>
                                <span class="text-[9px] text-emerald-500 font-medium">Normal</span>
                            </div>
                            <div class="flex items-center bg-white rounded-lg border border-emerald-200 px-2 py-1">
                                <input type="number" name="sla_low_hours" x-model="subForm.sla_low_hours" min="1" max="720" required class="w-full text-xs font-bold text-slate-800 focus:outline-none text-right">
                                <span class="text-[11px] font-semibold text-slate-500 ml-1">hrs</span>
                            </div>
                        </div>
                    </div>

                    <!-- Default Priority Selector for this Subcategory -->
                    <div class="pt-2 border-t border-slate-200/60">
                        <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1.5">
                            Default Priority
                            <span class="text-slate-400 font-normal lowercase">(choose which default priority fits this subcategory)</span>
                        </label>
                        <div class="grid grid-cols-4 gap-2">
                            <!-- Low Button -->
                            <button type="button" @click="subForm.default_priority = 'low'"
                                    :class="subForm.default_priority === 'low' ? 'border-emerald-500 bg-emerald-50 text-emerald-800 ring-2 ring-emerald-500/20 font-bold' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50 font-medium'"
                                    class="py-1.5 px-2 rounded-xl border text-xs flex flex-col items-center justify-center transition cursor-pointer">
                                <span class="flex items-center space-x-1"><span>🟢</span><span>Low</span></span>
                                <span class="text-[9px] opacity-80 mt-0.5" x-text="(subForm.sla_low_hours || 48) + ' hrs'"></span>
                            </button>

                            <!-- Medium Button -->
                            <button type="button" @click="subForm.default_priority = 'medium'"
                                    :class="subForm.default_priority === 'medium' ? 'border-blue-500 bg-blue-50 text-blue-800 ring-2 ring-blue-500/20 font-bold' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50 font-medium'"
                                    class="py-1.5 px-2 rounded-xl border text-xs flex flex-col items-center justify-center transition cursor-pointer">
                                <span class="flex items-center space-x-1"><span>🔵</span><span>Medium</span></span>
                                <span class="text-[9px] opacity-80 mt-0.5" x-text="(subForm.sla_medium_hours || 24) + ' hrs'"></span>
                            </button>

                            <!-- High Button -->
                            <button type="button" @click="subForm.default_priority = 'high'"
                                    :class="subForm.default_priority === 'high' ? 'border-orange-500 bg-orange-50 text-orange-800 ring-2 ring-orange-500/20 font-bold' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50 font-medium'"
                                    class="py-1.5 px-2 rounded-xl border text-xs flex flex-col items-center justify-center transition cursor-pointer">
                                <span class="flex items-center space-x-1"><span>🟠</span><span>High</span></span>
                                <span class="text-[9px] opacity-80 mt-0.5" x-text="(subForm.sla_high_hours || 8) + ' hrs'"></span>
                            </button>

                            <!-- Critical Button -->
                            <button type="button" @click="subForm.default_priority = 'critical'"
                                    :class="subForm.default_priority === 'critical' ? 'border-red-500 bg-red-50 text-red-800 ring-2 ring-red-500/20 font-bold' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50 font-medium'"
                                    class="py-1.5 px-2 rounded-xl border text-xs flex flex-col items-center justify-center transition cursor-pointer">
                                <span class="flex items-center space-x-1"><span>🔴</span><span>Critical</span></span>
                                <span class="text-[9px] opacity-80 mt-0.5" x-text="(subForm.sla_critical_hours || 4) + ' hrs'"></span>
                            </button>
                        </div>
                        <input type="hidden" name="default_priority" :value="subForm.default_priority">
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

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Workflow Type</label>
                        <select name="type" x-model="editCat.type" required class="w-full text-xs px-3 py-2 rounded-xl border border-slate-300 bg-white">
                            <option value="incident_issue">Incident / Fault</option>
                            <option value="service_request">Service Request</option>
                        </select>
                    </div>

                    <div x-show="!isEditingParent">
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Target Ack SLA (Minutes)</label>
                        <input type="number" name="target_acknowledge_minutes" x-model="editCat.target_acknowledge_minutes" min="5" class="w-full text-xs px-3 py-2 rounded-xl border border-slate-300">
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

                    <!-- Color Swatches & Custom Color Picker -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-[10px] font-bold text-slate-500 uppercase">Tile Background Color</label>
                            <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded-md bg-slate-100 text-slate-700" x-text="editCat.icon_color"></span>
                        </div>

                        <div class="p-2.5 bg-slate-50/80 rounded-2xl border border-slate-200/80 space-y-2">
                            <!-- Curated Palette Swatches (36 Shades) -->
                            <div class="flex items-center flex-wrap gap-1.5 max-h-36 overflow-y-auto p-1 bg-white rounded-xl border border-slate-200/60">
                                <template x-for="c in colorPalette" :key="c.id">
                                    <button type="button" @click="editCat.icon_color = c.hex"
                                            :style="`background-color: ${c.hex};`"
                                            :class="(editCat.icon_color && (editCat.icon_color.toLowerCase() === c.hex.toLowerCase() || editCat.icon_color.toLowerCase() === c.id.toLowerCase())) ? 'ring-2 ring-offset-2 ring-indigo-600 scale-110 z-10' : 'opacity-85 hover:opacity-100 hover:scale-105'"
                                            class="w-6 h-6 rounded-full transition-all duration-150 shadow-2xs shrink-0 border border-black/10 cursor-pointer"
                                            :title="c.name + ' (' + c.hex + ')'">
                                    </button>
                                </template>
                            </div>

                            <!-- Custom Color Picker + Hex Input Row -->
                            <div class="flex items-center space-x-2 pt-1 border-t border-slate-200/60">
                                <label class="flex items-center space-x-1.5 cursor-pointer bg-white px-2 py-1 rounded-lg border border-slate-200 hover:bg-slate-50 transition shrink-0">
                                    <input type="color"
                                           :value="getColorHex(editCat.icon_color)"
                                           @input="editCat.icon_color = $event.target.value"
                                           class="w-5 h-5 rounded cursor-pointer p-0 border-0 bg-transparent" />
                                    <span class="text-[11px] font-semibold text-slate-600">Custom Picker</span>
                                </label>
                                <div class="flex-1 flex items-center bg-white border border-slate-200 rounded-lg px-2 py-1">
                                    <span class="text-xs text-slate-400 font-mono font-bold mr-1">#</span>
                                    <input type="text"
                                           :value="(editCat.icon_color || '').replace('#', '')"
                                           @input="editCat.icon_color = '#' + $event.target.value.replace(/[^0-9A-Fa-f]/g, '')"
                                           placeholder="A16B54"
                                           maxlength="6"
                                           class="w-full text-xs font-mono font-semibold text-slate-800 focus:outline-none uppercase" />
                                </div>
                            </div>
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
                            <div :style="getColorStyle(editCat.icon_color)"
                                 :class="isLightColor(editCat.icon_color) ? 'text-slate-800 border border-slate-200' : 'text-white'"
                                 class="w-12 h-12 rounded-2xl flex items-center justify-center shrink-0 shadow-xs overflow-hidden transition-colors">
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

                        <!-- Resolution SLA Hours by Priority Card -->
                        <div class="p-3.5 bg-slate-50/90 rounded-2xl border border-slate-200/90 space-y-3">
                            <div class="flex items-center justify-between">
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-700 uppercase tracking-wide">
                                        Resolution SLAs by Priority
                                    </label>
                                    <p class="text-[10px] text-slate-500">Set target hours to complete for every priority tier</p>
                                </div>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 font-mono">Multi-Tier SLA</span>
                            </div>

                            <!-- 4 Priority SLA Inputs Grid -->
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                                <!-- Critical Tier -->
                                <div class="p-2.5 bg-red-50/70 rounded-xl border border-red-200/80">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-[10px] font-extrabold uppercase text-red-700 flex items-center space-x-1">
                                            <span>🔴</span>
                                            <span>Critical</span>
                                        </span>
                                        <span class="text-[9px] text-red-500 font-medium">Emergency</span>
                                    </div>
                                    <div class="flex items-center bg-white rounded-lg border border-red-200 px-2 py-1">
                                        <input type="number" name="sla_critical_hours" x-model="editCat.sla_critical_hours" min="1" max="720" required class="w-full text-xs font-bold text-slate-800 focus:outline-none text-right">
                                        <span class="text-[11px] font-semibold text-slate-500 ml-1">hrs</span>
                                    </div>
                                </div>

                                <!-- High Tier -->
                                <div class="p-2.5 bg-orange-50/70 rounded-xl border border-orange-200/80">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-[10px] font-extrabold uppercase text-orange-700 flex items-center space-x-1">
                                            <span>🟠</span>
                                            <span>High</span>
                                        </span>
                                        <span class="text-[9px] text-orange-500 font-medium">Urgent</span>
                                    </div>
                                    <div class="flex items-center bg-white rounded-lg border border-orange-200 px-2 py-1">
                                        <input type="number" name="sla_high_hours" x-model="editCat.sla_high_hours" min="1" max="720" required class="w-full text-xs font-bold text-slate-800 focus:outline-none text-right">
                                        <span class="text-[11px] font-semibold text-slate-500 ml-1">hrs</span>
                                    </div>
                                </div>

                                <!-- Medium Tier -->
                                <div class="p-2.5 bg-blue-50/70 rounded-xl border border-blue-200/80">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-[10px] font-extrabold uppercase text-blue-700 flex items-center space-x-1">
                                            <span>🔵</span>
                                            <span>Medium</span>
                                        </span>
                                        <span class="text-[9px] text-blue-500 font-medium">Standard</span>
                                    </div>
                                    <div class="flex items-center bg-white rounded-lg border border-blue-200 px-2 py-1">
                                        <input type="number" name="sla_medium_hours" x-model="editCat.sla_medium_hours" min="1" max="720" required class="w-full text-xs font-bold text-slate-800 focus:outline-none text-right">
                                        <span class="text-[11px] font-semibold text-slate-500 ml-1">hrs</span>
                                    </div>
                                </div>

                                <!-- Low Tier -->
                                <div class="p-2.5 bg-emerald-50/70 rounded-xl border border-emerald-200/80">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-[10px] font-extrabold uppercase text-emerald-700 flex items-center space-x-1">
                                            <span>🟢</span>
                                            <span>Low</span>
                                        </span>
                                        <span class="text-[9px] text-emerald-500 font-medium">Normal</span>
                                    </div>
                                    <div class="flex items-center bg-white rounded-lg border border-emerald-200 px-2 py-1">
                                        <input type="number" name="sla_low_hours" x-model="editCat.sla_low_hours" min="1" max="720" required class="w-full text-xs font-bold text-slate-800 focus:outline-none text-right">
                                        <span class="text-[11px] font-semibold text-slate-500 ml-1">hrs</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Default Priority Selector for this Subcategory -->
                            <div class="pt-2 border-t border-slate-200/60">
                                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1.5">
                                    Default Priority
                                    <span class="text-slate-400 font-normal lowercase">(choose which default priority fits this subcategory)</span>
                                </label>
                                <div class="grid grid-cols-4 gap-2">
                                    <!-- Low Button -->
                                    <button type="button" @click="editCat.default_priority = 'low'"
                                            :class="editCat.default_priority === 'low' ? 'border-emerald-500 bg-emerald-50 text-emerald-800 ring-2 ring-emerald-500/20 font-bold' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50 font-medium'"
                                            class="py-1.5 px-2 rounded-xl border text-xs flex flex-col items-center justify-center transition cursor-pointer">
                                        <span class="flex items-center space-x-1"><span>🟢</span><span>Low</span></span>
                                        <span class="text-[9px] opacity-80 mt-0.5" x-text="(editCat.sla_low_hours || 48) + ' hrs'"></span>
                                    </button>

                                    <!-- Medium Button -->
                                    <button type="button" @click="editCat.default_priority = 'medium'"
                                            :class="editCat.default_priority === 'medium' ? 'border-blue-500 bg-blue-50 text-blue-800 ring-2 ring-blue-500/20 font-bold' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50 font-medium'"
                                            class="py-1.5 px-2 rounded-xl border text-xs flex flex-col items-center justify-center transition cursor-pointer">
                                        <span class="flex items-center space-x-1"><span>🔵</span><span>Medium</span></span>
                                        <span class="text-[9px] opacity-80 mt-0.5" x-text="(editCat.sla_medium_hours || 24) + ' hrs'"></span>
                                    </button>

                                    <!-- High Button -->
                                    <button type="button" @click="editCat.default_priority = 'high'"
                                            :class="editCat.default_priority === 'high' ? 'border-orange-500 bg-orange-50 text-orange-800 ring-2 ring-orange-500/20 font-bold' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50 font-medium'"
                                            class="py-1.5 px-2 rounded-xl border text-xs flex flex-col items-center justify-center transition cursor-pointer">
                                        <span class="flex items-center space-x-1"><span>🟠</span><span>High</span></span>
                                        <span class="text-[9px] opacity-80 mt-0.5" x-text="(editCat.sla_high_hours || 8) + ' hrs'"></span>
                                    </button>

                                    <!-- Critical Button -->
                                    <button type="button" @click="editCat.default_priority = 'critical'"
                                            :class="editCat.default_priority === 'critical' ? 'border-red-500 bg-red-50 text-red-800 ring-2 ring-red-500/20 font-bold' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50 font-medium'"
                                            class="py-1.5 px-2 rounded-xl border text-xs flex flex-col items-center justify-center transition cursor-pointer">
                                        <span class="flex items-center space-x-1"><span>🔴</span><span>Critical</span></span>
                                        <span class="text-[9px] opacity-80 mt-0.5" x-text="(editCat.sla_critical_hours || 4) + ' hrs'"></span>
                                    </button>
                                </div>
                                <input type="hidden" name="default_priority" :value="editCat.default_priority">
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
