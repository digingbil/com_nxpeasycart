<?php

namespace Joomla\Component\Nxpeasycart\Site\View\Product;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Uri\Uri;

/**
 * Product frontend view.
 */

class HtmlView extends BaseHtmlView
{
    /**
     * Loaded product payload.
     *
     * @var array<string, mixed>
     */
    protected array $product = [];

    /**
     * Flag indicating whether the view renders a placeholder instead of a product.
     */
    protected bool $isPlaceholder = false;

    /**
     * Display handler.
     *
     * @param string|null $tpl Template name
     *
     * @return void
     */
    public function display($tpl = null): void
    {
        $app      = Factory::getApplication();
        $document = $this->document;

        $wa = $document->getWebAssetManager();
        $wa->registerAndUseStyle(
            'com_nxpeasycart.site.css',
            'media/com_nxpeasycart/css/site.css',
            ['version' => 'auto', 'relative' => true]
        );
        $wa->getRegistry()->addRegistryFile('media/com_nxpeasycart/joomla.asset.json');
        $wa->useScript('com_nxpeasycart.site');

        $model   = $this->getModel();
        $product = $model ? $model->getItem() : null;

        if (!$product) {
            $this->product       = [];
            $this->isPlaceholder = true;

            $document->setTitle(Text::_('COM_NXPEASYCART_PRODUCT_PLACEHOLDER'));

            parent::display($tpl);

            return;
        }

        $this->product       = $product;
        $this->isPlaceholder = false;
        $sitename            = (string) $app->get('sitename');
        $title               = (string) ($product['title'] ?? '');
        $fullTitle           = $sitename !== '' ? trim($title . ' | ' . $sitename, ' |') : $title;
        $document->setTitle($fullTitle);

        $descriptionSource = $product['short_desc'] ?: $product['long_desc'] ?? '';
        $description       = $this->truncateText(strip_tags((string) $descriptionSource), 160);

        if ($description !== '') {
            $document->setDescription($description);
        }

        $uri       = Uri::getInstance();
        $canonical = $uri->toString(['scheme', 'host', 'port', 'path', 'query']);
        $document->addHeadLink($canonical, 'canonical');

        $image = $product['images'][0] ?? '';

        $document->setMetaData('og:title', $title, 'property');
        $document->setMetaData('og:type', 'product', 'property');
        $document->setMetaData('og:url', $canonical, 'property');

        if ($image) {
            $document->setMetaData('og:image', $image, 'property');
        }

        if ($description !== '') {
            $document->setMetaData('og:description', $description, 'property');
            $document->setMetaData('twitter:description', $description);
        }

        $document->setMetaData('twitter:card', $image ? 'summary_large_image' : 'summary');
        $document->setMetaData('twitter:title', $title);

        if ($image) {
            $document->setMetaData('twitter:image', $image);
        }

        $schemaOffers = array_map(
            static function (array $variant) use ($canonical) {
                $availability = ((int) ($variant['stock'] ?? 0)) > 0
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock';

                return [
                    '@type'         => 'Offer',
                    'sku'           => $variant['sku'],
                    'priceCurrency' => $variant['currency'],
                    'price'         => number_format(((int) $variant['price_cents']) / 100, 2, '.', ''),
                    'availability'  => $availability,
                    'url'           => $canonical,
                ];
            },
            $product['variants'] ?? []
        );

        $schema = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Product',
            'name'        => $title,
            'description' => $description,
            'image'       => $image ?: null,
            'sku'         => $product['variants'][0]['sku'] ?? null,
        ];

        if (!empty($schemaOffers)) {
            $schema['offers'] = \count($schemaOffers) === 1 ? $schemaOffers[0] : $schemaOffers;
        }

        $document->addCustomTag(
            '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>'
        );

        $pathway = $app->getPathway();
        $pathway->addItem($title, $canonical);

        parent::display($tpl);
    }

    /**
     * Truncate text to the provided length.
     */
    private function truncateText(string $text, int $length): string
    {
        $text = trim($text);

        if ($text === '' || mb_strlen($text) <= $length) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $length - 1)) . '…';
    }
}
