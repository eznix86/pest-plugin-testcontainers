<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers\Container\PortMapping;

use Random\RandomException;
use RuntimeException;
use Testcontainers\Utils\PortGenerator\PortGenerator;

final class SaferRandomPortGenerator implements PortGenerator
{
    private const int MIN_PORT = 10000;

    private const int MAX_PORT = 65535;

    private const int MAX_ATTEMPTS = 200;

    public function generatePort(): int
    {
        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            try {
                $port = \random_int(self::MIN_PORT, self::MAX_PORT);
            } catch (RandomException $e) {
                throw new RuntimeException('Failed to generate random port', 0, $e);
            }

            if ($this->isAvailable($port)) {
                return $port;
            }
        }

        throw new RuntimeException('Failed to find an available random host port.');
    }

    private function isAvailable(int $port): bool
    {
        $tcpServer = $this->tryBindSocket(
            sprintf('tcp://127.0.0.1:%d', $port),
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
        );

        if ($tcpServer === false) {
            return false;
        }

        $udpServer = $this->tryBindSocket(
            sprintf('udp://127.0.0.1:%d', $port),
            STREAM_SERVER_BIND,
        );

        if ($udpServer === false) {
            fclose($tcpServer);

            return false;
        }

        fclose($udpServer);
        fclose($tcpServer);

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
