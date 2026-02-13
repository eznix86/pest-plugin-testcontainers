<?php

declare(strict_types=1);

use Eznix86\PestPluginTestContainers\Container\PortMapping\PortAvailabilityChecker;
use Eznix86\PestPluginTestContainers\Container\PortMapping\SaferRandomPortGenerator;
use Eznix86\PestPluginTestContainers\Container\PortMapping\SaferRandomUniquePortGenerator;
use Eznix86\PestPluginTestContainers\Container\PortMapping\WorkerPortAllocator;
use Eznix86\PestPluginTestContainers\Container\PortMapping\WorkerPortGenerator;
use Eznix86\PestPluginTestContainers\Container\PortMapping\WorkerPortSequence;
use Testcontainers\Utils\PortGenerator\PortGenerator;

function testPortMappingResetUniqueAssignedPorts(): void
{
    $reflection = new \ReflectionClass(SaferRandomUniquePortGenerator::class);
    $property = $reflection->getProperty('assignedPorts');
    $property->setAccessible(true);
    $property->setValue([]);
}

beforeEach(function () {
    testPortMappingResetUniqueAssignedPorts();
});

afterEach(function () {
    testPortMappingResetUniqueAssignedPorts();
});

it('should check tcp and udp port availability', function () {
    $checker = new PortAvailabilityChecker;

    $tcpServer = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
    expect($tcpServer)->not->toBeFalse();

    $tcpPort = portFromSocketAddress((string) stream_socket_get_name($tcpServer, false));

    expect($checker->isAvailable($tcpPort, 'tcp'))->toBeFalse();
    expect($checker->isAvailable($tcpPort, 'both'))->toBeFalse();

    fclose($tcpServer);

    expect($checker->isAvailable($tcpPort, 'both'))->toBeTrue();

    $udpServer = stream_socket_server('udp://127.0.0.1:0', $errorCode, $errorMessage, STREAM_SERVER_BIND);
    expect($udpServer)->not->toBeFalse();

    $udpPort = portFromSocketAddress((string) stream_socket_get_name($udpServer, false));

    expect($checker->isAvailable($udpPort, 'udp'))->toBeFalse();

    fclose($udpServer);
});

it('should return an available random port', function () {
    $port = (new SaferRandomPortGenerator(new PortAvailabilityChecker))->generatePort();

    expect($port)->toBeInt()
        ->and($port)->toBeGreaterThanOrEqual(10000)
        ->and($port)->toBeLessThanOrEqual(65535);
});

it('should ensure unique generated ports even when wrapped generator repeats values', function () {
    $generator = new class implements PortGenerator
    {
        /** @var list<int> */
        private array $ports = [12001, 12001, 12002];

        private int $index = 0;

        public function generatePort(): int
        {
            return $this->ports[$this->index++];
        }
    };

    $uniqueGenerator = new SaferRandomUniquePortGenerator($generator);

    expect($uniqueGenerator->generatePort())->toBe(12001)
        ->and($uniqueGenerator->generatePort())->toBe(12002);
});

it('should generate worker-scoped ports and fail after worker range is exhausted', function () {
    withEnvironmentSnapshot(['TEST_TOKEN', 'PEST_PARALLEL_PROCESS', 'PEST_WORKER'], function (): void {
        setEnvironmentValue('TEST_TOKEN', '3');
        setEnvironmentValue('PEST_PARALLEL_PROCESS', '8');
        setEnvironmentValue('PEST_WORKER', null);

        $sequence = new WorkerPortSequence;

        expect($sequence->nextPort())->toBe(49452);

        for ($i = 1; $i < 100; $i++) {
            $lastPort = $sequence->nextPort();
        }

        expect($lastPort ?? null)->toBe(49551);
        expect(fn () => $sequence->nextPort())
            ->toThrow(\RuntimeException::class, 'Worker port range exhausted.');
    });
});

it('should normalize negative worker id and expose allocator and generator wrappers', function () {
    withEnvironmentSnapshot(['TEST_TOKEN', 'PEST_PARALLEL_PROCESS', 'PEST_WORKER'], function (): void {
        setEnvironmentValue('TEST_TOKEN', null);
        setEnvironmentValue('PEST_PARALLEL_PROCESS', null);
        setEnvironmentValue('PEST_WORKER', '-7');

        $sequence = new WorkerPortSequence;
        $allocator = new WorkerPortAllocator;
        $generator = new WorkerPortGenerator;

        expect($sequence->nextPort())->toBe(49152)
            ->and($allocator->allocateForContainerPort(80))->toBe(49152)
            ->and($generator->generatePort())->toBe(49152);
    });
});
