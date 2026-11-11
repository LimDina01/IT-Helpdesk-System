<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asset extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_tag',
        'name',
        'category_id',
        'ownership_type',
        'branch_id',
        'department_id',
        'location_details',
        'assigned_user_id',
        'brand',
        'model',
        'serial_number',
        'status',
        'specs',
        'purchase_date',
        'purchase_cost',
        'warranty_expiry',
        'vendor_supplier',
        'notes',
    ];

    protected $casts = [
        'specs' => 'array',
        'purchase_date' => 'date',
        'warranty_expiry' => 'date',
        'purchase_cost' => 'decimal:2',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'category_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function components(): HasMany
    {
        return $this->hasMany(AssetComponent::class, 'asset_id');
    }

    public function tickets(): BelongsToMany
    {
        return $this->belongsToMany(Ticket::class, 'ticket_assets')
            ->withPivot(['attached_by_user_id', 'notes'])
            ->withTimestamps()
            ->orderByDesc('tickets.created_at');
    }

    public function ticketParts(): HasMany
    {
        return $this->hasMany(TicketAssetPart::class, 'asset_id')->orderByDesc('created_at');
    }

    public function isShared(): bool
    {
        return $this->ownership_type === 'shared' || empty($this->assigned_user_id);
    }

    public function getStatusBadgeAttribute(): array
    {
        return match ($this->status) {
            'in_use' => ['label' => 'In Use / Active', 'class' => 'bg-emerald-50 text-emerald-700 border-emerald-200'],
            'spare_stock' => ['label' => 'Spare in Stock', 'class' => 'bg-blue-50 text-blue-700 border-blue-200'],
            'under_repair' => ['label' => 'Under Repair / RMA', 'class' => 'bg-amber-50 text-amber-700 border-amber-200'],
            'decommissioned' => ['label' => 'Decommissioned / Retired', 'class' => 'bg-slate-100 text-slate-600 border-slate-300'],
            default => ['label' => ucfirst(str_replace('_', ' ', $this->status)), 'class' => 'bg-slate-100 text-slate-700 border-slate-200'],
        };
    }
}
