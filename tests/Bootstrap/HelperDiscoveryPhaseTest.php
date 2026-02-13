<?php

declare(strict_types=1);

use function Eznix86\PestPluginTestContainers\container;

$helperDiscoveryPhaseMessage = null;

try {
    container('alpine:3.20');
} catch (\Throwable $throwable) {
    $helperDiscoveryPhaseMessage = $throwable->getMessage();
}

it('should report a meaningful error when helper is called during discovery phase', function () use (&$helperDiscoveryPhaseMessage) {
    expect($helperDiscoveryPhaseMessage)->toBeString();
});
