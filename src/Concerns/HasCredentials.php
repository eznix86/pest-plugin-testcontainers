<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers\Concerns;

trait HasCredentials
{
    protected string $username = 'root';

    protected ?string $password = null;

    public function credentials(string $username, string $password): static
    {
        $this->username = $username;
        $this->password = $password;

        return $this;
    }

    public function username(): string
    {
        return $this->username;
    }

    public function password(): string
    {
        return $this->password ??= $this->generatePassword();
    }

    abstract protected function generatePassword(): string;
}
