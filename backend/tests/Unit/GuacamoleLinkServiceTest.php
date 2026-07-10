<?php

namespace Tests\Unit;

use App\Services\GuacamoleLinkService;
use Tests\TestCase;

class GuacamoleLinkServiceTest extends TestCase
{
    public function test_it_returns_base_url_when_no_direct_connection_is_configured(): void
    {
        config()->set('remote_pc.guacamole.base_url', 'http://localhost:8080/guacamole/');
        config()->set('remote_pc.guacamole.client_identifier', null);
        config()->set('remote_pc.guacamole.connection_id', null);

        $service = app(GuacamoleLinkService::class);

        $this->assertSame('http://localhost:8080/guacamole/', $service->getControlUrl());
        $this->assertFalse($service->hasDirectConnectionConfigured());
    }

    public function test_it_builds_a_direct_connection_url_from_connection_id_and_provider(): void
    {
        config()->set('remote_pc.guacamole.base_url', 'http://localhost:8080/guacamole/');
        config()->set('remote_pc.guacamole.client_identifier', null);
        config()->set('remote_pc.guacamole.connection_id', '2');
        config()->set('remote_pc.guacamole.auth_provider', 'mysql');

        $service = app(GuacamoleLinkService::class);

        $this->assertSame(
            'http://localhost:8080/guacamole/#/client/MgBjAG15c3Fs',
            $service->getControlUrl()
        );
        $this->assertTrue($service->hasDirectConnectionConfigured());
    }

    public function test_it_uses_explicit_client_identifier_when_provided(): void
    {
        config()->set('remote_pc.guacamole.base_url', 'http://localhost:8080/guacamole/');
        config()->set('remote_pc.guacamole.client_identifier', 'copied-from-guacamole');
        config()->set('remote_pc.guacamole.connection_id', '2');
        config()->set('remote_pc.guacamole.auth_provider', 'mysql');

        $service = app(GuacamoleLinkService::class);

        $this->assertSame(
            'http://localhost:8080/guacamole/#/client/copied-from-guacamole',
            $service->getControlUrl()
        );
    }
}
