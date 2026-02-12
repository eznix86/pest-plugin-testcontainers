<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers\Builders;

use Eznix86\PestPluginTestContainers\Concerns\ConfiguresDatabase;
use Eznix86\PestPluginTestContainers\Concerns\ConfiguresDatabaseCache;
use Eznix86\PestPluginTestContainers\Concerns\ConfiguresQueue;
use Eznix86\PestPluginTestContainers\Concerns\HasCredentials;
use Eznix86\PestPluginTestContainers\Container\ContainerBuilder;

final class MariaDbContainerBuilder extends SpecializedContainerBuilder
{
    use ConfiguresDatabase;
    use ConfiguresDatabaseCache;
    use ConfiguresQueue;
    use HasCredentials;

    private const int DEFAULT_PORT = 3306;

    public function __construct(ContainerBuilder $builder)
    {
        parent::__construct($builder, 'mariadb');

        $this->username = 'root';
        $this->ports([self::DEFAULT_PORT]);
        $this->waitForPort(self::DEFAULT_PORT, timeoutSeconds: 60);
    }

    protected function prepareContainer(): void
    {
        $env = [
            'MARIADB_ROOT_PASSWORD' => $this->password(),
            'MARIADB_DATABASE' => $this->databaseName(),
        ];

        if ($this->username() !== 'root') {
            $env['MARIADB_USER'] = $this->username();
            $env['MARIADB_PASSWORD'] = $this->password();
        }

        $this->builder->env($env);
    }

    protected function getDriverName(): string
    {
        return 'mysql';
    }

    protected function getDefaultPort(): int
    {
        return self::DEFAULT_PORT;
    }

    protected function getQueueDriverName(): string
    {
        return 'database';
    }

    protected function generatePassword(): string
    {
        return bin2hex(random_bytes(16));
    }
}
