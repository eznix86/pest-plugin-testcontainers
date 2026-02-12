<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers\ConfigInjectors;

use Eznix86\PestPluginTestContainers\Container\StartedContainer;

final class StorageConfigInjector
{
    public static function inject(
        StartedContainer $container,
        int $port,
        string $key,
        string $secret,
        ?string $disk = null,
        string $bucket = 'test',
        string $region = 'us-east-1'
    ): void {
        $disk ??= sprintf('testcontainer_%s', bin2hex(random_bytes(6)));

        config([
            "filesystems.disks.{$disk}" => [
                'driver' => 's3',
                'key' => $key,
                'secret' => $secret,
                'region' => $region,
                'bucket' => $bucket,
                'endpoint' => sprintf('http://%s:%d', $container->host(), $container->mappedPort($port)),
                'use_path_style_endpoint' => true,
            ],
            'filesystems.default' => $disk,
        ]);
    }
}
