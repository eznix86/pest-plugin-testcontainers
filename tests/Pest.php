<?php

declare(strict_types=1);

use Eznix86\PestPluginTestContainers\Tests\TestCase;

require_once __DIR__.'/Support/helpers.php';

pest()->extend(TestCase::class)->in(__DIR__);

pest()
    ->group('integration')
    ->in(
        'Builders/ContainerBuilderTest.php',
        'Builders/ContainerHelperFunctionTest.php',
        'Containers',
        'Integration',
        'PortMapping',
    );
