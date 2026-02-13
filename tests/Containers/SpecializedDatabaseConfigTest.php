<?php

declare(strict_types=1);

use function Eznix86\PestPluginTestContainers\mariadb;
use function Eznix86\PestPluginTestContainers\mysql;
use function Eznix86\PestPluginTestContainers\postgres;

dataset('databaseServices', [
    'postgres' => [
        fn () => postgres()
            ->credentials('app_user', 'secret-pass')
            ->asDatabase('app_test_db')
            ->waitForCommand(['sh', '-lc', '/usr/bin/pg_isready -h 127.0.0.1 -p 5432 -U "$POSTGRES_USER" -d "$POSTGRES_DB"']),
        'pgsql',
        5432,
        ['sh', '-lc', 'psql -U app_user -d app_test_db -tAc "SELECT current_database();"'],
        false,
        'POSTGRES_USER',
        'POSTGRES_PASSWORD',
        'POSTGRES_DB',
    ],
    'mysql' => [
        fn () => mysql()
            ->credentials('app_user', 'secret-pass')
            ->asDatabase('app_test_db')
            ->waitForCommand(['sh', '-lc', 'mysqladmin ping -h 127.0.0.1 -uroot -p"$MYSQL_ROOT_PASSWORD" --silent']),
        'mysql',
        3306,
        ['sh', '-lc', 'mysql -uapp_user -psecret-pass -D app_test_db -N -e "SELECT DATABASE();" 2>/dev/null'],
        true,
        'MYSQL_USER',
        'MYSQL_PASSWORD',
        'MYSQL_DATABASE',
    ],
    'mariadb' => [
        fn () => mariadb()
            ->credentials('app_user', 'secret-pass')
            ->asDatabase('app_test_db')
            ->waitForCommand(['sh', '-lc', 'mariadb -uapp_user -psecret-pass -D app_test_db -N -e "SELECT 1" 2>/dev/null']),
        'mysql',
        3306,
        ['sh', '-lc', 'mariadb -uapp_user -psecret-pass -D app_test_db -N -e "SELECT DATABASE();" 2>/dev/null'],
        true,
        'MARIADB_USER',
        'MARIADB_PASSWORD',
        'MARIADB_DATABASE',
    ],
]);

it('should inject database configuration for each service', function (callable $makeBuilder, string $driver, int $containerPort, array $activeDatabaseCommand, bool $activeDatabaseUsesRetry, string $envUserKey, string $envPasswordKey, string $envDatabaseKey) {
    $builder = $makeBuilder();

    $container = $builder->start();
    $connection = $container->connectionName();
    $mappedPort = $container->mappedPort($containerPort);
    $activeDatabase = $activeDatabaseUsesRetry
        ? eventuallyTrimmedOutput($container, $activeDatabaseCommand)
        : trimmedOutput($container, $activeDatabaseCommand);

    expect(config('database.default'))->toBe($connection)
        ->and($container->connectionName())->toBe($connection)
        ->and(config("database.connections.{$connection}.driver"))->toBe($driver)
        ->and(config("database.connections.{$connection}.database"))->toBe('app_test_db')
        ->and(config("database.connections.{$connection}.username"))->toBe($builder->username())
        ->and(config("database.connections.{$connection}.password"))->toBe($builder->password())
        ->and(config("database.connections.{$connection}.port"))->toBe($mappedPort)
        ->and($activeDatabase)->toBe('app_test_db')
        ->and(trimmedOutput($container, ['sh', '-lc', "printenv {$envUserKey}"]))->toBe($builder->username())
        ->and(trimmedOutput($container, ['sh', '-lc', "printenv {$envPasswordKey}"]))->toBe($builder->password())
        ->and(trimmedOutput($container, ['sh', '-lc', "printenv {$envDatabaseKey}"]))->toBe('app_test_db');
})->with('databaseServices');

it('should generate and use a random postgres database name by default', function () {
    $builder = postgres()->asDatabase();
    $databaseName = $builder->databaseName();
    $builder->waitForCommand(['sh', '-lc', '/usr/bin/pg_isready -h 127.0.0.1 -p 5432 -U "$POSTGRES_USER" -d "$POSTGRES_DB"']);

    $container = $builder->start();
    $connection = $container->connectionName();
    $activeDatabase = eventuallyTrimmedOutput($container, ['sh', '-lc', sprintf('psql -U postgres -d %s -tAc "SELECT current_database();"', $databaseName)]);

    expect($databaseName)->toStartWith('test_')
        ->and($connection)->toStartWith('testcontainer_')
        ->and(config("database.connections.{$connection}.database"))->toBe($databaseName)
        ->and($activeDatabase)->toBe($databaseName)
        ->and(trimmedOutput($container, ['sh', '-lc', 'printenv POSTGRES_DB']))->toBe($databaseName);
});

it('should generate and use a random mysql database name by default', function () {
    $builder = mysql()->asDatabase();
    $databaseName = $builder->databaseName();
    $password = $builder->password();

    $builder->waitForCommand(['sh', '-lc', 'mysqladmin ping -h 127.0.0.1 -uroot -p"$MYSQL_ROOT_PASSWORD" --silent']);

    $container = $builder->start();
    $connection = $container->connectionName();
    $activeDatabase = eventuallyTrimmedOutput($container, ['sh', '-lc', sprintf('mysql -uroot -p%s -D %s -N -e "SELECT DATABASE();" 2>/dev/null', $password, $databaseName)]);

    expect($databaseName)->toStartWith('test_')
        ->and($connection)->toStartWith('testcontainer_')
        ->and(config("database.connections.{$connection}.database"))->toBe($databaseName)
        ->and($activeDatabase)->toBe($databaseName)
        ->and(trimmedOutput($container, ['sh', '-lc', 'printenv MYSQL_DATABASE']))->toBe($databaseName);
});

it('should use reuse name as connection name, including per worker suffix', function () {
    withEnvironmentSnapshot(['TEST_TOKEN'], function (): void {
        setEnvironmentValue('TEST_TOKEN', '200');

        $builder = postgres()
            ->reuse('shared-postgres', perWorker: true)
            ->asDatabase('shared_db')
            ->waitForCommand(['sh', '-lc', '/usr/bin/pg_isready -h 127.0.0.1 -p 5432 -U "$POSTGRES_USER" -d "$POSTGRES_DB"']);

        $container = $builder->start();
        $connection = $container->connectionName();

        expect($connection)->toBe('shared-postgres-worker-200')
            ->and($container->connectionName())->toBe($connection)
            ->and(config('database.default'))->toBe($connection)
            ->and(config("database.connections.{$connection}.database"))->toBe('shared_db')
            ->and($container->mappedPort(5432))->toBeInt();
    });
});

it('should allow overriding the helper image while preserving builder configuration', function () {
    $builder = postgres('15')
        ->asDatabase('image_override_db')
        ->image('postgres:16')
        ->waitForCommand(['sh', '-lc', '/usr/bin/pg_isready -h 127.0.0.1 -p 5432 -U "$POSTGRES_USER" -d "$POSTGRES_DB"']);

    $container = $builder->start();
    $versionOutput = trimmedOutput($container, ['sh', '-lc', 'psql --version']);

    expect($versionOutput)->toMatch('/\\b16\\./')
        ->and(config("database.connections.{$container->connectionName()}.database"))->toBe('image_override_db');
});
