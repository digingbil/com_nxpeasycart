<?php

namespace Joomla\Component\Nxpeasycart\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Document\Document;
use Joomla\CMS\Uri\Uri;

/**
 * Registers shared storefront assets (CSS/JS) with Joomla's Web Asset Manager.
 */
class AssetRegistrar
{
    private const SITE_SCRIPT_ASSET = 'com_nxpeasycart.site';
    private const SITE_STYLE_ASSET  = 'com_nxpeasycart.site.css';
    private const MANIFEST_PATH     = '/media/com_nxpeasycart/joomla.asset.json';

    /**
     * Ensure the storefront CSS + JS bundles are available on the current document.
     */
    public static function ensureSiteAssets(Document $document): void
    {
        $wa = $document->getWebAssetManager();

        $wa->registerAndUseStyle(
            self::SITE_STYLE_ASSET,
            'media/com_nxpeasycart/css/site.css',
            ['version' => 'auto', 'relative' => true]
        );

        $manifest = JPATH_ROOT . self::MANIFEST_PATH;

        if (is_file($manifest)) {
            $wa->getRegistry()->addRegistryFile($manifest);
        }

        if (!$wa->assetExists('script', self::SITE_SCRIPT_ASSET)) {
            $wa->registerScript(
                self::SITE_SCRIPT_ASSET,
                rtrim(Uri::root(), '/') . '/media/com_nxpeasycart/js/site.iife.js',
                [],
                ['defer' => true, 'version' => 'auto']
            );
        }

        $wa->useScript(self::SITE_SCRIPT_ASSET);
    }
}
