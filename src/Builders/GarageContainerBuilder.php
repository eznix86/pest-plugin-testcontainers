<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers\Builders;

use Eznix86\PestPluginTestContainers\Concerns\ConfiguresStorage;
use Eznix86\PestPluginTestContainers\Concerns\HasS3Keys;
use Eznix86\PestPluginTestContainers\Container\ContainerBuilder;
use Eznix86\PestPluginTestContainers\Container\ExecResult;
use Eznix86\PestPluginTestContainers\Container\StartedContainer;
use Eznix86\PestPluginTestContainers\Values\StorageAccessMode;
use InvalidArgumentException;
use RuntimeException;

final class GarageContainerBuilder extends SpecializedContainerBuilder
{
    use ConfiguresStorage;
    use HasS3Keys {
        credentials as private withCredentials;
    }

    private const int S3_API_PORT = 3900;

    private const int RPC_PORT = 3901;

    private const int S3_WEB_PORT = 3902;

    private const int ADMIN_API_PORT = 3903;

    private const int ADMIN_READY_MAX_ATTEMPTS = 20;

    private const int NODE_ID_MAX_ATTEMPTS = 12;

    private const int RETRY_DELAY_MICROSECONDS = 250_000;

    private const string SINGLE_NODE_CAPACITY = '1G';

    private ?string $tomlConfigPath = null;

    private ?string $generatedTomlConfigPath = null;

    private ?string $resolvedAccessKey = null;

    private ?string $resolvedSecretKey = null;

    public function __construct(ContainerBuilder $builder)
    {
        parent::__construct($builder, 'garage');

        $this->accessKey = 'garage';
        $this->secretKey = 'garage-secret';

        $this->ports([
            self::S3_API_PORT,
            self::RPC_PORT,
            self::S3_WEB_PORT,
            self::ADMIN_API_PORT,
        ]);

        $this->waitForLog('S3 API server listening on', timeoutSeconds: 60);
    }

    public function credentials(string $accessKey, string $secretKey): static
    {
        $this->resolvedAccessKey = null;
        $this->resolvedSecretKey = null;

        return $this->withCredentials($accessKey, $secretKey);
    }

    public function withTomlConfig(string $path): static
    {
        if (! is_file($path)) {
            throw new InvalidArgumentException(sprintf('Garage TOML config file does not exist: %s', $path));
        }

        $this->tomlConfigPath = $path;

        return $this;
    }

    public function accessKey(): string
    {
        return $this->resolvedAccessKey ??= $this->normalizeAccessKeyId($this->accessKey);
    }

    public function secretKey(): string
    {
        $rawSecretKey = $this->secretKey ??= $this->generateSecretKey();

        return $this->resolvedSecretKey ??= $this->normalizeSecretKey($rawSecretKey);
    }

    protected function prepareContainer(): void
    {
        $configPath = $this->tomlConfigPath ??= $this->createDefaultTomlConfig();

        $this->builder->volume($configPath, '/etc/garage.toml');
    }

    protected function getDefaultPort(): int
    {
        return self::S3_API_PORT;
    }

    protected function storageRegion(): string
    {
        return 'garage';
    }

    protected function configureStorageBackend(StartedContainer $container, string $bucket, StorageAccessMode $mode): void
    {
        $this->waitUntilAdminReady($container);
        $this->ensureSingleNodeLayout($container);
        $this->ensureBucket($container, $bucket);
        $this->ensureKey($container);
        $this->allowBucketAccess($container, $bucket);

        $this->runGarageOrFail($container, ['bucket', 'website', $this->websiteFlagFor($mode), $bucket]);
    }

    private function waitUntilAdminReady(StartedContainer $container): void
    {
        for ($attempt = 0; $attempt < self::ADMIN_READY_MAX_ATTEMPTS; $attempt++) {
            $result = $this->runGarage($container, ['status']);

            if ($result->successful()) {
                return;
            }

            usleep(self::RETRY_DELAY_MICROSECONDS);
        }

        throw new RuntimeException('Garage admin API did not become ready in time.');
    }

    private function ensureSingleNodeLayout(StartedContainer $container): void
    {
        $nodeId = $this->resolveNodeIdPrefixFromStatus($container);

        if ($nodeId === null) {
            throw new RuntimeException('Unable to resolve Garage node id while bootstrapping single-node layout.');
        }

        $this->runGarageOrFail($container, ['layout', 'assign', '-z', 'dc1', '-c', self::SINGLE_NODE_CAPACITY, $nodeId]);

        $currentVersion = $this->resolveCurrentLayoutVersion($container);
        $this->runGarageOrFail($container, ['layout', 'apply', '--version', (string) ($currentVersion + 1)]);
    }

    private function resolveNodeIdPrefixFromStatus(StartedContainer $container): ?string
    {
        for ($attempt = 0; $attempt < self::NODE_ID_MAX_ATTEMPTS; $attempt++) {
            $nodeIdOutput = $this->runGarageOrFail($container, ['node', 'id', '-q']);
            $nodeIdPrefix = $this->extractNodeIdPrefix($nodeIdOutput);

            if ($nodeIdPrefix !== null) {
                return $nodeIdPrefix;
            }

            $statusOutput = $this->runGarageOrFail($container, ['status']);
            $statusPrefix = $this->extractNodeIdPrefix($statusOutput);

            if ($statusPrefix !== null) {
                return $statusPrefix;
            }

            usleep(self::RETRY_DELAY_MICROSECONDS);
        }

        return null;
    }

