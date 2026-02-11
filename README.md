# Pest Plugin Testcontainers

`eznix86/pest-plugin-testcontainers` is a Laravel-focused Pest plugin that starts containers from your tests.

## Requirements

- PHP 8.3+
- Docker running locally
- PestPHP v4

## Install

```bash
composer require --dev eznix86/pest-plugin-testcontainers
```

The plugin autoloads itself.

## Quick Start (Laravel + Pest)

### 1) Add the trait to your base test case

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

### 2) Make Pest use that test case

`tests/Pest.php`

```php
<?php

use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature', 'Unit');
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

## function API

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

Note: the function helper still requires a running Pest test that uses a test case with `InteractsWithContainers`.

## Expectations

`$container->expect(...)` supports container-focused assertions:

- `$container->expect('echo hello')->toRunSuccessfully()->toContain('hello')`
- `$container->expect('/tmp/file')->toExist()->toBeReadable()->toNotExist()`
- `$container->expect('app started')->toBeInLogs()`

## API Reference

Builder methods available before `start()`:

```php
// container(...)
    ->ports(array $ports)
    ->env(array $env)
    ->labels(array $labels)
    ->volume(string $sourcePath, string $containerPath)
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

From `packages/pest-plugin-testcontainers`:

```bash
composer install
composer test
```

## Notes

- Started containers are tracked per test class and cleaned up during Laravel teardown.

## Credits

- [Testcontainers PHP](https://github.com/testcontainers/testcontainers-php)
