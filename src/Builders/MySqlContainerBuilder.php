<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers\Builders;

use Eznix86\PestPluginTestContainers\Concerns\ConfiguresCache;
use Eznix86\PestPluginTestContainers\Concerns\ConfiguresDatabase;
use Eznix86\PestPluginTestContainers\Concerns\ConfiguresQueue;
use Eznix86\PestPluginTestContainers\Concerns\HasCredentials;
use Eznix86\PestPluginTestContainers\Container\ContainerBuilder;

final class MySqlContainerBuilder extends SpecializedContainerBuilder
{
    use ConfiguresCache;
    use ConfiguresDatabase;
    use ConfiguresQueue;
    use HasCredentials;

    private const int DEFAULT_PORT = 3306;

    public function __construct(ContainerBuilder $builder)
    {
        parent::__construct($builder, 'mysql');

        $this->username = 'root';
        $this->ports([self::DEFAULT_PORT]);
        $this->waitForPort(self::DEFAULT_PORT);
    }

    protected function prepareContainer(): void
    {
        $env = [
            'MYSQL_ROOT_PASSWORD' => $this->password(),
            'MYSQL_DATABASE' => $this->databaseName(),
        ];

        if ($this->username() !== 'root') {
            $env['MYSQL_USER'] = $this->username();
            $env['MYSQL_PASSWORD'] = $this->password();
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
