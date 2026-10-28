<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Services\LdapAuthService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MakeStaffUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:make-staff 
                            {identifier : Username or email, e.g. dina.lim or dina.lim@cbvh66.com} 
                            {role=system_admin : The staff role to assign (system_admin, hod, technician, helpdesk)}
                            {--name= : Optional full name override}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create or promote a user to a staff role (system_admin, hod, technician, helpdesk)';

    /**
     * Execute the console command.
     */
    public function handle(LdapAuthService $ldapAuth): int
    {
        $identifier = trim($this->argument('identifier'));
        $roleName = strtolower(trim($this->argument('role')));
        $nameOverride = $this->option('name');

        // 1. Verify Role Exists
        $validRoles = ['system_admin', 'hod', 'technician', 'helpdesk', 'requester'];
        if (!in_array($roleName, $validRoles, true)) {
            $this->error("Invalid role '{$roleName}'. Valid roles are: " . implode(', ', $validRoles));
            return 1;
        }

        $role = Role::where('name', $roleName)->first();
        if (!$role) {
            $this->error("Role '{$roleName}' was not found in the database.");
            return 1;
        }

        // 2. Find Existing Local User
        $user = User::where('email', $identifier)
            ->orWhere('email', 'like', "{$identifier}@%")
            ->orWhere('name', $identifier)
            ->first();

        // 3. If User Doesn't Exist, Try to Fetch Info from Active Directory
        if (!$user) {
            $this->info("User '{$identifier}' not found in local database. Checking Active Directory...");

            $adResult = $ldapAuth->testConnection($identifier);
            $fullName = $nameOverride;
            $email = null;

            if ($adResult['connected'] && $adResult['service_bind'] && !empty($adResult['user_found'])) {
                $u = $adResult['user_found'];
                $fullName = $fullName ?: ($u['displayName'] ?? $identifier);
                $email = $u['mail'] ?? null;
                $this->info("  [OK] Found user in Active Directory: {$fullName} ({$email})");
            }

            if (!$email) {
                $email = str_contains($identifier, '@') ? $identifier : "{$identifier}@cbvh66.com";
            }

            $fullName = $fullName ?: $identifier;

            $user = User::create([
                'name' => $fullName,
                'email' => strtolower($email),
                'password' => Hash::make(Str::random(32)), // Random password (authenticated via AD)
                'is_active' => true,
            ]);

            $this->info("✅ Created local user record: #{$user->id} {$user->name} ({$user->email})");
        } else {
            $this->info("Found existing user record: #{$user->id} {$user->name} ({$user->email})");
            $user->update(['is_active' => true]);
        }

        // 4. Attach Role
        if (!$user->roles->contains('id', $role->id)) {
            $user->roles()->attach($role->id);
            $this->info("✅ Assigned role '{$role->label}' ({$role->name}) to {$user->name}.");
        } else {
            $this->info("ℹ️ User {$user->name} already possesses role '{$role->name}'.");
        }

        // If system_admin, optionally ensure they also have HOD role if missing
        if ($roleName === 'system_admin') {
            $hodRole = Role::where('name', 'hod')->first();
            if ($hodRole && !$user->roles->contains('id', $hodRole->id)) {
                $user->roles()->attach($hodRole->id);
                $this->info("ℹ️ Also attached HOD role for executive queue visibility.");
            }
        }

        $this->newLine();
        $this->info("🎉 SUCCESS! You can now log into the portal at /portal/login using:");
        $this->line("   Username: {$identifier} (or {$user->email})");
        $this->line("   Password: Your Active Directory (Windows) password");
        return 0;
    }
}
