<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers\Builders;

use Eznix86\PestPluginTestContainers\Concerns\ConfiguresSearch;
use Eznix86\PestPluginTestContainers\Concerns\HasSinglePassPhrase;
use Eznix86\PestPluginTestContainers\Container\ContainerBuilder;

final class TypesenseContainerBuilder extends SpecializedContainerBuilder
{
    use ConfiguresSearch;
    use HasSinglePassPhrase;

    private const int DEFAULT_PORT = 8108;

    public function __construct(ContainerBuilder $builder)
    {
        parent::__construct($builder, 'typesense');
        $this->ports([self::DEFAULT_PORT]);
        $this->waitForPort(self::DEFAULT_PORT);
    }

    protected function prepareContainer(): void
    {
        $this->builder->env([
            'TYPESENSE_API_KEY' => $this->getPassphrase(),
            'TYPESENSE_DATA_DIR' => '/tmp',
        ]);
    }

    protected function getDriverName(): string
    {
        return 'typesense';
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
            'api_key' => $this->getPassphrase(),
            'protocol' => 'http',
        ];
    }
}
