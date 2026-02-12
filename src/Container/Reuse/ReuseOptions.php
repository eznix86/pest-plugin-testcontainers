<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers\Container\Reuse;

final class ReuseOptions
{
    public ?string $name = null;

    public bool $perWorker = false;
}
