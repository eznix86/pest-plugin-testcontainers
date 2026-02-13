<?php

declare(strict_types=1);

use function Eznix86\PestPluginTestContainers\meilisearch;
use function Eznix86\PestPluginTestContainers\typesense;

dataset('searchServices', [
    'typesense' => [
        fn () => typesense()->passphrase('typesense-key')->asSearch(),
        8108,
        'typesense',
        'scout.typesense.api_key',
        'scout.typesense.host',
        'scout.typesense.port',
        static function (string $passphrase): void {
            expect(config('scout.typesense.protocol'))->toBe('http');
        },
        'TYPESENSE_API_KEY',
    ],
    'meilisearch' => [
        fn () => meilisearch()->passphrase('meili-key')->asSearch(),
        7700,
        'meilisearch',
        'scout.meilisearch.key',
        'scout.meilisearch.host',
        'scout.meilisearch.port',
        static function (string $passphrase): void {
            // No extra assertions for Meilisearch.
        },
        'MEILI_MASTER_KEY',
    ],
]);

it('should inject search configuration for each service', function (callable $makeBuilder, int $containerPort, string $driver, string $configKeyPath, string $hostPath, string $portPath, callable $extraConfigAsserts, string $envPassphraseKey) {
    $builder = $makeBuilder();

    $container = $builder->start();
    $mappedPort = $container->mappedPort($containerPort);

    expect(config('scout.driver'))->toBe($driver)
        ->and(config($configKeyPath))->toBe($builder->getPassphrase())
        ->and(config($hostPath))->toBe($container->host())
        ->and(config($portPath))->toBe($mappedPort)
        ->and(trimmedOutput($container, ['sh', '-lc', "printenv {$envPassphraseKey}"]))->toBe($builder->getPassphrase());

    $extraConfigAsserts($builder->getPassphrase());
})->with('searchServices');
