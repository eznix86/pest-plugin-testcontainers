<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers;

use PHPUnit\Framework\Assert;

final class ContainerExpectation
{
    private ?ExecResult $lastCommandResult = null;

    /**
     * @param  string|list<string>  $subject
     */
    public function __construct(private readonly StartedContainer $container, private readonly string|array $subject) {}

    public function toExist(): self
    {
        $path = $this->pathSubject();
        $result = $this->container->exec(['sh', '-lc', 'test -e '.escapeshellarg($path)]);

        Assert::assertSame(0, $result->exitCode, sprintf('Expected path "%s" to exist. Output: %s', $path, $result->output));

        return $this;
    }

    public function toNotExist(): self
    {
        $path = $this->pathSubject();
        $result = $this->container->exec(['sh', '-lc', 'test ! -e '.escapeshellarg($path)]);

        Assert::assertSame(0, $result->exitCode, sprintf('Expected path "%s" to not exist. Output: %s', $path, $result->output));

        return $this;
    }

    public function toBeDirectory(): self
    {
        $path = $this->pathSubject();
        $result = $this->container->exec(['sh', '-lc', 'test -d '.escapeshellarg($path)]);

        Assert::assertSame(0, $result->exitCode, sprintf('Expected path "%s" to be a directory. Output: %s', $path, $result->output));

        return $this;
    }

    public function toBeReadable(): self
    {
        $path = $this->pathSubject();
        $result = $this->container->exec(['sh', '-lc', 'test -r '.escapeshellarg($path)]);

        Assert::assertSame(0, $result->exitCode, sprintf('Expected path "%s" to be readable. Output: %s', $path, $result->output));

        return $this;
    }

    public function toRunSuccessfully(): self
    {
        $result = $this->runCommandSubject();

        Assert::assertSame(
            0,
            $result->exitCode,
            sprintf('Expected command to succeed: %s. Output: %s', implode(' ', $result->command), $result->output),
        );

        return $this;
    }

    public function toFail(): self
    {
        $result = $this->runCommandSubject();

        Assert::assertNotSame(
            0,
            $result->exitCode,
            sprintf('Expected command to fail: %s. Output: %s', implode(' ', $result->command), $result->output),
        );

        return $this;
    }

    public function toContain(string $needle): self
    {
        $result = $this->lastCommandResult ?? $this->runCommandSubject();

        Assert::assertStringContainsString(
            $needle,
            $result->output,
            sprintf('Expected command output to contain "%s". Output: %s', $needle, $result->output),
        );

        return $this;
    }

    public function toBeInLogs(): self
    {
        $needle = $this->pathSubject();
        $logs = $this->container->logs();

        Assert::assertStringContainsString(
            $needle,
            $logs,
            sprintf('Expected container logs to contain "%s". Logs: %s', $needle, $logs),
        );

        return $this;
    }

    /**
     * @return list<string>
     */
    private function commandSubject(): array
    {
        if (is_array($this->subject)) {
            return $this->subject;
        }

        return ['sh', '-lc', $this->subject];
    }

    private function pathSubject(): string
    {
        Assert::assertIsString($this->subject, 'Expected path/log subject to be a string.');

        return $this->subject;
    }

    private function runCommandSubject(): ExecResult
    {
        $this->lastCommandResult = $this->container->exec($this->commandSubject());

        return $this->lastCommandResult;
    }
}
