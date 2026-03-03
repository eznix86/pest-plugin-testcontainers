<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers\Builders;

use Eznix86\PestPluginTestContainers\Concerns\ConfiguresStorage;
use Eznix86\PestPluginTestContainers\Concerns\HasS3Keys;
use Eznix86\PestPluginTestContainers\Container\ContainerBuilder;
use Eznix86\PestPluginTestContainers\Container\StartedContainer;
use Eznix86\PestPluginTestContainers\Values\StorageAccessMode;
use RuntimeException;

final class MinioContainerBuilder extends SpecializedContainerBuilder
{
    use ConfiguresStorage;
    use HasS3Keys;

    private const int DEFAULT_PORT = 9000;

    private const int CONSOLE_PORT = 9001;

    public function __construct(ContainerBuilder $builder)
    {
        parent::__construct($builder, 'minio');

        $this->accessKey = 'minioadmin';
        $this->secretKey = 'minioadmin';
        $this->ports([self::DEFAULT_PORT, self::CONSOLE_PORT]);
        $this->waitForPort(self::DEFAULT_PORT);
    }

    protected function prepareContainer(): void
    {
        $this->builder->env([
            'MINIO_ROOT_USER' => $this->accessKey(),
            'MINIO_ROOT_PASSWORD' => $this->secretKey(),
        ]);

        $this->builder->command(['server', '/data']);
    }

    protected function getDefaultPort(): int
    {
        return self::DEFAULT_PORT;
    }

    protected function configureStorageBackend(StartedContainer $container, string $bucket, StorageAccessMode $mode): void
    {
        $endpoint = sprintf('http://127.0.0.1:%d', self::DEFAULT_PORT);
        $alias = 'local';

        $this->runCommandOrFail($container, ['mc', 'alias', 'set', $alias, $endpoint, $this->accessKey(), $this->secretKey()]);
        $this->runCommandOrFail($container, ['mc', 'mb', '--ignore-existing', sprintf('%s/%s', $alias, $bucket)]);

        $permission = $mode === StorageAccessMode::Public ? 'download' : 'private';

        $this->runCommandOrFail($container, ['mc', 'anonymous', 'set', $permission, sprintf('%s/%s', $alias, $bucket)]);
    }

    /**
     * @param  list<string>  $command
     */
    private function runCommandOrFail(StartedContainer $container, array $command): void
    {
        $result = $container->exec($command);

        if ($result->successful()) {
            return;
        }

        throw new RuntimeException(sprintf(
            'MinIO bootstrap command failed (%s): %s',
            implode(' ', $command),
            trim($result->output),
        ));
    }
}
