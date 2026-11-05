<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepartmentApprover extends Model
{
    use HasFactory;

    protected $fillable = [
        'department_id',
        'branch_id',
        'user_id',
        'level',
        'title',
        'is_active',
    ];

    protected $casts = [
        'level' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saved(function (DepartmentApprover $card) {
            if ($card->is_active && $card->user_id) {
                $user = User::find($card->user_id);
                if ($user) {
                    $hodRole = Role::firstOrCreate(['name' => 'hod'], ['label' => 'Head of Department']);
                    $user->roles()->syncWithoutDetaching([$hodRole->id]);
                }
            }
        });
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeLevel(Builder $query, int $level): Builder
    {
        return $query->where('level', $level);
    }

    public function getLevelNameAttribute(): string
    {
        return match ($this->level) {
            1 => 'Level 1: Team Leader',
            2 => 'Level 2: Supervisor',
            3 => 'Level 3: Department Manager',
            4 => 'Level 4: Executive / Director',
            default => "Level {$this->level}",
        };
    }
}

