<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers\Container\Reuse;

final class WorkerTokenResolver
{
    public function resolve(): ?string
    {
        $token = $_SERVER['TEST_TOKEN'] ?? $_ENV['TEST_TOKEN'] ?? getenv('TEST_TOKEN');

        if (! is_string($token)) {
            return null;
        }

        $token = trim($token);

        if (! ctype_digit($token)) {
            return null;
        }

        return $token;
    }
}
