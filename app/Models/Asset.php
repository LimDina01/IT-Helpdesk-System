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
        'eod_date',
        'eol_date',
        'vendor_supplier',
        'notes',
    ];

    protected $casts = [
        'specs' => 'array',
        'purchase_date' => 'date',
        'warranty_expiry' => 'date',
        'eod_date' => 'date',
        'eol_date' => 'date',
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

    public function isPastEol(): bool
    {
        return $this->eol_date !== null && $this->eol_date->isPast();
    }

    public function isPastEod(): bool
    {
        return $this->eod_date !== null && $this->eod_date->isPast();
    }

    public function isWarrantyExpired(): bool
    {
        return $this->warranty_expiry !== null && $this->warranty_expiry->isPast();
    }

    public function isWarrantyActive(): bool
    {
        return $this->warranty_expiry !== null && ! $this->warranty_expiry->isPast();
    }

    /**
     * Get lifecycle condition assessment (Warranty vs EOD vs EOL).
     *
     * @return array{state: string, label: string, class: string, icon: string, description: string}
     */
    public function getLifecycleStatusAttribute(): array
    {
        if ($this->status === 'decommissioned') {
            return [
                'state' => 'decommissioned',
                'label' => 'Decommissioned',
                'class' => 'bg-slate-100 text-slate-600 border-slate-300',
                'icon' => '⚪',
                'description' => 'Permanently retired from active inventory.',
            ];
        }

        if ($this->isPastEol()) {
            return [
                'state' => 'eol',
                'label' => 'End of Life (EOL)',
                'class' => 'bg-rose-100 text-rose-800 border-rose-300',
                'icon' => '🛑',
                'description' => 'Hardware has reached End of Life (obsolete). Plan complete decommissioning.',
            ];
        }

        if ($this->isPastEod()) {
            return [
                'state' => 'past_eod',
                'label' => 'Past EOD (Due for Review/Fix)',
                'class' => 'bg-amber-100 text-amber-900 border-amber-300',
                'icon' => '⚠️',
                'description' => 'Fully depreciated & recommended duty threshold reached. Check performance, overhaul, or budget replacement.',
            ];
        }

        if ($this->isWarrantyExpired()) {
            return [
                'state' => 'out_of_warranty',
                'label' => 'Out of Warranty',
                'class' => 'bg-slate-100 text-slate-700 border-slate-300',
                'icon' => '🛡️',
                'description' => 'Supplier warranty ended, but still within normal useful operating lifecycle.',
            ];
        }

        if ($this->isWarrantyActive()) {
            $daysLeft = now()->diffInDays($this->warranty_expiry);

            return [
                'state' => 'under_warranty',
                'label' => "Under Warranty ({$daysLeft}d left)",
                'class' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                'icon' => '🟢',
                'description' => 'Covered under free supplier/manufacturer warranty.',
            ];
        }

        return [
            'state' => 'active',
            'label' => 'Standard Lifecycle',
            'class' => 'bg-blue-50 text-blue-700 border-blue-200',
            'icon' => '✨',
            'description' => 'Active hardware in regular operating rotation.',
        ];
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
