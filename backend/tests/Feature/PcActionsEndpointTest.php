<?php

namespace Tests\Feature;

use App\Services\RemotePcSshService;
use App\Services\WakeOnLanService;
use Mockery;
use Tests\TestCase;

class PcActionsEndpointTest extends TestCase
{
    public function test_it_calls_wake_on_lan_service(): void
    {
        config()->set('remote_pc.wol.mac', 'AA:BB:CC:DD:EE:FF');

        $wolMock = Mockery::mock(WakeOnLanService::class);
        $wolMock->shouldReceive('wake')
            ->once()
            ->andReturn([
                'success' => true,
                'message' => 'Paquete WOL enviado.',
                'target' => '192.168.1.255',
            ]);

        $this->app->instance(WakeOnLanService::class, $wolMock);

        $this->postJson('/api/pc/wake')
            ->assertOk()
            ->assertJsonPath('data.success', true);
    }

    public function test_it_calls_shutdown_via_ssh_service(): void
    {
        $sshMock = Mockery::mock(RemotePcSshService::class);
        $sshMock->shouldReceive('shutdown')
            ->once()
            ->andReturn([
                'configured' => true,
                'attempted' => true,
                'success' => true,
                'message' => 'Comando enviado correctamente.',
                'output' => '',
            ]);

        $this->app->instance(RemotePcSshService::class, $sshMock);

        $this->postJson('/api/pc/shutdown')
            ->assertOk()
            ->assertJsonPath('data.success', true);
    }

    public function test_it_calls_restart_via_ssh_service(): void
    {
        $sshMock = Mockery::mock(RemotePcSshService::class);
        $sshMock->shouldReceive('restart')
            ->once()
            ->andReturn([
                'configured' => true,
                'attempted' => true,
                'success' => true,
                'message' => 'Comando enviado correctamente.',
                'output' => '',
            ]);

        $this->app->instance(RemotePcSshService::class, $sshMock);

        $this->postJson('/api/pc/restart')
            ->assertOk()
            ->assertJsonPath('data.success', true);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
