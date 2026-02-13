<?php

declare(strict_types=1);

use Eznix86\PestPluginTestContainers\Container\Reuse\WorkerTokenResolver;

use function Eznix86\PestPluginTestContainers\registerPluginUses;
use function Eznix86\PestPluginTestContainers\registerStorageExpectations;
use function Eznix86\PestPluginTestContainers\resolveStorageDisk;

it('should resolve and normalize worker token from configured environment variables', function () {
    withEnvironmentSnapshot([
        'TEST_TOKEN',
        'PARATEST',
        'PARATEST_PROCESS',
        'PEST_WORKER',
        'PEST_PARALLEL_PROCESS',
    ], function (): void {
        setEnvironmentValue('TEST_TOKEN', null);
        setEnvironmentValue('PARATEST_PROCESS', null);
        setEnvironmentValue('PEST_WORKER', null);
        setEnvironmentValue('PEST_PARALLEL_PROCESS', null);
        setEnvironmentValue('PARATEST', '  Worker #1  ');

        expect((new WorkerTokenResolver)->resolve())->toBe('Worker--1');

        setEnvironmentValue('TEST_TOKEN', 'alpha/beta');

        expect((new WorkerTokenResolver)->resolve())->toBe('alpha-beta');

        setEnvironmentValue('TEST_TOKEN', null);
        setEnvironmentValue('PARATEST', null);
        setEnvironmentValue('PEST_PARALLEL_PROCESS', 'proc@42');

        expect((new WorkerTokenResolver)->resolve())->toBe('proc-42');
    });
});

it('should return null when no valid worker token is available', function () {
    withEnvironmentSnapshot([
        'TEST_TOKEN',
        'PARATEST',
        'PARATEST_PROCESS',
        'PEST_WORKER',
        'PEST_PARALLEL_PROCESS',
    ], function (): void {
        setEnvironmentValue('TEST_TOKEN', null);
        setEnvironmentValue('PARATEST', null);
        setEnvironmentValue('PARATEST_PROCESS', " \n\t ");
        setEnvironmentValue('PEST_WORKER', null);
        setEnvironmentValue('PEST_PARALLEL_PROCESS', null);

        expect((new WorkerTokenResolver)->resolve())->toBeNull();
    });
});

it('should register plugin/bootstrap helpers and resolve storage disk variants', function () {
    registerPluginUses();
    registerStorageExpectations();

    require __DIR__.'/../../src/Autoload.php';

    config(['filesystems.default' => 'local']);

    expect(resolveStorageDisk('s3'))->toBe('s3')
        ->and(resolveStorageDisk(null))->toBe('local');

    config(['filesystems.default' => '']);

    expect(resolveStorageDisk(null))->toBe('testcontainer');
});
