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

        $deptId = $ticket->requester->employee?->department_id;
        $hasDeptHod = $deptId && $deptId != 2 && User::whereHas('roles', fn($q) => $q->where('name', 'hod'))
            ->whereHas('employee', fn($q) => $q->where('department_id', $deptId)->where('employee_code', '!=', 'EMP-1007'))
            ->exists();

        if ($hasDeptHod) {
            // Normal 2-Stage Approval: Step 1 is Department HOD
            $step1 = TicketApproval::create([
                'ticket_id' => $ticket->id,
                'step_number' => 1,
                'step_name' => 'Department HOD Approval',
                'role_name' => 'department_hod',
                'department_id' => $deptId,
                'status' => 'pending',
            ]);

            $step2 = TicketApproval::create([
                'ticket_id' => $ticket->id,
                'step_number' => 2,
                'step_name' => 'IT Manager Approval',
                'role_name' => 'it_manager',
                'department_id' => null,
                'status' => 'pending',
            ]);

            // Notify Step 1 approvers (Department HOD)
            $this->notifyStepApprover($step1, $ticket);
        } else {
            // Requester department has NO designated HOD -> Auto-pass Step 1 and route directly to IT HOD (Step 2)
            $step1 = TicketApproval::create([
                'ticket_id' => $ticket->id,
                'step_number' => 1,
                'step_name' => 'Department HOD Approval',
                'role_name' => 'department_hod',
                'department_id' => $deptId,
                'status' => 'approved',
                'comments' => 'Department has no designated HOD — automatically routed to IT Review.',
                'actioned_at' => now(),
            ]);

            $step2 = TicketApproval::create([
                'ticket_id' => $ticket->id,
                'step_number' => 2,
                'step_name' => 'IT Manager Approval',
                'role_name' => 'it_manager',
                'department_id' => null,
                'status' => 'pending',
            ]);

            TicketMessage::create([
                'ticket_id' => $ticket->id,
                'sender_id' => $ticket->requester_id,
                'visibility' => 'system_event',
                'message_type' => 'status_change',
                'body' => 'ℹ️ Requester department has no designated HOD. Request routed directly to IT Manager for approval.',
            ]);

            // Notify Step 2 (IT HOD) immediately!
            $this->notifyStepApprover($step2, $ticket);
        }

        // Audit Log
        TicketAuditLog::create([
            'ticket_id' => $ticket->id,
            'actor_id' => $ticket->requester_id,
            'action' => 'APPROVAL_CHAIN_INITIALIZED',
            'new_value' => 'pending_approval',
            'metadata' => [
                'steps' => ['Department HOD Approval', 'IT Manager Approval'],
                'has_dept_hod' => $hasDeptHod,
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

        return true;
    }

    /**
     * Check if a given user is authorized to approve this step.
     */
    public function canUserApprove(User $user, TicketApproval $approval): bool
    {
        if ($approval->status !== 'pending') {
            return false;
        }

        $ticket = $approval->ticket;

        // Sequential check: Previous steps must all be approved first!
        $priorPending = $ticket->approvals()
            ->where('step_number', '<', $approval->step_number)
            ->where('status', '!=', 'approved')
            ->exists();

        if ($priorPending) {
            return false;
        }

        if ($approval->approver_id && $approval->approver_id === $user->id) {
            return true;
        }

        $userDeptCode = $user->employee?->department?->code;
        $userEmpCode = $user->employee?->employee_code;
        $userDeptId = $user->employee?->department_id;
        $isItHod = ($userEmpCode === 'EMP-1007')
            || ($userDeptCode === 'IT')
            || ($userDeptId === 2)
            || ($user->hasRole('system_admin') && ($userDeptId === 2 || $userDeptCode === 'IT'));

        // ========================================================
        // Process 1: Department HOD Approval (Strictly Step 1)
        // ========================================================
        if ($approval->role_name === 'department_hod' || $approval->step_number === 1) {
            // IT HOD (EMP-1007) MUST NOT approve Step 1! IT HOD is strictly Step 2.
            if ($isItHod) {
                return false;
            }

            $targetDeptId = $approval->department_id ?? $ticket->requester->employee?->department_id;

            // Direct match ONLY: User MUST be the designated HOD of the requester's department
            if ($userDeptId && $targetDeptId && $userDeptId === $targetDeptId && $user->hasRole('hod')) {
                return true;
            }

            // Strictly NO approval across different departments
            return false;
        }

        // ========================================================
        // Process 2: IT Department HOD Approval (Strictly Step 2)
        // ========================================================
        if ($approval->role_name === 'it_manager' || $approval->step_number === 2) {
            // Non-IT Department HOD (EMP-1008) MUST NOT approve Step 2!
            return $isItHod && $user->hasRole(['hod', 'system_admin']);
        }

        return false;
    }

    /**
     * Get tickets currently requiring approval from this specific user.
     */
    public function getPendingApprovalTicketsForUser(User $user): \Illuminate\Support\Collection
    {
        $isHod = $user->hasRole(['hod', 'system_admin']);
        if (!$isHod) {
            return collect();
        }

        $candidates = Ticket::where('status', 'pending_approval')
            ->with(['branch', 'category.parent', 'technician', 'requester.employee.department', 'approvals'])
            ->orderByDesc('created_at')
            ->get();

        return $candidates->filter(function (Ticket $ticket) use ($user) {
            $currentApproval = $ticket->currentApproval();
            if (!$currentApproval) {
                return false;
            }
            return $this->canUserApprove($user, $currentApproval);
        })->values();
    }

    /**
     * Get tickets where this user actioned (approved or rejected) an approval step.
     */
    public function getApprovedTicketsForUser(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return Ticket::whereHas('approvals', function ($q) use ($user) {
            $q->where('approver_id', $user->id)
              ->whereIn('status', ['approved', 'rejected']);
        })
        ->with(['branch', 'category.parent', 'technician', 'requester.employee.department', 'approvals', 'latestRequesterMessage.sender'])
        ->orderByDesc('updated_at')
        ->get();
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

        $ticket->update([
            'status' => 'rejected',
            'closed_at' => now(),
            'cancellation_reason' => $reason,
        ]);

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
        if ($step->role_name === 'department_hod') {
            $deptId = $step->department_id ?? $ticket->requester->employee?->department_id;
            $approvers = User::whereHas('roles', fn($q) => $q->where('name', 'hod'))
                ->whereHas('employee', fn($q) => $q->where('department_id', $deptId))
                ->whereNotNull('telegram_id')
                ->get();
        } else {
            // Process 2: IT Department HOD
            $approvers = User::whereHas('roles', fn($q) => $q->whereIn('name', ['hod', 'system_admin']))
                ->whereHas('employee', fn($q) => $q->where('department_id', 2)->orWhere('employee_code', 'EMP-1007'))
                ->whereNotNull('telegram_id')
                ->get();
        }

        foreach ($approvers as $approver) {
            $this->telegramNotification->sendApprovalRequestAlert($approver, $ticket, $step);
        }
    }
}
