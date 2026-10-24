<?php

namespace App\Http\Controllers\Tma;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketAuditLog;
use App\Models\TicketCategory;
use App\Models\TicketMessage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Services\TelegramNotificationService;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TmaTicketController extends Controller
{
    use AuthorizesRequests;

    /**
     * Get tickets for the authenticated employee.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tab = $request->input('tab', 'active'); // 'active', 'history', or 'approvals'

        if ($tab === 'history') {
            $query = Ticket::where('requester_id', $user->id)
                ->whereIn('status', ['resolved_confirmed', 'resolved_auto_closed', 'cancelled', 'rejected'])
                ->orderByDesc('closed_at')
                ->orderByDesc('updated_at');
        } elseif ($tab === 'approvals') {
            // Approvals tab for HOD / Manager
            $query = Ticket::whereHas('approvals', function ($q) {
                $q->where('status', 'pending');
            })->orderByDesc('created_at');
        } else {
            $query = Ticket::where('requester_id', $user->id)
                ->whereIn('status', [
                    'pending_approval',
                    'submitted',
                    'clarifying',
                    'assigned',
                    'in_progress',
                    'blocked',
                    'pending_confirmation',
                    'reopened',
                ])->orderByDesc('created_at');
        }

        $query->with(['branch', 'category.parent', 'technician', 'requester', 'approvals', 'latestRequesterMessage.sender']);

        $tickets = $query->get()->map(function (Ticket $t) use ($user) {
            $currentApproval = $t->currentApproval();
            $latestMsg = $t->latestRequesterMessage;
            $isStaff = $latestMsg && $latestMsg->sender ? $latestMsg->sender->isStaff() : false;

            return [
                'id' => $t->id,
                'ticket_code' => $t->ticket_code,
                'ticket_type' => $t->ticket_type ?? 'incident',
                'subject' => $t->subject,
                'status' => $t->status,
                'priority' => $t->priority,
                'branch_name' => $t->branch->name ?? 'N/A',
                'category_name' => $t->category->name ?? 'N/A',
                'parent_category_name' => $t->category->parent->name ?? null,
                'technician_name' => $t->technician->name ?? 'Awaiting Assignment',
                'requester_name' => $t->requester->name ?? 'N/A',
                'created_at' => $t->created_at->format('d-M-Y H:i'),
                'created_at_human' => $t->created_at->diffForHumans(),
                'resolved_at' => $t->resolved_at?->format('d-M-Y H:i'),
                'auto_close_deadline' => $t->auto_close_deadline?->format('d-M-Y H:i'),
                'is_pending_confirmation' => $t->isPendingConfirmation(),
                'is_pending_approval' => $t->isPendingApproval(),
                'is_clarifying' => $t->isClarifying(),
                'current_approval_step' => $currentApproval ? [
                    'id' => $currentApproval->id,
                    'step_number' => $currentApproval->step_number,
                    'step_name' => $currentApproval->step_name,
                    'role_name' => $currentApproval->role_name,
                ] : null,
                'latest_message' => $latestMsg ? [
                    'id' => $latestMsg->id,
                    'body' => Str::limit($latestMsg->body ?? '(Attachment)', 80),
                    'sender_name' => $latestMsg->sender->name ?? 'System',
                    'is_staff' => $isStaff,
                    'created_at_human' => $latestMsg->created_at->diffForHumans(),
                ] : null,
                'has_new_reply' => $isStaff && !in_array($t->status, ['resolved_confirmed', 'resolved_auto_closed', 'cancelled', 'rejected']),
            ];
        });

        // Count pending approvals for current user if they are HOD
        $pendingApprovalsCount = 0;
        if ($user->hasRole(['hod', 'system_admin'])) {
            $pendingApprovalsCount = TicketApproval::where('status', 'pending')->count();
        }

        return response()->json([
            'tab' => $tab,
            'tickets' => $tickets,
            'pending_approvals_count' => $pendingApprovalsCount,
        ]);
    }

    /**
     * Get full details and public conversation timeline for a single ticket.
     */
    public function show(Ticket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);

        // Record heartbeat: User is actively looking at this ticket chat (8s TTL, sustained by 3.5s poller)
        if (Auth::check()) {
            Cache::put("ticket_presence_{$ticket->id}_" . Auth::id(), now()->timestamp, 8);
        }

        $ticket->load([
            'branch',
            'category.parent',
            'technician',
            'helpdesk',
            'requester.employee.department',
            'approvals.approver',
            'approvals.department',
        ]);

        // Strict Server-Side Privacy: NEVER return internal staff notes to requesters
        $messages = $ticket->messages()
            ->whereIn('visibility', ['requester_visible', 'system_event'])
            ->with(['sender', 'attachments'])
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function (TicketMessage $m) {
                return [
                    'id' => $m->id,
                    'sender_id' => $m->sender_id,
                    'sender_name' => $m->sender->name,
                    'is_me' => $m->sender_id === Auth::id(),
                    'is_staff' => $m->sender->isStaff(),
                    'message_type' => $m->message_type,
                    'visibility' => $m->visibility,
                    'body' => $m->body,
                    'created_at' => $m->created_at->format('d-M-Y H:i:s'),
                    'created_at_human' => $m->created_at->diffForHumans(),
                    'full_timestamp' => $m->created_at->format('d-M-Y H:i') . ' (' . $m->created_at->diffForHumans() . ')',
                    'attachments' => $m->attachments->map(fn(TicketAttachment $a) => [
                        'id' => $a->id,
                        'original_filename' => $a->original_filename,
                        'mime_type' => $a->mime_type,
                        'is_image' => str_starts_with($a->mime_type, 'image/'),
                        'file_size' => $a->formatted_size,
                        'download_url' => route('tma.attachments.download', ['ticket' => $m->ticket_id, 'attachment' => $a->id]),
                        'preview_url' => route('tma.attachments.download', ['ticket' => $m->ticket_id, 'attachment' => $a->id, 'preview' => 1]),
                    ]),
                ];
            });

        $approvals = $ticket->approvals->map(function ($app) {
            return [
                'id' => $app->id,
                'step_number' => $app->step_number,
                'step_name' => $app->step_name,
                'role_name' => $app->role_name,
                'status' => $app->status,
                'comments' => $app->comments,
                'approver_name' => $app->approver->name ?? null,
                'actioned_at' => $app->actioned_at?->format('d-M-Y H:i'),
                'actioned_at_human' => $app->actioned_at?->diffForHumans(),
            ];
        });

        $currentApproval = $ticket->currentApproval();
        $canApprove = false;
        if ($currentApproval) {
            $canApprove = app(\App\Services\TicketApprovalService::class)->canUserApprove(Auth::user(), $currentApproval);
        }

        return response()->json([
            'ticket' => [
                'id' => $ticket->id,
                'ticket_code' => $ticket->ticket_code,
                'ticket_type' => $ticket->ticket_type ?? 'incident',
                'subject' => $ticket->subject,
                'description' => $ticket->description,
                'status' => $ticket->status,
                'priority' => $ticket->priority,
                'branch_name' => $ticket->branch->name ?? 'N/A',
                'category_name' => $ticket->category->name ?? 'N/A',
                'parent_category_name' => $ticket->category->parent->name ?? null,
                'category_type' => $ticket->category->type ?? 'incident_issue',
                'technician_name' => $ticket->technician->name ?? 'Not Assigned Yet',
                'helpdesk_name' => $ticket->helpdesk->name ?? null,
                'requester_name' => $ticket->requester->name,
                'requester_dept' => $ticket->requester->employee?->department?->name ?? 'N/A',
                'created_at' => $ticket->created_at->format('d-M-Y H:i:s'),
                'created_at_human' => $ticket->created_at->diffForHumans(),
                'full_created_timestamp' => $ticket->created_at->format('d-M-Y H:i') . ' (' . $ticket->created_at->diffForHumans() . ')',
                'resolved_at' => $ticket->resolved_at?->format('d-M-Y H:i'),
                'closed_at' => $ticket->closed_at?->format('d-M-Y H:i'),
                'auto_close_deadline' => $ticket->auto_close_deadline?->format('d-M-Y H:i'),
                'blocked_reason' => $ticket->blocked_reason,
                'is_pending_confirmation' => $ticket->isPendingConfirmation(),
                'is_pending_approval' => $ticket->isPendingApproval(),
                'is_clarifying' => $ticket->isClarifying(),
                'is_closed' => $ticket->isClosed(),
                'approvals' => $approvals,
                'can_approve_current_step' => $canApprove,
                'current_approval_step' => $currentApproval ? [
                    'id' => $currentApproval->id,
                    'step_number' => $currentApproval->step_number,
                    'step_name' => $currentApproval->step_name,
                    'role_name' => $currentApproval->role_name,
                ] : null,
            ],
            'messages' => $messages,
        ]);
    }

    /**
     * Clear presence heartbeat immediately when the user leaves the chat modal.
     */
    public function leaveChat(Ticket $ticket): JsonResponse
    {
        if (Auth::check()) {
            Cache::forget("ticket_presence_{$ticket->id}_" . Auth::id());
        }

        return response()->json(['status' => 'left']);
    }

    /**
     * Submit a new ticket with optional multi-file attachments.
     */
    public function store(Request $request, \App\Services\TicketApprovalService $approvalService): JsonResponse
    {
        $validated = $request->validate([
            'ticket_type' => 'nullable|in:incident,service_request',
            'branch_id' => 'required|exists:branches,id',
            'category_id' => 'required|exists:ticket_categories,id',
            'priority' => 'required|in:low,medium,high,critical',
            'subject' => 'required|string|max:255',
            'description' => 'required|string|min:5',
            'attachments.*' => 'nullable|file|max:20480|mimes:jpeg,jpg,png,webp,pdf',
        ]);

        $user = Auth::user();
        $category = TicketCategory::findOrFail($validated['category_id']);
        $ticketType = $validated['ticket_type'] ?? ($category->type === 'service_request' ? 'service_request' : 'incident');

        $ticket = DB::transaction(function () use ($validated, $user, $request, $ticketType, $category, $approvalService) {
            $ticketCode = Ticket::generateTicketCode();

            $ticket = Ticket::create([
                'ticket_code' => $ticketCode,
                'ticket_type' => $ticketType,
                'requester_id' => $user->id,
                'branch_id' => $validated['branch_id'],
                'category_id' => $validated['category_id'],
                'status' => 'submitted',
                'priority' => $validated['priority'],
                'subject' => $validated['subject'],
                'description' => $validated['description'],
            ]);

            // Create initial ticket message
            $initialMessage = TicketMessage::create([
                'ticket_id' => $ticket->id,
                'sender_id' => $user->id,
                'visibility' => 'requester_visible',
                'message_type' => 'text',
                'body' => $validated['description'],
            ]);

            // Handle multi-file attachments (Private disk)
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $yearMonth = date('Y/m');
                    $storedName = (string) Str::uuid() . '.' . $file->getClientOriginalExtension();
                    $storagePath = $file->storeAs("attachments/{$yearMonth}", $storedName, 'attachments');

                    TicketAttachment::create([
                        'ticket_id' => $ticket->id,
                        'message_id' => $initialMessage->id,
                        'uploaded_by_id' => $user->id,
                        'original_filename' => $file->getClientOriginalName(),
                        'storage_path' => $storagePath,
                        'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                        'file_size_bytes' => $file->getSize(),
                    ]);
                }
            }

            // Initialize Approval Chain if service request requires it
            $approvalService->createApprovalChain($ticket);

            // Record audit log
            TicketAuditLog::create([
                'ticket_id' => $ticket->id,
                'actor_id' => $user->id,
                'action' => 'TICKET_CREATED',
                'old_value' => null,
                'new_value' => $ticket->fresh()->status,
                'metadata' => [
                    'ticket_code' => $ticket->ticket_code,
                    'ticket_type' => $ticket->ticket_type,
                    'priority' => $ticket->priority,
                    'category_id' => $ticket->category_id,
                ],
                'created_at' => now(),
            ]);

            return $ticket;
        });

        $statusMsg = $ticket->isPendingApproval()
            ? 'Service request submitted! Pending department approval.'
            : 'Ticket created successfully!';

        return response()->json([
            'status' => 'success',
            'message' => $statusMsg,
            'ticket_id' => $ticket->id,
            'ticket_code' => $ticket->ticket_code,
            'ticket_type' => $ticket->ticket_type,
            'ticket_status' => $ticket->status,
        ], 201);
    }

    /**
     * Approve a pending approval step via TMA.
     */
    public function approveStep(Request $request, \App\Models\TicketApproval $approval, \App\Services\TicketApprovalService $approvalService): JsonResponse
    {
        $comment = $request->input('comments');
        $success = $approvalService->approve($approval, Auth::user(), $comment);

        if (!$success) {
            return response()->json(['error' => 'Unauthorized or invalid approval step.'], 403);
        }

        return response()->json([
            'status' => 'success',
            'message' => "{$approval->step_name} approved successfully!",
        ]);
    }

    /**
     * Reject a pending approval step via TMA.
     */
    public function rejectStep(Request $request, \App\Models\TicketApproval $approval, \App\Services\TicketApprovalService $approvalService): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|min:3',
        ]);

        $success = $approvalService->reject($approval, Auth::user(), $validated['reason']);

        if (!$success) {
            return response()->json(['error' => 'Unauthorized or invalid approval step.'], 403);
        }

        return response()->json([
            'status' => 'success',
            'message' => "{$approval->step_name} rejected.",
        ]);
    }

    /**
     * Send a reply message from the employee.
     */
    public function addMessage(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('addMessage', $ticket);

        $validated = $request->validate([
            'body' => 'required_without:attachments|nullable|string',
            'attachments.*' => 'nullable|file|max:20480|mimes:jpeg,jpg,png,webp,pdf',
        ]);

        $user = Auth::user();
        Cache::put("ticket_presence_{$ticket->id}_{$user->id}", now()->timestamp, 8);

        $message = DB::transaction(function () use ($validated, $user, $ticket, $request) {
            $msg = TicketMessage::create([
                'ticket_id' => $ticket->id,
                'sender_id' => $user->id,
                'visibility' => 'requester_visible',
                'message_type' => $request->hasFile('attachments') && empty($validated['body']) ? 'attachment' : 'text',
                'body' => $validated['body'] ?? null,
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

            // If ticket was in 'clarifying' state, return it to 'submitted' since requester replied
            if ($ticket->isClarifying()) {
                $ticket->update(['status' => 'submitted']);

                TicketAuditLog::create([
                    'ticket_id' => $ticket->id,
                    'actor_id' => $user->id,
                    'action' => 'CLARIFICATION_REPLIED',
                    'old_value' => 'clarifying',
                    'new_value' => 'submitted',
                    'metadata' => ['note' => 'Requester provided requested clarification.'],
                    'created_at' => now(),
                ]);
            }

            return $msg;
        });

        return response()->json([
            'status' => 'success',
            'message_id' => $message->id,
        ]);
    }

    /**
     * Action: Employee clicks [Yes, Solved].
     */
    public function confirmResolution(Ticket $ticket): JsonResponse
    {
        $this->authorize('resolveConfirm', $ticket);

        $user = Auth::user();

        DB::transaction(function () use ($ticket, $user) {
            $ticket->update([
                'status' => 'resolved_confirmed',
                'closed_at' => now(),
            ]);

            TicketMessage::create([
                'ticket_id' => $ticket->id,
                'sender_id' => $user->id,
                'visibility' => 'system_event',
                'message_type' => 'status_change',
                'body' => '✔ Requester confirmed the issue is resolved. Ticket closed.',
            ]);

            TicketAuditLog::create([
                'ticket_id' => $ticket->id,
                'actor_id' => $user->id,
                'action' => 'RESOLVED_CONFIRMED',
                'old_value' => 'pending_confirmation',
                'new_value' => 'resolved_confirmed',
                'metadata' => ['closed_at' => now()->toIso8601String()],
                'created_at' => now(),
            ]);
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Thank you! The ticket is marked as resolved.',
        ]);
    }

    /**
     * Action: Employee clicks [No, Still Broken].
     */
    public function reopenTicket(Request $request, Ticket $ticket, TelegramNotificationService $telegram): JsonResponse
    {
        $this->authorize('resolveConfirm', $ticket);

        $validated = $request->validate([
            'reason' => 'required|string|min:3',
        ]);

        $user = Auth::user();

        DB::transaction(function () use ($ticket, $user, $validated) {
            $ticket->update([
                'status' => 'reopened',
            ]);

            TicketMessage::create([
                'ticket_id' => $ticket->id,
                'sender_id' => $user->id,
                'visibility' => 'requester_visible',
                'message_type' => 'text',
                'body' => "⚠️ REOPENED BY REQUESTER: " . $validated['reason'],
            ]);

            TicketAuditLog::create([
                'ticket_id' => $ticket->id,
                'actor_id' => $user->id,
                'action' => 'REOPENED',
                'old_value' => 'pending_confirmation',
                'new_value' => 'reopened',
                'metadata' => ['reason' => $validated['reason']],
                'created_at' => now(),
            ]);
        });

        // Trigger alert to technician if assigned
        if ($ticket->technician) {
            $telegram->sendReopenAlert($ticket->technician, $ticket, $validated['reason']);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Ticket reopened and returned to technician queue.',
        ]);
    }

    /**
     * Download or stream authorized private file attachment.
     */
    public function downloadAttachment(Ticket $ticket, TicketAttachment $attachment, Request $request): StreamedResponse
    {
        $this->authorize('downloadAttachment', [$ticket, $attachment]);

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
     * Get branches and hierarchical categories for the ticket creation form.
     */
    public function getFormData(): JsonResponse
    {
        $branches = Branch::where('is_active', true)->select('id', 'name', 'code')->get();

        $categories = TicketCategory::whereNull('parent_id')
            ->where('is_active', true)
            ->with(['children' => function ($query) {
                $query->where('is_active', true)->select('id', 'parent_id', 'name', 'type', 'requires_approval', 'default_priority');
            }])
            ->select('id', 'name', 'type', 'requires_approval', 'default_priority')
            ->get();

        $user = Auth::user();
        $isHod = $user ? $user->hasRole(['hod', 'system_admin']) : false;
        $pendingApprovalsCount = 0;
        if ($isHod) {
            $pendingApprovalsCount = \App\Models\TicketApproval::where('status', 'pending')->count();
        }

        return response()->json([
            'branches' => $branches,
            'categories' => $categories,
            'user' => [
                'branch_id' => $user->employee->branch_id ?? null,
                'is_hod' => $isHod,
                'pending_approvals_count' => $pendingApprovalsCount,
            ],
        ]);
    }
}