    private function resolveCurrentLayoutVersion(StartedContainer $container): int
    {
        $layoutShowOutput = $this->runGarageOrFail($container, ['layout', 'show']);
        preg_match('/Current cluster layout version:\s*(\d+)/', $layoutShowOutput, $matches);

        return isset($matches[1]) ? (int) $matches[1] : 0;
    }

    private function extractNodeIdPrefix(string $output): ?string
    {
        $plainOutput = preg_replace('/\e\[[\d;]*m/', '', $output) ?? $output;

        if (preg_match('/([a-f0-9]{16,64})(?:@|\s|$)/i', $plainOutput, $matches) !== 1) {
            return null;
        }

        return strtolower(substr($matches[1], 0, 16));
    }

    private function ensureBucket(StartedContainer $container, string $bucket): void
    {
        $result = $this->runGarage($container, ['bucket', 'info', $bucket]);

        if ($result->successful()) {
            return;
        }

        $this->runGarageOrFail($container, ['bucket', 'create', $bucket]);
    }

    private function ensureKey(StartedContainer $container): void
    {
        $keyId = $this->accessKey();
        $secret = $this->secretKey();
        $info = $this->runGarage($container, ['key', 'info', $keyId, '--show-secret']);

        if ($info->successful()) {
            preg_match('/Secret key:\s*([a-f0-9]+)/i', $info->output, $matches);
            $existingSecret = strtolower($matches[1] ?? '');

            if ($existingSecret !== '' && $existingSecret !== strtolower($secret)) {
                throw new RuntimeException(sprintf(
                    'Garage key %s already exists with a different secret; cannot safely reuse container with new credentials.',
                    $keyId,
                ));
            }

            return;
        }

        $this->runGarageOrFail($container, ['key', 'import', $keyId, $secret, '-n', 'pest-plugin-testcontainers-key', '--yes']);
    }

    private function websiteFlagFor(StorageAccessMode $mode): string
    {
        return $mode === StorageAccessMode::Public ? '--allow' : '--deny';
    }

    private function allowBucketAccess(StartedContainer $container, string $bucket): void
    {
        $this->runGarageOrFail($container, ['bucket', 'allow', '--read', '--write', '--owner', $bucket, '--key', $this->accessKey()]);
    }

    /**
     * @param  list<string>  $arguments
     */
    private function runGarage(StartedContainer $container, array $arguments): ExecResult
    {
        return $container->exec(array_merge(['/garage'], $arguments));
    }

    /**
     * @param  list<string>  $arguments
     */
    private function runGarageOrFail(StartedContainer $container, array $arguments): string
    {
        $result = $this->runGarage($container, $arguments);

        if ($result->successful()) {
            return $result->output;
        }

        throw new RuntimeException(sprintf(
            'Garage bootstrap command failed (%s): %s',
            implode(' ', array_merge(['/garage'], $arguments)),
            trim($result->output),
        ));
    }

    private function normalizeAccessKeyId(string $key): string
    {
        if (preg_match('/^GK[a-f0-9]{24}$/i', $key) === 1) {
            return 'GK'.strtolower(substr($key, 2));
        }

        return 'GK'.substr(hash('sha256', $key), 0, 24);
    }

    private function normalizeSecretKey(string $secret): string
    {
        if (preg_match('/^[a-f0-9]{64}$/i', $secret) === 1) {
            return strtolower($secret);
        }

        return hash('sha256', $secret);
    }

    private function createDefaultTomlConfig(): string
    {
        $path = $this->generatedTomlConfigPath ??= sprintf(
            '%s/pest-plugin-testcontainers-garage-%s.toml',
            rtrim(sys_get_temp_dir(), '/'),
            bin2hex(random_bytes(8)),
        );

        $config = implode(PHP_EOL, [
            'metadata_dir = "/var/lib/garage/meta"',
            'data_dir = "/var/lib/garage/data"',
            'db_engine = "sqlite"',
            '',
            'replication_factor = 1',
            '',
            'rpc_bind_addr = "[::]:3901"',
            'rpc_public_addr = "127.0.0.1:3901"',
            sprintf('rpc_secret = "%s"', bin2hex(random_bytes(32))),
            '',
            '[s3_api]',
            's3_region = "garage"',
            'api_bind_addr = "[::]:3900"',
            '',
            '[s3_web]',
            'bind_addr = "[::]:3902"',
            'root_domain = ".localtest"',
            'index = "index.html"',
            '',
            '[admin]',
            'api_bind_addr = "[::]:3903"',
            sprintf('admin_token = "%s"', bin2hex(random_bytes(16))),
            sprintf('metrics_token = "%s"', bin2hex(random_bytes(16))),
            '',
        ]);

        if (file_put_contents($path, $config) === false) {
            throw new RuntimeException(sprintf('Unable to write Garage default config file at %s', $path));
        }

        return $path;
    }
}
