<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers\Concerns;

trait HasS3Keys
{
    protected string $accessKey = 'minioadmin';

    protected ?string $secretKey = null;

    public function credentials(string $accessKey, string $secretKey): static
    {
        $this->accessKey = $accessKey;
        $this->secretKey = $secretKey;

        return $this;
    }

    public function accessKey(): string
    {
        return $this->accessKey;
    }

    public function secretKey(): string
    {
        return $this->secretKey ??= $this->generateSecretKey();
    }

    protected function generateSecretKey(): string
    {
        return bin2hex(random_bytes(16));
    }
}
