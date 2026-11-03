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
        $department = $ticket->requester->employee?->department;
        $deptCode = $department?->code;
        $isItDept = ($department && $department->isItDepartment()) || ($deptCode === 'IT' || str_starts_with($deptCode ?? '', 'IT') || $deptId == 2);

        // Check if requester's branch & department has a designated HOD, Line Manager, or Approvers (excluding IT Department / IT HOD)
        $lineManagerId = $ticket->requester->employee?->line_manager_user_id;
        $hasLineManager = !empty($lineManagerId);

        $hasBranchApprover = false;
        $designatedL1ApproverId = null;

        if (!$isItDept && $department) {
            // Find Level 1 approver card for (branch_id + department_id), falling back to (null branch_id + department_id)
            $branchL1Card = null;
            if ($branchId) {
                $branchL1Card = \App\Models\DepartmentApprover::where('department_id', $deptId)
                    ->where('branch_id', $branchId)
                    ->where('is_active', true)
                    ->orderBy('level')
                    ->first();
            }

            $globalL1Card = \App\Models\DepartmentApprover::where('department_id', $deptId)
                ->whereNull('branch_id')
                ->where('is_active', true)
                ->orderBy('level')
                ->first();

            $matchedCard = $branchL1Card ?: $globalL1Card;
            if ($matchedCard) {
                $hasBranchApprover = true;
                if ($matchedCard->level === 1) {
                    $designatedL1ApproverId = $matchedCard->user_id;
                }
            }
        }

        $hasDeptHod = false;
        if (!$isItDept && $department) {
            $hasDeptHod = $hasLineManager
                || $hasBranchApprover
                || !empty($department->head_user_id);
        }

        if (!$hasDeptHod && !$isItDept && $deptId && $branchId) {
            $hasDeptHod = User::whereHas('roles', fn($q) => $q->where('name', 'hod'))
                ->whereNotNull('employee_id')
                ->where('email', '!=', 'admin@cbvh.com.kh')
                ->whereHas('employee', fn($q) => $q->where('department_id', $deptId)->where('branch_id', $branchId))
                ->exists();
        }

        $itDivision = $ticket->it_division ?? $ticket->category?->it_division ?? 'opr';
        $itStepName = $itDivision === 'prd' ? 'IT Production Manager Approval' : 'IT Operations Manager Approval';

        if ($hasDeptHod) {
            // Normal 2-Stage Approval: Step 1 is Branch Department Approver (Team Leader / Line Manager or HOD)
            $step1ApproverId = $lineManagerId ?: $designatedL1ApproverId;
            $step1Name = ($hasLineManager || $hasBranchApprover) ? 'Team Leader Approval' : 'Department HOD Approval';
            $step1 = TicketApproval::create([
                'ticket_id' => $ticket->id,
                'step_number' => 1,
                'step_name' => $step1Name,
                'role_name' => 'department_hod',
                'department_id' => $deptId,
                'branch_id' => $branchId,
                'approver_id' => $step1ApproverId,
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
        // Process 1: Department HOD / Team Leader / Supervisor Approval (Step 1 or Escalated Step)
        // Branch + Department specific
        // ========================================================
        if (in_array($approval->role_name, ['department_hod', 'department_supervisor']) || ($approval->step_number === 1 && empty($approval->role_name))) {
            $targetDeptId = $approval->department_id ?? $ticket->requester->employee?->department_id;
            $targetDept = $targetDeptId ? \App\Models\Department::find($targetDeptId) : null;
            $targetDeptCode = $targetDept?->code ?? $ticket->requester->employee?->department?->code;

            // IT HOD cannot approve Step 1 for business departments (Operations, Finance, Sales, HR, etc.)
            if ($isItHod && !in_array($targetDeptCode, ['IT', 'IT-OPR', 'IT-PRD']) && $targetDeptId != 2) {
                $isExplicitHead = $targetDept && $targetDept->head_user_id === $user->id;
                $isConfiguredApprover = $targetDeptId && \App\Models\DepartmentApprover::where('department_id', $targetDeptId)->where('user_id', $user->id)->exists();
                if (!$isExplicitHead && !$isConfiguredApprover) {
                    return false;
                }
            }

            // 1. Direct Assigned Approver Match (Option B: Line Manager or specific assigned user)
            if ($approval->approver_id && $user->id === $approval->approver_id) {
                return true;
            }

            // 2. Requester's Line Manager (Option B direct mapping)
            $lineManagerId = $ticket->requester->employee?->line_manager_user_id;
            if ($lineManagerId && $user->id === $lineManagerId) {
                return true;
            }

            // 3. Department Approver card match (Level 1 / Level 2 in department_approvers scoped to branch or HQ)
            if ($targetDeptId) {
                $targetBranchId = $approval->branch_id ?? $ticket->branch_id ?? $ticket->requester->employee?->branch_id;

                // Priority: match approver configured for THIS specific branch, fallback to global/HQ card (branch_id is null)
                $deptApprover = \App\Models\DepartmentApprover::where('department_id', $targetDeptId)
                    ->where('user_id', $user->id)
                    ->where('is_active', true)
                    ->when($targetBranchId, function ($q) use ($targetBranchId) {
                        $q->where(function ($sub) use ($targetBranchId) {
                            $sub->where('branch_id', $targetBranchId)
                                ->orWhereNull('branch_id');
                        });
                    }, function ($q) {
                        $q->whereNull('branch_id');
                    })
                    ->orderByRaw('CASE WHEN branch_id IS NOT NULL THEN 0 ELSE 1 END')
                    ->first();

                if ($deptApprover) {
                    if ($approval->role_name === 'department_supervisor') {
                        // For supervisor escalation step: user must be Level 2 or higher
                        return $deptApprover->level >= 2;
                    }
                    return true;
                }
            }

            // 4. Direct Designated Approver Match: Explicitly configured in Admin Portal (head_user_id)
            if ($targetDept && $targetDept->head_user_id) {
                return $user->id === $targetDept->head_user_id;
            }

            $targetBranchId = $approval->branch_id ?? $ticket->branch_id ?? $ticket->requester->employee?->branch_id;

            // 5. Direct match fallback: User MUST be the HOD of the requester's department AND branch
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
            if ($user->hasRole('system_admin')) {
                return true;
            }

            $targetBranchId = $approval->branch_id ?? $ticket->branch_id ?? $ticket->requester->employee?->branch_id;
            $targetBranch = $targetBranchId ? \App\Models\Branch::find($targetBranchId) : null;

            // 1. Designated Branch Manager match: Explicitly configured in Admin Portal
            if ($targetBranch && $targetBranch->manager_user_id && $user->id === $targetBranch->manager_user_id) {
                return true;
            }

            // 2. Fallback to branch HOD
            if ($userBranchId && $targetBranchId && $userBranchId === $targetBranchId && $user->hasRole(['hod', 'system_admin'])) {
                return true;
            }

            return false;
        }

        // ========================================================
        // Process 3: IT Department HOD Approval (Final Slot / IT Manager)
        // Centralized at HQ for all bank branches, split dynamically by IT Unit/Division
        // ========================================================
        if ($approval->role_name === 'it_manager' || $approval->step_number === 2 || $approval->step_number === 3) {
            if ($user->hasRole('system_admin')) {
                return true;
            }

            // 1. Resolve Target IT Unit/Sub-Department dynamically
            $itDept = $ticket->category?->itDepartment;

            if (!$itDept) {
                $itDivision = $ticket->it_division ?? $ticket->category?->it_division;
                if ($itDivision) {
                    $targetDeptCode = str_starts_with($itDivision, 'IT-') ? $itDivision : 'IT-' . strtoupper($itDivision);
                    $itDept = \App\Models\Department::where('code', $targetDeptCode)->first();
                }
            }

            // Fallback default to IT-OPR if neither is set
            if (!$itDept) {
                $itDept = \App\Models\Department::where('code', 'IT-OPR')->first();
            }

            // 2. Direct Designated Unit Head match: Explicitly configured in Admin Portal (e.g. IT-OPR, IT-PRD, IT-SOL)
            if ($itDept && $itDept->head_user_id && $user->id === $itDept->head_user_id) {
                return true;
            }

            // 3. Parent IT Department Head match: Chief Information Officer / Head of IT (can approve across IT units)
            $parentItDept = $itDept?->parent ?? \App\Models\Department::where('code', 'IT')->first();
            if ($parentItDept && $parentItDept->head_user_id && $user->id === $parentItDept->head_user_id) {
                return true;
            }

            // If user is explicitly designated as the head of another IT unit, they cannot approve this unit's tickets
            if ($itDept) {
                $isHeadOfOtherUnit = \App\Models\Department::where('head_user_id', $user->id)
                    ->where('id', '!=', $itDept->id)
                    ->where(function ($q) {
                        $q->where('code', 'LIKE', 'IT-%')
                          ->orWhereNotNull('parent_id');
                    })
                    ->exists();

                if ($isHeadOfOtherUnit) {
                    return false;
                }
            }

            // If this IT unit has an explicit designated head, only that designated head or parent IT head can approve
            if ($itDept && $itDept->head_user_id) {
                return false;
            }

            if (!$isItHod) {
                return false;
            }

            $userDeptCode = $user->employee?->department?->code;
            $userDeptId = $user->employee?->department_id;

            // 4. If user belongs specifically to this IT sub-department
            if ($itDept && $userDeptId && $userDeptId === $itDept->id) {
                return true;
            }

            // 5. Code match for division
            if ($itDept && $userDeptCode === $itDept->code) {
                return true;
            }

            // 6. Central IT HOD / System Admin / or general IT parent without sub-division
            if (empty($userDeptCode) || $userDeptCode === 'IT' || $userDeptId == 2) {
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
     * Escalate a step to a higher level (e.g. Level 1 Team Leader escalates to Level 2 Supervisor or designated person).
     */
    public function escalate(TicketApproval $approval, User $actor, ?string $comment = null, ?int $targetUserId = null): bool
    {
        if (!$this->canUserApprove($actor, $approval)) {
            return false;
        }

        $ticket = $approval->ticket;
        $department = $ticket->requester->employee?->department;

        // 1. Resolve Target Higher Approver
        $targetApprover = null;
        $stepTitle = 'Level 2: Supervisor Approval';
        $roleName = 'department_supervisor';

        if ($targetUserId) {
            $targetApprover = User::find($targetUserId);
        }

        if (!$targetApprover && $department) {
            // Find active approver at Level 2 (or higher)
            $deptApprover = $department->activeApprovers()
                ->where('level', '>=', 2)
                ->where('user_id', '!=', $actor->id)
                ->first();

            if ($deptApprover) {
                $targetApprover = $deptApprover->user;
                $stepTitle = $deptApprover->title ? "{$deptApprover->title} Approval" : 'Level 2: Supervisor Approval';
            } elseif ($department->head_user_id && $department->head_user_id !== $actor->id) {
                $targetApprover = User::find($department->head_user_id);
                $stepTitle = 'Department Head Approval';
            } elseif ($department->parent && $department->parent->head_user_id) {
                $targetApprover = User::find($department->parent->head_user_id);
                $stepTitle = "Parent Department Head ({$department->parent->name}) Approval";
            }
        }

        // 2. Mark current step as endorsed & escalated
        $endorsementComment = 'Endorsed & Escalated to superior' . ($comment ? ": {$comment}" : '');
        $approval->update([
            'status' => 'approved',
            'approver_id' => $actor->id,
            'comments' => $endorsementComment,
            'actioned_at' => now(),
        ]);

        // 3. Shift subsequent steps up by 1 (e.g. IT Manager becomes step 3)
        $subsequentSteps = $ticket->approvals()
            ->where('step_number', '>', $approval->step_number)
            ->orderBy('step_number', 'desc')
            ->get();

        foreach ($subsequentSteps as $step) {
            $step->update(['step_number' => $step->step_number + 1]);
        }

        // 4. Create the new escalated step right after current step
        $newStepNumber = $approval->step_number + 1;
        $escalatedStep = TicketApproval::create([
            'ticket_id' => $ticket->id,
            'step_number' => $newStepNumber,
            'step_name' => $stepTitle . ' (Escalated)',
            'role_name' => $roleName,
            'department_id' => $department?->id,
            'branch_id' => $approval->branch_id,
            'approver_id' => $targetApprover?->id,
            'status' => 'pending',
            'comments' => null,
        ]);

        // 5. Audit Log & Ticket Chat Message
        TicketAuditLog::create([
            'ticket_id' => $ticket->id,
            'actor_id' => $actor->id,
            'action' => 'STEP_ESCALATED',
            'old_value' => $approval->step_name,
            'new_value' => $escalatedStep->step_name,
            'metadata' => [
                'escalated_by' => $actor->name,
                'target_approver' => $targetApprover?->name,
                'comment' => $comment,
            ],
        ]);

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'sender_id' => $actor->id,
            'visibility' => 'system_event',
            'message_type' => 'status_change',
            'body' => "⬆️ {$approval->step_name} endorsed by {$actor->name} and escalated to " . ($targetApprover ? $targetApprover->name : 'Department Supervisor') . ($comment ? " (Note: {$comment})" : '') . '.',
        ]);

        // 6. Notify the escalated approver
        $this->notifyStepApprover($escalatedStep, $ticket);

        return true;
    }

    /**
     * Forward / Delegate a pending step to another approver (e.g. peer Team Leader).
     */
    public function forward(TicketApproval $approval, User $actor, User $targetUser, ?string $comment = null): bool
    {
        if (!$this->canUserApprove($actor, $approval)) {
            return false;
        }

        $ticket = $approval->ticket;

        $approval->update([
            'approver_id' => $targetUser->id,
            'comments' => "Delegated by {$actor->name}" . ($comment ? ": {$comment}" : ''),
        ]);

        TicketAuditLog::create([
            'ticket_id' => $ticket->id,
            'actor_id' => $actor->id,
            'action' => 'STEP_DELEGATED',
            'old_value' => $actor->name,
            'new_value' => $targetUser->name,
            'metadata' => ['comment' => $comment],
        ]);

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'sender_id' => $actor->id,
            'visibility' => 'system_event',
            'message_type' => 'status_change',
            'body' => "🔄 {$approval->step_name} review delegated from {$actor->name} to {$targetUser->name}" . ($comment ? " (Note: {$comment})" : '') . '.',
        ]);

        // Notify new approver
        $this->notifyStepApprover($approval, $ticket);

        return true;
    }

    /**
     * Helper to find and alert designated approvers via Telegram.
     */
    protected function notifyStepApprover(TicketApproval $step, Ticket $ticket): void
    {
        // Direct assigned approver notification
        if ($step->approver_id) {
            $assignedUser = User::find($step->approver_id);
            if ($assignedUser && $assignedUser->telegram_id) {
                $this->telegramNotification->sendApprovalRequestAlert($assignedUser, $ticket, $step);
                return;
            }
        }

        if (in_array($step->role_name, ['department_hod', 'department_supervisor'])) {
            $deptId = $step->department_id ?? $ticket->requester->employee?->department_id;
            $department = $deptId ? \App\Models\Department::with(['head', 'activeApprovers.user'])->find($deptId) : null;

            // Check department_approvers
            $level = $step->role_name === 'department_supervisor' ? 2 : 1;
            $approverUsers = $department?->activeApprovers()
                ->where('level', $level)
                ->with('user')
                ->get()
                ->pluck('user')
                ->filter(fn($u) => !empty($u->telegram_id));

            if ($approverUsers && $approverUsers->isNotEmpty()) {
                $approvers = $approverUsers;
            } elseif ($department && $department->head && $department->head->telegram_id) {
                $approvers = collect([$department->head]);
            } else {
                $branchId = $step->branch_id ?? $ticket->branch_id ?? $ticket->requester->employee?->branch_id;
                $approvers = User::whereHas('roles', fn($q) => $q->where('name', 'hod'))
                    ->whereHas('employee', fn($q) => $q->where('department_id', $deptId)->where('branch_id', $branchId))
                    ->whereNotNull('telegram_id')
                    ->get();
            }
        } elseif (in_array($step->role_name, ['branch_manager', 'division_head', 'intermediate_approver'])) {
            $branchId = $step->branch_id ?? $ticket->branch_id ?? $ticket->requester->employee?->branch_id;
            $branch = $branchId ? \App\Models\Branch::with('manager')->find($branchId) : null;

            if ($branch && $branch->manager && $branch->manager->telegram_id) {
                $approvers = collect([$branch->manager]);
            } else {
                $approvers = User::whereHas('roles', fn($q) => $q->whereIn('name', ['hod', 'system_admin']))
                    ->whereHas('employee', fn($q) => $q->where('branch_id', $branchId))
                    ->whereNotNull('telegram_id')
                    ->get();
            }
        } else {
            // Process: Central IT Department HOD (HQ) & IT HODs (filtered by dynamic IT unit: OPR / PRD / SOL / etc.)
            $itDept = $ticket->category?->itDepartment;
            if (!$itDept) {
                $itDivision = $ticket->it_division ?? $ticket->category?->it_division ?? 'opr';
                $targetDeptCode = str_starts_with($itDivision, 'IT-') ? $itDivision : 'IT-' . strtoupper($itDivision);
                $itDept = \App\Models\Department::where('code', $targetDeptCode)->with('head')->first();
            }

            if ($itDept && $itDept->head && $itDept->head->telegram_id) {
                $approvers = collect([$itDept->head]);
            } else {
                $targetDeptCode = $itDept?->code ?? 'IT-OPR';
                $approvers = User::whereHas('roles', fn($q) => $q->whereIn('name', ['hod', 'system_admin']))
                    ->where(function ($q) use ($targetDeptCode) {
                        $q->whereDoesntHave('employee')
                          ->orWhereHas('employee.department', fn($d) => $d->whereIn('code', [$targetDeptCode, 'IT', 'IT-OPR', 'IT-PRD', 'IT-SOL'])->orWhere('id', 2));
                    })
                    ->whereNotNull('telegram_id')
                    ->get();
            }
        }

        foreach ($approvers as $approver) {
            $this->telegramNotification->sendApprovalRequestAlert($approver, $ticket, $step);
        }
    }
}
