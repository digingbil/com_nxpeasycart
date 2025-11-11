<?php

declare(strict_types=1);

/**
 * Build a trimmed vendor directory inside administrator/components/com_nxpeasycart
 * containing only runtime dependencies (no dev packages). This prevents the dev
 * autoloader from hijacking Joomla when the component is symlinked into a live site.
 *
 * Usage:
 *   php tools/build-runtime-vendor.php
 *
 * Optional env vars:
 *   COMPOSER_BINARY=/path/to/composer  (defaults to "composer")
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run this script from the command line.\n");
    exit(1);
}

$repoRoot  = dirname(__DIR__);
$targetDir = $repoRoot . '/administrator/components/com_nxpeasycart/vendor';
$composerBinary = getenv('COMPOSER_BINARY') ?: 'composer';

$composerJsonPath = $repoRoot . '/composer.json';

if (!is_file($composerJsonPath)) {
    fwrite(STDERR, "Unable to locate composer.json at {$composerJsonPath}\n");
    exit(1);
}

try {
    $composerData = json_decode((string) file_get_contents($composerJsonPath), true, 512, JSON_THROW_ON_ERROR);
} catch (\JsonException $exception) {
    fwrite(STDERR, "Failed to parse composer.json: {$exception->getMessage()}\n");
    exit(1);
}

if (empty($composerData['require']) || !is_array($composerData['require'])) {
    fwrite(STDERR, "composer.json does not define runtime dependencies.\n");
    exit(1);
}

$runtimeComposer = [
    'name'         => 'nxp/easy-cart-runtime',
    'type'         => 'project',
    'require'      => $composerData['require'],
    'config'       => [],
    'repositories' => $composerData['repositories'] ?? [],
];

if (isset($composerData['config']['platform'])) {
    $runtimeComposer['config']['platform'] = $composerData['config']['platform'];
}

if (empty($runtimeComposer['config'])) {
    unset($runtimeComposer['config']);
}

$tempDir = sys_get_temp_dir() . '/nxpeasycart-runtime-' . bin2hex(random_bytes(4));

if (!mkdir($tempDir, 0777, true) && !is_dir($tempDir)) {
    fwrite(STDERR, "Failed to create temp directory: {$tempDir}\n");
    exit(1);
}

$runtimeComposerPath = $tempDir . '/composer.json';

file_put_contents(
    $runtimeComposerPath,
    json_encode($runtimeComposer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
);

$command = sprintf(
    '%s install --no-dev --optimize-autoloader --prefer-dist --no-interaction',
    escapeshellarg($composerBinary)
);

$descriptorSpec = [
    0 => STDIN,
    1 => STDOUT,
    2 => STDERR,
];

$process = proc_open($command, $descriptorSpec, $pipes, $tempDir);

if (!\is_resource($process)) {
    fwrite(STDERR, "Failed to start composer process.\n");
    cleanup($tempDir);
    exit(1);
}

$exitCode = proc_close($process);

if ($exitCode !== 0) {
    fwrite(STDERR, "Composer install failed with exit code {$exitCode}.\n");
    cleanup($tempDir);
    exit($exitCode);
}

$builtVendor = $tempDir . '/vendor';

if (!is_dir($builtVendor)) {
    fwrite(STDERR, "Composer did not produce a vendor directory.\n");
    cleanup($tempDir);
    exit(1);
}

if (is_dir($targetDir)) {
    removeDirectory($targetDir);
}

if (!rename($builtVendor, $targetDir)) {
    fwrite(STDERR, "Failed to move vendor directory into {$targetDir}.\n");
    cleanup($tempDir);
    exit(1);
}

cleanup($tempDir);

echo "Runtime vendor built at {$targetDir}\n";

exit(0);

/**
 * Remove a directory recursively.
 */
function removeDirectory(string $path): void
{
    if (!is_dir($path)) {
        return;
    }

    $items = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
        \RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }

    rmdir($path);
}

/**
 * Clean up temp directory.
 */
function cleanup(string $path): void
{
    if (!is_dir($path)) {
        return;
    }

    removeDirectory($path);
}
