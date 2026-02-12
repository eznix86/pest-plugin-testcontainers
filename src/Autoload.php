<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers;

use Eznix86\PestPluginTestContainers\Builders\MeilisearchContainerBuilder;
use Eznix86\PestPluginTestContainers\Builders\MinioContainerBuilder;
use Eznix86\PestPluginTestContainers\Builders\MySqlContainerBuilder;
use Eznix86\PestPluginTestContainers\Builders\PostgresContainerBuilder;
use Eznix86\PestPluginTestContainers\Builders\RedisContainerBuilder;
use Eznix86\PestPluginTestContainers\Builders\TypesenseContainerBuilder;
use Illuminate\Support\Facades\Storage;
use Pest\PendingCalls\TestCall;
use Pest\Plugin;
use PHPUnit\Framework\Assert;
use RuntimeException;

function container(string $image): ContainerBuilder
{
    return resolveBaseContainerBuilder('container', $image);
}

function postgres(?string $version = null): PostgresContainerBuilder
{
    $image = $version !== null ? "postgres:{$version}" : 'postgres:latest';

    return new PostgresContainerBuilder(resolveBaseContainerBuilder('postgres', $image));
}

function mysql(?string $version = null): MySqlContainerBuilder
{
    $image = $version !== null ? "mysql:{$version}" : 'mysql:latest';

    return new MySqlContainerBuilder(resolveBaseContainerBuilder('mysql', $image));
}

function redis(?string $version = null): RedisContainerBuilder
{
    $image = $version !== null ? "redis:{$version}" : 'redis:alpine';

    return new RedisContainerBuilder(resolveBaseContainerBuilder('redis', $image));
}

function typesense(?string $version = null): TypesenseContainerBuilder
{
    $image = $version !== null ? "typesense/typesense:{$version}" : 'typesense/typesense:28.0';

    return new TypesenseContainerBuilder(resolveBaseContainerBuilder('typesense', $image));
}

function meilisearch(?string $version = null): MeilisearchContainerBuilder
{
    $image = $version !== null ? "getmeili/meilisearch:{$version}" : 'getmeili/meilisearch:latest';

    return new MeilisearchContainerBuilder(resolveBaseContainerBuilder('meilisearch', $image));
}

function minio(?string $version = null): MinioContainerBuilder
{
    $image = $version !== null ? "minio/minio:{$version}" : 'minio/minio:latest';

    return new MinioContainerBuilder(resolveBaseContainerBuilder('minio', $image));
}

function resolveBaseContainerBuilder(string $helperName, string $image): ContainerBuilder
{
    $test = test();

    if ($test instanceof TestCall) {
        throw new RuntimeException(sprintf('The %s() helper can only be used inside a running test closure.', $helperName));
    }

    $testCase = $test->target;

    if (! is_callable([$testCase, 'container'])) {
        throw new RuntimeException(sprintf(
            'The %s() helper requires a Pest test case using InteractsWithContainers.',
            $helperName,
        ));
    }

    $container = $testCase->container($image);

    if (! $container instanceof ContainerBuilder) {
        throw new RuntimeException('Unexpected container() helper return type.');
    }

    return $container;
}

Plugin::uses(InteractsWithContainers::class);

expect()->extend('toBeInStorage', function (?string $disk = null): object {
    $path = (string) $this->value;

    if ($disk === null) {
        $configuredDisk = config('filesystems.default');
        $disk = is_string($configuredDisk) && $configuredDisk !== '' ? $configuredDisk : 'testcontainer';
    }

    Assert::assertTrue(
        Storage::disk($disk)->exists($path),
        sprintf('Expected path "%s" to exist in storage disk "%s".', $path, $disk),
    );

    return $this;
});

expect()->extend('toNotBeInStorage', function (?string $disk = null): object {
    $path = (string) $this->value;

    if ($disk === null) {
        $configuredDisk = config('filesystems.default');
        $disk = is_string($configuredDisk) && $configuredDisk !== '' ? $configuredDisk : 'testcontainer';
    }

    Assert::assertFalse(
        Storage::disk($disk)->exists($path),
        sprintf('Expected path "%s" to not exist in storage disk "%s".', $path, $disk),
    );

    return $this;
});
