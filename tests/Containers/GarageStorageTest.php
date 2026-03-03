<?php

declare(strict_types=1);

use function Eznix86\PestPluginTestContainers\garage;

it('should allow mounting a custom garage toml config', function () {
    $path = tempnam(sys_get_temp_dir(), 'garage-test-');
    expect($path)->not->toBeFalse();

    file_put_contents($path, implode(PHP_EOL, [
        'metadata_dir = "/var/lib/garage/meta"',
        'data_dir = "/var/lib/garage/data"',
        'db_engine = "sqlite"',
        '',
        'replication_factor = 1',
        '',
        'rpc_bind_addr = "[::]:3901"',
        'rpc_public_addr = "127.0.0.1:3901"',
        sprintf('rpc_secret = "%s"', bin2hex(random_bytes(32))),
        '',
        '[s3_api]',
        's3_region = "garage"',
        'api_bind_addr = "[::]:3900"',
        '',
        '[s3_web]',
        'bind_addr = "[::]:3902"',
        'root_domain = ".localtest"',
        'index = "index.html"',
        '',
        '[admin]',
        'api_bind_addr = "[::]:3903"',
        sprintf('admin_token = "%s"', bin2hex(random_bytes(16))),
        sprintf('metrics_token = "%s"', bin2hex(random_bytes(16))),
        '',
    ]));

    try {
        $container = garage()
            ->withTomlConfig($path)
            ->credentials('garage-custom', 'garage-custom-secret')
            ->asStorage()
            ->start();

        $bucketInfo = $container->exec(['/garage', 'bucket', 'info', 'test']);

        expect($bucketInfo->successful())->toBeTrue()
            ->and(config("filesystems.disks.{$container->connectionName()}.region"))->toBe('garage');
    } finally {
        if (is_string($path) && $path !== '' && file_exists($path)) {
            unlink($path);
        }
    }
});
