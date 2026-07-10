<?php

namespace App\Services;

class RemotePcStatusService
{
    public function __construct(
        private readonly RemotePcSshService $remotePcSshService,
        private readonly GuacamoleLinkService $guacamoleLinkService
    ) {}

    public function getStatus(): array
    {
        $host = (string) config('remote_pc.host');
        $label = (string) config('remote_pc.label');
        $rdpPort = (int) config('remote_pc.rdp_port');
        $connectTimeout = (float) config('remote_pc.connect_timeout', 1.5);
        $sshHost = (string) config('remote_pc.ssh.host', $host);
        $sshPort = (int) config('remote_pc.ssh.port', 22);
        $rdpReachable = $this->isTcpPortOpen($host, $rdpPort, $connectTimeout);
        $sshPortReachable = $this->isTcpPortOpen($sshHost, $sshPort, $connectTimeout);
        $sshProbe = $this->remotePcSshService->probe($sshHost, $sshPort);
        $guacamoleStatus = $this->getGuacamoleStatus();

        $status = $this->resolveStatus($rdpReachable, $sshPortReachable, $sshProbe['success']);

        return [
            'label' => $label,
            'host' => $host,
            'status' => $status,
            'checks' => [
                'rdp' => [
                    'host' => $host,
                    'port' => $rdpPort,
                    'reachable' => $rdpReachable,
                ],
                'ssh_port' => [
                    'host' => $sshHost,
                    'port' => $sshPort,
                    'reachable' => $sshPortReachable,
                ],
                'ssh_probe' => $sshProbe,
                'guacamole' => $guacamoleStatus,
            ],
            'flow' => [
                'next_action' => $this->resolveNextAction($status),
                'poll_interval_ms' => 5000,
                'control_url' => $status === 'available' ? $this->guacamoleLinkService->getControlUrl() : null,
                'control_url_mode' => $this->guacamoleLinkService->hasDirectConnectionConfigured() ? 'direct_connection' : 'home',
            ],
        ];
    }

    private function isTcpPortOpen(string $host, int $port, float $timeout): bool
    {
        $connection = @fsockopen($host, $port, $errorCode, $errorMessage, $timeout);

        if (! is_resource($connection)) {
            return false;
        }

        fclose($connection);

        return true;
    }

    private function resolveStatus(bool $rdpReachable, bool $sshPortReachable, bool $sshProbeSucceeded): string
    {
        if ($sshProbeSucceeded) {
            return 'available';
        }

        if ($rdpReachable || $sshPortReachable) {
            return 'booting';
        }

        return 'offline';
    }

    private function resolveNextAction(string $status): string
    {
        return match ($status) {
            'offline' => 'wake',
            'booting' => 'poll_status',
            'available' => 'open_guacamole',
            default => 'poll_status',
        };
    }

    private function getGuacamoleStatus(): array
    {
        $gatewayTarget = $this->guacamoleLinkService->getGatewayTarget();
        $timeout = (float) config('remote_pc.guacamole.connect_timeout', config('remote_pc.connect_timeout', 1.5));
        $host = $gatewayTarget['host'];
        $reachable = filled($host)
            ? $this->isTcpPortOpen((string) $host, (int) $gatewayTarget['port'], $timeout)
            : false;

        return [
            ...$gatewayTarget,
            'reachable' => $reachable,
            'direct_connection_configured' => $this->guacamoleLinkService->hasDirectConnectionConfigured(),
        ];
    }
}
