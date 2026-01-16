<?php

declare(strict_types=1);

// Lightweight compatibility wrapper used across the app.
// Provides RouterOSClient::talk() on top of evilfreelancer/routeros-api-php.

require_once __DIR__ . '/../vendor/autoload.php';

use RouterOS\Client;
use RouterOS\Query;

class RouterOSClient
{
    private Client $client;

    public function __construct(
        string $host,
        int $port,
        string $user,
        string $pass,
        int $timeout = 8,
        bool $useTls = true
    ) {
        $config = [
            'host' => $host,
            'user' => $user,
            'pass' => $pass,
            'timeout' => $timeout,
            'port' => $port,
            'ssl' => $useTls,
            // Reasonable defaults for RouterOS API-SSL (self-signed / MikroTik cert)
            'ssl_options' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
                // RouterOS often requires ADH ciphers (library default is also ADH:ALL)
                'ciphers' => 'ADH:ALL',
            ],
        ];

        $this->client = new Client($config, true);
    }

    /**
     * RouterOS "talk"-style helper.
     *
     * Examples:
     *   talk('/system/identity/print')
     *   talk('/ip/dhcp-server/lease/print', ['?status=bound', '.proplist' => '.id,address,mac-address'])
     *   talk('/ip/firewall/filter/add', ['chain'=>'forward','action'=>'drop','src-mac-address'=>'aa:bb:cc:dd:ee:ff'])
     */
    public function talk(string $endpoint, array $args = []): array
    {
        $query = new Query($endpoint);

        foreach ($args as $key => $value) {
            // Numeric keys are treated as raw RouterOS "words" (already formatted)
            if (is_int($key)) {
                if (is_string($value) && $value !== '') {
                    $query->add($value);
                }
                continue;
            }

            // If key itself looks like a complete word, add as-is (optionally with value)
            if ($key !== '' && ($key[0] === '?' || $key[0] === '=' || $key[0] === '.')) {
                if ($value === null || $value === '') {
                    $query->add($key);
                } else {
                    // If caller provided something like '.proplist' => '.id,comment'
                    // we convert it to '=.<key>=<value>' format
                    $query->equal($key, (string)$value);
                }
                continue;
            }

            // Default: setter format (=key=value)
            $query->equal((string)$key, is_bool($value) ? ($value ? 'true' : 'false') : (string)$value);
        }

        return $this->client->query($query)->read(true);
    }

    public function close(): void
    {
        // Library does not expose a public disconnect(); close the socket resource directly.
        $socket = $this->client->getSocket();
        if (is_resource($socket)) {
            @fclose($socket);
        }
    }
}
