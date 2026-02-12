<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers\Container;

use Throwable;

final class DockerAvailabilityDetector
{
    /**
     * @var list<string>
     */
    private const array UNAVAILABLE_MARKERS = [
        'cannot connect to the docker daemon',
        'is the docker daemon running',
        'error while fetching server api version',
        'error during connect',
        'docker daemon is not running',
        'failed to connect to localhost port 2375',
        'failed to connect to localhost port 2376',
        'permission denied while trying to connect to the docker daemon socket',
        'dial unix /var/run/docker.sock',
        'open //./pipe/docker_engine',
    ];

    public static function isUnavailable(Throwable $exception): bool
    {
        for ($current = $exception; $current instanceof Throwable; $current = $current->getPrevious()) {
            $message = strtolower($current->getMessage());

            foreach (self::UNAVAILABLE_MARKERS as $marker) {
                if (str_contains($message, $marker)) {
                    return true;
                }
            }
        }

        return false;
    }
}
