<?php

namespace Tests\Feature;

use App\Services\GuacamoleLinkService;
use App\Services\RemotePcStatusService;
use Mockery;
use Tests\TestCase;

class PcControlLinkEndpointTest extends TestCase
{
    public function test_it_returns_the_guacamole_link_when_pc_is_available(): void
    {
        $statusMock = Mockery::mock(RemotePcStatusService::class);
        $statusMock->shouldReceive('getStatus')
            ->once()
            ->andReturn([
                'status' => 'available',
            ]);

        $guacMock = Mockery::mock(GuacamoleLinkService::class);
        $guacMock->shouldReceive('getControlUrl')
            ->once()
            ->andReturn('http://localhost:8080/guacamole/');

        $this->app->instance(RemotePcStatusService::class, $statusMock);
        $this->app->instance(GuacamoleLinkService::class, $guacMock);

        $this->getJson('/api/pc/control-link')
            ->assertOk()
            ->assertJsonPath('data.ready', true)
            ->assertJsonPath('data.control_url', 'http://localhost:8080/guacamole/');
    }

    public function test_it_returns_conflict_when_pc_is_not_ready(): void
    {
        $statusMock = Mockery::mock(RemotePcStatusService::class);
        $statusMock->shouldReceive('getStatus')
            ->once()
            ->andReturn([
                'status' => 'booting',
            ]);

        $guacMock = Mockery::mock(GuacamoleLinkService::class);

        $this->app->instance(RemotePcStatusService::class, $statusMock);
        $this->app->instance(GuacamoleLinkService::class, $guacMock);

        $this->getJson('/api/pc/control-link')
            ->assertStatus(409)
            ->assertJsonPath('data.ready', false)
            ->assertJsonPath('data.status', 'booting');
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
