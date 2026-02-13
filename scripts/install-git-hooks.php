<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$hookSource = $projectRoot.'/.githooks/pre-commit';

if (! is_file($hookSource)) {
    fwrite(STDERR, "[hooks] Source hook not found at {$hookSource}.\n");
    exit(1);
}

$hooksDir = resolveHooksDirectory($projectRoot);

if ($hooksDir === null) {
    fwrite(STDOUT, "[hooks] Skipping hook installation: .git directory was not found.\n");
    exit(0);
}

if (! is_dir($hooksDir) && ! mkdir($hooksDir, 0775, true) && ! is_dir($hooksDir)) {
    fwrite(STDERR, "[hooks] Unable to create hooks directory at {$hooksDir}.\n");
    exit(1);
}

$hookDestination = $hooksDir.'/pre-commit';

if (! copy($hookSource, $hookDestination)) {
    fwrite(STDERR, "[hooks] Failed to copy pre-commit hook to {$hookDestination}.\n");
    exit(1);
}

chmod($hookDestination, 0755);

fwrite(STDOUT, "[hooks] Installed pre-commit hook at {$hookDestination}.\n");

function resolveHooksDirectory(string $projectRoot): ?string
{
    $gitPath = $projectRoot.'/.git';

    if (is_dir($gitPath)) {
        return $gitPath.'/hooks';
    }

    if (! is_file($gitPath)) {
        return null;
    }

    $contents = trim((string) file_get_contents($gitPath));

    if (! str_starts_with($contents, 'gitdir:')) {
        return null;
    }

    $gitDir = trim(substr($contents, strlen('gitdir:')));

    if ($gitDir === '') {
        return null;
    }

    if (! str_starts_with($gitDir, '/')) {
        $gitDir = realpath($projectRoot.'/'.$gitDir) ?: $projectRoot.'/'.$gitDir;
    }

    return rtrim($gitDir, '/').'/hooks';
}
