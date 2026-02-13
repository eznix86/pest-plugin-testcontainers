<?php

declare(strict_types=1);

use Eznix86\PestPluginTestContainers\Container\StartedContainer;
use Eznix86\PestPluginTestContainers\Tests\TestCase;

/**
 * @return list<string>
 */
function idleContainerCommand(): array
{
    return ['sh', '-lc', 'while true; do sleep 1; done'];
}

/**
 * @param  null|callable(object):void  $configure
 */
function startIdleContainer(TestCase $testCase, string $image = 'alpine:3.20', ?callable $configure = null): StartedContainer
{
    $builder = $testCase->container($image)
        ->command(idleContainerCommand());

    if ($configure !== null) {
        $configure($builder);
    }

    return $builder->start();
}

function portFromSocketAddress(string $address): int
{
    $parts = explode(':', $address);

    return (int) end($parts);
}

/**
 * @param  array<string, ?string>  $variables
 */
function withTemporaryEnvironment(array $variables, callable $callback): mixed
{
    $snapshot = [];

    foreach ($variables as $key => $value) {
        $snapshot[$key] = getenv($key);
        setEnvironmentValue($key, $value);
    }

    try {
        return $callback();
    } finally {
        foreach ($snapshot as $key => $value) {
            setEnvironmentValue($key, $value === false ? null : $value);
        }
    }
}

function setEnvironmentValue(string $key, ?string $value): void
{
    if ($value === null) {
        unset($_SERVER[$key], $_ENV[$key]);
        putenv($key);

        return;
    }

    $_SERVER[$key] = $value;
    $_ENV[$key] = $value;
    putenv($key.'='.$value);
}

/**
 * @param  list<string>  $command
 */
function trimmedOutput(StartedContainer $container, array $command): string
{
    return trim($container->exec($command)->output);
}

/**
 * @param  list<string>  $command
 */
function eventuallyTrimmedOutput(StartedContainer $container, array $command, int $attempts = 8, int $sleepMilliseconds = 250): string
{
    $lastExitCode = null;
    $lastOutput = '';

    for ($attempt = 0; $attempt < $attempts; $attempt++) {
        $result = $container->exec($command);
        $lastExitCode = $result->exitCode;
        $lastOutput = trim($result->output);

        if ($result->successful() && $lastOutput !== '') {
            return $lastOutput;
        }

        usleep($sleepMilliseconds * 1000);
    }

    throw new RuntimeException(sprintf(
        'Command did not return successful non-empty output after %d attempts. Exit code: %s. Output: %s',
        $attempts,
        $lastExitCode === null ? 'unknown' : (string) $lastExitCode,
        $lastOutput,
    ));
}
