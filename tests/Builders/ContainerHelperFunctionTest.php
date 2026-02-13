<?php

declare(strict_types=1);

use Eznix86\PestPluginTestContainers\Container\StartedContainer;

use function Eznix86\PestPluginTestContainers\container;

it('should provide a namespaced container helper function', function () {
    $startedContainer = container('alpine:3.20')
        ->command(idleContainerCommand())
        ->waitForCommand(['sh', '-lc', 'test -d /bin'])
        ->start();

    expect($startedContainer)->toBeInstanceOf(StartedContainer::class);

    $startedContainer
        ->expect('echo via-function')
        ->toRunSuccessfully()
        ->toContain('via-function');
});
