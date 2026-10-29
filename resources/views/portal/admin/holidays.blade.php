@extends('portal.layout')

@section('title', 'System Administration - Company Holidays')

@section('content')
<div x-data="{ createModal: false }" class="space-y-6">

    <!-- Top Admin Header -->
    <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="text-xs text-indigo-600 font-extrabold uppercase tracking-wider">System Administration</div>
            <h1 class="text-xl sm:text-2xl font-black text-slate-900 mt-1">Official Company Holidays Calendar</h1>
            <p class="text-xs text-slate-500 mt-0.5">Holidays are automatically excluded from technician acknowledgement SLAs and auto-closure windows (along with all Sundays).</p>
        </div>

        <button type="button" @click="createModal = true" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs px-4 py-2.5 rounded-xl shadow-xs transition flex items-center space-x-1.5 self-start sm:self-auto">
            <span>➕</span>
            <span>Add Holiday</span>
        </button>
    </div>

    <!-- Admin Navigation Tabs -->
    <div class="flex items-center space-x-2 bg-slate-100 p-1 rounded-2xl text-xs font-semibold overflow-x-auto w-fit">
        <a href="{{ route('portal.admin.index') }}" class="text-slate-600 hover:text-slate-900 px-4 py-2 rounded-xl transition flex items-center space-x-1.5">
            <span>📁</span>
            <span>Categories & SLAs</span>
        </a>
        <a href="{{ route('portal.admin.holidays') }}" class="bg-white text-indigo-600 shadow-xs px-4 py-2 rounded-xl transition flex items-center space-x-1.5">
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

    <!-- Holidays Table Card -->
    <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-500 font-bold uppercase tracking-wider text-[10px] border-b border-slate-200">
                    <tr>
                        <th class="py-3 px-6">Holiday Name</th>
                        <th class="py-3 px-4">Official Date</th>
                        <th class="py-3 px-4">Day of Week</th>
                        <th class="py-3 px-4">Type</th>
                        <th class="py-3 px-4">Notes / Background</th>
                        <th class="py-3 px-6 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($holidays as $h)
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="py-3.5 px-6 font-bold text-slate-900 whitespace-nowrap">
                                {{ $h->name }}
                            </td>

                            <td class="py-3.5 px-4 font-mono font-semibold text-indigo-700 whitespace-nowrap">
                                {{ $h->holiday_date->format('l, d-M-Y') }}
                            </td>

                            <td class="py-3.5 px-4 text-slate-600 whitespace-nowrap">
                                {{ $h->holiday_date->format('l') }}
                            </td>

                            <td class="py-3.5 px-4 whitespace-nowrap">
                                @if($h->is_recurring)
                                    <span class="bg-blue-50 text-blue-700 border border-blue-200 text-[10px] font-bold px-2 py-0.5 rounded-full">
                                        Annual Recurring
                                    </span>
                                @else
                                    <span class="bg-slate-100 text-slate-700 text-[10px] font-medium px-2 py-0.5 rounded-full">
                                        Single Year
                                    </span>
                                @endif
                            </td>

                            <td class="py-3.5 px-4 text-slate-500 text-[11px]">
                                {{ $h->notes ?? 'Official Public Holiday' }}
                            </td>

                            <td class="py-3.5 px-6 text-right whitespace-nowrap">
                                <form method="POST" action="{{ route('portal.admin.holidays.destroy', $h->id) }}" onsubmit="return confirm('Remove this holiday from the schedule?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-rose-600 hover:text-rose-800 text-[11px] font-semibold hover:underline">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-400 text-xs">
                                No company holidays registered. All standard non-Sunday days will be considered working hours.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- CREATE HOLIDAY MODAL -->
    <div x-show="createModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4" x-cloak>
        <div @click.away="createModal = false" class="bg-white rounded-3xl max-w-md w-full shadow-2xl p-6 space-y-4">
            <h3 class="text-base font-bold text-slate-800">Add Company Holiday</h3>
            
            <form method="POST" action="{{ route('portal.admin.holidays.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Holiday Name</label>
                    <input type="text" name="name" required placeholder="e.g. Royal Ploughing Ceremony" class="w-full text-xs px-3 py-2 rounded-xl border border-slate-300">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Date</label>
                    <input type="date" name="holiday_date" required class="w-full text-xs px-3 py-2 rounded-xl border border-slate-300">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Notes / Description (Optional)</label>
                    <input type="text" name="notes" placeholder="e.g. National holiday" class="w-full text-xs px-3 py-2 rounded-xl border border-slate-300">
                </div>

                <div class="p-3 bg-slate-50 rounded-2xl border border-slate-200">
                    <label class="flex items-center space-x-2 cursor-pointer">
                        <input type="checkbox" name="is_recurring" value="1" class="rounded text-indigo-600 focus:ring-indigo-500">
                        <span class="text-xs font-semibold text-slate-800">Recurs Every Year on This Same Date</span>
                    </label>
                </div>

                <div class="flex justify-end space-x-2 pt-2">
                    <button type="button" @click="createModal = false" class="px-3.5 py-2 text-xs text-slate-600 font-semibold">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-xs transition">Save Holiday</button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
