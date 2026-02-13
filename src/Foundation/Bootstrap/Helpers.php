<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers;

use Eznix86\PestPluginTestContainers\Builders\MariaDbContainerBuilder;
use Eznix86\PestPluginTestContainers\Builders\MeilisearchContainerBuilder;
use Eznix86\PestPluginTestContainers\Builders\MinioContainerBuilder;
use Eznix86\PestPluginTestContainers\Builders\MySqlContainerBuilder;
use Eznix86\PestPluginTestContainers\Builders\PostgresContainerBuilder;
use Eznix86\PestPluginTestContainers\Builders\RedisContainerBuilder;
use Eznix86\PestPluginTestContainers\Builders\TypesenseContainerBuilder;
use Eznix86\PestPluginTestContainers\Container\ContainerBuilder;
use Pest\PendingCalls\TestCall;
use RuntimeException;

function container(string $image): ContainerBuilder
{
    return resolveBaseContainerBuilder('container', $image);
}

function postgres(?string $version = null): PostgresContainerBuilder
{
    $image = resolveImage($version, 'postgres', 'latest');

    return new PostgresContainerBuilder(resolveBaseContainerBuilder('postgres', $image));
}

function mysql(?string $version = null): MySqlContainerBuilder
{
    $image = resolveImage($version, 'mysql', 'latest');

    return new MySqlContainerBuilder(resolveBaseContainerBuilder('mysql', $image));
}

function mariadb(?string $version = null): MariaDbContainerBuilder
{
    $image = resolveImage($version, 'mariadb', 'latest');

    return new MariaDbContainerBuilder(resolveBaseContainerBuilder('mariadb', $image));
}

function redis(?string $version = null): RedisContainerBuilder
{
    $image = resolveImage($version, 'redis', 'alpine');

    return new RedisContainerBuilder(resolveBaseContainerBuilder('redis', $image));
}

function typesense(?string $version = null): TypesenseContainerBuilder
{
    $image = resolveImage($version, 'typesense/typesense', '28.0');

    return new TypesenseContainerBuilder(resolveBaseContainerBuilder('typesense', $image));
}

function meilisearch(?string $version = null): MeilisearchContainerBuilder
{
    $image = resolveImage($version, 'getmeili/meilisearch', 'latest');

    return new MeilisearchContainerBuilder(resolveBaseContainerBuilder('meilisearch', $image));
}

function minio(?string $version = null): MinioContainerBuilder
{
    $image = resolveImage($version, 'minio/minio', 'latest');

    return new MinioContainerBuilder(resolveBaseContainerBuilder('minio', $image));
}

function resolveImage(?string $version, string $repository, string $defaultTag): string
{
    $tag = $version ?? $defaultTag;

    return sprintf('%s:%s', $repository, $tag);
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
