<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\SoftwareLicense;
use App\Models\SoftwareLicenseAssignment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PortalSoftwareLicenseController extends Controller
{
    /**
     * Store a new software application or digital license.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'vendor_publisher' => 'nullable|string|max:100',
            'license_type' => 'required|string|in:subscription,perpetual,volume,oem,free',
            'license_key' => 'nullable|string|max:255',
            'seats_total' => 'required|integer|min:1',
            'purchase_date' => 'nullable|date',
            'expiry_date' => 'nullable|date',
            'purchase_cost' => 'nullable|numeric|min:0',
            'billing_cycle' => 'required|string|in:annual,monthly,one_time',
            'status' => 'required|string|in:active,expiring_soon,expired,cancelled',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Security check: Only HOD / System Admin can record/edit financial cost
        if (! Auth::user()->hasRole(['hod', 'system_admin'])) {
            unset($validated['purchase_cost']);
        }

        SoftwareLicense::create($validated);

        return redirect()->route('portal.assets.index', ['tab' => 'licenses'])
            ->with('status', 'Software license "'.$validated['name'].'" registered successfully.');
    }

    /**
     * Update an existing software license profile.
     */
    public function update(Request $request, SoftwareLicense $license): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'vendor_publisher' => 'nullable|string|max:100',
            'license_type' => 'required|string|in:subscription,perpetual,volume,oem,free',
            'license_key' => 'nullable|string|max:255',
            'seats_total' => 'required|integer|min:1',
            'purchase_date' => 'nullable|date',
            'expiry_date' => 'nullable|date',
            'purchase_cost' => 'nullable|numeric|min:0',
            'billing_cycle' => 'required|string|in:annual,monthly,one_time',
            'status' => 'required|string|in:active,expiring_soon,expired,cancelled',
            'notes' => 'nullable|string|max:1000',
        ]);

        if (! Auth::user()->hasRole(['hod', 'system_admin'])) {
            unset($validated['purchase_cost']);
        }

        $license->update($validated);

        return redirect()->route('portal.assets.index', ['tab' => 'licenses'])
            ->with('status', 'Software license "'.$license->name.'" updated successfully.');
    }

    /**
     * Delete a software license.
     */
    public function destroy(SoftwareLicense $license): RedirectResponse
    {
        $name = $license->name;
        $license->delete();

        return redirect()->route('portal.assets.index', ['tab' => 'licenses'])
            ->with('status', 'Software license "'.$name.'" deleted.');
    }

    /**
     * Allocate / assign a seat to an employee or hardware workstation.
     */
    public function assignSeat(Request $request, SoftwareLicense $license): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'asset_id' => 'nullable|exists:assets,id',
            'notes' => 'nullable|string|max:255',
        ]);

        if (empty($validated['user_id']) && empty($validated['asset_id'])) {
            return back()->with('error', 'Please select either an employee or a computer asset to allocate this license seat.');
        }

        if ($license->seats_available <= 0) {
            return back()->with('error', 'All '.$license->seats_total.' seats for "'.$license->name.'" are fully allocated. Increase total seats before assigning more.');
        }

        $validated['software_license_id'] = $license->id;
        $validated['assigned_date'] = now()->toDateString();

        SoftwareLicenseAssignment::create($validated);

        return redirect()->route('portal.assets.index', ['tab' => 'licenses'])
            ->with('status', 'License seat successfully allocated for "'.$license->name.'".');
    }

    /**
     * Deallocate / revoke a license seat.
     */
    public function removeSeat(SoftwareLicenseAssignment $assignment): RedirectResponse
    {
        $licenseName = $assignment->license->name ?? 'Software';
        $assignment->delete();

        return redirect()->route('portal.assets.index', ['tab' => 'licenses'])
            ->with('status', 'Seat deallocated from "'.$licenseName.'".');
    }
}
