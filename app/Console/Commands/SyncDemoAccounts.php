<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

class SyncDemoAccounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auth:sync-demo-accounts {--password=Password123! : The standard demo password} {--cache : Also warm production caches}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Guarantee all 4 sample 1-Click demo accounts exist, are active, and have correct roles without affecting custom admin users';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('======================================================');
        $this->info('  CBVH Help Desk: Demo & Staff Accounts Synchronizer  ');
        $this->info('======================================================');

        // 1. Ensure basic roles exist
        $rolesData = [
            'system_admin' => 'System Administrator',
            'hod' => 'Head of Department',
            'technician' => 'IT Technician',
            'helpdesk' => 'Help Desk Specialist',
            'requester' => 'Standard Employee / Requester',
        ];

        $roles = [];
        foreach ($rolesData as $slug => $label) {
            $roles[$slug] = Role::firstOrCreate(['name' => $slug], ['label' => $label]);
        }

        $password = $this->option('password') ?: 'Password123!';
        $hashed = Hash::make($password);

        // 2. Define the standard 4 sample accounts
        $sampleAccounts = [
            [
                'email' => 'admin@cbvh.com.kh',
                'name' => 'Vannak Ouk (HOD / Admin)',
                'roles' => ['system_admin', 'hod'],
            ],
            [
                'email' => 'helpdesk@cbvh.com.kh',
                'name' => 'Help Desk Agent',
                'roles' => ['helpdesk'],
            ],
            [
                'email' => 'tech1@cbvh.com.kh',
                'name' => 'Dara Sam (Field Technician)',
                'roles' => ['technician'],
            ],
            [
                'email' => 'tech2@cbvh.com.kh',
                'name' => 'Kosal Meng (Senior IT & Triage)',
                'roles' => ['technician', 'helpdesk'],
            ],
        ];

        foreach ($sampleAccounts as $sample) {
            $user = User::where('email', $sample['email'])->first();

            if (!$user) {
                $user = User::create([
                    'name' => $sample['name'],
                    'email' => $sample['email'],
                    'password' => $hashed,
                    'is_active' => true,
                ]);
                $this->info(" [+] Created demo account: {$sample['email']} ({$sample['name']})");
            } else {
                $user->update([
                    'password' => $hashed,
                    'is_active' => true,
                ]);
                $this->line(" [✓] Updated demo account: {$sample['email']} (Password reset to: {$password})");
            }

            // Sync designated roles
            $roleIds = array_map(fn($r) => $roles[$r]->id, $sample['roles']);
            $user->roles()->syncWithoutDetaching($roleIds);
        }

        // 3. Clear cache
        $this->newLine();
        $this->info('Clearing stale application caches...');
        Artisan::call('optimize:clear');
        $this->line(' [✓] Stale config, route, and view caches cleared.');

        if ($this->option('cache')) {
            $this->info('Warming up production caches...');
            Artisan::call('config:cache');
            Artisan::call('route:cache');
            Artisan::call('view:cache');
            $this->line(' [✓] Production caches warmed.');
        }

        // 4. Print clean staff user roster
        $this->newLine();
        $this->info('--- Current Staff & Admin Roster in Database ---');

        $staffUsers = User::with('roles')
            ->whereHas('roles', fn($q) => $q->whereIn('name', ['system_admin', 'hod', 'technician', 'helpdesk']))
            ->get();

        $tableData = $staffUsers->map(function ($u) {
            return [
                'ID' => $u->id,
                'Name' => $u->name,
                'Email' => $u->email,
                'Status' => $u->is_active ? 'ACTIVE' : 'DEACTIVATED',
                'Roles' => $u->roles->pluck('name')->join(', '),
            ];
        })->toArray();

        $this->table(['ID', 'Name', 'Email', 'Status', 'Roles'], $tableData);

        $this->info('Done! Sample 1-Click logins are fully operational.');
        return 0;
    }
}
