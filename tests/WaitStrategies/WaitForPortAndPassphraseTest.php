<?php

declare(strict_types=1);

use Eznix86\PestPluginTestContainers\Concerns\HasSinglePassPhrase;
use Eznix86\PestPluginTestContainers\Container\WaitForPort;
use Testcontainers\Container\StartedTestContainer;
use Testcontainers\Exception\ContainerWaitingTimeoutException;

it('should wait until a mapped port is open', function () {
    $server = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
    expect($server)->not->toBeFalse();

    $openPort = portFromSocketAddress((string) stream_socket_get_name($server, false));

    /** @var StartedTestContainer&\PHPUnit\Framework\MockObject\MockObject $container */
    $container = $this->createMock(StartedTestContainer::class);
    $container->method('getHost')->willReturn('127.0.0.1');
    $container->method('getMappedPort')->with(8080)->willReturn($openPort);

    (new WaitForPort(port: 8080, timeout: 400, pollInterval: 10))->wait($container);

    fclose($server);
});

it('should use first mapped port when no explicit port is provided', function () {
    $server = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
    expect($server)->not->toBeFalse();

    $openPort = portFromSocketAddress((string) stream_socket_get_name($server, false));

    /** @var StartedTestContainer&\PHPUnit\Framework\MockObject\MockObject $container */
    $container = $this->createMock(StartedTestContainer::class);
    $container->method('getHost')->willReturn('127.0.0.1');
    $container->method('getFirstMappedPort')->willReturn($openPort);

    (new WaitForPort(timeout: 400, pollInterval: 10))->wait($container);

    fclose($server);
});

it('should time out when the mapped port never opens', function () {
    /** @var StartedTestContainer&\PHPUnit\Framework\MockObject\MockObject $container */
    $container = $this->createMock(StartedTestContainer::class);
    $container->method('getId')->willReturn('fake-id');
    $container->method('getHost')->willReturn('127.0.0.1');
    $container->method('getMappedPort')->with(80)->willReturn(65534);

    expect(fn () => (new WaitForPort(port: 80, timeout: 40, pollInterval: 5))->wait($container))
        ->toThrow(ContainerWaitingTimeoutException::class);
});

it('should generate one passphrase and reuse it until overridden', function () {
    $subject = new class
    {
        use HasSinglePassPhrase;
    };

    $generated = $subject->getPassphrase();

    expect($generated)->toBeString()
        ->and(strlen($generated))->toBe(32)
        ->and($subject->getPassphrase())->toBe($generated)
        ->and($subject->passphrase('manual-secret')->getPassphrase())->toBe('manual-secret');
});
