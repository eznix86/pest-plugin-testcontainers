<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;

use function Eznix86\PestPluginTestContainers\mariadb;
use function Eznix86\PestPluginTestContainers\meilisearch;
use function Eznix86\PestPluginTestContainers\minio;
use function Eznix86\PestPluginTestContainers\mysql;
use function Eznix86\PestPluginTestContainers\postgres;
use function Eznix86\PestPluginTestContainers\redis;
use function Eznix86\PestPluginTestContainers\typesense;

function trimmedOutput($container, array $command): string
{
    return trim($container->exec($command)->output);
}

function eventuallyTrimmedOutput($container, array $command, int $attempts = 8, int $sleepMilliseconds = 250): string
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

it('injects postgres database configuration', function () {
    $builder = postgres()
        ->credentials('app_user', 'secret-pass')
        ->asDatabase('app_test_db')
        ->waitForCommand(['sh', '-lc', 'psql -U app_user -d app_test_db -tAc "SELECT 1"']);

    $container = $builder->start();
    $connection = $container->connectionName();
    $mappedPort = $container->mappedPort(5432);
    $activeDatabase = trimmedOutput($container, ['sh', '-lc', 'psql -U app_user -d app_test_db -tAc "SELECT current_database();"']);

    expect(config('database.default'))->toBe($connection)
        ->and($container->connectionName())->toBe($connection)
        ->and(config("database.connections.{$connection}.driver"))->toBe('pgsql')
        ->and(config("database.connections.{$connection}.database"))->toBe('app_test_db')
        ->and(config("database.connections.{$connection}.username"))->toBe($builder->username())
        ->and(config("database.connections.{$connection}.password"))->toBe($builder->password())
        ->and(config("database.connections.{$connection}.port"))->toBe($mappedPort)
        ->and($activeDatabase)->toBe('app_test_db')
        ->and(trimmedOutput($container, ['sh', '-lc', 'printenv POSTGRES_USER']))->toBe($builder->username())
        ->and(trimmedOutput($container, ['sh', '-lc', 'printenv POSTGRES_PASSWORD']))->toBe($builder->password())
        ->and(trimmedOutput($container, ['sh', '-lc', 'printenv POSTGRES_DB']))->toBe('app_test_db');
});

it('generates and uses a random postgres database name by default', function () {
    $builder = postgres()->asDatabase();
    $databaseName = $builder->databaseName();
    $builder->waitForCommand(['sh', '-lc', sprintf('psql -U postgres -d %s -tAc "SELECT 1"', $databaseName)]);

    $container = $builder->start();
    $connection = $container->connectionName();
    $activeDatabase = eventuallyTrimmedOutput($container, ['sh', '-lc', sprintf('psql -U postgres -d %s -tAc "SELECT current_database();"', $databaseName)]);

    expect($databaseName)->toStartWith('test_')
        ->and($connection)->toStartWith('testcontainer_')
        ->and(config("database.connections.{$connection}.database"))->toBe($databaseName)
        ->and($activeDatabase)->toBe($databaseName)
        ->and(trimmedOutput($container, ['sh', '-lc', 'printenv POSTGRES_DB']))->toBe($databaseName);
});

it('injects mysql database configuration', function () {
    $builder = mysql()
        ->credentials('app_user', 'secret-pass')
        ->asDatabase('app_test_db')
        ->waitForCommand(['sh', '-lc', 'mysql -uapp_user -psecret-pass -D app_test_db -N -e "SELECT 1" 2>/dev/null']);

    $container = $builder->start();
    $connection = $container->connectionName();
    $mappedPort = $container->mappedPort(3306);
    $activeDatabase = eventuallyTrimmedOutput($container, ['sh', '-lc', 'mysql -uapp_user -psecret-pass -D app_test_db -N -e "SELECT DATABASE();" 2>/dev/null']);

    expect(config('database.default'))->toBe($connection)
        ->and(config("database.connections.{$connection}.driver"))->toBe('mysql')
        ->and(config("database.connections.{$connection}.database"))->toBe('app_test_db')
        ->and(config("database.connections.{$connection}.username"))->toBe($builder->username())
        ->and(config("database.connections.{$connection}.password"))->toBe($builder->password())
        ->and(config("database.connections.{$connection}.port"))->toBe($mappedPort)
        ->and($activeDatabase)->toBe('app_test_db')
        ->and(trimmedOutput($container, ['sh', '-lc', 'printenv MYSQL_USER']))->toBe($builder->username())
        ->and(trimmedOutput($container, ['sh', '-lc', 'printenv MYSQL_PASSWORD']))->toBe($builder->password())
        ->and(trimmedOutput($container, ['sh', '-lc', 'printenv MYSQL_DATABASE']))->toBe('app_test_db');
});

