<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers\Builders;

use Eznix86\PestPluginTestContainers\Concerns\ConfiguresCache;
use Eznix86\PestPluginTestContainers\Concerns\ConfiguresQueue;
use Eznix86\PestPluginTestContainers\Concerns\HasSinglePassPhrase;
use Eznix86\PestPluginTestContainers\ContainerBuilder;

final class RedisContainerBuilder extends SpecializedContainerBuilder
{
    use ConfiguresCache;
    use ConfiguresQueue;
    use HasSinglePassPhrase;

    private const int DEFAULT_PORT = 6379;

    public function __construct(ContainerBuilder $builder)
    {
        parent::__construct($builder, 'redis');

        $this->ports([self::DEFAULT_PORT]);
        $this->waitForPort(self::DEFAULT_PORT);
    }

    protected function getDefaultPort(): int
    {
        return self::DEFAULT_PORT;
    }

    protected function getQueueDriverName(): string
    {
        return 'redis';
    }
}
