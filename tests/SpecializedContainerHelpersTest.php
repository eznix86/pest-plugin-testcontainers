<?php

declare(strict_types=1);

use Eznix86\PestPluginTestContainers\Builders\MariaDbContainerBuilder;
use Eznix86\PestPluginTestContainers\Builders\MeilisearchContainerBuilder;
use Eznix86\PestPluginTestContainers\Builders\MinioContainerBuilder;
use Eznix86\PestPluginTestContainers\Builders\MySqlContainerBuilder;
use Eznix86\PestPluginTestContainers\Builders\PostgresContainerBuilder;
use Eznix86\PestPluginTestContainers\Builders\RedisContainerBuilder;
use Eznix86\PestPluginTestContainers\Builders\TypesenseContainerBuilder;

use function Eznix86\PestPluginTestContainers\mariadb;
use function Eznix86\PestPluginTestContainers\meilisearch;
use function Eznix86\PestPluginTestContainers\minio;
use function Eznix86\PestPluginTestContainers\mysql;
use function Eznix86\PestPluginTestContainers\postgres;
use function Eznix86\PestPluginTestContainers\redis;
use function Eznix86\PestPluginTestContainers\typesense;

it('provides specialized container helper functions', function () {
    expect(postgres())->toBeInstanceOf(PostgresContainerBuilder::class)
        ->and(mysql())->toBeInstanceOf(MySqlContainerBuilder::class)
        ->and(mariadb())->toBeInstanceOf(MariaDbContainerBuilder::class)
        ->and(redis())->toBeInstanceOf(RedisContainerBuilder::class)
        ->and(typesense())->toBeInstanceOf(TypesenseContainerBuilder::class)
        ->and(meilisearch())->toBeInstanceOf(MeilisearchContainerBuilder::class)
        ->and(minio())->toBeInstanceOf(MinioContainerBuilder::class);
});

it('accepts version tags in specialized helpers', function () {
    expect(postgres('18'))->toBeInstanceOf(PostgresContainerBuilder::class)
        ->and(mysql('8.4'))->toBeInstanceOf(MySqlContainerBuilder::class)
        ->and(mariadb('11.8'))->toBeInstanceOf(MariaDbContainerBuilder::class)
        ->and(redis('7-alpine'))->toBeInstanceOf(RedisContainerBuilder::class)
        ->and(typesense('0.28.0'))->toBeInstanceOf(TypesenseContainerBuilder::class)
        ->and(meilisearch('v1.12'))->toBeInstanceOf(MeilisearchContainerBuilder::class)
        ->and(minio('RELEASE.2025-09-07T16-13-09Z-cpuv1'))->toBeInstanceOf(MinioContainerBuilder::class);
});
