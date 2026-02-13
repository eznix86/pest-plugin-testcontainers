<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers;

require_once __DIR__.'/Foundation/Bootstrap/Helpers.php';
require_once __DIR__.'/Foundation/Bootstrap/Plugin.php';
require_once __DIR__.'/Foundation/Bootstrap/Expectations.php';
require_once __DIR__.'/Foundation/Bootstrap/Cleanup.php';

registerPluginUses();
registerStorageExpectations();
registerManagedContainersShutdownCleanup();
