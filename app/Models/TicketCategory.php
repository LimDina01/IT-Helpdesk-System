<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'name',
        'name_km',
        'description',
        'description_km',
        'icon',
        'icon_url',
        'icon_color',
        'type',
        'requires_approval',
        'default_priority',
        'sla_critical_hours',
        'sla_high_hours',
        'sla_medium_hours',
        'sla_low_hours',
        'target_acknowledge_minutes',
        'target_resolve_hours',
        'is_active',
    ];

    protected $casts = [
        'requires_approval' => 'boolean',
        'sla_critical_hours' => 'integer',
        'sla_high_hours' => 'integer',
        'sla_medium_hours' => 'integer',
        'sla_low_hours' => 'integer',
        'target_acknowledge_minutes' => 'integer',
        'target_resolve_hours' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Get resolution SLA target hours for a given ticket priority.
     */
    public function getResolveHoursForPriority(?string $priority = null): int
    {
        $p = strtolower($priority ?? $this->default_priority ?? 'medium');
        return match($p) {
            'critical' => (int) ($this->sla_critical_hours ?? $this->target_resolve_hours ?? 4),
            'high'     => (int) ($this->sla_high_hours ?? $this->target_resolve_hours ?? 8),
            'low'      => (int) ($this->sla_low_hours ?? $this->target_resolve_hours ?? 48),
            default    => (int) ($this->sla_medium_hours ?? $this->target_resolve_hours ?? 24),
        };
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(TicketCategory::class, 'parent_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'category_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRootCategories($query)
    {
        return $query->whereNull('parent_id');
    }
}
