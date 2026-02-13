<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;

use function Eznix86\PestPluginTestContainers\minio;

it('should inject minio storage configuration', function () {
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

it('should provide storage expectations', function () {
    config(['filesystems.default' => 'testcontainer']);
    Storage::fake('testcontainer');

    Storage::disk('testcontainer')->put('present.txt', 'ok');

    expect('present.txt')->toBeInStorage();
    expect('missing.txt')->toNotBeInStorage();
});
