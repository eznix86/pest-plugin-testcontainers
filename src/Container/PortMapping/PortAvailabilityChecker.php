<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers\Container\PortMapping;

final class PortAvailabilityChecker
{
    public function isAvailable(int $port, string $protocol): bool
    {
        if ($protocol === 'both') {
            return $this->isTcpAndUdpAvailable($port);
        }

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

    private function isTcpAndUdpAvailable(int $port): bool
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
