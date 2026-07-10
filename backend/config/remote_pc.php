<?php

return [
    'host' => env('REMOTE_PC_HOST', '127.0.0.1'),
    'label' => env('REMOTE_PC_LABEL', 'PC principal'),
    'connect_timeout' => (float) env('REMOTE_PC_CONNECT_TIMEOUT', 1.5),
    'rdp_port' => (int) env('REMOTE_PC_RDP_PORT', 3389),
    'wol' => [
        'mac' => env('REMOTE_PC_MAC'),
        'broadcast' => env('REMOTE_PC_WOL_BROADCAST', '255.255.255.255'),
        'port' => (int) env('REMOTE_PC_WOL_PORT', 9),
    ],
    'power' => [
        'shutdown_command' => env('REMOTE_PC_SHUTDOWN_COMMAND', 'shutdown /s /t 0'),
        'restart_command' => env('REMOTE_PC_RESTART_COMMAND', 'shutdown /r /t 0'),
    ],
    'guacamole' => [
        'base_url' => env('REMOTE_PC_GUACAMOLE_BASE_URL', 'http://localhost:8080/guacamole/'),
        'client_identifier' => env('REMOTE_PC_GUACAMOLE_CLIENT_IDENTIFIER'),
        'connection_id' => env('REMOTE_PC_GUACAMOLE_CONNECTION_ID'),
        'auth_provider' => env('REMOTE_PC_GUACAMOLE_AUTH_PROVIDER', 'mysql'),
        'connect_timeout' => (float) env('REMOTE_PC_GUACAMOLE_CONNECT_TIMEOUT', env('REMOTE_PC_CONNECT_TIMEOUT', 1.5)),
    ],
    'ssh' => [
        'host' => env('REMOTE_PC_SSH_HOST', env('REMOTE_PC_HOST', '127.0.0.1')),
        'port' => (int) env('REMOTE_PC_SSH_PORT', 22),
        'user' => env('REMOTE_PC_SSH_USER'),
        'private_key_path' => env('REMOTE_PC_SSH_PRIVATE_KEY_PATH'),
        'binary' => env('REMOTE_PC_SSH_BINARY', 'ssh'),
        'probe_command' => env('REMOTE_PC_SSH_PROBE_COMMAND', 'echo ssh-ok'),
        'timeout' => (float) env('REMOTE_PC_SSH_TIMEOUT', 8),
        'probe_timeout' => (float) env('REMOTE_PC_SSH_PROBE_TIMEOUT', env('REMOTE_PC_SSH_TIMEOUT', 8)),
        'command_timeout' => (float) env('REMOTE_PC_SSH_COMMAND_TIMEOUT', 6),
    ],
];
