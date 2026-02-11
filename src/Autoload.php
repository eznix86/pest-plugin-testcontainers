<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers;

use Pest\PendingCalls\TestCall;
use Pest\Plugin;
use RuntimeException;

function container(string $image): ContainerBuilder
{
    $test = test();

    if ($test instanceof TestCall) {
        throw new RuntimeException('The container() helper can only be used inside a running test closure.');
    }

    $testCase = $test->target;

    if (! is_callable([$testCase, 'container'])) {
        throw new RuntimeException('The container() helper requires a Pest test case using InteractsWithContainers.');
    }

    $container = $testCase->container($image);

    if (! $container instanceof ContainerBuilder) {
        throw new RuntimeException('Unexpected container() helper return type.');
    }

    return $container;
}

Plugin::uses(InteractsWithContainers::class);
