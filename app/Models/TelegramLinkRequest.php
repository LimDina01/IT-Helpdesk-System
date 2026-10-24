<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramLinkRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'telegram_id',
        'telegram_username',
        'first_name',
        'last_name',
        'employee_code',
        'status',
        'reviewed_by_id',
        'reviewed_at',
        'rejection_reason',
    ];

    protected $casts = [
        'telegram_id' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_code', 'employee_code');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
