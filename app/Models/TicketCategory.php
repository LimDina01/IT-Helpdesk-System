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
        'target_acknowledge_minutes',
        'target_resolve_hours',
        'is_active',
    ];

    protected $casts = [
        'requires_approval' => 'boolean',
        'target_acknowledge_minutes' => 'integer',
        'target_resolve_hours' => 'integer',
        'is_active' => 'boolean',
    ];

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
