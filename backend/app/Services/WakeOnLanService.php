<?php

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;

class WakeOnLanService
{
    public function wake(string $mac, string $broadcast, int $port, ?string $host = null): array
    {
        $macBytes = $this->macToBytes($mac);
        $packet = str_repeat("\xFF", 6).str_repeat($macBytes, 16);
        $broadcastTargets = $this->resolveBroadcastTargets($broadcast, $host);
        $lastErrorMessage = 'No se pudo enviar el paquete WOL.';

        foreach ($broadcastTargets as $target) {
            $context = stream_context_create([
                'socket' => [
                    'so_broadcast' => true,
                ],
            ]);

            $client = @stream_socket_client(
                sprintf('udp://%s:%d', $target, $port),
                $errorCode,
                $errorMessage,
                2,
                STREAM_CLIENT_CONNECT,
                $context
            );

            if ($client === false) {
                $lastErrorMessage = 'No se pudo abrir el socket UDP para Wake-on-LAN en '.$target.': '.$errorMessage;
                continue;
            }

            $sent = fwrite($client, $packet);
            fclose($client);

            if ($sent !== false && $sent === strlen($packet)) {
                return [
                    'success' => true,
                    'message' => 'Paquete WOL enviado.',
                    'target' => $target,
                ];
            }

            $lastErrorMessage = 'No se pudo enviar el paquete WOL en '.$target.'.';
        }

        throw new RuntimeException($lastErrorMessage);
    }

    private function macToBytes(string $mac): string
    {
        $clean = preg_replace('/[^0-9A-Fa-f]/', '', $mac) ?? '';
        if (strlen($clean) !== 12) {
            throw new InvalidArgumentException('MAC inválida.');
        }

        return hex2bin($clean) ?: throw new InvalidArgumentException('MAC inválida.');
    }

    private function resolveBroadcastTargets(string $broadcast, ?string $host): array
    {
        $targets = [$broadcast];

        if ($host !== null && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $host);
            if (count($parts) === 4) {
                $derivedBroadcast = sprintf('%s.%s.%s.255', $parts[0], $parts[1], $parts[2]);
                $targets[] = $derivedBroadcast;
            }
        }

        return array_values(array_unique($targets));
    }
}
