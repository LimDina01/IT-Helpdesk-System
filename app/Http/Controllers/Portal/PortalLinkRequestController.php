<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Role;
use App\Models\TelegramLinkRequest;
use App\Models\User;
use App\Services\TelegramNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PortalLinkRequestController extends Controller
{
    /**
     * Display pending and reviewed Telegram link requests.
     */
    public function index(Request $request): View
    {
        $status = $request->input('status', 'pending');

        $requests = TelegramLinkRequest::with(['employee.branch', 'employee.department', 'reviewer'])
            ->where('status', $status)
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        $pendingCount = TelegramLinkRequest::where('status', 'pending')->count();
        $approvedCount = TelegramLinkRequest::where('status', 'approved')->count();
        $rejectedCount = TelegramLinkRequest::where('status', 'rejected')->count();

        return view('portal.link-requests', compact(
            'requests',
            'status',
            'pendingCount',
            'approvedCount',
            'rejectedCount'
        ));
    }

    /**
     * Approve an employee Telegram account link request.
     */
    public function approve(TelegramLinkRequest $linkRequest): RedirectResponse
    {
        if ($linkRequest->status !== 'pending') {
            return back()->withErrors(['error' => 'This request has already been processed.']);
        }

        $employee = Employee::where('employee_code', $linkRequest->employee_code)->first();

        if (!$employee) {
            return back()->withErrors(['error' => 'Associated employee record was not found.']);
        }

        $user = Auth::user();

        DB::transaction(function () use ($linkRequest, $employee, $user) {
            // 1. Mark request as approved
            $linkRequest->update([
                'status' => 'approved',
                'reviewed_by_id' => $user->id,
                'reviewed_at' => now(),
            ]);

            // 2. Find or create the user record
            $appUser = User::firstOrNew(['employee_id' => $employee->id]);
            $appUser->name = $employee->full_name;
            $appUser->email = $employee->email;
            $appUser->telegram_id = $linkRequest->telegram_id;
            $appUser->telegram_username = $linkRequest->telegram_username;
            $appUser->is_active = true;
            $appUser->save();

            // 3. Ensure requester role is assigned
            $requesterRole = Role::firstOrCreate(['name' => 'requester'], ['label' => 'Requester']);
            if (!$appUser->roles->contains($requesterRole->id)) {
                $appUser->roles()->attach($requesterRole->id);
            }
        });

        // Notify employee on Telegram that their account is activated
        try {
            app(TelegramNotificationService::class)->sendMessage(
                $linkRequest->telegram_id,
                "🎉 *Welcome to CBVH IT Help Desk!*\n\n"
                . "Your Telegram account has been approved and linked to Employee Code *{$linkRequest->employee_code}*.\n\n"
                . "You can now submit tickets, track status, and chat with IT support directly from Telegram.",
                [
                    [
                        ['text' => '📱 Open IT Help Desk', 'web_app' => ['url' => rtrim(config('app.url'), '/') . '/tma']]
                    ]
                ],
                null,
                'account_linked'
            );
        } catch (\Throwable $e) {
            // Log but don't disrupt response
        }

        return back()->with('status', "Approved! {$employee->full_name} ({$linkRequest->employee_code}) is now linked and active on Telegram.");
    }

    /**
     * Reject an employee Telegram account link request.
     */
    public function reject(Request $request, TelegramLinkRequest $linkRequest): RedirectResponse
    {
        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:255',
        ]);

        $linkRequest->update([
            'status' => 'rejected',
            'reviewed_by_id' => Auth::id(),
            'reviewed_at' => now(),
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        return back()->with('status', 'Link request rejected.');
    }
}
