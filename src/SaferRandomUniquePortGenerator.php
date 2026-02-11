<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers;

use Testcontainers\Utils\PortGenerator\PortGenerator;

final class SaferRandomUniquePortGenerator implements PortGenerator
{
    /** @var array<int, true> */
    private static array $assignedPorts = [];

    public function __construct(
        private readonly PortGenerator $portGenerator = new SaferRandomPortGenerator,
    ) {}

    public function generatePort(): int
    {
        do {
            $port = $this->portGenerator->generatePort();
        } while (isset(self::$assignedPorts[$port]));

        self::$assignedPorts[$port] = true;

        return $port;
    }
}