it('generates and uses a random mysql database name by default', function () {
    $builder = mysql()->asDatabase();
    $databaseName = $builder->databaseName();
    $password = $builder->password();

    $builder->waitForCommand(['sh', '-lc', sprintf('mysql -uroot -p%s -D %s -N -e "SELECT 1" 2>/dev/null', $password, $databaseName)]);

    $container = $builder->start();
    $connection = $container->connectionName();
    $activeDatabase = eventuallyTrimmedOutput($container, ['sh', '-lc', sprintf('mysql -uroot -p%s -D %s -N -e "SELECT DATABASE();" 2>/dev/null', $password, $databaseName)]);

    expect($databaseName)->toStartWith('test_')
        ->and($connection)->toStartWith('testcontainer_')
        ->and(config("database.connections.{$connection}.database"))->toBe($databaseName)
        ->and($activeDatabase)->toBe($databaseName)
        ->and(trimmedOutput($container, ['sh', '-lc', 'printenv MYSQL_DATABASE']))->toBe($databaseName);
});

it('injects mariadb database configuration', function () {
    $builder = mariadb()
        ->credentials('app_user', 'secret-pass')
        ->asDatabase('app_test_db')
        ->waitForCommand(['sh', '-lc', 'mariadb -uapp_user -psecret-pass -D app_test_db -N -e "SELECT 1" 2>/dev/null']);

    $container = $builder->start();
    $connection = $container->connectionName();
    $mappedPort = $container->mappedPort(3306);
    $activeDatabase = eventuallyTrimmedOutput($container, ['sh', '-lc', 'mariadb -uapp_user -psecret-pass -D app_test_db -N -e "SELECT DATABASE();" 2>/dev/null']);

    expect(config('database.default'))->toBe($connection)
        ->and(config("database.connections.{$connection}.driver"))->toBe('mysql')
        ->and(config("database.connections.{$connection}.database"))->toBe('app_test_db')
        ->and(config("database.connections.{$connection}.username"))->toBe($builder->username())
        ->and(config("database.connections.{$connection}.password"))->toBe($builder->password())
        ->and(config("database.connections.{$connection}.port"))->toBe($mappedPort)
        ->and($activeDatabase)->toBe('app_test_db')
        ->and(trimmedOutput($container, ['sh', '-lc', 'printenv MARIADB_USER']))->toBe($builder->username())
        ->and(trimmedOutput($container, ['sh', '-lc', 'printenv MARIADB_PASSWORD']))->toBe($builder->password())
        ->and(trimmedOutput($container, ['sh', '-lc', 'printenv MARIADB_DATABASE']))->toBe('app_test_db');
});

it('injects postgres cache configuration through database cache store', function () {
    $builder = postgres()->asCache();
    $container = $builder->start();
    $connection = $container->connectionName();

    expect(config('cache.default'))->toBe($connection)
        ->and(config("cache.stores.{$connection}.driver"))->toBe('database')
        ->and(config("cache.stores.{$connection}.connection"))->toBe($connection)
        ->and(config("database.connections.{$connection}.driver"))->toBe('pgsql');
});

it('injects mysql cache configuration through database cache store', function () {
    $builder = mysql()->asCache();
    $container = $builder->start();
    $connection = $container->connectionName();

    expect(config('cache.default'))->toBe($connection)
        ->and(config("cache.stores.{$connection}.driver"))->toBe('database')
        ->and(config("cache.stores.{$connection}.connection"))->toBe($connection)
        ->and(config("database.connections.{$connection}.driver"))->toBe('mysql');
});

it('injects mariadb cache configuration through database cache store', function () {
    $builder = mariadb()->asCache();
    $container = $builder->start();
    $connection = $container->connectionName();

    expect(config('cache.default'))->toBe($connection)
        ->and(config("cache.stores.{$connection}.driver"))->toBe('database')
        ->and(config("cache.stores.{$connection}.connection"))->toBe($connection)
        ->and(config("database.connections.{$connection}.driver"))->toBe('mysql');
});

