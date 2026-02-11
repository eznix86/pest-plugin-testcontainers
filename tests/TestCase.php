<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers\Tests;

use Eznix86\PestPluginTestContainers\InteractsWithContainers;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use InteractsWithContainers;
}
