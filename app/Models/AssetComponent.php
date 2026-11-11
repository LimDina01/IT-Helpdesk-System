<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetComponent extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_id',
        'branch_id',
        'component_type',
        'brand_model',
        'serial_number',
        'capacity_or_specs',
        'status',
        'installed_at',
        'notes',
    ];

    protected $casts = [
        'installed_at' => 'datetime',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function getStatusBadgeAttribute(): array
    {
        return match ($this->status) {
            'installed' => ['label' => 'Installed in PC', 'class' => 'bg-emerald-50 text-emerald-700 border-emerald-200'],
            'in_stock' => ['label' => 'In Spare Stock', 'class' => 'bg-blue-50 text-blue-700 border-blue-200'],
            'faulty' => ['label' => 'Faulty / Replaced', 'class' => 'bg-rose-50 text-rose-700 border-rose-200'],
            'disposed' => ['label' => 'Disposed / Scrapped', 'class' => 'bg-slate-100 text-slate-500 border-slate-300'],
            default => ['label' => ucfirst($this->status), 'class' => 'bg-slate-100 text-slate-700 border-slate-200'],
        };
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->component_type) {
            'ram' => 'RAM Memory',
            'ssd' => 'SSD Storage',
            'hdd' => 'Hard Disk Drive',
            'psu' => 'Power Supply Unit',
            'gpu' => 'Graphics Card',
            'cable' => 'Cable / Adapter',
            default => ucfirst($this->component_type),
        };
    }
}
