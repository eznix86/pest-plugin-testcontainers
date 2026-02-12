<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers\Container\PortMapping;

use RuntimeException;
use Testcontainers\Utils\PortGenerator\PortGenerator;

final class FixedPortSequenceGenerator implements PortGenerator
{
    private int $cursor = 0;

    /**
     * @param  list<int>  $ports
     */
    public function __construct(
        private array $ports,
    ) {}

    public function generatePort(): int
    {
        if ($this->ports === []) {
            throw new RuntimeException('Fixed host port mapping requires at least one host port.');
        }

        $index = $this->cursor % count($this->ports);
        $port = $this->ports[$index] ?? null;
        $this->cursor++;

        if (! is_int($port)) {
            throw new RuntimeException('Fixed host port mapping contains a non-integer host port.');
        }

        return $port;
    }
}
