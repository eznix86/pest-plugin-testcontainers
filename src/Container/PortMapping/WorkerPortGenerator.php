<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers\Container\PortMapping;

use Testcontainers\Utils\PortGenerator\PortGenerator;

final readonly class WorkerPortGenerator implements PortGenerator
{
    private WorkerPortSequence $sequence;

    public function __construct()
    {
        $this->sequence = new WorkerPortSequence;
    }

    public function generatePort(): int
    {
        return $this->sequence->nextPort();
    }
}
