<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers\Builders;

use Eznix86\PestPluginTestContainers\Concerns\ConfiguresStorage;
use Eznix86\PestPluginTestContainers\Concerns\HasCredentials;
use Eznix86\PestPluginTestContainers\Container\ContainerBuilder;

final class MinioContainerBuilder extends SpecializedContainerBuilder
{
    use ConfiguresStorage;
    use HasCredentials;

    private const int DEFAULT_PORT = 9000;

    private const int CONSOLE_PORT = 9001;

    public function __construct(ContainerBuilder $builder)
    {
        parent::__construct($builder, 'minio');

        $this->username = 'minioadmin';
        $this->password = 'minioadmin';
        $this->ports([self::DEFAULT_PORT, self::CONSOLE_PORT]);
        $this->waitForPort(self::DEFAULT_PORT);
    }

    protected function prepareContainer(): void
    {
        $this->builder->env([
            'MINIO_ROOT_USER' => $this->username(),
            'MINIO_ROOT_PASSWORD' => $this->password(),
        ]);

        $this->builder->command(['server', '/data']);
    }

    protected function getDefaultPort(): int
    {
        return self::DEFAULT_PORT;
    }

    protected function generatePassword(): string
    {
        return bin2hex(random_bytes(16));
    }
}
