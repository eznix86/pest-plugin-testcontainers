<?php

declare(strict_types=1);

use function Eznix86\PestPluginTestContainers\mariadb;
use function Eznix86\PestPluginTestContainers\mysql;
use function Eznix86\PestPluginTestContainers\postgres;
use function Eznix86\PestPluginTestContainers\redis;

dataset('databaseCacheServices', [
    'postgres' => [
        fn () => postgres()
            ->asCache()
            ->waitForCommand(['sh', '-lc', '/usr/bin/pg_isready -h 127.0.0.1 -p 5432 -U "$POSTGRES_USER" -d "$POSTGRES_DB"']),
        'pgsql',
    ],
    'mysql' => [
        fn () => mysql()
            ->asCache()
            ->waitForCommand(['sh', '-lc', 'mysqladmin ping -h 127.0.0.1 -uroot -p"$MYSQL_ROOT_PASSWORD" --silent']),
        'mysql',
    ],
    'mariadb' => [
        fn () => mariadb()->asCache(),
        'mysql',
    ],
]);

it('should inject database-backed cache configuration for each service', function (callable $makeBuilder, string $driver) {
    $builder = $makeBuilder();
    $container = $builder->start();
    $connection = $container->connectionName();

    expect(config('cache.default'))->toBe($connection)
        ->and(config("cache.stores.{$connection}.driver"))->toBe('database')
        ->and(config("cache.stores.{$connection}.connection"))->toBe($connection)
        ->and(config("database.connections.{$connection}.driver"))->toBe($driver);
})->with('databaseCacheServices');

it('should inject redis cache configuration', function () {
    $builder = redis()->asCache();

    $container = $builder->start();
    $connection = $container->connectionName();

    $mappedPort = $container->mappedPort(6379);

    expect(config('cache.default'))->toBe($connection)
        ->and(config("cache.stores.{$connection}.driver"))->toBe('redis')
        ->and(config("cache.stores.{$connection}.connection"))->toBe($connection)
        ->and(config("database.redis.{$connection}.host"))->toBeString()
        ->and(config("database.redis.{$connection}.port"))->toBe($mappedPort);
});

it('should inject redis queue configuration', function () {
    $builder = redis()->asQueue();

    $container = $builder->start();
    $connection = $container->connectionName();

    $mappedPort = $container->mappedPort(6379);

    expect(config('queue.default'))->toBe($connection)
        ->and(config("queue.connections.{$connection}.driver"))->toBe('redis')
        ->and(config("queue.connections.{$connection}.connection"))->toBe($connection)
        ->and(config("database.redis.{$connection}.host"))->toBeString()
        ->and(config("database.redis.{$connection}.port"))->toBe($mappedPort);
});
