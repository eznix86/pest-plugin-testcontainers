# Pest Plugin Testcontainers

`eznix86/pest-plugin-testcontainers` is a Laravel-focused Pest plugin that starts containers directly from your tests.

## Why use this plugin?

- Test your own containers to ensure it runs smoothly with the expected behavior.
- Spin up services (Postgres, Redis, Meilisearch, MinIO, and more) in tests with minimal setup.
- Integrates with Laravel (`database`, `cache`, `scout`, `storage`) in one fluent chain.

## Table of Contents

- [Requirements](#requirements)
- [Install](#install)
- [Quick Start (Laravel + Pest)](#quick-start-laravel--pest)
- [Function API](#function-api)
- [Laravel Helpers](#laravel-helpers)
  - [Database (`asDatabase`)](#database-asdatabase)
  - [Cache (`asCache`)](#cache-ascache)
  - [Search (`asSearch`)](#search-assearch)
  - [Storage (`asStorage`)](#storage-asstorage)
- [Expectations](#expectations)
- [API Reference](#api-reference)
- [Docker Comparison](#docker-comparison)
- [Local Development](#local-development)
- [Troubleshooting](#troubleshooting)
- [Notes](#notes)
- [Credits](#credits)

## Requirements

- PHP 8.3+
- Docker running locally
- PestPHP v4

## Install

```bash
composer require --dev eznix86/pest-plugin-testcontainers
```

The plugin autoloads itself and auto-registers the helper functions, expectations, and `InteractsWithContainers` trait for Pest tests.

## Quick Start (Laravel + Pest)

### 1) Make Pest use your Laravel base test case

`tests/Pest.php`

```php
<?php

use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature', 'Unit');
```

### 2) (Optional) Add the trait explicitly in your base test case

This is usually not required because the plugin auto-registers `InteractsWithContainers` for Pest tests.

`tests/TestCase.php`

```php
<?php

namespace Tests;

use Eznix86\PestPluginTestContainers\InteractsWithContainers;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use InteractsWithContainers;
}
```

### 3) Write your first container test

```php
it('starts nginx and exposes a mapped port', function () {
    $container = $this->container('nginx:alpine')
        ->ports([80])
        ->waitForHttp(path: '/', port: 80)
        ->start();

    expect($container->getGeneratedPortFor(80))->toBeInt();
});
```

## Function API

You can use either style:

- Trait style: `$this->container('image')->...->start()`
- Function style: `container('image')->...->start()`

Function-style example:

```php
use function Eznix86\PestPluginTestContainers\container;

it('starts nginx with the helper function', function () {
    $container = container('nginx:alpine')
        ->ports([80])
        ->waitForHttp(path: '/', port: 80)
        ->start();

    expect($container->getGeneratedPortFor(80))->toBeInt();
});
```

Note: the function helper must run inside an active Pest test. `InteractsWithContainers` is auto-registered by the plugin (or can be added manually).

## Laravel Helpers

Specialized helpers are available for common Laravel services:

- `postgres(?string $version = null)`
- `mysql(?string $version = null)`
- `mariadb(?string $version = null)`
- `redis(?string $version = null)`
- `typesense(?string $version = null)`
- `meilisearch(?string $version = null)`
- `minio(?string $version = null)`

All specialized builders also support:

- `->image(string $image)` to override the helper image

### Database (`asDatabase`)

Use `postgres()`, `mysql()`, or `mariadb()` with `asDatabase()` to inject a dedicated Laravel database connection and set it as default.
By default, the connection name is randomized per builder (`testcontainer_xxx`). If `reuse('name')` is enabled, the connection name matches the reuse name (including `perWorker` suffixes).
You can read that name from `$container->connectionName()` after `start()`.

```php
use function Eznix86\PestPluginTestContainers\postgres;

it('uses postgres as laravel database', function () {
    $builder = postgres('16')
        ->credentials('app_user', 'secret-pass')
        ->asDatabase(); // random database name by default

    $container = $builder->start();
    $connection = $container->connectionName();

    expect(config('database.default'))->toBe($connection)
        ->and(config("database.connections.{$connection}.driver"))->toBe('pgsql')
        ->and(config("database.connections.{$connection}.port"))->toBe($container->mappedPort(5432));
});
```

### Cache (`asCache`)

Use `redis()->asCache()` to configure Laravel cache against Redis in the test container.
Use `postgres()->asCache()`, `mysql()->asCache()`, or `mariadb()->asCache()` to configure Laravel cache with the database store over the injected database connection.

```php
use function Eznix86\PestPluginTestContainers\redis;

it('uses redis as laravel cache', function () {
    $builder = redis()->asCache();
    $container = $builder->start();
    $connection = $container->connectionName();

    expect(config('cache.default'))->toBe($connection)
        ->and(config("database.redis.{$connection}.port"))->toBe($container->mappedPort(6379));
});
```

### Search (`asSearch`)

Use `typesense()` or `meilisearch()` with `asSearch()` to inject Scout config and set `scout.driver`.

```php
use function Eznix86\PestPluginTestContainers\typesense;

it('uses typesense as laravel scout driver', function () {
    $container = typesense()
        ->passphrase('typesense-key')
        ->asSearch()
        ->start();

    expect(config('scout.driver'))->toBe('typesense')
        ->and(config('scout.typesense.port'))->toBe($container->mappedPort(8108));
});
```

### Storage (`asStorage`)

Use `minio()->asStorage()` to inject an S3 disk and set it as default.

```php
use Illuminate\Support\Facades\Storage;
use function Eznix86\PestPluginTestContainers\minio;

it('uses minio as laravel storage', function () {
    $builder = minio()->credentials('storage_user', 'storage_pass')->asStorage();
    $container = $builder->start();

    Storage::disk($container->connectionName())->put('hello.txt', 'ok');

    expect('hello.txt')->toBeInStorage();
    expect('missing.txt')->toNotBeInStorage();
});
```

## Expectations

`$container->expect(...)` supports container-focused assertions:

- `$container->expect('echo hello')->toRunSuccessfully()->toContain('hello')`
- `$container->expect('/tmp/file')->toExist()->toBeReadable()->toNotExist()`
- `$container->expect('app started')->toBeInLogs()`

Global storage expectations (useful with `minio()->asStorage()`):

- `expect('path/to/file')->toBeInStorage()`
- `expect('path/to/file')->toNotBeInStorage()`

## API Reference

Builder methods available before `start()`:

```php
// container(...)
    ->ports(array $ports)
    ->env(array $env)
    ->labels(array $labels)
    ->volume(string $sourcePath, string $containerPath)
    ->reuse(string $name, bool $perWorker = false)
    ->command(array $command)
    ->healthcheck(array $command)
    ->waitForLog(string $message, bool $regex = false, int $timeoutSeconds = 30, int $pollIntervalMilliseconds = 500)
    ->waitForHttp(
        string $path = '/',
        ?int $port = null,
        int $expectedStatusCode = 200,
        Testcontainers\Container\HttpMethod|string $method = Testcontainers\Container\HttpMethod::GET,
        bool $https = false,
        bool $allowInsecure = false,
        int $timeoutSeconds = 30,
        int $pollIntervalMilliseconds = 500,
        int $readTimeoutMilliseconds = 1000,
        array $headers = [],
    )
    ->waitForPort(?int $port = null, int $timeoutSeconds = 30, int $pollIntervalMilliseconds = 500)
    ->waitForCommand(array $command, int $timeoutSeconds = 30, int $pollIntervalMilliseconds = 500)
    ->start()
```

Specialized helper methods:

```php
// postgres(), mysql(), mariadb()
    ->credentials(string $username, string $password)
    ->asDatabase(?string $databaseName = null)
    ->asCache()
    ->asQueue(?string $connection = null)

// redis()
    ->passphrase(string $phrase)
    ->asCache()
    ->asQueue(?string $connection = null)

// typesense(), meilisearch()
    ->passphrase(string $phrase)
    ->asSearch()

// minio()
    ->credentials(string $username, string $password)
    ->asStorage(?string $disk = null)

// all specialized builders
    ->reuse(string $name, bool $perWorker = false)
    ->image(string $image)
```

Port mapping notes:

- `ports([80])` picks a free host port automatically.
- `ports([80 => 8080])` maps container `80` to host `8080`.

Expectation methods available from `$container->expect(...)`:

```php
// $container->expect(...)
    ->toExist()
    ->toNotExist()
    ->toBeDirectory()
    ->toBeReadable()
    ->toRunSuccessfully()
    ->toFail()
    ->toContain(string $needle)
    ->toBeInLogs()
```

Started container helpers:

```php
// $container
    ->connectionName() // nullable string, set when started from specialized builders
    ->host()
    ->mappedPort(int $containerPort)
    ->getGeneratedPortFor(int $containerPort)
    ->logs() // sanitized logs
    ->rawLogs() // raw Docker stream output
    ->exec(array $command) // returns ExecResult with sanitized output
    ->execRaw(array $command) // returns ExecResult with raw output
```

## Docker Comparison

```bash
docker run --rm \
  -p 8080:80 \
  -v "$(pwd)/tests/Fixtures/nginx/index.html:/usr/share/nginx/html/index.html" \
  --health-cmd="test -f /usr/share/nginx/html/index.html" \
  nginx:alpine
```

Equivalent test setup:

```php
$container = $this->container('nginx:alpine')
    ->ports([80 => 8080])
    ->volume(base_path('tests/Fixtures/nginx/index.html'), '/usr/share/nginx/html/index.html')
    ->healthcheck(['sh', '-lc', 'test -f /usr/share/nginx/html/index.html'])
    ->start();
```

## Local Development

From the project root:

```bash
composer install
composer test
```

## Troubleshooting

- **Docker is not running**: start Docker Desktop (or your Docker daemon) and rerun tests.
- **Container startup timeout**: add an explicit wait strategy (`waitForLog`, `waitForHttp`, `waitForPort`, or `waitForCommand`) and increase timeout values.
- **Port conflicts**: prefer dynamic host ports (`ports([80])`) over fixed host mappings (`ports([80 => 8080])`).
- **Parallel test collisions**: for reusable containers in parallel runs, use `->reuse('name', perWorker: true)`.

## Notes

- Started containers are tracked per test class and cleaned up during Laravel teardown.
- `->reuse('name', perWorker: false)` attaches to a running container with that Docker name when available, and keeps it running across test teardown.
- `->reuse('name', perWorker: true)` appends a worker token suffix in parallel runs so each worker gets its own reusable container name.
- Connection naming follows the same rule: randomized by default, reuse name when `->reuse(...)` is configured.

## Credits

- [Testcontainers PHP](https://github.com/testcontainers/testcontainers-php)
