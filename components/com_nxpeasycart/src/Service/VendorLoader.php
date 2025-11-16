<?php

namespace Joomla\Component\Nxpeasycart\Site\Service;

\defined('_JEXEC') or die;

use Composer\Autoload\ClassLoader;

/**
 * Conservative vendor loader for site runtime when the packaged vendor tree is absent.
 */
class VendorLoader
{
    private const ALLOWED_PACKAGES = [
        'brick/math',
        'brick/money',
        'guzzlehttp/guzzle',
        'guzzlehttp/promises',
        'guzzlehttp/psr7',
        'psr/http-client',
        'psr/http-factory',
        'psr/http-message',
        'psr/simple-cache',
        'psr/log',
        'ralouphie/getallheaders',
        'ramsey/collection',
        'ramsey/uuid',
        'symfony/deprecation-contracts',
        'symfony/polyfill-ctype',
        'symfony/polyfill-intl-grapheme',
        'symfony/polyfill-intl-idn',
        'symfony/polyfill-intl-normalizer',
        'symfony/polyfill-mbstring',
        'symfony/polyfill-php72',
    ];

    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        if (self::loadComponentVendor()) {
            self::$registered = true;
            return;
        }

        self::registerSelectivePackages();
        self::$registered = true;
    }

    private static function loadComponentVendor(): bool
    {
        $paths = [
            JPATH_SITE . '/components/com_nxpeasycart/vendor/autoload.php',
            JPATH_ADMINISTRATOR . '/components/com_nxpeasycart/vendor/autoload.php',
        ];

        foreach ($paths as $path) {
            if (is_file($path)) {
                require_once $path;
                return true;
            }
        }

        return false;
    }

    private static function registerSelectivePackages(): void
    {
        $rootVendor = dirname(__DIR__, 4) . '/vendor';

        if (!is_dir($rootVendor)) {
            return;
        }

        $classLoaderFile = $rootVendor . '/composer/ClassLoader.php';

        if (!class_exists(ClassLoader::class, false)) {
            if (!is_file($classLoaderFile)) {
                return;
            }

            require_once $classLoaderFile;
        }

        $loader = new ClassLoader();
        self::registerPsr4Namespaces($loader, $rootVendor);
        self::registerClassMap($loader, $rootVendor);
        self::requireAutoloadFiles($rootVendor);

        // Prepend to avoid redefining the existing global composer loader.
        $loader->register(true);
    }

    private static function registerPsr4Namespaces(ClassLoader $loader, string $vendorDir): void
    {
        $mapFile = $vendorDir . '/composer/autoload_psr4.php';

        if (!is_file($mapFile)) {
            return;
        }

        $map = require $mapFile;

        foreach ($map as $prefix => $paths) {
            $paths = (array) $paths;

            if (!self::pathsAllowed($paths)) {
                continue;
            }

            $loader->setPsr4($prefix, $paths);
        }
    }

    private static function registerClassMap(ClassLoader $loader, string $vendorDir): void
    {
        $classMapFile = $vendorDir . '/composer/autoload_classmap.php';

        if (!is_file($classMapFile)) {
            return;
        }

        $classMap = require $classMapFile;
        $filtered = [];

        foreach ($classMap as $class => $path) {
            if (self::pathAllowed($path)) {
                $filtered[$class] = $path;
            }
        }

        if (!empty($filtered)) {
            $loader->addClassMap($filtered);
        }
    }

    private static function requireAutoloadFiles(string $vendorDir): void
    {
        $filesMap = $vendorDir . '/composer/autoload_files.php';

        if (!is_file($filesMap)) {
            return;
        }

        $files = require $filesMap;

        foreach ($files as $file) {
            if (self::pathAllowed($file) && is_file($file)) {
                require_once $file;
            }
        }
    }

    /**
     * @param array<int, string> $paths
     */
    private static function pathsAllowed(array $paths): bool
    {
        foreach ($paths as $path) {
            if (self::pathAllowed($path)) {
                return true;
            }
        }

        return false;
    }

    private static function pathAllowed(string $path): bool
    {
        $normalized = str_replace('\\', '/', $path);

        foreach (self::ALLOWED_PACKAGES as $package) {
            if (str_contains($normalized, '/vendor/' . $package . '/')) {
                return true;
            }
        }

        return false;
    }
}
