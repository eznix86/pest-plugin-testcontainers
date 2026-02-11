<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers;

use Docker\API\Endpoint\ContainerLogs;
use Docker\API\Endpoint\ExecStart;
use Docker\API\Model\ContainersIdExecPostBody;
use Docker\API\Model\IdResponse;
use RuntimeException;
use Testcontainers\Container\StartedGenericContainer as UnpatchedGenericContainer;

final class StartedGenericContainer extends UnpatchedGenericContainer
{
    /**
     * @param  list<string>  $command
     */
    #[\Override]
    public function exec(array $command): string
    {
        return $this->sanitizeOutput($this->execRaw($command));
    }

    /**
     * @param  list<string>  $command
     */
    public function execRaw(array $command): string
    {
        $execConfig = (new ContainersIdExecPostBody)
            ->setCmd($command)
            ->setAttachStdout(true)
            ->setAttachStderr(true);

        /** @var IdResponse|null $exec */
        $exec = $this->dockerClient->containerExec($this->id, $execConfig);

        throw_unless($exec?->getId() !== null, RuntimeException::class, 'Failed to create exec command');

        $this->lastExecId = $exec->getId();

        $contents = $this->dockerClient
            ->executeRawEndpoint(new ExecStart($this->lastExecId))
            ->getBody()
            ->getContents();

        return $contents;
    }

    #[\Override]
    public function logs(): string
    {
        return $this->sanitizeOutput($this->logsRaw());
    }

    public function logsRaw(): string
    {
        $output = $this->dockerClient
            ->executeRawEndpoint(new ContainerLogs($this->id, ['stdout' => true, 'stderr' => true]))
            ->getBody()
            ->getContents();

        /**
         * @var string|false $converted
         */
        $converted = mb_convert_encoding($output, 'UTF-8', 'UTF-8');

        return $converted === false ? $output : $converted;
    }
}
