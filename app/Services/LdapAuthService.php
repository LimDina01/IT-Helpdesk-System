<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class LdapAuthService
{
    protected bool $enabled;
    protected string $url;
    protected string $domain;
    protected string $searchBase;
    protected string $serviceUsername;
    protected string $servicePassword;
    protected bool $useSsl;
    protected bool $validateCert;

    public function __construct()
    {
        $this->enabled = (bool) config('services.ldap.enabled', false);
        $this->url = (string) config('services.ldap.url', 'ldaps://192.168.11.101:636');
        $this->domain = (string) config('services.ldap.domain', 'cbvh66');
        $this->searchBase = (string) config('services.ldap.search_base', 'DC=cbvh66,DC=com');
        $this->serviceUsername = (string) config('services.ldap.username', 'pdf_convertor_svc');
        $this->servicePassword = (string) config('services.ldap.password', '$Cbvh@168');
        $this->useSsl = (bool) config('services.ldap.use_ssl', true);
        $this->validateCert = (bool) config('services.ldap.validate_cert', false);
    }

    /**
     * Check if LDAP is enabled in the current environment.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Attempt authentication against Active Directory (LDAP).
     * Returns the synchronized User model if successful, or null on failure.
     */
    public function authenticate(string $username, string $password): ?User
    {
        if (!$this->enabled || empty($username) || empty($password)) {
            return null;
        }

        if (!extension_loaded('ldap')) {
            Log::warning('LDAP extension is not loaded in PHP. Falling back to local auth.');
            return null;
        }

        // Configure LDAP connection options (bypass self-signed/internal AD cert if configured)
        if (!$this->validateCert && defined('LDAP_OPT_X_TLS_REQUIRE_CERT') && defined('LDAP_OPT_X_TLS_NEVER')) {
            ldap_set_option(null, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_NEVER);
        }

        $conn = @ldap_connect($this->url);
        if (!$conn) {
            Log::warning("Could not connect to LDAP server at {$this->url}");
            return null;
        }

        ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);
        ldap_set_option($conn, LDAP_OPT_NETWORK_TIMEOUT, 3); // 3-second timeout

        try {
            // 1. Initial bind using Service Account
            $serviceBindUser = str_contains($this->serviceUsername, '@') || str_contains($this->serviceUsername, '\\')
                ? $this->serviceUsername
                : "{$this->domain}\\{$this->serviceUsername}";

            $serviceBind = @ldap_bind($conn, $serviceBindUser, $this->servicePassword);
            if (!$serviceBind) {
                Log::warning('LDAP service account bind failed: ' . ldap_error($conn));
                @ldap_unbind($conn);
                return null;
            }

            // 2. Search for the user in AD by sAMAccountName or mail
            $cleanUser = ldap_escape($username, '', LDAP_ESCAPE_FILTER);
            $filter = "(&(objectClass=user)(|(sAMAccountName={$cleanUser})(mail={$cleanUser})))";
            $search = @ldap_search($conn, $this->searchBase, $filter, ['dn', 'sAMAccountName', 'mail', 'givenName', 'sn', 'displayName']);

            if (!$search) {
                @ldap_unbind($conn);
                return null;
            }

            $entries = @ldap_get_entries($conn, $search);
            if (!$entries || $entries['count'] === 0) {
                @ldap_unbind($conn);
                return null; // User not found in AD
            }

            $userEntry = $entries[0];
            $userDn = $userEntry['dn'];

            // 3. Authenticate: Attempt user bind with their own password
            $userBind = @ldap_bind($conn, $userDn, $password);
            if (!$userBind) {
                @ldap_unbind($conn);
                return null; // Invalid credentials
            }

            // Extract AD user profile attributes
            $adEmail = $userEntry['mail'][0] ?? null;
            $adUsername = $userEntry['samaccountname'][0] ?? $username;
            $firstName = $userEntry['givenname'][0] ?? '';
            $lastName = $userEntry['sn'][0] ?? '';
            $fullName = $userEntry['displayname'][0] ?? trim("{$firstName} {$lastName}");

            @ldap_unbind($conn);

            // 4. Synchronize user record into local database
            $localUser = null;
            if ($adEmail) {
                $localUser = User::where('email', $adEmail)->first();
            }

            if (!$localUser) {
                $localUser = User::where('name', $fullName)->orWhere('email', $username)->first();
            }

            if ($localUser) {
                // Ensure name and active status are kept in sync
                $localUser->update([
                    'name' => $fullName ?: $localUser->name,
                    'is_active' => true,
                ]);
            }

            return $localUser;
        } catch (\Throwable $e) {
            Log::error("LDAP authentication exception: {$e->getMessage()}");
            if (is_resource($conn) || is_object($conn)) {
                @ldap_unbind($conn);
            }
            return null;
        }
    }
}
