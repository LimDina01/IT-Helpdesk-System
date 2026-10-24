@extends('portal.layout')

@section('title', 'System Administration - Categories & SLAs')

@section('content')
<div x-data="{ createModal: false, editModal: false, editCat: {} }" class="space-y-6">

    <!-- Top Admin Header -->
    <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="text-xs text-indigo-600 font-extrabold uppercase tracking-wider">System Administration</div>
            <h1 class="text-xl sm:text-2xl font-black text-slate-900 mt-1">Categories, Workflows & SLA Targets</h1>
            <p class="text-xs text-slate-500 mt-0.5">Configure incident routing, service request multi-step approvals, and response time commitments.</p>
        </div>

        <button type="button" @click="createModal = true" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs px-4 py-2.5 rounded-xl shadow-xs transition flex items-center space-x-1.5 self-start sm:self-auto">
            <span>➕</span>
            <span>New Category</span>
        </button>
    </div>

    <!-- Admin Navigation Tabs -->
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
    </div>

    <!-- Categories List (Grouped by Parent) -->
    <div class="space-y-6">
        @foreach($categories as $parent)
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
                <!-- Parent Header -->
                <div class="bg-slate-50/80 px-6 py-4 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="flex items-center space-x-3">
                        <div class="w-8 h-8 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold text-sm">
                            📁
                        </div>
                        <div>
                            <div class="font-extrabold text-sm text-slate-900">{{ $parent->name }}</div>
                            <div class="text-[11px] text-slate-500">
                                @if($parent->type === 'service_request')
                                    <span class="text-indigo-600 font-semibold">🔵 Service Request Group</span>
                                @else
                                    <span class="text-rose-600 font-semibold">🔴 Incident / Fault Group</span>
                                @endif
                                &bull; {{ $parent->children->count() }} subcategories
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center space-x-2">
                        <form method="POST" action="{{ route('portal.admin.categories.toggle', $parent->id) }}">
                            @csrf
                            <button type="submit" class="text-[10px] font-bold px-2 py-1 rounded-lg border {{ $parent->is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200' }}">
                                {{ $parent->is_active ? 'Active' : 'Disabled' }}
                            </button>
                        </form>
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
                                <tr class="hover:bg-slate-50/70 transition">
                                    <td class="py-3 px-6 font-bold text-slate-800 whitespace-nowrap">
                                        {{ $child->name }}
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
                                        <button type="button"
                                                @click="editCat = {{ json_encode($child) }}; editModal = true"
                                                class="text-[11px] text-blue-600 hover:underline font-semibold px-2 py-1">
                                            Edit
                                        </button>
                                        
                                        <form method="POST" action="{{ route('portal.admin.categories.toggle', $child->id) }}" class="inline-block">
                                            @csrf
                                            <button type="submit" class="text-[10px] font-bold px-2 py-0.5 rounded border {{ $child->is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200' }}">
                                                {{ $child->is_active ? 'Active' : 'Disabled' }}
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="py-4 px-6 text-slate-400 text-xs italic">
                                        No subcategories in this group.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    </div>

    <!-- CREATE CATEGORY MODAL -->
    <div x-show="createModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4" x-cloak>
        <div @click.away="createModal = false" class="bg-white rounded-3xl max-w-lg w-full shadow-2xl p-6 space-y-4">
            <h3 class="text-base font-bold text-slate-800">Create New Ticket Category</h3>
            
            <form method="POST" action="{{ route('portal.admin.categories.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Parent Group (Optional)</label>
                    <select name="parent_id" class="w-full text-xs px-3 py-2 rounded-xl border border-slate-300 bg-white">
                        <option value="">-- Create as Root Parent Group --</option>
                        @foreach($categories as $p)
                            <option value="{{ $p->id }}">{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Category Name</label>
                    <input type="text" name="name" required placeholder="e.g. Dual Monitor Display Request" class="w-full text-xs px-3 py-2 rounded-xl border border-slate-300">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Workflow Type</label>
                        <select name="type" required class="w-full text-xs px-3 py-2 rounded-xl border border-slate-300 bg-white">
                            <option value="incident_issue">Incident / Fault (Something Broken)</option>
                            <option value="service_request">Service Request (Enhancement / Buy)</option>
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

                <div class="flex justify-end space-x-2 pt-2">
                    <button type="button" @click="createModal = false" class="px-3.5 py-2 text-xs text-slate-600 font-semibold">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-xs transition">Create Category</button>
                </div>
            </form>
        </div>
    </div>

    <!-- EDIT CATEGORY MODAL -->
    <div x-show="editModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4" x-cloak>
        <div @click.away="editModal = false" class="bg-white rounded-3xl max-w-lg w-full shadow-2xl p-6 space-y-4">
            <h3 class="text-base font-bold text-slate-800">Edit Category (<span x-text="editCat.name"></span>)</h3>
            
            <form :action="'{{ url('portal/admin/categories') }}/' + editCat.id" method="POST" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Category Name</label>
                    <input type="text" name="name" x-model="editCat.name" required class="w-full text-xs px-3 py-2 rounded-xl border border-slate-300">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Workflow Type</label>
                        <select name="type" x-model="editCat.type" required class="w-full text-xs px-3 py-2 rounded-xl border border-slate-300 bg-white">
                            <option value="incident_issue">Incident / Fault</option>
                            <option value="service_request">Service Request</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Default Priority</label>
                        <select name="default_priority" x-model="editCat.default_priority" required class="w-full text-xs px-3 py-2 rounded-xl border border-slate-300 bg-white">
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>
                </div>

                <div class="p-3 bg-purple-50 rounded-2xl border border-purple-200">
                    <label class="flex items-center space-x-2 cursor-pointer">
                        <input type="checkbox" name="requires_approval" value="1" :checked="editCat.requires_approval" class="rounded text-purple-600 focus:ring-purple-500">
                        <span class="text-xs font-bold text-purple-900">Requires Multi-Step Approval (Dept HOD + IT Manager)</span>
                    </label>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Target Ack SLA (Minutes)</label>
                        <input type="number" name="target_acknowledge_minutes" x-model="editCat.target_acknowledge_minutes" min="5" required class="w-full text-xs px-3 py-2 rounded-xl border border-slate-300">
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Target Resolve SLA (Hours)</label>
                        <input type="number" name="target_resolve_hours" x-model="editCat.target_resolve_hours" min="1" required class="w-full text-xs px-3 py-2 rounded-xl border border-slate-300">
                    </div>
                </div>

                <div class="flex justify-end space-x-2 pt-2">
                    <button type="button" @click="editModal = false" class="px-3.5 py-2 text-xs text-slate-600 font-semibold">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-xs transition">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
