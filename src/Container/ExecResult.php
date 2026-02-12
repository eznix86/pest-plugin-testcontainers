<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers\Container;

final readonly class ExecResult
{
    /**
     * @param  list<string>  $command
     */
    public function __construct(
        public array $command,
        public string $output,
        public int $exitCode,
    ) {}

    public function successful(): bool
    {
        return $this->exitCode === 0;
    }
}
