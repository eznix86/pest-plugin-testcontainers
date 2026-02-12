<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers;

use Pest\Plugin;

function registerPluginUses(): void
{
    Plugin::uses(InteractsWithContainers::class);
}
