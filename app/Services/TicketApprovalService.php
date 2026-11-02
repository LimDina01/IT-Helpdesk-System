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
        $branchId = $ticket->branch_id ?? $ticket->requester->employee?->branch_id;
        $deptCode = $ticket->requester->employee?->department?->code;
        $isItDept = ($deptCode === 'IT' || $deptCode === 'IT-OPR' || $deptCode === 'IT-PRD' || $deptId == 2 || ($deptCode && str_starts_with($deptCode, 'IT')));

        // Check if requester's branch & department has a designated HOD (excluding IT Department / IT HOD)
        $hasDeptHod = !$isItDept && $deptId && $branchId && User::whereHas('roles', fn($q) => $q->where('name', 'hod'))
            ->whereNotNull('employee_id')
            ->where('email', '!=', 'admin@cbvh.com.kh')
            ->whereHas('employee', fn($q) => $q->where('department_id', $deptId)->where('branch_id', $branchId))
            ->exists();

        $itDivision = $ticket->it_division ?? $ticket->category?->it_division ?? 'opr';
        $itStepName = $itDivision === 'prd' ? 'IT Production Manager Approval' : 'IT Operations Manager Approval';

        if ($hasDeptHod) {
            // Normal 2-Stage Approval: Step 1 is Branch Department HOD
            $step1 = TicketApproval::create([
                'ticket_id' => $ticket->id,
                'step_number' => 1,
                'step_name' => 'Department HOD Approval',
                'role_name' => 'department_hod',
                'department_id' => $deptId,
                'branch_id' => $branchId,
                'status' => 'pending',
            ]);

            $step2 = TicketApproval::create([
                'ticket_id' => $ticket->id,
                'step_number' => 2,
                'step_name' => 'IT Manager Approval',
                'role_name' => 'it_manager',
                'department_id' => null,
                'branch_id' => null, // IT HOD is Centralized at HQ for all bank branches
                'status' => 'pending',
            ]);

            // Notify Step 1 approvers (Branch Department HOD)
            $this->notifyStepApprover($step1, $ticket);
        } else {
            // If IT department or branch department has NO designated HOD -> Auto-pass Step 1 and route directly to Central IT HOD (Step 2)
            $step1Comments = $isItDept
                ? 'IT Department requester — automatically routed directly to Central IT HOD Approval.'
                : 'Branch department has no designated HOD — automatically routed to Central IT Review.';

            $systemMsgBody = $isItDept
                ? 'ℹ️ IT Department requester: automatically routed directly to Central IT Manager for approval.'
                : 'ℹ️ Requester branch department has no designated HOD. Request routed directly to Central IT Manager for approval.';

            $step1 = TicketApproval::create([
                'ticket_id' => $ticket->id,
                'step_number' => 1,
                'step_name' => 'Department HOD Approval',
                'role_name' => 'department_hod',
                'department_id' => $deptId,
                'branch_id' => $branchId,
                'status' => 'approved',
                'comments' => $step1Comments,
                'actioned_at' => now(),
            ]);

            $step2 = TicketApproval::create([
                'ticket_id' => $ticket->id,
                'step_number' => 2,
                'step_name' => 'IT Manager Approval',
                'role_name' => 'it_manager',
                'department_id' => null,
                'branch_id' => null,
                'status' => 'pending',
            ]);

            TicketMessage::create([
                'ticket_id' => $ticket->id,
                'sender_id' => $ticket->requester_id,
                'visibility' => 'system_event',
                'message_type' => 'status_change',
                'body' => $systemMsgBody,
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
        $userBranchId = $user->employee?->branch_id;
        $isItHod = $user->isItHod();

        // ========================================================
        // Process 1: Department HOD Approval (Strictly Step 1)
        // Branch + Department specific
        // ========================================================
        if ($approval->role_name === 'department_hod' || ($approval->step_number === 1 && empty($approval->role_name))) {
            $targetDeptId = $approval->department_id ?? $ticket->requester->employee?->department_id;
            $targetDeptCode = $ticket->requester->employee?->department?->code;

            // IT HOD cannot approve Step 1 for business departments (Operations, Finance, Sales, HR, etc.)
            if ($isItHod && $targetDeptCode !== 'IT' && $targetDeptId != 2) {
                return false;
            }

            $targetBranchId = $approval->branch_id ?? $ticket->branch_id ?? $ticket->requester->employee?->branch_id;

            // Direct match ONLY: User MUST be the designated HOD of the requester's department AND branch
            if ($userDeptId && $targetDeptId && $userDeptId === $targetDeptId
                && $userBranchId && $targetBranchId && $userBranchId === $targetBranchId
                && $user->hasRole('hod')) {
                return true;
            }

            // Strictly NO approval across different departments or branches
            return false;
        }

        // ========================================================
        // Process 2: Intermediate Approvers (Slot 2 - Pluggable)
        // Branch Manager / Division Head / Operations Head
        // ========================================================
        if (in_array($approval->role_name, ['branch_manager', 'division_head', 'intermediate_approver'])) {
            $targetBranchId = $approval->branch_id ?? $ticket->branch_id ?? $ticket->requester->employee?->branch_id;
            if ($userBranchId && $targetBranchId && $userBranchId === $targetBranchId && $user->hasRole(['hod', 'system_admin'])) {
                return true;
            }
            if ($user->hasRole('system_admin')) {
                return true;
            }
            return false;
        }

        // ========================================================
        // Process 3: IT Department HOD Approval (Final Slot / IT Manager)
        // Centralized at HQ for all bank branches, split by IT Division (OPR vs PRD)
        // ========================================================
        if ($approval->role_name === 'it_manager' || $approval->step_number === 2 || $approval->step_number === 3) {
            if ($user->hasRole('system_admin')) {
                return true;
            }

            if (!$isItHod) {
                return false;
            }

            $itDivision = $ticket->it_division ?? $ticket->category?->it_division ?? 'opr';
            $userDeptCode = $user->employee?->department?->code;

            // If user belongs specifically to IT-PRD, they approve PRD tickets
            if ($itDivision === 'prd' && $userDeptCode === 'IT-PRD') {
                return true;
            }

            // If user belongs specifically to IT-OPR, they approve OPR tickets
            if ($itDivision === 'opr' && $userDeptCode === 'IT-OPR') {
                return true;
            }

            // Central IT HOD / System Admin / or general IT without sub-division
            if (empty($userDeptCode) || in_array($userDeptCode, ['IT', 'IT-OPR', 'IT-PRD']) || $userDeptId == 2) {
                return true;
            }

            return false;
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
            $branchId = $step->branch_id ?? $ticket->branch_id ?? $ticket->requester->employee?->branch_id;
            $approvers = User::whereHas('roles', fn($q) => $q->where('name', 'hod'))
                ->whereHas('employee', fn($q) => $q->where('department_id', $deptId)->where('branch_id', $branchId))
                ->whereNotNull('telegram_id')
                ->get();
        } elseif (in_array($step->role_name, ['branch_manager', 'division_head', 'intermediate_approver'])) {
            $branchId = $step->branch_id ?? $ticket->branch_id ?? $ticket->requester->employee?->branch_id;
            $approvers = User::whereHas('roles', fn($q) => $q->whereIn('name', ['hod', 'system_admin']))
                ->whereHas('employee', fn($q) => $q->where('branch_id', $branchId))
                ->whereNotNull('telegram_id')
                ->get();
        } else {
            // Process: Central IT Department HOD (HQ) & IT HODs (filtered by OPR / PRD)
            $itDivision = $ticket->it_division ?? $ticket->category?->it_division ?? 'opr';
            $targetDeptCode = $itDivision === 'prd' ? 'IT-PRD' : 'IT-OPR';

            $approvers = User::whereHas('roles', fn($q) => $q->whereIn('name', ['hod', 'system_admin']))
                ->where(function ($q) use ($targetDeptCode) {
                    $q->whereDoesntHave('employee')
                      ->orWhereHas('employee.department', fn($d) => $d->whereIn('code', [$targetDeptCode, 'IT', 'IT-OPR', 'IT-PRD'])->orWhere('id', 2));
                })
                ->whereNotNull('telegram_id')
                ->get();
        }

        foreach ($approvers as $approver) {
            $this->telegramNotification->sendApprovalRequestAlert($approver, $ticket, $step);
        }
    }
}
