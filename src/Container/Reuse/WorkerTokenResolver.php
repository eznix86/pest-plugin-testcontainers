<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers\Container\Reuse;

final class WorkerTokenResolver
{
    /** @var list<string> */
    private const array ENV_KEYS = [
        'TEST_TOKEN',
        'PARATEST',
        'PARATEST_PROCESS',
        'PEST_WORKER',
        'PEST_PARALLEL_PROCESS',
    ];

    public function resolve(): ?string
    {
        foreach (self::ENV_KEYS as $key) {
            $value = $_SERVER[$key] ?? $_ENV[$key] ?? getenv($key);

            if (! is_string($value)) {
                continue;
            }

            $normalized = preg_replace('/[^A-Za-z0-9_.-]/', '-', trim($value));

            if (is_string($normalized) && $normalized !== '') {
                return $normalized;
            }
        }

        return null;
    }
}
