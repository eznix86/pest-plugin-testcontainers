<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers\Container\PortMapping;

use Random\RandomException;
use RuntimeException;

final class ProtocolAwareRandomUniquePortAllocator
{
    private const int MIN_PORT = 10000;

    private const int MAX_PORT = 65535;

    private const int MAX_ATTEMPTS = 200;

    /** @var array<int, true> */
    private static array $assignedPorts = [];

    public function allocateForContainerPort(int|string $containerPort): int
    {
        $protocol = $this->inferProtocol($containerPort);

        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            try {
                $port = random_int(self::MIN_PORT, self::MAX_PORT);
            } catch (RandomException $e) {
                throw new RuntimeException('Failed to generate random port', 0, $e);
            }

            if (isset(self::$assignedPorts[$port])) {
                continue;
            }

            if (! $this->isAvailable($port, $protocol)) {
                continue;
            }

            self::$assignedPorts[$port] = true;

            return $port;
        }

        throw new RuntimeException(sprintf('Failed to find an available random host port for %s.', $protocol));
    }

    private function inferProtocol(int|string $containerPort): string
    {
        if (is_int($containerPort)) {
            return 'tcp';
        }

        return str_ends_with(strtolower($containerPort), '/udp') ? 'udp' : 'tcp';
    }

    private function isAvailable(int $port, string $protocol): bool
    {
        $flags = $protocol === 'udp'
            ? STREAM_SERVER_BIND
            : STREAM_SERVER_BIND | STREAM_SERVER_LISTEN;

        $server = $this->tryBindSocket(
            sprintf('%s://127.0.0.1:%d', $protocol, $port),
            $flags,
        );

        if ($server === false) {
            return false;
        }

        fclose($server);

        return true;
    }

    /**
     * @return resource|false
     */
    private function tryBindSocket(string $address, int $flags)
    {
        set_error_handler(static fn (): bool => true);

        try {
            return stream_socket_server($address, $errorCode, $errorMessage, $flags);
        } finally {
            restore_error_handler();
        }
    }
}
