<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'sender_id',
        'visibility',
        'message_type',
        'body',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class, 'message_id');
    }

    public function isInternalNote(): bool
    {
        return $this->visibility === 'internal_staff_only';
    }

    public function isRequesterVisible(): bool
    {
        return in_array($this->visibility, ['requester_visible', 'system_event']);
    }

    public function scopeForRequester($query)
    {
        return $query->whereIn('visibility', ['requester_visible', 'system_event']);
    }

    public function scopeInternalNotes($query)
    {
        return $query->where('visibility', 'internal_staff_only');
    }
}
