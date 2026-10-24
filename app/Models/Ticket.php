<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_code',
        'ticket_type',
        'requester_id',
        'branch_id',
        'category_id',
        'current_helpdesk_id',
        'current_technician_id',
        'status',
        'priority',
        'subject',
        'description',
        'assigned_at',
        'acknowledged_at',
        'resolved_at',
        'closed_at',
        'auto_close_deadline',
        'blocked_reason',
        'cancellation_reason',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'auto_close_deadline' => 'datetime',
    ];

    public static function generateTicketCode(): string
    {
        $prefix = 'IT-' . date('Ym') . '-';
        $latest = self::where('ticket_code', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('ticket_code');

        if (!$latest) {
            return $prefix . '0001';
        }

        $number = (int) substr($latest, strlen($prefix)) + 1;
        return $prefix . str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }

    // Relationships
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'category_id');
    }

    public function helpdesk(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_helpdesk_id');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_technician_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TicketAssignment::class)->orderByDesc('id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class)->orderBy('created_at');
    }

    public function latestRequesterMessage(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(TicketMessage::class)
            ->whereIn('visibility', ['requester_visible', 'system_event'])
            ->latestOfMany();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(TicketAuditLog::class)->orderByDesc('id');
    }

    public function notificationLogs(): HasMany
    {
        return $this->hasMany(NotificationLog::class)->orderByDesc('id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(TicketApproval::class)->orderBy('step_number');
    }

    public function currentApproval(): ?TicketApproval
    {
        return $this->approvals()->where('status', 'pending')->first();
    }

    // Lifecycle Status Helpers
    public function isServiceRequest(): bool
    {
        return $this->ticket_type === 'service_request';
    }

    public function isIncident(): bool
    {
        return $this->ticket_type === 'incident';
    }

    public function isPendingApproval(): bool
    {
        return $this->status === 'pending_approval';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isSubmitted(): bool
    {
        return $this->status === 'submitted';
    }

    public function isClarifying(): bool
    {
        return $this->status === 'clarifying';
    }

    public function isAssigned(): bool
    {
        return $this->status === 'assigned';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isBlocked(): bool
    {
        return $this->status === 'blocked';
    }

    public function isPendingConfirmation(): bool
    {
        return $this->status === 'pending_confirmation';
    }

    public function isResolved(): bool
    {
        return in_array($this->status, ['resolved_confirmed', 'resolved_auto_closed']);
    }

    public function isClosed(): bool
    {
        return in_array($this->status, ['resolved_confirmed', 'resolved_auto_closed', 'cancelled', 'rejected']);
    }

    public function isReopened(): bool
    {
        return $this->status === 'reopened';
    }
}
