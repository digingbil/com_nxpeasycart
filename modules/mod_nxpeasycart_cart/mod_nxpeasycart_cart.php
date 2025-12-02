<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\Component\Nxpeasycart\Administrator\Helper\ConfigHelper;
use Joomla\Component\Nxpeasycart\Site\Helper\SiteAssetHelper;
use Joomla\Component\Nxpeasycart\Site\Service\CartPresentationService;
use Joomla\Component\Nxpeasycart\Site\Service\CartSessionService;
use Joomla\Component\Nxpeasycart\Site\Service\TemplateAdapter;
use Ramsey\Uuid\Uuid;

$needsVendor = !class_exists(Uuid::class, false);
$runningInsideJoomla = \defined('JPATH_LIBRARIES') && is_file(JPATH_LIBRARIES . '/src/Layout/FileLayout.php');

if ($needsVendor) {
    $autoloadCandidates = [];

    if (\defined('JPATH_SITE')) {
        $autoloadCandidates[] = JPATH_SITE . '/components/com_nxpeasycart/vendor/autoload.php';
    }

    if (\defined('JPATH_ADMINISTRATOR')) {
        $autoloadCandidates[] = JPATH_ADMINISTRATOR . '/components/com_nxpeasycart/vendor/autoload.php';
    }

    if (!$runningInsideJoomla) {
        $autoloadCandidates[] = dirname(__DIR__, 2) . '/vendor/autoload.php';
    }

    foreach (array_unique($autoloadCandidates) as $autoload) {
        if (is_file($autoload)) {
            require_once $autoload;

            if (class_exists(Uuid::class, false)) {
                break;
            }
        }
    }
}

foreach (
    [
        'Joomla\\Component\\Nxpeasycart\\Site' => JPATH_SITE . '/components/com_nxpeasycart/src',
        'Joomla\\Component\\Nxpeasycart\\Administrator' => JPATH_ADMINISTRATOR . '/components/com_nxpeasycart/src',
    ] as $namespace => $path
) {
    \JLoader::registerNamespace($namespace, $path, false, false, 'psr4');
}

$app = Factory::getApplication();
$container = Factory::getContainer();

$module->cache = false;

// Ensure component services are registered even when the module is rendered standalone.
if (!$container->has(CartSessionService::class)) {
    $providerPath = JPATH_ADMINISTRATOR . '/components/com_nxpeasycart/services/provider.php';

    if (is_file($providerPath)) {
        $provider = require $providerPath;
        $container->registerServiceProvider($provider);
    }
}

$language = $app->getLanguage();
$language->load('mod_nxpeasycart_cart', JPATH_SITE);
$language->load('mod_nxpeasycart_cart', __DIR__);

// Use the centralised asset helper to register site JS/CSS with proper fallbacks.
$document = $app->getDocument();

if ($document instanceof \Joomla\CMS\Document\HtmlDocument) {
    SiteAssetHelper::useSiteAssets($document);
}

try {
    $sessionService = $container->get(CartSessionService::class);
    $presentation   = $container->get(CartPresentationService::class);
    $cart           = $presentation->hydrate($sessionService->current());
} catch (\Throwable $exception) {
    $cart = [
        'items'   => [],
        'summary' => [
            'currency'       => ConfigHelper::getBaseCurrency(),
            'subtotal_cents' => 0,
        'total_cents'    => 0,
    ],
];
}
$theme = TemplateAdapter::resolve();

require ModuleHelper::getLayoutPath('mod_nxpeasycart_cart', $params->get('layout', 'default'));
