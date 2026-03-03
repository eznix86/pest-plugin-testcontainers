<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers\Values;

enum StorageAccessMode: string
{
    case Private = 'private';
    case Public = 'public';

    public function visibility(): string
    {
        return $this->value;
    }
}
