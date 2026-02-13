<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers\Builders;

use Eznix86\PestPluginTestContainers\Concerns\ConfiguresDatabase;
use Eznix86\PestPluginTestContainers\Concerns\ConfiguresDatabaseCache;
use Eznix86\PestPluginTestContainers\Concerns\ConfiguresQueue;
use Eznix86\PestPluginTestContainers\Concerns\HasCredentials;
use Eznix86\PestPluginTestContainers\Container\ContainerBuilder;

final class PostgresContainerBuilder extends SpecializedContainerBuilder
{
    use ConfiguresDatabase;
    use ConfiguresDatabaseCache;
    use ConfiguresQueue;
    use HasCredentials;

    private const int DEFAULT_PORT = 5432;

    public function __construct(ContainerBuilder $builder)
    {
        parent::__construct($builder, 'postgres');

        $this->username = 'postgres';
        $this->ports([self::DEFAULT_PORT]);
    }

    protected function prepareContainer(): void
    {
        $this->builder->env([
            'POSTGRES_USER' => $this->username(),
            'POSTGRES_PASSWORD' => $this->password(),
            'POSTGRES_DB' => $this->databaseName(),
        ]);
    }

    protected function getDriverName(): string
    {
        return 'pgsql';
    }

    protected function getDefaultPort(): int
    {
        return self::DEFAULT_PORT;
    }

    protected function getQueueDriverName(): string
    {
        return 'database';
    }
}
