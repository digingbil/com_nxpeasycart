<?php

namespace Joomla\Component\Nxpeasycart\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Uri\Uri;

/**
 * Centralised loader for storefront assets with manifest fallback.
 */
class SiteAssetHelper
{
    /**
    * Register and enqueue site CSS/JS via the Web Asset Manager, with
    * a Vite manifest fallback when the asset registry is unavailable.
     */
    public static function useSiteAssets(HtmlDocument $document): void
    {
        $wa = $document->getWebAssetManager();
        $wa->registerAndUseStyle(
            'com_nxpeasycart.site.css',
            'media/com_nxpeasycart/css/site.css',
            ['version' => 'auto', 'relative' => true]
        );

        $assetRegistry = 'media/com_nxpeasycart/joomla.asset.json';
        $assetRegistryPath = JPATH_ROOT . '/' . $assetRegistry;

        if (is_file($assetRegistryPath)) {
            // Use absolute path to avoid registry resolution issues in site context.
            $wa->getRegistry()->addRegistryFile($assetRegistryPath);

            try {
                $wa->useScript('com_nxpeasycart.site');
                $after = array_keys($document->getScripts());

                foreach ($after as $script) {
                    if (str_contains($script, 'com_nxpeasycart/js/site')) {
                        return;
                    }
                }
            } catch (\Throwable $exception) {
                // Fall through to manifest fallback.
            }
        }

        $manifestScript = self::resolveManifestScript();

        if ($manifestScript !== null) {
            try {
                $wa->registerAndUseScript(
                    'com_nxpeasycart.site.manifest',
                    $manifestScript,
                    ['relative' => true],
                    ['defer' => true]
                );
                return;
            } catch (\Throwable $exception) {
                // Swallow; no script will be enqueued if WAM fails.
            }
        }
    }

    /**
     * Debug helper to log enqueued scripts.
     */
    private static function logScripts($wa, string $prefix): void
    {
        $names = array_map(static fn($asset) => method_exists($asset, 'getName') ? $asset->getName() : '', $wa->getAssets('script'));
        $names = array_filter($names, static fn($name) => $name !== '');
        @file_put_contents(JPATH_ROOT . '/administrator/logs/com_nxpeasycart_assets.log', $prefix . ' ' . implode(',', $names) . PHP_EOL, FILE_APPEND);
    }

    /**
     * Resolve the hashed site bundle path from the Vite manifest.
     */
    private static function resolveManifestScript(): ?string
    {
        $manifestPath = JPATH_ROOT . '/media/com_nxpeasycart/.vite/manifest.json';

        if (!is_file($manifestPath)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($manifestPath), true);

        if (json_last_error() !== JSON_ERROR_NONE || !\is_array($decoded) || !$decoded) {
            return null;
        }

        $entry = reset($decoded);

        if (!\is_array($entry) || empty($entry['file'])) {
            return null;
        }

        $uri = (string) $entry['file'];

        return 'media/com_nxpeasycart/' . ltrim($uri, '/');
    }
}
