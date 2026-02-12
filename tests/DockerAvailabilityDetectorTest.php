<?php

declare(strict_types=1);

use Eznix86\PestPluginTestContainers\Container\DockerAvailabilityDetector;

it('detects unavailable docker daemon errors', function () {
    $exception = new RuntimeException('Cannot connect to the Docker daemon at unix:///var/run/docker.sock. Is the docker daemon running?');

    expect(DockerAvailabilityDetector::isUnavailable($exception))->toBeTrue();
});

it('detects unavailable docker daemon errors in previous exceptions', function () {
    $previous = new RuntimeException('Error while fetching server API version: dial unix /var/run/docker.sock: connect: no such file or directory');
    $exception = new RuntimeException('Request failed', previous: $previous);

    expect(DockerAvailabilityDetector::isUnavailable($exception))->toBeTrue();
});

it('does not classify testcontainers runtime failures as docker unavailable', function () {
    $exception = new RuntimeException('Mapped port for container port 3306 was not available after 6 attempts.');

    expect(DockerAvailabilityDetector::isUnavailable($exception))->toBeFalse();
});

it('does not classify docker api parameter errors as docker unavailable', function () {
    $exception = new RuntimeException('Bad parameter: failed to parse port bindings');

    expect(DockerAvailabilityDetector::isUnavailable($exception))->toBeFalse();
});
