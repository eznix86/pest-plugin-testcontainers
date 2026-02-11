<?php

declare(strict_types=1);

use Eznix86\PestPluginTestContainers\StartedContainer;
use Eznix86\PestPluginTestContainers\Tests\TestCase;
use Random\RandomException;

it(/**
 * @throws RandomException
 */ 'maps unique host ports for multiple nginx containers', function () {
    /** @var TestCase $testCase */
    $testCase = $this;

    /** @var list<StartedContainer> $containers */
    $containers = [];

    for ($index = 0; $index < 3; $index++) {
        $containers[] = $testCase->container('nginx:alpine')
            ->ports([80])
            ->start();
    }

    $ports = array_map(
        static fn (StartedContainer $container): int => $container->getGeneratedPortFor(80),
        $containers,
    );

    expect($ports)->toHaveCount(3)
        ->and(array_unique($ports))->toHaveCount(3);

    foreach ($containers as $index => $container) {

        try {
            $marker = sprintf('nginx-%d-%s', $index, bin2hex(random_bytes(8)));
        } catch (RandomException $e) {
            throw new RuntimeException('Unable to generate a random marker for nginx container.', 0, $e);
        }

        $container
            ->expect('printf %s '.escapeshellarg($marker).' > /usr/share/nginx/html/index.html')
            ->toRunSuccessfully();

        $url = sprintf('http://%s:%d', $container->host(), $container->getGeneratedPortFor(80));

        [$status, $body] = waitForReadyNginx($url, $marker);

        expect($status)->toBe(200)
            ->and($body)->toContain($marker);
    }
});

it(/**
 * @throws Throwable
 * @throws RandomException
 */ 'maps a requested host port for nginx', function () {
    /** @var TestCase $testCase */
    $testCase = $this;

    $container = null;

    for ($attempt = 0; $attempt < 10; $attempt++) {
        $requestedPort = random_int(20000, 60000);

        try {
            $container = $testCase->container('nginx:alpine')
                ->ports([80 => $requestedPort])
                ->start();

            break;
        } catch (Throwable $exception) {
            $message = strtolower($exception->getMessage());

            if (! str_contains($message, 'port') || ! str_contains($message, 'allocated')) {
                throw $exception;
            }
        }
    }

    if (! $container instanceof StartedContainer) {
        throw new RuntimeException('Unable to allocate a requested host port for nginx after retries.');
    }

    expect($container->getGeneratedPortFor(80))->toBe($requestedPort);

    $marker = 'fixed-port-'.bin2hex(random_bytes(8));

    $container
        ->expect('printf %s '.escapeshellarg($marker).' > /usr/share/nginx/html/index.html')
        ->toRunSuccessfully();

    $url = sprintf('http://%s:%d', $container->host(), $requestedPort);

    [$status, $body] = waitForReadyNginx($url, $marker);

    expect($status)->toBe(200)
        ->and($body)->toContain($marker);
});

/**
 * @return array{0: int, 1: string}
 */
function waitForReadyNginx(string $url, string $marker, int $attempts = 40, int $sleepMilliseconds = 250): array
{
    $lastStatus = 0;
    $lastBody = '';

    for ($attempt = 0; $attempt < $attempts; $attempt++) {
        [$status, $body] = httpGet($url, 1);
        $lastStatus = $status;
        $lastBody = $body;

        if ($status === 200 && str_contains($body, $marker)) {
            return [$status, $body];
        }

        usleep($sleepMilliseconds * 1000);
    }

    throw new RuntimeException(sprintf(
        'Expected %s to return marker "%s". Last status: %d. Last body: %s',
        $url,
        $marker,
        $lastStatus,
        $lastBody,
    ));
}

/**
 * @return array{0: int, 1: string}
 */
function httpGet(string $url, int $timeoutSeconds): array
{
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => $timeoutSeconds,
            'ignore_errors' => true,
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    $statusLine = $http_response_header[0] ?? '';
    $status = preg_match('/\s(\d{3})\s/', $statusLine, $matches) === 1
        ? (int) $matches[1]
        : 0;

    return [$status, is_string($body) ? $body : ''];
}
