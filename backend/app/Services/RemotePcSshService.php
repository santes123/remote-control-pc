<?php

namespace App\Services;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class RemotePcSshService
{
    public function probe(string $host, int $port): array
    {
        $probeCommand = (string) config('remote_pc.ssh.probe_command', 'echo ssh-ok');

        return $this->run($host, $port, $probeCommand, true);
    }

    public function shutdown(): array
    {
        return $this->runPowerCommand((string) config('remote_pc.power.shutdown_command'));
    }

    public function restart(): array
    {
        return $this->runPowerCommand((string) config('remote_pc.power.restart_command'));
    }

    public function runPowerCommand(string $remoteCommand): array
    {
        $host = (string) config('remote_pc.ssh.host');
        $port = (int) config('remote_pc.ssh.port', 22);

        return $this->run($host, $port, $remoteCommand, false);
    }

    private function run(string $host, int $port, string $remoteCommand, bool $isProbe): array
    {
        $user = config('remote_pc.ssh.user');
        $privateKeyPath = config('remote_pc.ssh.private_key_path');

        if (blank($user) || blank($privateKeyPath)) {
            return [
                'configured' => false,
                'attempted' => false,
                'success' => false,
                'message' => 'Faltan REMOTE_PC_SSH_USER o REMOTE_PC_SSH_PRIVATE_KEY_PATH.',
                'output' => null,
            ];
        }

        $resolvedKeyPath = $this->resolvePrivateKeyPath((string) $privateKeyPath);

        if (! is_file($resolvedKeyPath)) {
            return [
                'configured' => true,
                'attempted' => false,
                'success' => false,
                'message' => 'La clave SSH configurada no existe en la ruta indicada.',
                'output' => $resolvedKeyPath,
            ];
        }

        $timeoutSeconds = $isProbe
            ? (float) config('remote_pc.ssh.probe_timeout', config('remote_pc.ssh.timeout', 8))
            : (float) config('remote_pc.ssh.command_timeout', 6);
        $connectTimeoutSeconds = (string) max(1, (int) ceil($timeoutSeconds));

        $command = [
            $this->resolveSshBinary((string) config('remote_pc.ssh.binary', 'ssh')),
            '-n',
            '-o', 'BatchMode=yes',
            '-o', 'StrictHostKeyChecking=accept-new',
            '-o', 'ConnectTimeout='.$connectTimeoutSeconds,
            '-p', (string) $port,
            '-i', $resolvedKeyPath,
            sprintf('%s@%s', $user, $host),
            $remoteCommand,
        ];

        if (DIRECTORY_SEPARATOR === '\\') {
            return $this->runOnWindows($command, $isProbe);
        }

        $process = $this->createProcess($command);
        $process->setTimeout($timeoutSeconds);

        try {
            $process->mustRun();

            $result = [
                'configured' => true,
                'attempted' => true,
                'success' => true,
                'message' => $isProbe ? 'Conexión SSH correcta.' : 'Comando enviado correctamente.',
                'output' => trim($process->getOutput()),
            ];

            return $this->appendDebugData($result, $process);
        } catch (ProcessFailedException) {
            $errorOutput = trim($process->getErrorOutput() ?: $process->getOutput());

            $result = [
                'configured' => true,
                'attempted' => true,
                'success' => false,
                'message' => $isProbe ? 'La prueba SSH ha fallado.' : 'El comando ha fallado.',
                'output' => $errorOutput !== '' ? $errorOutput : 'SSH terminó con código '.$process->getExitCode().' sin salida visible.',
            ];

            return $this->appendDebugData($result, $process);
        }
    }

    private function runOnWindows(array $command, bool $isProbe): array
    {
        $commandLine = $this->buildWindowsCommandLine($command);
        $outputLines = [];
        $exitCode = 0;

        exec($commandLine.' 2>&1', $outputLines, $exitCode);

        $result = [
            'configured' => true,
            'attempted' => true,
            'success' => $exitCode === 0,
            'message' => $exitCode === 0
                ? ($isProbe ? 'Conexión SSH correcta.' : 'Comando enviado correctamente.')
                : ($isProbe ? 'La prueba SSH ha fallado.' : 'El comando ha fallado.'),
            'output' => trim(implode(PHP_EOL, $outputLines)),
        ];

        if ($result['output'] === '' && $exitCode !== 0) {
            $result['output'] = 'SSH terminó con código '.$exitCode.' sin salida visible.';
        }

        if (config('app.debug')) {
            $result['debug'] = [
                'exit_code' => $exitCode,
                'command_line' => $commandLine,
            ];
        }

        return $result;
    }

    private function createProcess(array $command): Process
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return Process::fromShellCommandline($this->buildWindowsCommandLine($command), base_path());
        }

        return new Process($command, base_path());
    }

    private function buildWindowsCommandLine(array $command): string
    {
        $binary = array_shift($command);
        $commandLine = escapeshellcmd((string) $binary);

        if ($command !== []) {
            $commandLine .= ' '.implode(' ', array_map('escapeshellarg', $command));
        }

        return $commandLine;
    }

    private function resolvePrivateKeyPath(string $privateKeyPath): string
    {
        if (str_starts_with($privateKeyPath, '~/')) {
            $home = getenv('HOME') ?: getenv('USERPROFILE') ?: '';
            $candidate = $home.DIRECTORY_SEPARATOR.ltrim(substr($privateKeyPath, 2), DIRECTORY_SEPARATOR);

            return $this->maybeConvertPubKeyToPrivateKey($candidate);
        }

        return $this->maybeConvertPubKeyToPrivateKey($privateKeyPath);
    }

    private function maybeConvertPubKeyToPrivateKey(string $path): string
    {
        if (str_ends_with($path, '.pub')) {
            $withoutPub = substr($path, 0, -4);

            if (is_file($withoutPub)) {
                return $withoutPub;
            }
        }

        return $path;
    }

    private function resolveSshBinary(string $binary): string
    {
        if (DIRECTORY_SEPARATOR !== '\\') {
            return $binary;
        }

        if (str_contains($binary, '\\') || str_contains($binary, '/') || str_ends_with(strtolower($binary), '.exe')) {
            return $binary;
        }

        $candidates = [
            'C:/Windows/System32/OpenSSH/ssh.exe',
            'C:/Windows/Sysnative/OpenSSH/ssh.exe',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return $binary;
    }

    private function appendDebugData(array $result, Process $process): array
    {
        if (! config('app.debug')) {
            return $result;
        }

        $result['debug'] = [
            'exit_code' => $process->getExitCode(),
            'command_line' => $process->getCommandLine(),
        ];

        return $result;
    }
}
