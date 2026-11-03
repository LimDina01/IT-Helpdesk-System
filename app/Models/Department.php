<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'parent_id',
        'head_user_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Department::class, 'parent_id');
    }

    public function head(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_user_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(TicketCategory::class, 'it_department_id');
    }

    public function isItDepartment(): bool
    {
        return $this->code === 'IT' || ($this->parent && $this->parent->code === 'IT') || str_starts_with($this->code, 'IT-');
    }

    public function isSubUnit(): bool
    {
        return !empty($this->parent_id);
    }

    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeItUnits($query)
    {
        return $query->where(function ($q) {
            $q->whereHas('parent', fn($p) => $p->where('code', 'IT'))
              ->orWhere('code', 'LIKE', 'IT-%');
        });
    }
}
