<?php

declare(strict_types=1);

use Eznix86\PestPluginTestContainers\StartedContainer;

use function Eznix86\PestPluginTestContainers\container;

it('provides a namespaced container helper function', function () {
    $startedContainer = container('alpine:3.20')
        ->command(['sh', '-lc', 'while true; do sleep 1; done'])
        ->waitForCommand(['sh', '-lc', 'test -d /bin'])
        ->start();

    expect($startedContainer)->toBeInstanceOf(StartedContainer::class);

    $startedContainer
        ->expect('echo via-function')
        ->toRunSuccessfully()
        ->toContain('via-function');
});
