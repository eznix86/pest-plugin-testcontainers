<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers\Container\PortMapping;

interface PortAllocator
{
    public function allocateForContainerPort(int|string $containerPort): int;
}
