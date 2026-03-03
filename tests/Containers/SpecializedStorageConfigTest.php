<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;

use function Eznix86\PestPluginTestContainers\garage;
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
        ->and(config("filesystems.disks.{$disk}.key"))->toBe($builder->accessKey())
        ->and(config("filesystems.disks.{$disk}.secret"))->toBe($builder->secretKey())
        ->and(config("filesystems.disks.{$disk}.visibility"))->toBe('private')
        ->and(config("filesystems.disks.{$disk}.endpoint"))->toBe(sprintf('http://%s:%d', $container->host(), $mappedPort))
        ->and(config("filesystems.disks.{$disk}.use_path_style_endpoint"))->toBeTrue()
        ->and(trimmedOutput($container, ['sh', '-lc', 'printenv MINIO_ROOT_USER']))->toBe($builder->accessKey())
        ->and(trimmedOutput($container, ['sh', '-lc', 'printenv MINIO_ROOT_PASSWORD']))->toBe($builder->secretKey());
});

it('should apply public mode for minio disk and bucket visibility', function () {
    $builder = minio()
        ->credentials('storage_user', 'storage_pass')
        ->public()
        ->asStorage();

    $container = $builder->start();
    $disk = $container->connectionName();

    $anonymousPolicy = trimmedOutput($container, ['mc', 'anonymous', 'get', 'local/test']);

    expect(config("filesystems.disks.{$disk}.visibility"))->toBe('public')
        ->and($anonymousPolicy)->toContain('Access permission for `local/test` is `download`');
});

it('should apply private mode for minio disk and bucket visibility', function () {
    $builder = minio()
        ->credentials('storage_user', 'storage_pass')
        ->public()
        ->private()
        ->asStorage();

    $container = $builder->start();
    $disk = $container->connectionName();

    $anonymousPolicy = trimmedOutput($container, ['mc', 'anonymous', 'get', 'local/test']);

    expect(config("filesystems.disks.{$disk}.visibility"))->toBe('private')
        ->and($anonymousPolicy)->toContain('Access permission for `local/test` is `private`');
});

it('should inject garage storage configuration with normalized credentials', function () {
    $builder = garage()
        ->credentials('garage-key', 'garage-secret')
        ->asStorage();

    $container = $builder->start();
    $disk = $container->connectionName();
    $mappedPort = $container->mappedPort(3900);

    expect(config('filesystems.default'))->toBe($disk)
        ->and(config("filesystems.disks.{$disk}.driver"))->toBe('s3')
        ->and(config("filesystems.disks.{$disk}.key"))->toBe($builder->accessKey())
        ->and(config("filesystems.disks.{$disk}.secret"))->toBe($builder->secretKey())
        ->and(config("filesystems.disks.{$disk}.region"))->toBe('garage')
        ->and(config("filesystems.disks.{$disk}.visibility"))->toBe('private')
        ->and(config("filesystems.disks.{$disk}.endpoint"))->toBe(sprintf('http://%s:%d', $container->host(), $mappedPort))
        ->and(config("filesystems.disks.{$disk}.use_path_style_endpoint"))->toBeTrue()
        ->and($builder->accessKey())->toStartWith('GK')
        ->and($builder->accessKey())->toMatch('/^GK[a-f0-9]{24}$/')
        ->and($builder->secretKey())->toMatch('/^[a-f0-9]{64}$/');
});

it('should apply public mode for garage disk visibility and website exposure', function () {
    $builder = garage()
        ->credentials('garage-key', 'garage-secret')
        ->public()
        ->asStorage();

    $container = $builder->start();
    $disk = $container->connectionName();

    expect(config("filesystems.disks.{$disk}.visibility"))->toBe('public');
});

it('should apply private mode for garage disk visibility and website exposure', function () {
    $builder = garage()
        ->credentials('garage-key', 'garage-secret')
        ->public()
        ->private()
        ->asStorage();

    $container = $builder->start();
    $disk = $container->connectionName();

    expect(config("filesystems.disks.{$disk}.visibility"))->toBe('private');
});

it('should provide storage expectations', function () {
    config(['filesystems.default' => 'testcontainer']);
    Storage::fake('testcontainer');

    Storage::disk('testcontainer')->put('present.txt', 'ok');

    expect('present.txt')->toBeInStorage();
    expect('missing.txt')->toNotBeInStorage();
});
