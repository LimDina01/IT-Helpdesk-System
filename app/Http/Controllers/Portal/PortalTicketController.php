<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\TicketAttachment;
use App\Models\TicketAuditLog;
use App\Models\TicketMessage;
use App\Models\User;
use App\Services\BusinessHoursService;
use App\Services\TelegramNotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PortalTicketController extends Controller
{
    /**
     * Show ticket operational details and dual message stream.
     */
    public function show(Ticket $ticket): View
    {
        $ticket->load([
            'requester.employee.department',
            'branch',
            'category.parent',
            'technician',
            'helpdesk',
            'assignments.assignedBy',
            'assignments.technician',
            'auditLogs.actor',
            'approvals.approver',
        ]);

        // Load all messages (Staff can see both requester-visible and internal notes)
        $messages = $ticket->messages()
            ->with(['sender.roles', 'attachments'])
            ->orderBy('created_at', 'asc')
            ->get();

        // Available technicians for reassignment
        $technicians = User::whereHas('roles', fn($q) => $q->where('name', 'technician'))
            ->where('is_active', true)
            ->get();

        // 30-min acknowledgement SLA status
        $isOverdueAcknowledgement = false;
        if ($ticket->status === 'assigned' && !$ticket->acknowledged_at && $ticket->assigned_at) {
            $isOverdueAcknowledgement = abs((int) now()->diffInMinutes($ticket->assigned_at)) > 30;
        }

        $approvalService = app(\App\Services\TicketApprovalService::class);
        $currentApproval = $ticket->currentApproval();
        $canApproveCurrent = $currentApproval ? $approvalService->canUserApprove(Auth::user(), $currentApproval) : false;

        return view('portal.ticket-detail', compact(
            'ticket',
            'messages',
            'technicians',
            'isOverdueAcknowledgement',
            'currentApproval',
            'canApproveCurrent'
        ));
    }

    /**
     * Assign ticket to an individual technician.
     */
    public function assign(Request $request, Ticket $ticket, TelegramNotificationService $telegram): RedirectResponse
    {
        $validated = $request->validate([
            'technician_id' => 'required|exists:users,id',
            'priority' => 'nullable|in:low,medium,high,critical',
            'assignment_note' => 'nullable|string',
        ]);

        $user = Auth::user();
        $tech = User::findOrFail($validated['technician_id']);

        DB::transaction(function () use ($ticket, $user, $tech, $validated) {
            $oldTechId = $ticket->current_technician_id;

            $ticket->update([
                'current_technician_id' => $tech->id,
                'current_helpdesk_id' => $user->id,
                'status' => 'assigned',
                'assigned_at' => now(),
                'acknowledged_at' => null, // Reset acknowledgement on new assignment
                'priority' => $validated['priority'] ?? $ticket->priority,
            ]);

            // Record assignment history
            TicketAssignment::create([
                'ticket_id' => $ticket->id,
                'assigned_by_id' => $user->id,
                'technician_id' => $tech->id,
                'assigned_at' => now(),
                'reassignment_reason' => $validated['assignment_note'] ?? null,
            ]);

            // Add system timeline event
            TicketMessage::create([
                'ticket_id' => $ticket->id,
                'sender_id' => $user->id,
                'visibility' => 'system_event',
                'message_type' => 'reassignment',
                'body' => "Ticket assigned to {$tech->name} by {$user->name}.",
            ]);

            // Audit Log
            TicketAuditLog::create([
                'ticket_id' => $ticket->id,
                'actor_id' => $user->id,
                'action' => 'ASSIGNED',
                'old_value' => $oldTechId ? (string)$oldTechId : null,
                'new_value' => (string)$tech->id,
                'metadata' => [
                    'technician_name' => $tech->name,
                    'note' => $validated['assignment_note'] ?? null,
                ],
                'created_at' => now(),
            ]);
        });

        // Trigger Telegram Bot alert to the assigned technician
        $telegram->sendAssignmentAlert($tech, $ticket);

        return back()->with('status', "Ticket assigned to {$tech->name}. 30-minute acknowledgement SLA timer started.");
    }

    /**
     * Quick action: Assign ticket directly to the logged-in staff member.
     */
    public function assignToMe(Ticket $ticket, TelegramNotificationService $telegram): RedirectResponse
    {
        $user = Auth::user();

        DB::transaction(function () use ($ticket, $user) {
            $oldTechId = $ticket->current_technician_id;

            $ticket->update([
                'current_technician_id' => $user->id,
                'current_helpdesk_id' => $ticket->current_helpdesk_id ?? $user->id,
                'status' => 'assigned',
                'assigned_at' => now(),
                'acknowledged_at' => null,
            ]);

            TicketAssignment::create([
                'ticket_id' => $ticket->id,
                'assigned_by_id' => $user->id,
                'technician_id' => $user->id,
                'assigned_at' => now(),
                'reassignment_reason' => 'Self-assigned from ticket workspace',
            ]);

            TicketMessage::create([
                'ticket_id' => $ticket->id,
                'sender_id' => $user->id,
                'visibility' => 'system_event',
                'message_type' => 'reassignment',
                'body' => "Ticket assigned to {$user->name} (Self-assigned).",
            ]);

            TicketAuditLog::create([
                'ticket_id' => $ticket->id,
                'actor_id' => $user->id,
                'action' => 'ASSIGNED',
                'old_value' => $oldTechId ? (string)$oldTechId : null,
                'new_value' => (string)$user->id,
                'metadata' => [
                    'technician_name' => $user->name,
                    'note' => 'Self-assigned from ticket workspace',
                ],
                'created_at' => now(),
            ]);
        });

        return back()->with('status', 'Ticket successfully assigned to you.');
    }

    /**
     * Quick action: Update ticket priority.
     */
    public function updatePriority(Request $request, Ticket $ticket): RedirectResponse
    {
        $validated = $request->validate([
            'priority' => 'required|in:low,medium,high,critical',
        ]);

        $oldPriority = $ticket->priority;
        $newPriority = $validated['priority'];

        if ($oldPriority !== $newPriority) {
            DB::transaction(function () use ($ticket, $oldPriority, $newPriority) {
                $ticket->update(['priority' => $newPriority]);

                TicketAuditLog::create([
                    'ticket_id' => $ticket->id,
                    'actor_id' => Auth::id(),
                    'action' => 'PRIORITY_CHANGED',
                    'old_value' => $oldPriority,
                    'new_value' => $newPriority,
                    'metadata' => ['priority' => $newPriority],
                    'created_at' => now(),
                ]);

                TicketMessage::create([
                    'ticket_id' => $ticket->id,
                    'sender_id' => Auth::id(),
                    'visibility' => 'system_event',
                    'message_type' => 'priority_change',
                    'body' => "Priority changed from " . strtoupper($oldPriority) . " to " . strtoupper($newPriority) . ".",
                ]);
            });
        }

        return back()->with('status', 'Ticket priority updated to ' . strtoupper($newPriority) . '.');
    }

    /**
     * Technician action: Accept & Start or Resume Work.
     */
    public function acceptAndStart(Ticket $ticket): RedirectResponse
    {
        $user = Auth::user();

        // Must be the assigned tech or an admin
        if ($ticket->current_technician_id !== $user->id && !$user->hasRole(['system_admin', 'hod'])) {
            return back()->withErrors(['error' => 'Only the assigned technician can accept or resume this ticket.']);
        }

        $oldStatus = $ticket->status;

        DB::transaction(function () use ($ticket, $user, $oldStatus) {
            $now = now();

            $updateData = [
                'status' => 'in_progress',
                'acknowledged_at' => $ticket->acknowledged_at ?? $now,
            ];

            // If resuming from Blocked/Hold, accumulate paused working minutes
            if ($oldStatus === 'blocked' && $ticket->blocked_at) {
                $additionalPauseMinutes = app(\App\Services\BusinessHoursService::class)->getWorkingMinutesBetween($ticket->blocked_at, $now);
                $updateData['paused_minutes'] = ($ticket->paused_minutes ?? 0) + $additionalPauseMinutes;
                $updateData['blocked_at'] = null;
            }

            $ticket->update($updateData);

            // Stamp acknowledgement on latest assignment record
            $latestAssignment = $ticket->assignments()->latest('id')->first();
            if ($latestAssignment && !$latestAssignment->acknowledged_at) {
                $latestAssignment->update(['acknowledged_at' => $now]);
            }

            if ($oldStatus === 'reopened') {
                $messageBody = "{$user->name} resumed work on this reopened ticket.";
            } elseif ($oldStatus === 'blocked') {
                $messageBody = "{$user->name} resumed work. SLA countdown active again.";
            } else {
                $messageBody = "{$user->name} accepted the ticket and started investigation.";
            }

            TicketMessage::create([
                'ticket_id' => $ticket->id,
                'sender_id' => $user->id,
                'visibility' => 'system_event',
                'message_type' => 'status_change',
                'body' => $messageBody,
            ]);

            TicketAuditLog::create([
                'ticket_id' => $ticket->id,
                'actor_id' => $user->id,
                'action' => $oldStatus === 'reopened' ? 'RESUMED' : ($oldStatus === 'blocked' ? 'UNBLOCKED' : 'ACKNOWLEDGED'),
                'old_value' => $oldStatus,
                'new_value' => 'in_progress',
                'metadata' => [
                    'acknowledged_at' => $now->toIso8601String(),
                    'previous_status' => $oldStatus,
                ],
                'created_at' => now(),
            ]);
        });

        if ($oldStatus === 'reopened') {
            $statusMsg = 'Work resumed on reopened ticket. Status is now In Progress.';
        } elseif ($oldStatus === 'blocked') {
            $statusMsg = 'Ticket resumed from hold. SLA clock unpaused and active.';
        } else {
            $statusMsg = 'Ticket accepted. Status is now In Progress.';
        }

        return back()->with('status', $statusMsg);
    }

    /**
     * Ask employee for clarification.
     */
    public function requestClarification(Request $request, Ticket $ticket, TelegramNotificationService $telegram): RedirectResponse
    {
        $validated = $request->validate([
            'question' => 'required|string|min:5',
        ]);

        $user = Auth::user();

        DB::transaction(function () use ($ticket, $user, $validated) {
            $ticket->update(['status' => 'clarifying']);

            TicketMessage::create([
                'ticket_id' => $ticket->id,
                'sender_id' => $user->id,
                'visibility' => 'requester_visible',
                'message_type' => 'text',
                'body' => "❓ CLARIFICATION REQUESTED: " . $validated['question'],
            ]);

            TicketAuditLog::create([
                'ticket_id' => $ticket->id,
                'actor_id' => $user->id,
                'action' => 'CLARIFICATION_REQUESTED',
                'old_value' => $ticket->getOriginal('status'),
                'new_value' => 'clarifying',
                'metadata' => ['question' => $validated['question']],
                'created_at' => now(),
            ]);
        });

        // Trigger Telegram alert to requester only if they are NOT active in chat right now
        $isRequesterActive = Cache::has("ticket_presence_{$ticket->id}_{$ticket->requester_id}");
        if (!$isRequesterActive && $ticket->requester) {
            $telegram->sendClarificationAlert($ticket->requester, $ticket, $validated['question']);
        }

        return back()->with('status', 'Clarification requested from employee.');
    }

    /**
     * Technician action: Mark as Done.
     */
    public function markDone(Ticket $ticket, BusinessHoursService $businessHours, TelegramNotificationService $telegram): RedirectResponse
    {
        $user = Auth::user();

        DB::transaction(function () use ($ticket, $user, $businessHours) {
            $now = now();
            // Calculate 3 business days auto-close deadline (skipping Sundays and custom holidays)
            $deadline = $businessHours->calculateAutoCloseDeadline($now, 3);

            $updateData = [
                'status' => 'pending_confirmation',
                'resolved_at' => $now,
                'auto_close_deadline' => $deadline,
            ];

            if ($ticket->status === 'blocked' && $ticket->blocked_at) {
                $additionalPauseMinutes = app(\App\Services\BusinessHoursService::class)->getWorkingMinutesBetween($ticket->blocked_at, $now);
                $updateData['paused_minutes'] = ($ticket->paused_minutes ?? 0) + $additionalPauseMinutes;
                $updateData['blocked_at'] = null;
            }

            $ticket->update($updateData);

            TicketMessage::create([
                'ticket_id' => $ticket->id,
                'sender_id' => $user->id,
                'visibility' => 'system_event',
                'message_type' => 'status_change',
                'body' => "{$user->name} marked the issue as resolved. Awaiting employee confirmation.",
            ]);

            TicketAuditLog::create([
                'ticket_id' => $ticket->id,
                'actor_id' => $user->id,
                'action' => 'RESOLVED_PENDING_CONFIRMATION',
                'old_value' => 'in_progress',
                'new_value' => 'pending_confirmation',
                'metadata' => [
                    'resolved_at' => $now->toIso8601String(),
                    'auto_close_deadline' => $deadline->toIso8601String(),
                ],
                'created_at' => now(),
            ]);
        });

        // Trigger Telegram alert to requester with confirmation button
        if ($ticket->requester) {
            $telegram->sendResolutionAlert($ticket->requester, $ticket);
        }

        return back()->with('status', 'Ticket marked as Done. Sent confirmation request to employee.');
    }

    /**
     * Technician action: Mark Blocked / On Hold.
     */
    public function markBlocked(Request $request, Ticket $ticket): RedirectResponse
    {
        $validated = $request->validate([
            'blocked_reason' => 'required|string|min:5',
        ]);

        $user = Auth::user();

        DB::transaction(function () use ($ticket, $user, $validated) {
            $ticket->update([
                'status' => 'blocked',
                'blocked_reason' => $validated['blocked_reason'],
                'blocked_at' => now(),
            ]);

            TicketMessage::create([
                'ticket_id' => $ticket->id,
                'sender_id' => $user->id,
                'visibility' => 'system_event',
                'message_type' => 'status_change',
                'body' => "Ticket marked as BLOCKED: {$validated['blocked_reason']}",
            ]);

            TicketAuditLog::create([
                'ticket_id' => $ticket->id,
                'actor_id' => $user->id,
                'action' => 'BLOCKED',
                'old_value' => 'in_progress',
                'new_value' => 'blocked',
                'metadata' => ['reason' => $validated['blocked_reason']],
                'created_at' => now(),
            ]);
        });

        return back()->with('status', 'Ticket marked as Blocked/On-Hold.');
    }

    /**
     * Add public reply or confidential internal note.
     */
    public function addMessage(Request $request, Ticket $ticket, TelegramNotificationService $telegram): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'body' => 'required|string',
            'visibility' => 'required|in:requester_visible,internal_staff_only',
            'attachments.*' => 'nullable|file|max:25600|mimes:jpeg,jpg,png,webp,gif,bmp,heic,heif,pdf,doc,docx,xls,xlsx,txt,zip',
        ]);

        $user = Auth::user();

        DB::transaction(function () use ($ticket, $user, $validated, $request) {
            $msg = TicketMessage::create([
                'ticket_id' => $ticket->id,
                'sender_id' => $user->id,
                'visibility' => $validated['visibility'],
                'message_type' => 'text',
                'body' => $validated['body'],
            ]);

            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $yearMonth = date('Y/m');
                    $storedName = (string) Str::uuid() . '.' . $file->getClientOriginalExtension();
                    $storagePath = $file->storeAs("attachments/{$yearMonth}", $storedName, 'attachments');

                    TicketAttachment::create([
                        'ticket_id' => $ticket->id,
                        'message_id' => $msg->id,
                        'uploaded_by_id' => $user->id,
                        'original_filename' => $file->getClientOriginalName(),
                        'storage_path' => $storagePath,
                        'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                        'file_size_bytes' => $file->getSize(),
                    ]);
                }
            }

            // Audit internal note creation
            if ($validated['visibility'] === 'internal_staff_only') {
                TicketAuditLog::create([
                    'ticket_id' => $ticket->id,
                    'actor_id' => $user->id,
                    'action' => 'INTERNAL_NOTE_ADDED',
                    'old_value' => null,
                    'new_value' => null,
                    'metadata' => ['message_id' => $msg->id],
                    'created_at' => now(),
                ]);
            }
        });

        // Trigger Telegram alert to requester only if public and requester is NOT active in chat right now
        $isRequesterActive = Cache::has("ticket_presence_{$ticket->id}_{$ticket->requester_id}");
        if (!$isRequesterActive && $validated['visibility'] === 'requester_visible' && $ticket->requester?->telegram_id) {
            $telegram->sendStaffReplyAlert($ticket->requester, $ticket, $validated['body'], $user->name);
        }

        $msgType = $validated['visibility'] === 'internal_staff_only' ? 'Internal note added.' : 'Reply sent to requester.';

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'message' => $msgType,
            ]);
        }

        return redirect()->to(route('portal.tickets.show', $ticket->id) . '#chatPanel')->with('status', $msgType);
    }

    /**
     * Download or stream authorized attachment.
     */
    public function downloadAttachment(Ticket $ticket, TicketAttachment $attachment, Request $request): StreamedResponse
    {
        if (!Storage::disk('attachments')->exists($attachment->storage_path)) {
            abort(404, 'File not found on storage disk.');
        }

        if ($request->has('preview') || str_starts_with($attachment->mime_type, 'image/')) {
            return Storage::disk('attachments')->response(
                $attachment->storage_path,
                $attachment->original_filename,
                ['Content-Type' => $attachment->mime_type]
            );
        }

        return Storage::disk('attachments')->download(
            $attachment->storage_path,
            $attachment->original_filename
        );
    }

    /**
     * Approve a pending approval step via Web Portal.
     */
    public function approveStep(Request $request, Ticket $ticket, \App\Models\TicketApproval $approval, \App\Services\TicketApprovalService $approvalService): RedirectResponse
    {
        $comment = $request->input('comments');
        $success = $approvalService->approve($approval, Auth::user(), $comment);

        if (!$success) {
            return back()->withErrors(['error' => 'Unauthorized or invalid approval step.']);
        }

        return back()->with('status', "{$approval->step_name} approved successfully!");
    }

    /**
     * Reject a pending approval step via Web Portal.
     */
    public function rejectStep(Request $request, Ticket $ticket, \App\Models\TicketApproval $approval, \App\Services\TicketApprovalService $approvalService): RedirectResponse
    {
        $request->validate(['reason' => 'required|string|min:3']);
        $success = $approvalService->reject($approval, Auth::user(), $request->input('reason'));

        if (!$success) {
            return back()->withErrors(['error' => 'Unauthorized or invalid approval step.']);
        }

        return back()->with('status', "{$approval->step_name} rejected.");
    }

    /**
     * Polling endpoint for real-time ticket conversation and presence updates.
     */
    public function poll(Ticket $ticket): \Illuminate\Http\JsonResponse
    {
        $isRequesterActive = Cache::has("ticket_presence_{$ticket->id}_{$ticket->requester_id}");

        $messages = $ticket->messages()
            ->with(['sender.roles', 'attachments'])
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn($m) => [
                'id' => $m->id,
                'sender_name' => $m->sender->name,
                'is_staff' => $m->sender->isStaff(),
                'visibility' => $m->visibility,
                'body' => $m->body,
                'created_at_human' => $m->created_at->diffForHumans(),
                'created_at_full' => $m->created_at->format('d-M-Y, H:i:s'),
                'attachments' => $m->attachments->map(fn($a) => [
                    'id' => $a->id,
                    'original_filename' => $a->original_filename,
                    'file_size' => $a->formatted_size,
                    'is_image' => str_starts_with($a->mime_type, 'image/'),
                    'download_url' => route('portal.attachments.download', ['ticket' => $ticket->id, 'attachment' => $a->id]),
                    'preview_url' => route('portal.attachments.download', ['ticket' => $ticket->id, 'attachment' => $a->id, 'preview' => 1]),
                ]),
            ]);

        return response()->json([
            'status' => $ticket->status,
            'is_requester_active' => $isRequesterActive,
            'messages_count' => $messages->count(),
            'messages' => $messages,
        ]);
    }
}
