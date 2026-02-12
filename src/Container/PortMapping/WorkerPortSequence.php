<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers\Container\PortMapping;

use RuntimeException;

final class WorkerPortSequence
{
    private const int BASE_PORT = 49152;

    private const int PORTS_PER_WORKER = 100;

    private readonly int $basePort;

    private int $counter = 0;

    public function __construct()
    {
        $workerId = $this->detectWorkerId();
        $this->basePort = self::BASE_PORT + ($workerId * self::PORTS_PER_WORKER);
    }

    public function nextPort(): int
    {
        if ($this->counter >= self::PORTS_PER_WORKER) {
            throw new RuntimeException(sprintf(
                'Worker port range exhausted. Used %d ports from range %d-%d.',
                $this->counter,
                $this->basePort,
                $this->basePort + self::PORTS_PER_WORKER - 1,
            ));
        }

        return $this->basePort + $this->counter++;
    }

    private function detectWorkerId(): int
    {
        $token = $this->getEnvString('TEST_TOKEN')
            ?? $this->getEnvString('PEST_PARALLEL_PROCESS')
            ?? $this->getEnvString('PEST_WORKER')
            ?? '0';

        return max(0, (int) $token);
    }

    private function getEnvString(string $key): ?string
    {
        $value = $_SERVER[$key] ?? $_ENV[$key] ?? null;

        return is_string($value) ? $value : null;
    }
}
