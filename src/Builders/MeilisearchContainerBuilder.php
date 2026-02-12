<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers\Builders;

use Eznix86\PestPluginTestContainers\Concerns\ConfiguresSearch;
use Eznix86\PestPluginTestContainers\Concerns\HasSinglePassPhrase;
use Eznix86\PestPluginTestContainers\ContainerBuilder;

final class MeilisearchContainerBuilder extends SpecializedContainerBuilder
{
    use ConfiguresSearch;
    use HasSinglePassPhrase;

    private const int DEFAULT_PORT = 7700;

    public function __construct(ContainerBuilder $builder)
    {
        parent::__construct($builder, 'meilisearch');
        $this->ports([self::DEFAULT_PORT]);
        $this->waitForPort(self::DEFAULT_PORT);
    }

    protected function prepareContainer(): void
    {
        $this->builder->env([
            'MEILI_MASTER_KEY' => $this->getPassphrase(),
        ]);
    }

    protected function getDriverName(): string
    {
        return 'meilisearch';
    }

    protected function getDefaultPort(): int
    {
        return self::DEFAULT_PORT;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getSearchConfig(): array
    {
        return [
            'key' => $this->getPassphrase(),
        ];
    }
}
