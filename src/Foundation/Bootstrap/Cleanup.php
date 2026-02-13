<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers;

use Docker\API\Model\ContainerSummary;
use Testcontainers\ContainerClient\DockerContainerClient;
use Throwable;

function registerManagedContainersShutdownCleanup(): void
{
    static $registered = false;

    if ($registered || isParallelWorkerProcess()) {
        return;
    }

    $registered = true;

    register_shutdown_function(static function (): void {
        $previousErrorReporting = error_reporting();
        error_reporting($previousErrorReporting & ~E_DEPRECATED & ~E_USER_DEPRECATED);

        try {
            $docker = DockerContainerClient::getDockerClient();
            $filters = json_encode(['label' => ['pest-plugin-testcontainers.managed=1']]);

            if (! is_string($filters)) {
                return;
            }

            $containers = $docker->containerList([
                'all' => true,
                'filters' => $filters,
            ]);

            if (! is_iterable($containers)) {
                return;
            }

            foreach ($containers as $container) {
                if (! $container instanceof ContainerSummary) {
                    continue;
                }

                $id = $container->getId();
                if (! is_string($id)) {
                    continue;
                }
                if ($id === '') {
                    continue;
                }

                try {
                    $docker->containerDelete($id, ['force' => true]);
                } catch (Throwable) {
                    // Best-effort cleanup.
                }
            }
        } catch (Throwable) {
            // Best-effort cleanup.
        } finally {
            error_reporting($previousErrorReporting);
        }
    });
}

function isParallelWorkerProcess(): bool
{
    $workerToken = getenv('TEST_TOKEN');

    return is_string($workerToken) && $workerToken !== '' && ctype_digit($workerToken);
}
