<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers\Container\PortMapping;

final readonly class WorkerPortAllocator implements PortAllocator
{
    private WorkerPortSequence $sequence;

    public function __construct()
    {
        $this->sequence = new WorkerPortSequence;
    }

    public function allocateForContainerPort(int|string $containerPort): int
    {
        return $this->sequence->nextPort();
    }
}
