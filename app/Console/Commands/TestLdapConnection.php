<?php

namespace App\Console\Commands;

use App\Services\LdapAuthService;
use Illuminate\Console\Command;

class TestLdapConnection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ldap:test {username? : Optional username to search in Active Directory} {password? : Optional password to test authentication}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Active Directory (LDAP) connectivity, service account bind, and user authentication';

    /**
     * Execute the console command.
     */
    public function handle(LdapAuthService $ldapAuth): int
    {
        $this->info('--- Active Directory (LDAP) Diagnostic Test ---');
        $this->line('LDAP Enabled in config: ' . ($ldapAuth->isEnabled() ? '<fg=green>YES</>' : '<fg=yellow>NO (Check LDAP_ENABLED in .env)</>'));
        $this->line('Target URL: ' . config('services.ldap.url'));
        $this->line('Domain: ' . config('services.ldap.domain'));
        $this->line('Search Base: ' . config('services.ldap.search_base'));
        $this->line('Service Username: ' . config('services.ldap.username'));
        $this->newLine();

        $testUser = $this->argument('username');
        $testPass = $this->argument('password');

        $this->info('Testing connection & service account bind...');
        $res = $ldapAuth->testConnection($testUser, $testPass);

        if (!$res['extension_loaded']) {
            $this->error('❌ PHP LDAP extension is NOT installed or enabled on this system.');
            $this->line('Run: sudo apt-get install -y php8.5-ldap && sudo systemctl restart php8.5-fpm');
            return 1;
        }

        if (!$res['connected']) {
            $this->error('❌ Connection Failed: ' . $res['message']);
            return 1;
        }
        $this->info('  [OK] TCP / SSL Handshake with AD server established.');

        if (!$res['service_bind']) {
            $this->error('❌ Service Account Bind Failed: ' . $res['message']);
            return 1;
        }
        $this->info('  [OK] Service Account bound successfully to Active Directory.');

        if ($testUser) {
            $this->newLine();
            $this->info("Searching for test user: {$testUser}...");
            if (!$res['user_found']) {
                $this->warn("⚠️ User '{$testUser}' was not found in {$res['search_base']}.");
            } else {
                $u = $res['user_found'];
                $this->info('  [OK] User Found in AD:');
                $this->line("       • DN: {$u['dn']}");
                $this->line("       • sAMAccountName: {$u['sAMAccountName']}");
                $this->line("       • Display Name: {$u['displayName']}");
                $this->line("       • Email: {$u['mail']}");

                if ($testPass !== null) {
                    $this->newLine();
                    $this->info("Authenticating user '{$testUser}' with password...");
                    if ($res['user_authenticated']) {
                        $this->info('  [OK] ✅ Credentials VALID! Authentication against Active Directory succeeded.');
                    } else {
                        $this->error('  [FAIL] ❌ Invalid password or user account cannot bind in AD.');
                    }
                }
            }
        }

        $this->newLine();
        $this->info('🎉 LDAP connection test passed successfully!');
        return 0;
    }
}
