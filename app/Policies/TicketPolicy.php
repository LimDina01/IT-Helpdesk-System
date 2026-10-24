<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;

class TicketPolicy
{
    /**
     * Determine whether the user can view the ticket.
     */
    public function view(User $user, Ticket $ticket): bool
    {
        if ($user->isStaff()) {
            return true;
        }

        return $ticket->requester_id === $user->id;
    }

    /**
     * Determine whether the user can add a message or reply to the ticket.
     */
    public function addMessage(User $user, Ticket $ticket): bool
    {
        if ($ticket->isClosed() && !$ticket->isPendingConfirmation()) {
            return false;
        }

        if ($user->isStaff()) {
            return true;
        }

        return $ticket->requester_id === $user->id;
    }

    /**
     * Determine whether the user can confirm or reopen the ticket.
     */
    public function resolveConfirm(User $user, Ticket $ticket): bool
    {
        return $ticket->requester_id === $user->id && $ticket->isPendingConfirmation();
    }

    /**
     * Determine whether the user can download a specific attachment.
     */
    public function downloadAttachment(User $user, Ticket $ticket, TicketAttachment $attachment): bool
    {
        if (!$this->view($user, $ticket)) {
            return false;
        }

        // Internal note attachments are staff-only
        if ($attachment->message && $attachment->message->visibility === 'internal_staff_only') {
            return $user->isStaff();
        }

        return true;
    }
}
