<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers\Container\PortMapping;

use Random\RandomException;
use RuntimeException;
use Testcontainers\Utils\PortGenerator\PortGenerator;

final readonly class SaferRandomPortGenerator implements PortGenerator
{
    private const int MIN_PORT = 10000;

    private const int MAX_PORT = 65535;

    private const int MAX_ATTEMPTS = 200;

    public function __construct(
        private PortAvailabilityChecker $availabilityChecker = new PortAvailabilityChecker,
    ) {}

    public function generatePort(): int
    {
        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            try {
                $port = \random_int(self::MIN_PORT, self::MAX_PORT);
            } catch (RandomException $e) {
                throw new RuntimeException('Failed to generate random port', 0, $e);
            }

            if ($this->availabilityChecker->isAvailable($port, 'both')) {
                return $port;
            }
        }

        throw new RuntimeException('Failed to find an available random host port.');
    }
}
