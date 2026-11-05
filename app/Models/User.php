<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'telegram_id',
        'telegram_username',
        'name',
        'email',
        'password',
        'is_active',
        'can_access_portal',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'can_access_portal' => 'boolean',
            'telegram_id' => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function hasRole(string|array $roles): bool
    {
        if (is_string($roles)) {
            $roles = [$roles];
        }

        return $this->roles->pluck('name')->intersect($roles)->isNotEmpty();
    }

    public function hasAnyRole(array $roles): bool
    {
        return $this->hasRole($roles);
    }

    public function isStaff(): bool
    {
        return $this->hasAnyRole(['helpdesk', 'technician', 'hod', 'system_admin']);
    }

    public function isItStaff(): bool
    {
        if ($this->hasAnyRole(['technician', 'helpdesk', 'system_admin'])) {
            return true;
        }

        if ($this->hasRole('hod')) {
            // Standalone accounts with NO employee profile are IT Staff Portal HODs
            if (empty($this->employee_id)) {
                return true;
            }
        }

        return false;
    }

    public function isItHod(): bool
    {
        if ($this->hasRole('system_admin')) {
            return true;
        }

        if ($this->hasRole('hod')) {
            if ($this->isItStaff()) {
                return true;
            }

            $deptCode = $this->employee?->department?->code;
            $deptId = $this->employee?->department_id;
            if ($deptCode === 'IT' || $deptCode === 'IT-OPR' || $deptCode === 'IT-PRD' || $deptId == 2 || ($deptCode && str_starts_with($deptCode, 'IT'))) {
                return true;
            }
        }

        return false;
    }

    public function isBusinessHod(): bool
    {
        return $this->hasRole('hod') && !$this->isItHod();
    }

    public function isApprover(): bool
    {
        if ($this->hasRole(['hod', 'system_admin'])) {
            return true;
        }

        if ($this->relationLoaded('departmentApproverRoles')
            ? $this->departmentApproverRoles->where('is_active', true)->isNotEmpty()
            : $this->departmentApproverRoles()->where('is_active', true)->exists()) {
            return true;
        }

        if ($this->relationLoaded('subordinates')
            ? $this->subordinates->where('is_active', true)->isNotEmpty()
            : $this->subordinates()->where('is_active', true)->exists()) {
            return true;
        }

        if ($this->relationLoaded('headedDepartments')
            ? $this->headedDepartments->isNotEmpty()
            : $this->headedDepartments()->exists()) {
            return true;
        }

        if ($this->relationLoaded('managedBranches')
            ? $this->managedBranches->isNotEmpty()
            : $this->managedBranches()->exists()) {
            return true;
        }

        return false;
    }

    public function getApproverBadge(): ?array
    {
        if ($this->isItHod()) {
            return ['label' => 'IT HOD', 'class' => 'bg-indigo-100 text-indigo-800 border-indigo-200'];
        }

        $card = $this->relationLoaded('departmentApproverRoles')
            ? $this->departmentApproverRoles->where('is_active', true)->sortBy('level')->first()
            : $this->departmentApproverRoles()->where('is_active', true)->orderBy('level')->first();

        if ($card) {
            $defaultTitle = match ($card->level) {
                1 => 'Team Leader',
                2 => 'Supervisor',
                3 => 'Dept Manager',
                4 => 'General Manager',
                5 => 'Director',
                default => "Level {$card->level} Approver",
            };

            $rawTitle = trim($card->title ?? '');
            $displayTitle = !empty($rawTitle) ? $rawTitle : $defaultTitle;

            // Avoid double prefix like "Level 2: Level 2: Supervisor"
            if (preg_match('/^level\s*\d+\s*:\s*/i', $displayTitle)) {
                $label = $displayTitle;
            } else {
                $label = "Level {$card->level}: {$displayTitle}";
            }

            $cls = match (true) {
                $card->level === 1 => 'bg-blue-100 text-blue-800 border-blue-200',
                $card->level === 2 => 'bg-purple-100 text-purple-800 border-purple-200',
                $card->level === 3 => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                $card->level === 4 => 'bg-amber-100 text-amber-800 border-amber-200',
                $card->level >= 5 => 'bg-rose-100 text-rose-800 border-rose-200',
                default => 'bg-slate-100 text-slate-800 border-slate-200',
            };

            $branchName = $card->relationLoaded('branch') ? $card->branch?->name : ($card->branch_id ? \App\Models\Branch::find($card->branch_id)?->name : null);
            $deptName = $card->relationLoaded('department') ? $card->department?->name : ($card->department_id ? \App\Models\Department::find($card->department_id)?->name : null);

            return [
                'label' => $label,
                'class' => $cls,
                'title' => $displayTitle,
                'level' => $card->level,
                'branch' => $branchName ?? 'HQ / All Branches',
                'department' => $deptName,
            ];
        }

        $hasSubordinates = $this->relationLoaded('subordinates')
            ? $this->subordinates->where('is_active', true)->isNotEmpty()
            : $this->subordinates()->where('is_active', true)->exists();

        if ($hasSubordinates) {
            return ['label' => 'Team Leader', 'class' => 'bg-blue-100 text-blue-800 border-blue-200'];
        }

        if ($this->hasRole('hod') && $this->isItStaff()) {
            return ['label' => 'IT HOD', 'class' => 'bg-indigo-100 text-indigo-800 border-indigo-200'];
        }

        return null;
    }

    public function canAccessPortal(): bool
    {
        return (bool) $this->can_access_portal;
    }

    public function createdTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'requester_id');
    }

    public function assignedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'current_technician_id');
    }

    public function helpdeskTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'current_helpdesk_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class, 'sender_id');
    }

    public function notificationLogs(): HasMany
    {
        return $this->hasMany(NotificationLog::class, 'recipient_user_id');
    }

    public function departmentApproverRoles(): HasMany
    {
        return $this->hasMany(DepartmentApprover::class);
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(Employee::class, 'line_manager_user_id');
    }

    public function headedDepartments(): HasMany
    {
        return $this->hasMany(Department::class, 'head_user_id');
    }

    public function managedBranches(): HasMany
    {
        return $this->hasMany(Branch::class, 'manager_user_id');
    }
}
