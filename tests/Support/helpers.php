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
 * @param  list<string>  $keys
 * @return array<string, array{server: mixed, env: mixed, getenv: mixed}>
 */
function snapshotEnvironment(array $keys): array
{
    $snapshot = [];

    foreach ($keys as $key) {
        $snapshot[$key] = [
            'server' => $_SERVER[$key] ?? null,
            'env' => $_ENV[$key] ?? null,
            'getenv' => getenv($key) === false ? null : getenv($key),
        ];
    }

    return $snapshot;
}

/**
 * @param  array<string, array{server: mixed, env: mixed, getenv: mixed}>  $snapshot
 */
function restoreEnvironment(array $snapshot): void
{
    foreach ($snapshot as $key => $values) {
        unset($_SERVER[$key], $_ENV[$key]);
        putenv($key);

        if ($values['server'] !== null) {
            $_SERVER[$key] = $values['server'];
        }

        if ($values['env'] !== null) {
            $_ENV[$key] = $values['env'];
        }

        if (is_string($values['getenv'])) {
            putenv($key.'='.$values['getenv']);
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
 * @param  list<string>  $keys
 */
function withEnvironmentSnapshot(array $keys, callable $callback): mixed
{
    $snapshot = snapshotEnvironment($keys);

    try {
        return $callback();
    } finally {
        restoreEnvironment($snapshot);
    }
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
