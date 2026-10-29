<?php

namespace App\Http\Controllers\Tma;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketAuditLog;
use App\Models\TicketCategory;
use App\Models\TicketMessage;
use App\Models\TicketApproval;
use App\Models\User;
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
     * Get tickets for the authenticated employee across all categories.
     */
    public function index(Request $request, \App\Services\TicketApprovalService $approvalService): JsonResponse
    {
        $user = Auth::user();
        $isStaff = $user->isStaff();
        $isHod = $user->hasRole(['hod', 'system_admin']);

        // TMA is the self-service requester portal for employees.
        // "My Requests" strictly shows tickets personally requested by the current authenticated employee.
        $applyVisibilityScope = function ($query) use ($user) {
            $query->where('requester_id', $user->id);
        };

        // 1. Open Tickets (Pending Approval, Submitted, Assigned, Clarifying, Reopened)
        $openStatuses = ['pending_approval', 'submitted', 'assigned', 'clarifying', 'reopened'];

        // 2. In Progress Tickets (In Progress, Blocked, Pending Confirmation)
        $inProgressStatuses = ['in_progress', 'blocked', 'pending_confirmation'];

        // 3. Resolved / Completed Tickets
        $resolvedStatuses = ['resolved_confirmed', 'resolved_auto_closed', 'cancelled', 'rejected'];

        // All active tickets (Open + In Progress + Pending Approval), strictly ordered by date (newest first)
        $activeQuery = Ticket::query();
        $applyVisibilityScope($activeQuery);
        $activeTickets = $activeQuery
            ->whereIn('status', array_merge(['pending_approval', 'submitted', 'assigned', 'clarifying', 'reopened'], $inProgressStatuses))
            ->orderByDesc('created_at')
            ->with(['branch', 'category.parent', 'technician', 'requester.employee.department', 'approvals', 'latestRequesterMessage.sender'])
            ->get()
            ->map(fn(Ticket $t) => $this->formatTicketSummary($t, $user));

        // History tickets (Resolved / Completed / Rejected), strictly ordered by date
        $historyQuery = Ticket::query();
        if ($isHod) {
            // For HODs: include tickets personally requested OR tickets they actioned as approver that are resolved/cancelled/rejected
            $historyQuery->where(function ($q) use ($user) {
                $q->where('requester_id', $user->id)
                  ->orWhereHas('approvals', function ($aq) use ($user) {
                      $aq->where('approver_id', $user->id);
                  });
            });
        } else {
            $applyVisibilityScope($historyQuery);
        }

        $historyTickets = $historyQuery
            ->whereIn('status', $resolvedStatuses)
            ->orderByRaw('COALESCE(closed_at, updated_at) DESC')
            ->with(['branch', 'category.parent', 'technician', 'requester.employee.department', 'approvals', 'latestRequesterMessage.sender'])
            ->get()
            ->map(fn(Ticket $t) => $this->formatTicketSummary($t, $user));

        // Counts matching user specification
        $openCount = $activeTickets->filter(fn($t) => in_array($t['status'], $openStatuses))->count();
        $inProgressCount = $activeTickets->filter(fn($t) => in_array($t['status'], $inProgressStatuses))->count();
        $resolvedCount = $historyTickets->count();

        $tab = $request->input('tab', 'active');
        $tabTickets = match($tab) {
            'history' => $historyTickets,
            default => $activeTickets,
        };

        $approvalService = app(\App\Services\TicketApprovalService::class);
        $pendingApprovals = $approvalService->getPendingApprovalTicketsForUser($user)
            ->map(fn(Ticket $t) => $this->formatTicketSummary($t, $user));
        $approvedByMe = $approvalService->getApprovedTicketsForUser($user)
            ->map(fn(Ticket $t) => $this->formatTicketSummary($t, $user));

        return response()->json([
            'tab' => $tab,
            'tickets' => $tabTickets,
            'active' => $activeTickets,
            'history' => $historyTickets,
            'approvals' => $pendingApprovals,
            'approved_by_me' => $approvedByMe,
            'pending_approvals_count' => $pendingApprovals->count(),
            'active_count' => $activeTickets->count(),
            'history_count' => $historyTickets->count(),
            'open_count' => $openCount,
            'in_progress_count' => $inProgressCount,
            'resolved_count' => $resolvedCount,
            'all_count' => $openCount + $inProgressCount + $resolvedCount,
            'is_hod' => $isHod,
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    /**
     * Map a ticket model into a lean summary object for the TMA list.
     */
    private function formatTicketSummary(Ticket $t, User $user): array
    {
        $currentApproval = $t->currentApproval();
        $latestMsg = $t->latestRequesterMessage;
        $isStaff = $latestMsg && $latestMsg->sender ? $latestMsg->sender->isStaff() : false;
        $myApproval = $t->approvals->where('approver_id', $user->id)->first();
        $canApprove = false;
        if ($currentApproval) {
            $canApprove = app(\App\Services\TicketApprovalService::class)->canUserApprove($user, $currentApproval);
        }

        return [
            'id' => $t->id,
            'ticket_code' => $t->ticket_code,
            'ticket_type' => $t->ticket_type ?? 'incident',
            'subject' => $t->subject,
            'status' => $t->status,
            'priority' => $t->priority,
            'branch_name' => $t->branch->name ?? 'N/A',
            'category_name' => $t->category->name ?? 'N/A',
            'category_name_km' => $t->category->name_km ?? null,
            'parent_category_name' => $t->category->parent->name ?? null,
            'parent_category_name_km' => $t->category->parent->name_km ?? null,
            'technician_name' => $t->technician->name ?? 'Awaiting Assignment',
            'requester_name' => $t->requester->name ?? 'N/A',
            'requester_dept' => $t->requester->employee?->department?->name ?? 'N/A',
            'created_at' => $t->created_at->format('d-M-Y H:i'),
            'created_at_human' => $t->created_at->diffForHumans(),
            'resolved_at' => $t->resolved_at?->format('d-M-Y H:i'),
            'auto_close_deadline' => $t->auto_close_deadline?->format('d-M-Y H:i'),
            'is_pending_confirmation' => $t->isPendingConfirmation(),
            'is_pending_approval' => $t->isPendingApproval(),
            'is_clarifying' => $t->isClarifying(),
            'can_approve' => $canApprove,
            'approved_by_me' => (bool) ($myApproval && $myApproval->status === 'approved'),
            'rejected_by_me' => (bool) ($myApproval && $myApproval->status === 'rejected'),
            'my_approval_status' => $myApproval?->status,
            'my_approval_step' => $myApproval?->step_name,
            'my_approval_comments' => $myApproval?->comments,
            'cancellation_reason' => $t->cancellation_reason,
            'approvals' => $t->approvals->map(fn($a) => [
                'id' => $a->id,
                'step_number' => $a->step_number,
                'step_name' => $a->step_name,
                'status' => $a->status,
                'comments' => $a->comments,
                'approver_name' => $a->approver?->name,
                'actioned_at' => $a->actioned_at?->format('d-M-Y H:i'),
            ])->values()->all(),
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
                    'created_at' => $m->created_at->format('d-M-Y H:i'),
                    'created_at_human' => $m->created_at->diffForHumans(),
                    'full_timestamp' => $m->created_at->format('d-M-Y H:i'),
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
                'category_name_km' => $ticket->category->name_km ?? null,
                'parent_category_name' => $ticket->category->parent->name ?? null,
                'parent_category_name_km' => $ticket->category->parent->name_km ?? null,
                'category_type' => $ticket->category->type ?? 'incident_issue',
                'technician_name' => $ticket->technician->name ?? 'Not Assigned Yet',
                'helpdesk_name' => $ticket->helpdesk->name ?? null,
                'requester_name' => $ticket->requester->name,
                'requester_dept' => $ticket->requester->employee?->department?->name ?? 'N/A',
                'created_at' => $ticket->created_at->format('d-M-Y H:i'),
                'created_at_human' => $ticket->created_at->diffForHumans(),
                'full_created_timestamp' => $ticket->created_at->format('d-M-Y H:i'),
                'resolved_at' => $ticket->resolved_at?->format('d-M-Y H:i'),
                'closed_at' => $ticket->closed_at?->format('d-M-Y H:i'),
                'auto_close_deadline' => $ticket->auto_close_deadline?->format('d-M-Y H:i'),
                'blocked_reason' => $ticket->blocked_reason,
                'cancellation_reason' => $ticket->cancellation_reason,
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
        $user = Auth::user();
        $userBranchId = $user?->employee?->branch_id ?? $user?->branch_id ?? Branch::first()?->id ?? 1;

        $validated = $request->validate([
            'ticket_type' => 'nullable|in:incident,service_request',
            'branch_id' => 'nullable|exists:branches,id',
            'category_id' => 'required|exists:ticket_categories,id',
            'priority' => 'required|in:low,medium,high,critical',
            'subject' => 'required|string|max:255',
            'description' => 'required|string|min:5',
            'attachments.*' => 'nullable|file|max:25600|mimes:jpeg,jpg,png,webp,gif,bmp,heic,heif,pdf,doc,docx,xls,xlsx,txt,zip',
        ]);

        $category = TicketCategory::findOrFail($validated['category_id']);
        $ticketType = $validated['ticket_type'] ?? ($category->type === 'service_request' ? 'service_request' : 'incident');
        $effectiveBranchId = $validated['branch_id'] ?? $userBranchId;

        $ticket = DB::transaction(function () use ($validated, $user, $request, $ticketType, $category, $approvalService, $effectiveBranchId) {
            $ticketCode = Ticket::generateTicketCode();

            $ticket = Ticket::create([
                'ticket_code' => $ticketCode,
                'ticket_type' => $ticketType,
                'requester_id' => $user->id,
                'branch_id' => $effectiveBranchId,
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
            'attachments.*' => 'nullable|file|max:25600|mimes:jpeg,jpg,png,webp,gif,bmp,heic,heif,pdf,doc,docx,xls,xlsx,txt,zip',
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
    public function downloadAttachment(Ticket $ticket, TicketAttachment $attachment, Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorize('downloadAttachment', [$ticket, $attachment]);

        if (!Storage::disk('attachments')->exists($attachment->storage_path)) {
            abort(404, 'File not found on storage disk.');
        }

        // Ultra-fast lightweight thumbnail for in-chat previews (loads in <15ms instead of fetching multi-megabyte originals)
        if ($request->has('preview') && str_starts_with($attachment->mime_type, 'image/')) {
            return $this->getThumbnailResponse($attachment);
        }

        if (str_starts_with($attachment->mime_type, 'image/')) {
            return Storage::disk('attachments')->response(
                $attachment->storage_path,
                $attachment->original_filename,
                [
                    'Content-Type' => $attachment->mime_type,
                    'Cache-Control' => 'private, max-age=86400, stale-while-revalidate=604800',
                ]
            );
        }

        return Storage::disk('attachments')->download(
            $attachment->storage_path,
            $attachment->original_filename
        );
    }

    /**
     * Return a fast resized thumbnail response for image attachments.
     */
    protected function getThumbnailResponse(TicketAttachment $attachment): \Symfony\Component\HttpFoundation\Response
    {
        $storage = Storage::disk('attachments');
        $fullPath = $storage->path($attachment->storage_path);

        if (!file_exists($fullPath)) {
            abort(404, 'File not found on storage disk.');
        }

        $thumbDir = storage_path('app/attachments/thumbs');
        if (!is_dir($thumbDir)) {
            @mkdir($thumbDir, 0755, true);
        }

        $thumbFileName = 'thumb_' . $attachment->id . '_' . md5($attachment->storage_path) . '.jpg';
        $thumbFilePath = $thumbDir . DIRECTORY_SEPARATOR . $thumbFileName;

        if (!file_exists($thumbFilePath)) {
            $info = @getimagesize($fullPath);
            if ($info && in_array($info['mime'], ['image/jpeg', 'image/png', 'image/webp', 'image/gif'])) {
                $origW = $info[0];
                $origH = $info[1];
                $maxDim = 320;
                $scale = min($maxDim / max($origW, 1), $maxDim / max($origH, 1), 1.0);
                $newW = max(1, (int)($origW * $scale));
                $newH = max(1, (int)($origH * $scale));

                $srcImg = match($info['mime']) {
                    'image/jpeg' => @imagecreatefromjpeg($fullPath),
                    'image/png' => @imagecreatefrompng($fullPath),
                    'image/webp' => @imagecreatefromwebp($fullPath),
                    'image/gif' => @imagecreatefromgif($fullPath),
                    default => null,
                };

                if ($srcImg) {
                    $dstImg = imagecreatetruecolor($newW, $newH);
                    imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
                    imagejpeg($dstImg, $thumbFilePath, 78);
                    imagedestroy($srcImg);
                    imagedestroy($dstImg);
                }
            }
        }

        if (file_exists($thumbFilePath)) {
            return response()->file($thumbFilePath, [
                'Content-Type' => 'image/jpeg',
                'Cache-Control' => 'public, max-age=604800, immutable',
            ]);
        }

        return $storage->response($attachment->storage_path, $attachment->original_filename, [
            'Content-Type' => $attachment->mime_type,
            'Cache-Control' => 'private, max-age=86400',
        ]);
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
                $query->where('is_active', true)->select('id', 'parent_id', 'name', 'name_km', 'description', 'description_km', 'icon', 'icon_url', 'icon_color', 'type', 'requires_approval', 'default_priority');
            }])
            ->select('id', 'name', 'name_km', 'description', 'description_km', 'icon', 'icon_url', 'icon_color', 'type', 'requires_approval', 'default_priority')
            ->get();

        $user = Auth::user();
        $emp = $user?->employee?->load(['branch', 'department']);
        $userBranchId = $emp?->branch_id ?? $user?->branch_id ?? $branches->first()?->id;
        $userBranchName = $emp?->branch?->name ?? $branches->first()?->name ?? 'Main Branch';
        $userDeptName = $emp?->department?->name ?? 'General Department';

        $isHod = $user ? $user->hasRole(['hod', 'system_admin']) : false;
        $userDeptCode = $user?->employee?->department?->code;
        $userEmpCode = $user?->employee?->employee_code;
        $isItHod = $isHod && ($userDeptCode === 'IT' || $userEmpCode === 'EMP-1007');

        $pendingApprovalsCount = 0;
        if ($isHod) {
            $pendingApprovalsCount = app(\App\Services\TicketApprovalService::class)->getPendingApprovalTicketsForUser($user)->count();
        }

        return response()->json([
            'branches' => $branches,
            'categories' => $categories,
            'user' => [
                'branch_id' => $userBranchId,
                'branch_name' => $userBranchName,
                'department_id' => $emp?->department_id,
                'department_name' => $userDeptName,
                'department_code' => $userDeptCode,
                'name' => $user?->name,
                'is_hod' => $isHod,
                'is_it_hod' => $isItHod,
                'pending_approvals_count' => $pendingApprovalsCount,
            ],
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }
}
