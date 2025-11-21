<?php

declare(strict_types=1);

/**
 * Guard script to ensure runtime vendor trees do NOT include Joomla dev stubs
 * or links back to the repo root vendor.
 *
 * Usage:
 *   php tools/guard-runtime-vendor.php
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run this script from the command line.\n");
    exit(1);
}

$repoRoot = dirname(__DIR__);
$vendorPaths = [
    $repoRoot . '/administrator/components/com_nxpeasycart/vendor',
    $repoRoot . '/components/com_nxpeasycart/vendor',
];

$errors = [];

foreach ($vendorPaths as $path) {
    if (!file_exists($path)) {
        continue;
    }

    $label = str_replace($repoRoot . '/', '', $path);

    if (is_link($path)) {
        $target = realpath($path) ?: $path;
        $errors[] = "{$label} is a symlink to {$target}; runtime vendor must not link to the repo root vendor.";
        continue;
    }

    if (is_dir($path . '/joomla/joomla-cms')) {
        $errors[] = "{$label} contains joomla/joomla-cms; remove dev stubs and rebuild with tools/build-runtime-vendor.php.";
    }
}

if (!empty($errors)) {
    fwrite(STDERR, "Runtime vendor guard failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, " - {$error}\n");
    }
    exit(1);
}

echo "Runtime vendor guard passed: no Joomla dev stubs detected.\n";
