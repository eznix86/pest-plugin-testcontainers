<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers;

use RuntimeException;
use Testcontainers\Container\StartedTestContainer;
use Testcontainers\Exception\ContainerWaitingTimeoutException;
use Testcontainers\Wait\BaseWaitStrategy;

final class WaitForPort extends BaseWaitStrategy
{
    public function __construct(
        private readonly ?int $port = null,
        int $timeout = 10000,
        int $pollInterval = 500,
    ) {
        parent::__construct($timeout, $pollInterval);
    }

    public function wait(StartedTestContainer $container): void
    {
        $startTime = microtime(true) * 1000;

        while (true) {
            $elapsedTime = (microtime(true) * 1000) - $startTime;

            if ($elapsedTime > $this->timeout) {
                throw new ContainerWaitingTimeoutException($container->getId());
            }

            try {
                $hostPort = $this->port === null
                    ? $container->getFirstMappedPort()
                    : $container->getMappedPort($this->port);

                if ($this->isPortOpen($container->getHost(), $hostPort)) {
                    return;
                }
            } catch (RuntimeException) {
                // Port mapping may not be ready yet.
            }

            usleep($this->pollInterval * 1000);
        }
    }

    private function isPortOpen(string $host, int $port): bool
    {
        $normalizedHost = str_contains($host, ':') && ! str_starts_with($host, '[')
            ? sprintf('[%s]', $host)
            : $host;

        set_error_handler(static fn (): bool => true);

        try {
            $socket = stream_socket_client(
                sprintf('tcp://%s:%d', $normalizedHost, $port),
                $errorNumber,
                $errorMessage,
                2,
                STREAM_CLIENT_CONNECT,
            );
        } finally {
            restore_error_handler();
        }

        if (! is_resource($socket)) {
            return false;
        }

        fclose($socket);

        return true;
    }
}