it('uses reuse name as connection name, including per worker suffix', function () {
    $previousToken = $_SERVER['TEST_TOKEN'] ?? $_ENV['TEST_TOKEN'] ?? null;
    $_ENV['TEST_TOKEN'] = $_SERVER['TEST_TOKEN'] = '200';

    try {
        $builder = postgres()
            ->reuse('shared-postgres', perWorker: true)
            ->asDatabase('shared_db')
            ->waitForCommand(['sh', '-lc', 'psql -U postgres -d shared_db -tAc "SELECT 1"']);

        $container = $builder->start();
        $connection = $container->connectionName();

        expect($connection)->toBe('shared-postgres-worker-200')
            ->and($container->connectionName())->toBe($connection)
            ->and(config('database.default'))->toBe($connection)
            ->and(config("database.connections.{$connection}.database"))->toBe('shared_db')
            ->and($container->mappedPort(5432))->toBeInt();
    } finally {
        unset($_ENV['TEST_TOKEN'], $_SERVER['TEST_TOKEN']);
        if ($previousToken !== null) {
            $_ENV['TEST_TOKEN'] = $_SERVER['TEST_TOKEN'] = $previousToken;
        }
    }
});

it('allows overriding the helper image while preserving builder configuration', function () {
    $builder = postgres('15')
        ->asDatabase('image_override_db')
        ->image('postgres:16')
        ->waitForCommand(['sh', '-lc', 'psql -U postgres -d image_override_db -tAc "SELECT 1"']);

    $container = $builder->start();
    $versionOutput = trimmedOutput($container, ['sh', '-lc', 'psql --version']);

    expect($versionOutput)->toMatch('/\\b16\\./')
        ->and(config("database.connections.{$container->connectionName()}.database"))->toBe('image_override_db');
});

it('injects redis cache configuration', function () {
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

it('injects redis queue configuration', function () {
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

it('injects typesense search configuration', function () {
    $builder = typesense()
        ->passphrase('typesense-key')
        ->asSearch();

    $container = $builder->start();
    $mappedPort = $container->mappedPort(8108);

    expect(config('scout.driver'))->toBe('typesense')
        ->and(config('scout.typesense.api_key'))->toBe($builder->getPassphrase())
        ->and(config('scout.typesense.protocol'))->toBe('http')
        ->and(config('scout.typesense.host'))->toBe($container->host())
        ->and(config('scout.typesense.port'))->toBe($mappedPort)
        ->and(trimmedOutput($container, ['sh', '-lc', 'printenv TYPESENSE_API_KEY']))->toBe($builder->getPassphrase());
});

it('injects meilisearch configuration', function () {
    $builder = meilisearch()
        ->passphrase('meili-key')
        ->asSearch();

    $container = $builder->start();
    $mappedPort = $container->mappedPort(7700);

    expect(config('scout.driver'))->toBe('meilisearch')
        ->and(config('scout.meilisearch.key'))->toBe($builder->getPassphrase())
        ->and(config('scout.meilisearch.host'))->toBe($container->host())
        ->and(config('scout.meilisearch.port'))->toBe($mappedPort)
        ->and(trimmedOutput($container, ['sh', '-lc', 'printenv MEILI_MASTER_KEY']))->toBe($builder->getPassphrase());
});

it('injects minio storage configuration', function () {
    $builder = minio()
        ->credentials('storage_user', 'storage_pass')
        ->asStorage();

    $container = $builder->start();
    $disk = $container->connectionName();
    $mappedPort = $container->mappedPort(9000);

    expect(config('filesystems.default'))->toBe($disk)
        ->and(config("filesystems.disks.{$disk}.driver"))->toBe('s3')
        ->and(config("filesystems.disks.{$disk}.key"))->toBe($builder->username())
        ->and(config("filesystems.disks.{$disk}.secret"))->toBe($builder->password())
        ->and(config("filesystems.disks.{$disk}.endpoint"))->toBe(sprintf('http://%s:%d', $container->host(), $mappedPort))
        ->and(config("filesystems.disks.{$disk}.use_path_style_endpoint"))->toBeTrue()
        ->and(trimmedOutput($container, ['sh', '-lc', 'printenv MINIO_ROOT_USER']))->toBe($builder->username())
        ->and(trimmedOutput($container, ['sh', '-lc', 'printenv MINIO_ROOT_PASSWORD']))->toBe($builder->password());
});

it('provides storage expectations', function () {
    config(['filesystems.default' => 'testcontainer']);
    Storage::fake('testcontainer');

    Storage::disk('testcontainer')->put('present.txt', 'ok');

    expect('present.txt')->toBeInStorage();
    expect('missing.txt')->toNotBeInStorage();
});
