<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'recipient_user_id',
        'channel',
        'event_type',
        'telegram_chat_id',
        'message_payload',
        'status',
        'retry_count',
        'error_message',
        'sent_at',
    ];

    protected $casts = [
        'telegram_chat_id' => 'integer',
        'retry_count' => 'integer',
        'sent_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }
}
