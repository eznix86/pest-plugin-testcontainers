<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers\Container\PortMapping;

use RuntimeException;
use Testcontainers\Utils\PortGenerator\PortGenerator;

final class FixedPortSequenceGenerator implements PortGenerator
{
    /**
     * @param  list<int>  $ports
     */
    public function __construct(
        private array $ports,
    ) {}

    public function generatePort(): int
    {
        $port = array_shift($this->ports);

        if (! is_int($port)) {
            throw new RuntimeException('No host port left to assign for mapped container ports.');
        }

        return $port;
    }
}
