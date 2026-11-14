<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SoftwareLicense extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'vendor_publisher',
        'license_type',
        'license_key',
        'seats_total',
        'purchase_date',
        'expiry_date',
        'purchase_cost',
        'billing_cycle',
        'status',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'expiry_date' => 'date',
            'purchase_cost' => 'decimal:2',
            'seats_total' => 'integer',
        ];
    }

    /**
     * Seat assignments (assigned users or installed hardware assets).
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(SoftwareLicenseAssignment::class, 'software_license_id');
    }

    /**
     * Count of currently allocated seats.
     */
    public function getSeatsAssignedAttribute(): int
    {
        return $this->assignments->count();
    }

    /**
     * Available unallocated seats.
     */
    public function getSeatsAvailableAttribute(): int
    {
        return max(0, $this->seats_total - $this->seats_assigned);
    }

    /**
     * Check if subscription renewal is within 30 days.
     */
    public function isExpiringSoon(): bool
    {
        if (! $this->expiry_date) {
            return false;
        }

        return $this->expiry_date->isFuture() && (int) ceil($this->expiry_date->diffInDays(Carbon::now())) <= 30;
    }

    /**
     * Check if subscription or license is already expired.
     */
    public function isExpired(): bool
    {
        if (! $this->expiry_date) {
            return false;
        }

        return $this->expiry_date->isPast();
    }

    /**
     * Get visual badge configuration for status.
     *
     * @return array{label: string, class: string}
     */
    public function getStatusBadgeAttribute(): array
    {
        if ($this->status === 'cancelled') {
            return [
                'label' => 'Cancelled',
                'class' => 'bg-slate-100 text-slate-600 border-slate-300',
            ];
        }

        if ($this->isExpired()) {
            return [
                'label' => 'Expired',
                'class' => 'bg-rose-100 text-rose-800 border-rose-300',
            ];
        }

        if ($this->isExpiringSoon()) {
            return [
                'label' => 'Expiring Soon ('.(int) ceil(Carbon::now()->diffInDays($this->expiry_date)).'d)',
                'class' => 'bg-amber-100 text-amber-800 border-amber-300',
            ];
        }

        return [
            'label' => 'Active',
            'class' => 'bg-emerald-100 text-emerald-800 border-emerald-300',
        ];
    }
}
