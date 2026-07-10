<?php

namespace App\Services;

class GuacamoleLinkService
{
    public function getBaseUrl(): string
    {
        return rtrim((string) config('remote_pc.guacamole.base_url'), '/').'/';
    }

    public function getControlUrl(): string
    {
        $clientIdentifier = $this->getClientIdentifier();

        if (blank($clientIdentifier)) {
            return $this->getBaseUrl();
        }

        return $this->getBaseUrl().'#/client/'.$clientIdentifier;
    }

    public function getGatewayTarget(): array
    {
        $baseUrl = $this->getBaseUrl();
        $parts = parse_url($baseUrl);
        $scheme = $parts['scheme'] ?? 'http';

        return [
            'scheme' => $scheme,
            'host' => $parts['host'] ?? null,
            'port' => $parts['port'] ?? ($scheme === 'https' ? 443 : 80),
            'path' => $parts['path'] ?? '/',
            'base_url' => $baseUrl,
        ];
    }

    public function hasDirectConnectionConfigured(): bool
    {
        return filled($this->getClientIdentifier());
    }

    public function getClientIdentifier(): ?string
    {
        $explicitClientIdentifier = trim((string) config('remote_pc.guacamole.client_identifier'));

        if ($explicitClientIdentifier !== '') {
            return $explicitClientIdentifier;
        }

        $connectionId = trim((string) config('remote_pc.guacamole.connection_id'));
        $authProvider = trim((string) config('remote_pc.guacamole.auth_provider', 'mysql'));

        if ($connectionId === '' || $authProvider === '') {
            return null;
        }

        $rawIdentifier = $connectionId."\0".'c'."\0".$authProvider;

        return rtrim(strtr(base64_encode($rawIdentifier), '+/', '-_'), '=');
    }
}
