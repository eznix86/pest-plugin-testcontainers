<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers;

use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Assert;

function registerStorageExpectations(): void
{
    expect()->extend('toBeInStorage', function (?string $disk = null): object {
        $path = (string) $this->value;
        $disk = resolveStorageDisk($disk);

        Assert::assertTrue(
            Storage::disk($disk)->exists($path),
            sprintf('Expected path "%s" to exist in storage disk "%s".', $path, $disk),
        );

        return $this;
    });

    expect()->extend('toNotBeInStorage', function (?string $disk = null): object {
        $path = (string) $this->value;
        $disk = resolveStorageDisk($disk);

        Assert::assertFalse(
            Storage::disk($disk)->exists($path),
            sprintf('Expected path "%s" to not exist in storage disk "%s".', $path, $disk),
        );

        return $this;
    });
}

function resolveStorageDisk(?string $disk): string
{
    if ($disk !== null) {
        return $disk;
    }

    $configuredDisk = config('filesystems.default');

    return is_string($configuredDisk) && $configuredDisk !== '' ? $configuredDisk : 'testcontainer';
}
