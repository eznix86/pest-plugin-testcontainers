<?php

declare(strict_types=1);

use Eznix86\PestPluginTestContainers\Container\Reuse\WorkerTokenResolver;

use function Eznix86\PestPluginTestContainers\registerPluginUses;
use function Eznix86\PestPluginTestContainers\registerStorageExpectations;
use function Eznix86\PestPluginTestContainers\resolveStorageDisk;

it('should resolve worker token from TEST_TOKEN when numeric', function () {
    withTemporaryEnvironment(['TEST_TOKEN' => '4'], function (): void {
        expect((new WorkerTokenResolver)->resolve())->toBe('4');
    });
});

it('should return null when TEST_TOKEN is missing or not numeric', function () {
    withTemporaryEnvironment(['TEST_TOKEN' => null], function (): void {
        expect((new WorkerTokenResolver)->resolve())->toBeNull();
    });

    withTemporaryEnvironment(['TEST_TOKEN' => 'worker-one'], function (): void {
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
