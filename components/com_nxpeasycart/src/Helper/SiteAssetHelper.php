<?php

namespace Joomla\Component\Nxpeasycart\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Document\HtmlDocument;

/**
 * Centralised loader for storefront assets.
 *
 * @since 0.1.5
 */
class SiteAssetHelper
{
    /**
     * Register and enqueue site CSS/JS via the Web Asset Manager.
     *
     * @since 0.1.5
     */
    public static function useSiteAssets(HtmlDocument $document): void
    {
        $wa = $document->getWebAssetManager();

        // Register CSS directly (this works reliably)
        $wa->registerAndUseStyle(
            'com_nxpeasycart.site.css',
            'media/com_nxpeasycart/css/site.css',
            ['version' => 'auto', 'relative' => true]
        );

        // Resolve JS path from joomla.asset.json and register directly
        $scriptPath = self::resolveScriptPath();

        if ($scriptPath !== null) {
            $wa->registerAndUseScript(
                'com_nxpeasycart.site',
                $scriptPath,
                ['version' => 'auto', 'relative' => true],
                ['defer' => true],
                ['core']
            );
        }
    }

    /**
     * Resolve the site JS bundle path from joomla.asset.json.
     *
     * @since 0.1.5
     */
    private static function resolveScriptPath(): ?string
    {
        // First try joomla.asset.json (production)
        $assetJsonPath = JPATH_ROOT . '/media/com_nxpeasycart/joomla.asset.json';

        if (is_file($assetJsonPath)) {
            $content = file_get_contents($assetJsonPath);
            $decoded = json_decode($content, true);

            if (json_last_error() === JSON_ERROR_NONE && isset($decoded['assets'])) {
                foreach ($decoded['assets'] as $asset) {
                    if (($asset['name'] ?? '') === 'com_nxpeasycart.site' && !empty($asset['uri'])) {
                        $uri = $asset['uri'];
                        // Handle both relative (com_nxpeasycart/js/...) and full paths
                        if (str_starts_with($uri, 'com_nxpeasycart/')) {
                            return 'media/' . $uri;
                        }
                        return $uri;
                    }
                }
            }
        }

        // Fallback: try Vite manifest (development)
        $manifestPath = JPATH_ROOT . '/media/com_nxpeasycart/.vite/manifest.json';

        if (is_file($manifestPath)) {
            $decoded = json_decode((string) file_get_contents($manifestPath), true);

            if (json_last_error() === JSON_ERROR_NONE && \is_array($decoded)) {
                $entry = reset($decoded);

                if (\is_array($entry) && !empty($entry['file'])) {
                    return 'media/com_nxpeasycart/' . ltrim($entry['file'], '/');
                }
            }
        }

        return null;
    }
}
