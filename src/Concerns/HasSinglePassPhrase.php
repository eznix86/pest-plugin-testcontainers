<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers\Concerns;

trait HasSinglePassPhrase
{
    protected ?string $passphrase = null;

    public function passphrase(string $phrase): static
    {
        $this->passphrase = $phrase;

        return $this;
    }

    public function getPassphrase(): string
    {
        return $this->passphrase ??= $this->generatePassphrase();
    }

    protected function generatePassphrase(): string
    {
        return bin2hex(random_bytes(16));
    }
}
