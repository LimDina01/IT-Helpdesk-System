<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketApproval;
use App\Models\TicketAuditLog;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class TicketApprovalService
{
    public function __construct(
        protected TelegramNotificationService $telegramNotification
    ) {}

    /**
     * Create approval chain if ticket category or type requires it.
     */
    public function createApprovalChain(Ticket $ticket): bool
    {
        $category = $ticket->category;
        $requiresApproval = ($category && $category->requires_approval) || $ticket->ticket_type === 'service_request';

        if (!$requiresApproval) {
            $ticket->update(['status' => 'submitted']);
            return false;
        }

        // Set ticket to pending_approval
        $ticket->update(['status' => 'pending_approval']);

        // Step 1: Department HOD Approval
        $step1 = TicketApproval::create([
            'ticket_id' => $ticket->id,
            'step_number' => 1,
            'step_name' => 'Department HOD Approval',
            'role_name' => 'department_hod',
            'department_id' => $ticket->requester->employee?->department_id,
            'status' => 'pending',
        ]);

        // Step 2: IT Manager Approval
        $step2 = TicketApproval::create([
            'ticket_id' => $ticket->id,
            'step_number' => 2,
            'step_name' => 'IT Manager Approval',
            'role_name' => 'it_manager',
            'department_id' => null,
            'status' => 'pending',
        ]);

        // Audit Log
        TicketAuditLog::create([
            'ticket_id' => $ticket->id,
            'actor_id' => $ticket->requester_id,
            'action' => 'APPROVAL_CHAIN_INITIALIZED',
            'new_value' => 'pending_approval',
            'metadata' => [
                'steps' => ['Department HOD Approval', 'IT Manager Approval'],
            ],
        ]);

        // System message on ticket
        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'sender_id' => $ticket->requester_id,
            'visibility' => 'system_event',
            'message_type' => 'status_change',
            'body' => '⏳ Service request submitted. Multi-stage approval required before IT fulfillment.',
        ]);

        // Notify Step 1 approvers (Department HOD / System Admin)
        $this->notifyStepApprover($step1, $ticket);

        return true;
    }

    /**
     * Check if a given user is authorized to approve this step.
     */
    public function canUserApprove(User $user, TicketApproval $approval): bool
    {
        if ($user->hasRole('system_admin')) {
            return true;
        }

        if ($approval->approver_id && $approval->approver_id === $user->id) {
            return true;
        }

        if ($approval->role_name === 'department_hod') {
            // Can be approved by any user holding the 'hod' role
            return $user->hasRole('hod');
        }

        if ($approval->role_name === 'it_manager') {
            // Can be approved by HOD / System Admin
            return $user->hasRole('hod') || $user->hasRole('system_admin');
        }

        return false;
    }

    /**
     * Approve a step in the chain.
     */
    public function approve(TicketApproval $approval, User $approver, ?string $comment = null): bool
    {
        if (!$this->canUserApprove($approver, $approval)) {
            return false;
        }

        $ticket = $approval->ticket;

        $approval->update([
            'status' => 'approved',
            'approver_id' => $approver->id,
            'comments' => $comment,
            'actioned_at' => now(),
        ]);

        TicketAuditLog::create([
            'ticket_id' => $ticket->id,
            'actor_id' => $approver->id,
            'action' => 'STEP_APPROVED',
            'old_value' => 'pending',
            'new_value' => 'approved',
            'metadata' => [
                'step_name' => $approval->step_name,
                'comment' => $comment,
            ],
        ]);

        // Post event to ticket chat
        $commentNote = $comment ? " (Note: {$comment})" : '';
        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'sender_id' => $approver->id,
            'visibility' => 'system_event',
            'message_type' => 'status_change',
            'body' => "✅ {$approval->step_name} approved by {$approver->name}{$commentNote}.",
        ]);

        // Check for next pending step
        $nextStep = $ticket->approvals()
            ->where('status', 'pending')
            ->where('step_number', '>', $approval->step_number)
            ->orderBy('step_number')
            ->first();

        if ($nextStep) {
            // Notify next step approver
            $this->notifyStepApprover($nextStep, $ticket);

            // Inform requester of progress
            $this->telegramNotification->sendApprovalStatusUpdate(
                $ticket->requester,
                $ticket,
                "Approved at {$approval->step_name}. Now awaiting {$nextStep->step_name}."
            );
        } else {
            // All steps approved! Move to submitted for IT triage
            $ticket->update(['status' => 'submitted']);

            TicketAuditLog::create([
                'ticket_id' => $ticket->id,
                'actor_id' => $approver->id,
                'action' => 'STATUS_CHANGED',
                'old_value' => 'pending_approval',
                'new_value' => 'submitted',
                'metadata' => ['reason' => 'All approval steps successfully passed'],
            ]);

            TicketMessage::create([
                'ticket_id' => $ticket->id,
                'sender_id' => $approver->id,
                'visibility' => 'system_event',
                'message_type' => 'status_change',
                'body' => '🎉 All approvals completed! Request has been queued for IT Help Desk and Technician assignment.',
            ]);

            // Notify requester
            $this->telegramNotification->sendApprovalStatusUpdate(
                $ticket->requester,
                $ticket,
                'All approvals granted! Your request has been queued with IT for fulfillment.'
            );
        }

        return true;
    }

    /**
     * Reject a step, cancelling the request.
     */
    public function reject(TicketApproval $approval, User $approver, string $reason): bool
    {
        if (!$this->canUserApprove($approver, $approval)) {
            return false;
        }

        $ticket = $approval->ticket;

        $approval->update([
            'status' => 'rejected',
            'approver_id' => $approver->id,
            'comments' => $reason,
            'actioned_at' => now(),
        ]);

        $ticket->update(['status' => 'rejected']);

        TicketAuditLog::create([
            'ticket_id' => $ticket->id,
            'actor_id' => $approver->id,
            'action' => 'STEP_REJECTED',
            'old_value' => 'pending_approval',
            'new_value' => 'rejected',
            'metadata' => [
                'step_name' => $approval->step_name,
                'reason' => $reason,
            ],
        ]);

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'sender_id' => $approver->id,
            'visibility' => 'system_event',
            'message_type' => 'status_change',
            'body' => "❌ {$approval->step_name} rejected by {$approver->name}. Reason: {$reason}",
        ]);

        // Notify requester
        $this->telegramNotification->sendApprovalStatusUpdate(
            $ticket->requester,
            $ticket,
            "Request Rejected at {$approval->step_name}",
            $reason
        );

        return true;
    }

    /**
     * Helper to find and alert designated approvers via Telegram.
     */
    protected function notifyStepApprover(TicketApproval $step, Ticket $ticket): void
    {
        // Find HOD / Admin approvers
        $approvers = User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['hod', 'system_admin']);
        })->whereNotNull('telegram_id')->get();

        foreach ($approvers as $approver) {
            $this->telegramNotification->sendApprovalRequestAlert($approver, $ticket, $step);
        }
    }
}
