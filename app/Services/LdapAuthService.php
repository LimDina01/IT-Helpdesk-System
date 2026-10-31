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
        if (!$this->validateCert) {
            putenv('LDAPTLS_REQCERT=never');
            if (defined('LDAP_OPT_X_TLS_REQUIRE_CERT') && defined('LDAP_OPT_X_TLS_NEVER')) {
                ldap_set_option(null, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_NEVER);
            }
        }

        $conn = @ldap_connect($this->url);
        if (!$conn) {
            Log::warning("Could not connect to LDAP server at {$this->url}");
            return null;
        }

        ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);
        ldap_set_option($conn, LDAP_OPT_NETWORK_TIMEOUT, 5); // 5-second timeout

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

            // 2. Parse username format and search for user in AD
            $cleanUser = ldap_escape($username, '', LDAP_ESCAPE_FILTER);
            $sAMAccountName = $cleanUser;
            if (str_contains($cleanUser, '\\')) {
                $parts = explode('\\', $cleanUser, 2);
                $sAMAccountName = $parts[1];
            } elseif (str_contains($cleanUser, '@')) {
                $parts = explode('@', $cleanUser, 2);
                $sAMAccountName = $parts[0];
            }

            $filter = "(&(objectClass=user)(!(objectClass=computer))(|(sAMAccountName={$sAMAccountName})(sAMAccountName={$cleanUser})(mail={$cleanUser})(userPrincipalName={$cleanUser})))";
            $search = @ldap_search($conn, $this->searchBase, $filter, ['dn', 'sAMAccountName', 'mail', 'givenName', 'sn', 'displayName', 'userPrincipalName']);

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
            $adUsername = $userEntry['samaccountname'][0] ?? $sAMAccountName;

            // 3. Authenticate: Attempt user bind with DN first, fallback to UPN / Domain prefix
            $userBind = @ldap_bind($conn, $userDn, $password);
            if (!$userBind) {
                $upnBindUser = "{$this->domain}\\{$adUsername}";
                $userBind = @ldap_bind($conn, $upnBindUser, $password);
            }

            if (!$userBind) {
                @ldap_unbind($conn);
                return null; // Invalid credentials
            }

            // Extract AD user profile attributes
            $adEmail = $userEntry['mail'][0] ?? null;
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
                $localUser = User::where('email', $username)
                    ->orWhere('email', 'like', "{$adUsername}@%")
                    ->orWhereHas('employee', fn($q) => $q->where('employee_code', $adUsername))
                    ->orWhere('name', $fullName)
                    ->first();
            }

            $techRole = \App\Models\Role::firstOrCreate(['name' => 'technician'], ['label' => 'IT Technician']);
            $requesterRole = \App\Models\Role::where('name', 'requester')->first();

            if ($localUser) {
                // Ensure name and active status are kept in sync
                $localUser->update([
                    'name' => $fullName ?: $localUser->name,
                    'is_active' => true,
                    'can_access_portal' => true,
                ]);

                // If user doesn't already have IT staff roles, assign them as IT technician (no auto requester)
                if (!$localUser->isStaff()) {
                    $localUser->roles()->sync([$techRole->id]);
                } else {
                    // Detach redundant requester role from IT staff
                    if ($requesterRole) {
                        $localUser->roles()->detach($requesterRole->id);
                    }
                }
            } else {
                // Auto-create local user profile from Active Directory as IT Technician
                $email = $adEmail ?: (str_contains($username, '@') ? $username : "{$adUsername}@cbvh66.com");
                $localUser = User::create([
                    'name' => $fullName ?: $adUsername,
                    'email' => strtolower($email),
                    'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(32)),
                    'is_active' => true,
                    'can_access_portal' => true,
                ]);

                // Assign as IT technician (no auto requester)
                $localUser->roles()->sync([$techRole->id]);
            }

            return $localUser->fresh(['roles', 'employee.department']);
        } catch (\Throwable $e) {
            Log::error("LDAP authentication exception: {$e->getMessage()}");
            if (is_resource($conn) || is_object($conn)) {
                @ldap_unbind($conn);
            }
            return null;
        }
    }

    /**
     * Diagnostic tester for Active Directory connection and authentication.
     */
    public function testConnection(?string $testUser = null, ?string $testPassword = null): array
    {
        $result = [
            'extension_loaded' => extension_loaded('ldap'),
            'url' => $this->url,
            'search_base' => $this->searchBase,
            'connected' => false,
            'service_bind' => false,
            'user_found' => null,
            'user_authenticated' => null,
            'local_user_matched' => null,
            'message' => '',
        ];

        if (!$result['extension_loaded']) {
            $result['message'] = 'PHP LDAP extension is not installed or enabled.';
            return $result;
        }

        if (!$this->validateCert) {
            putenv('LDAPTLS_REQCERT=never');
            if (defined('LDAP_OPT_X_TLS_REQUIRE_CERT') && defined('LDAP_OPT_X_TLS_NEVER')) {
                ldap_set_option(null, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_NEVER);
            }
        }

        $conn = @ldap_connect($this->url);
        if (!$conn) {
            $result['message'] = "Cannot initialize connection to {$this->url}";
            return $result;
        }

        ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);
        ldap_set_option($conn, LDAP_OPT_NETWORK_TIMEOUT, 5);

        $result['connected'] = true;

        $serviceBindUser = str_contains($this->serviceUsername, '@') || str_contains($this->serviceUsername, '\\')
            ? $this->serviceUsername
            : "{$this->domain}\\{$this->serviceUsername}";

        $serviceBind = @ldap_bind($conn, $serviceBindUser, $this->servicePassword);
        if (!$serviceBind) {
            $result['message'] = "Service account ({$serviceBindUser}) bind failed: " . ldap_error($conn);
            @ldap_unbind($conn);
            return $result;
        }

        $result['service_bind'] = true;

        if ($testUser) {
            $cleanUser = ldap_escape($testUser, '', LDAP_ESCAPE_FILTER);
            $sAMAccountName = $cleanUser;
            if (str_contains($cleanUser, '\\')) {
                $parts = explode('\\', $cleanUser, 2);
                $sAMAccountName = $parts[1];
            } elseif (str_contains($cleanUser, '@')) {
                $parts = explode('@', $cleanUser, 2);
                $sAMAccountName = $parts[0];
            }

            $filter = "(&(objectClass=user)(!(objectClass=computer))(|(sAMAccountName={$sAMAccountName})(sAMAccountName={$cleanUser})(mail={$cleanUser})))";
            $search = @ldap_search($conn, $this->searchBase, $filter, ['dn', 'sAMAccountName', 'mail', 'displayName']);

            if ($search) {
                $entries = @ldap_get_entries($conn, $search);
                if ($entries && $entries['count'] > 0) {
                    $entry = $entries[0];
                    $result['user_found'] = [
                        'dn' => $entry['dn'],
                        'sAMAccountName' => $entry['samaccountname'][0] ?? null,
                        'mail' => $entry['mail'][0] ?? null,
                        'displayName' => $entry['displayname'][0] ?? null,
                    ];

                    if ($testPassword) {
                        $auth = @ldap_bind($conn, $entry['dn'], $testPassword);
                        if (!$auth) {
                            $upn = "{$this->domain}\\" . ($entry['samaccountname'][0] ?? $sAMAccountName);
                            $auth = @ldap_bind($conn, $upn, $testPassword);
                        }
                        $result['user_authenticated'] = (bool) $auth;
                    }
                }
            }
        }

        @ldap_unbind($conn);
        $result['message'] = 'Active Directory connection and service bind verified successfully!';
        return $result;
    }
}
