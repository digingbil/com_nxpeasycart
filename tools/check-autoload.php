<?php

declare(strict_types=1);

/**
 * Simple CLI health check ensuring the Joomla instance can autoload the
 * third-party libraries the component depends on (Guzzle + Ramsey UUID).
 *
 * Usage:
 *   php tools/check-autoload.php [/path/to/joomla/root]
 *
 * Default Joomla root: /var/www/html/j5.loc
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run this script from the command line.\n");
    exit(1);
}

$joomlaRoot = $argv[1] ?? '/var/www/html/j5.loc';
$joomlaRoot = rtrim($joomlaRoot, DIRECTORY_SEPARATOR);

if ($joomlaRoot === '') {
    fwrite(STDERR, "Joomla root path is empty.\n");
    exit(1);
}

if (!is_dir($joomlaRoot)) {
    fwrite(STDERR, "Joomla root '{$joomlaRoot}' does not exist.\n");
    exit(1);
}

if (!\defined('_JEXEC')) {
    \define('_JEXEC', 1);
}

$defines = $joomlaRoot . '/defines.php';
if (is_file($defines)) {
    require_once $defines;
}

if (!\defined('JPATH_BASE')) {
    \define('JPATH_BASE', $joomlaRoot);
}

if (!\defined('JPATH_ROOT')) {
    \define('JPATH_ROOT', JPATH_BASE);
}

$includesDefines = JPATH_BASE . '/includes/defines.php';
if (is_file($includesDefines)) {
    require_once $includesDefines;
}

$framework = JPATH_BASE . '/includes/framework.php';
if (is_file($framework)) {
    require_once $framework;
}

$targets = [
    [
        'name'  => 'GuzzleHttp\\ClientInterface',
        'type'  => 'interface',
        'state' => false,
    ],
    [
        'name'  => 'Ramsey\\Uuid\\Uuid',
        'type'  => 'class',
        'state' => false,
    ],
];

$resolveSource = null;

$checkAvailability = static function (array &$map): void {
    foreach ($map as &$target) {
        $name = $target['name'];
        $isInterface = $target['type'] === 'interface';
        $target['state'] = $isInterface ? interface_exists($name) : class_exists($name);
    }
    unset($target);
};

$checkAvailability($targets);

$allResolved = static function (array $map): bool {
    foreach ($map as $target) {
        if ($target['state'] === false) {
            return false;
        }
    }

    return true;
};

if (!$allResolved($targets)) {
    $autoloadCandidates = [
        $joomlaRoot . '/vendor/autoload.php',
        $joomlaRoot . '/libraries/vendor/autoload.php',
        $joomlaRoot . '/administrator/components/com_nxpeasycart/vendor/autoload.php',
        $joomlaRoot . '/components/com_nxpeasycart/vendor/autoload.php',
        dirname(__DIR__) . '/vendor/autoload.php',
    ];

    foreach ($autoloadCandidates as $autoload) {
        if (!is_file($autoload)) {
            continue;
        }

        require_once $autoload;

        $checkAvailability($targets);

        if ($allResolved($targets)) {
            $resolveSource = $autoload;
            break;
        }
    }
} else {
    $resolveSource = 'joomla-framework';
}

$missing = array_map(
    static fn (array $target): string => $target['name'],
    array_filter(
        $targets,
        static fn (array $target): bool => $target['state'] === false
    )
);

if (!empty($missing)) {
    fwrite(
        STDERR,
        "Autoload check failed. Missing classes:\n - " . implode("\n - ", $missing) .
        "\nRebuild the runtime vendor via `php tools/build-runtime-vendor.php` and retry.\n"
    );
    exit(1);
}

$sourceNote = $resolveSource ? " (resolved via {$resolveSource})" : '';
echo "Autoload check passed{$sourceNote}.\n";
