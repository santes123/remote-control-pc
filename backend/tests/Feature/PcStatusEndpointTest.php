<?php

namespace Tests\Feature;

use App\Services\RemotePcStatusService;
use Mockery;
use Tests\TestCase;

class PcStatusEndpointTest extends TestCase
{
    public function test_it_returns_the_remote_pc_status_payload(): void
    {
        config()->set('remote_pc.guacamole.base_url', 'http://localhost:8080/guacamole/');

        $serviceMock = Mockery::mock(RemotePcStatusService::class);
        $serviceMock->shouldReceive('getStatus')
            ->once()
            ->andReturn([
                'label' => 'PC principal',
                'host' => '192.168.1.50',
                'status' => 'available',
                'checks' => [
                    'rdp' => [
                        'host' => '192.168.1.50',
                        'port' => 3389,
                        'reachable' => true,
                    ],
                    'ssh_port' => [
                        'host' => '192.168.1.50',
                        'port' => 22,
                        'reachable' => true,
                    ],
                    'ssh_probe' => [
                        'configured' => true,
                        'attempted' => true,
                        'success' => true,
                        'message' => 'Conexión SSH correcta.',
                        'output' => 'ssh-ok',
                    ],
                ],
                'flow' => [
                    'next_action' => 'open_guacamole',
                    'poll_interval_ms' => 5000,
                    'control_url' => 'http://localhost:8080/guacamole/',
                ],
            ]);

        $this->app->instance(RemotePcStatusService::class, $serviceMock);

        $this->getJson('/api/pc/status')
            ->assertOk()
            ->assertJsonPath('data.status', 'available')
            ->assertJsonPath('data.checks.rdp.port', 3389)
            ->assertJsonPath('data.checks.ssh_probe.success', true)
            ->assertJsonPath('data.flow.next_action', 'open_guacamole');
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
