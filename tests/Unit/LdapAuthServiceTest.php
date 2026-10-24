<?php

namespace Tests\Unit;

use App\Services\LdapAuthService;
use Tests\TestCase;

class LdapAuthServiceTest extends TestCase
{
    public function test_ldap_is_disabled_by_default_in_local_testing(): void
    {
        $service = new LdapAuthService();
        $this->assertFalse($service->isEnabled());

        // When disabled, authenticate returns null safely without network calls
        $result = $service->authenticate('test_user', 'secret');
        $this->assertNull($result);
    }

    public function test_ldap_configuration_matches_cbvh_domain_specs(): void
    {
        $this->assertEquals('cbvh66', config('services.ldap.domain'));
        $this->assertEquals('DC=cbvh66,DC=com', config('services.ldap.search_base'));
        $this->assertEquals('pdf_convertor_svc', config('services.ldap.username'));
        $this->assertEquals('ldaps://192.168.11.101:636', config('services.ldap.url'));
    }
}
