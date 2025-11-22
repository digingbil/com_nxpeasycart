<?php

namespace Joomla\Component\Nxpeasycart\Site\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;
use Joomla\Component\Nxpeasycart\Administrator\Helper\ConfigHelper;
use Joomla\Component\Nxpeasycart\Site\Helper\RouteHelper;

/**
 * Model powering the shop landing page.
 */
class LandingModel extends BaseDatabaseModel
{
    /**
     * {@inheritDoc}
     */
    protected function populateState($ordering = null, $direction = null)
    {
        $app   = Factory::getApplication();
        $menu  = $app->getMenu()->getActive();
        $params = $menu ? clone $menu->getParams() : new Registry();

        $this->setState('params', $params);

        $categoryLimitParam = (int) $params->get('category_tile_limit', 6);
        $categoryLimit = $categoryLimitParam <= 0 ? 0 : $this->clampPositive($categoryLimitParam, 1);

        $this->setState('landing.category_ids', $this->normaliseIds($params->get('category_root_ids', [])));
        $this->setState('landing.category_limit', $categoryLimit);
        $this->setState('landing.featured_limit', $this->clampPositive((int) $params->get('featured_limit', 6), 1));
        $this->setState('landing.arrivals_limit', $this->clampPositive((int) $params->get('new_arrivals_limit', 4), 1));
        $this->setState('landing.deals_limit', $this->clampPositive((int) $params->get('deals_limit', 4), 1));
    }

    /**
     * Retrieve hero copy.
     *
     * @return array<string, mixed>
     */
    public function getHero(): array
    {
        /** @var Registry $params */
        $params   = $this->getState('params') instanceof Registry ? $this->getState('params') : new Registry();
        $app      = Factory::getApplication();
        $sitename = (string) $app->get('sitename', '');

        $defaultTitle = $sitename !== ''
            ? Text::sprintf('COM_NXPEASYCART_LANDING_HERO_TITLE_DEFAULT_WITH_NAME', $sitename)
            : Text::_('COM_NXPEASYCART_LANDING_HERO_TITLE_DEFAULT');

        $title = trim((string) $params->get('hero_headline', ''));
        $title = $title !== '' ? $title : $defaultTitle;

        $subtitle = trim((string) $params->get('hero_subheadline', ''));

        if ($subtitle === '') {
            $subtitle = Text::_('COM_NXPEASYCART_LANDING_HERO_SUBTITLE_DEFAULT');
        }

        $ctaLabel = trim((string) $params->get('hero_cta_label', ''));

        if ($ctaLabel === '') {
            $ctaLabel = Text::_('COM_NXPEASYCART_LANDING_HERO_CTA_LABEL_DEFAULT');
        }

        $ctaLink = trim((string) $params->get('hero_cta_link', ''));

        if ($ctaLink === '') {
            $ctaLink = 'index.php?option=com_nxpeasycart&view=category';
        }

        return [
            'eyebrow' => Text::_('COM_NXPEASYCART_LANDING_EYEBROW'),
            'title'    => $title,
            'subtitle' => $subtitle,
            'cta'      => [
                'label' => $ctaLabel,
                'link'  => $ctaLink,
            ],
        ];
    }

    /**
     * Retrieve search configuration.
     *
     * @return array<string, string>
     */
    public function getSearch(): array
    {
        /** @var Registry $params */
        $params      = $this->getState('params') instanceof Registry ? $this->getState('params') : new Registry();
        $placeholder = trim((string) $params->get('search_placeholder', ''));

        if ($placeholder === '') {
            $placeholder = Text::_('COM_NXPEASYCART_LANDING_SEARCH_PLACEHOLDER_DEFAULT');
        }

        return [
            'placeholder' => $placeholder,
            'action'      => RouteHelper::getCategoryRoute(),
        ];
    }

    /**
     * Retrieve category tiles.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getCategoryTiles(): array
    {
        $limitState = $this->getState('landing.category_limit', 6);

        if ($limitState === null) {
            $limit = 6;
        } elseif ((int) $limitState <= 0) {
            $limit = null;
        } else {
            $limit = max(0, (int) $limitState);
        }
        $ids   = (array) $this->getState('landing.category_ids', []);

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('id'),
                $db->quoteName('title'),
                $db->quoteName('slug'),
            ])
            ->from($db->quoteName('#__nxp_easycart_categories'));

        if (!empty($ids)) {
            $placeholders = [];

            foreach ($ids as $index => $id) {
                $placeholder    = ':landingRoot' . $index;
                $placeholders[] = $placeholder;
                $boundId        = (int) $id;
                $query->bind($placeholder, $boundId, ParameterType::INTEGER);
            }

            $query->where(
                $db->quoteName('id') . ' IN (' . implode(',', $placeholders) . ')'
            );

            $query->order(
                'FIELD(' . $db->quoteName('id') . ', ' . implode(',', array_map(static fn ($id) => (int) $id, $ids)) . ')'
            );
        } else {
            $query->where(
                '(' . $db->quoteName('parent_id') . ' IS NULL'
                . ' OR ' . $db->quoteName('parent_id') . ' = 0)'
            )
                ->order($db->quoteName('sort') . ' ASC')
                ->order($db->quoteName('title') . ' ASC');
        }

        if ($limit === null) {
            $db->setQuery($query);
        } else {
            $db->setQuery($query, 0, $limit);
        }

        $rows = $db->loadObjectList() ?: [];

        $tiles = [];

        foreach ($rows as $row) {
            $tiles[] = [
                'id'    => (int) $row->id,
                'title' => (string) $row->title,
                'slug'  => (string) $row->slug,
                'link'  => RouteHelper::getCategoryRoute((string) $row->slug),
            ];
        }

        return $tiles;
    }

    /**
     * Retrieve section titles organised by key.
     *
     * @return array<string, string>
     */
    public function getSectionTitles(): array
    {
        /** @var Registry $params */
        $params = $this->getState('params') instanceof Registry ? $this->getState('params') : new Registry();

        $featured = trim((string) $params->get('featured_title', ''));

        if ($featured === '') {
            $featured = Text::_('COM_NXPEASYCART_LANDING_FEATURED_TITLE_DEFAULT');
        }

        $arrivals = trim((string) $params->get('new_arrivals_title', ''));

        if ($arrivals === '') {
            $arrivals = Text::_('COM_NXPEASYCART_LANDING_NEW_ARRIVALS_TITLE_DEFAULT');
        }

        $deals = trim((string) $params->get('deals_title', ''));

        if ($deals === '') {
            $deals = Text::_('COM_NXPEASYCART_LANDING_DEALS_TITLE_DEFAULT');
        }

        return [
            'featured' => $featured,
            'arrivals' => $arrivals,
            'deals'    => $deals,
        ];
    }

    /**
     * Retrieve curated product collections for the landing page.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function getCollections(): array
    {
        $featuredLimit = max(0, (int) $this->getState('landing.featured_limit', 6));
        $arrivalsLimit = max(0, (int) $this->getState('landing.arrivals_limit', 4));
        $dealsLimit    = max(0, (int) $this->getState('landing.deals_limit', 4));

        if ($featuredLimit + $arrivalsLimit + $dealsLimit === 0) {
            return [
                'featured' => [],
                'arrivals' => [],
                'deals'    => [],
            ];
        }

        $categoryIds = (array) $this->getState('landing.category_ids', []);

        $featured = $this->fetchProducts($featuredLimit, $categoryIds, [], true);
        $featuredIds = array_column($featured, 'id');

        if ($featuredLimit > 0 && \count($featured) < $featuredLimit) {
            $needed   = $featuredLimit - \count($featured);
            $fallback = $this->fetchProducts($needed, $categoryIds, $featuredIds, false);
            $featured = array_slice(array_merge($featured, $fallback), 0, $featuredLimit);
            $featuredIds = array_column($featured, 'id');
        }

        $excludeIds = $featuredIds;

        $newArrivals = $this->fetchProducts($arrivalsLimit, $categoryIds, $excludeIds, false);
        if (!empty($newArrivals)) {
            $excludeIds = array_merge($excludeIds, array_column($newArrivals, 'id'));
        }

        $deals = $this->fetchProducts($dealsLimit, $categoryIds, $excludeIds, false);

        return [
            'featured' => $featured,
            'arrivals' => $newArrivals,
            'deals'    => $deals,
        ];
    }

    /**
     * Retrieve trust badge copy.
     */
    public function getTrustBadge(): string
    {
        /** @var Registry $params */
        $params = $this->getState('params') instanceof Registry ? $this->getState('params') : new Registry();
        $text   = trim((string) $params->get('trust_badge_text', ''));

        if ($text === '') {
            $text = Text::_('COM_NXPEASYCART_LANDING_TRUST_BADGE_DEFAULT');
        }

        return $text;
    }

    /**
     * Fetch active products with aggregated pricing.
     *
     * @param int                  $limit        Maximum rows to fetch
     * @param array<int, int>      $categoryIds  Optional category filter
     * @param array<int, int>      $excludeIds   Product IDs to exclude
     * @param bool                 $featuredOnly Limit results to featured items
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchProducts(
        int $limit,
        array $categoryIds = [],
        array $excludeIds = [],
        bool $featuredOnly = false
    ): array {
        if ($limit <= 0) {
            return [];
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('p.id'),
                $db->quoteName('p.slug'),
                $db->quoteName('p.title'),
                $db->quoteName('p.short_desc'),
                $db->quoteName('p.images'),
                $db->quoteName('p.featured'),
                $db->quoteName('p.created'),
                'COUNT(DISTINCT ' . $db->quoteName('v.id') . ') AS ' . $db->quoteName('variant_count'),
                'MIN(' . $db->quoteName('v.id') . ') AS ' . $db->quoteName('primary_variant_id'),
                'MIN(' . $db->quoteName('v.price_cents') . ') AS ' . $db->quoteName('min_price_cents'),
                'MAX(' . $db->quoteName('v.price_cents') . ') AS ' . $db->quoteName('max_price_cents'),
                'MAX(' . $db->quoteName('v.currency') . ') AS ' . $db->quoteName('currency'),
            ])
            ->from($db->quoteName('#__nxp_easycart_products', 'p'))
            ->leftJoin(
                $db->quoteName('#__nxp_easycart_variants', 'v')
                . ' ON ' . $db->quoteName('v.product_id') . ' = ' . $db->quoteName('p.id')
                . ' AND ' . $db->quoteName('v.active') . ' = 1'
            )
            ->where($db->quoteName('p.active') . ' = 1')
            ->group($db->quoteName('p.id'))
            ->order($db->quoteName('p.created') . ' DESC');

        if ($featuredOnly) {
            $query->where($db->quoteName('p.featured') . ' = 1');
        }

        $excludeIds = array_values(
            array_filter(
                array_map('intval', array_unique($excludeIds)),
                static fn ($id) => $id > 0
            )
        );

        if (!empty($excludeIds)) {
            $query->where(
                $db->quoteName('p.id') . ' NOT IN (' . implode(',', $excludeIds) . ')'
            );
        }

        $categoryIds = array_values(
            array_filter(
                array_map('intval', array_unique($categoryIds)),
                static fn ($id) => $id > 0
            )
        );

        if (!empty($categoryIds)) {
            $query->innerJoin(
                $db->quoteName('#__nxp_easycart_product_categories', 'pc')
                . ' ON ' . $db->quoteName('pc.product_id') . ' = ' . $db->quoteName('p.id')
            )
                ->where(
                    $db->quoteName('pc.category_id') . ' IN (' . implode(',', $categoryIds) . ')'
                );
        }

        $db->setQuery($query, 0, $limit);

        $rows         = $db->loadObjectList() ?: [];
        $baseCurrency = ConfigHelper::getBaseCurrency();

        $products = [];

        foreach ($rows as $row) {
            $images   = $this->decodeImages($row->images ?? '');
            $currency = $row->currency !== null ? strtoupper((string) $row->currency) : $baseCurrency;

            $minPrice = $row->min_price_cents !== null ? (int) $row->min_price_cents : null;
            $maxPrice = $row->max_price_cents !== null ? (int) $row->max_price_cents : null;

            $priceLabel = null;

            if ($minPrice !== null && $minPrice > 0) {
                if ($maxPrice !== null && $maxPrice > $minPrice) {
                    $priceLabel = Text::sprintf(
                        'COM_NXPEASYCART_PRODUCT_PRICE_RANGE',
                        $this->formatMoney($minPrice, $currency),
                        $this->formatMoney($maxPrice, $currency)
                    );
                } else {
                    $priceLabel = Text::sprintf(
                        'COM_NXPEASYCART_PRODUCT_PRICE_FROM',
                        $this->formatMoney($minPrice, $currency)
                    );
                }
            }

            $variantCount = $row->variant_count !== null ? (int) $row->variant_count : 0;
            $primaryVariantId = ($variantCount === 1 && $row->primary_variant_id !== null)
                ? (int) $row->primary_variant_id
                : null;

            $products[] = [
                'id'          => (int) $row->id,
                'title'       => (string) $row->title,
                'slug'        => (string) $row->slug,
                'short_desc'  => (string) ($row->short_desc ?? ''),
                'images'      => $images,
                'featured'    => (bool) $row->featured,
                'price_label' => $priceLabel,
                'primary_variant_id' => $primaryVariantId,
                'variant_count' => $variantCount,
                'link'        => RouteHelper::getProductRoute((string) $row->slug),
            ];
        }

        return $products;
    }

    /**
     * Decode JSON image payload.
     *
     * @return array<int, string>
     */
    private function decodeImages(string $payload): array
    {
        if ($payload === '') {
            return [];
        }

        $decoded = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE || !\is_array($decoded)) {
            return [];
        }

        $filtered = array_filter(
            array_map(
                static function ($value) {
                    if (!\is_string($value)) {
                        return null;
                    }

                    $trimmed = trim($value);

                    if ($trimmed === '') {
                        return null;
                    }

                    if (
                        !str_starts_with($trimmed, 'http://')
                        && !str_starts_with($trimmed, 'https://')
                        && !str_starts_with($trimmed, '//')
                    ) {
                        $base     = rtrim(Uri::root(true), '/');
                        $relative = '/' . ltrim($trimmed, '/');

                        $trimmed = ($base === '' ? '' : $base) . $relative;
                    }

                    return $trimmed;
                },
                $decoded
            )
        );

        return array_values($filtered);
    }

    /**
     * Normalise an array of identifiers.
     *
     * @param mixed $raw Input from menu params.
     *
     * @return array<int>
     */
    private function normaliseIds($raw): array
    {
        if (\is_string($raw) && str_starts_with(trim($raw), '[')) {
            $decoded = json_decode($raw, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $raw = $decoded;
            }
        }

        $ids = array_map('intval', (array) $raw);
        $ids = array_filter($ids, static fn ($id) => $id > 0);

        return array_values(array_unique($ids));
    }

    /**
     * Ensure a value is at least the provided minimum.
     */
    private function clampPositive(int $value, int $minimum): int
    {
        return max($minimum, $value);
    }

    /**
     * Format a monetary amount.
     */
    private function formatMoney(int $cents, string $currency): string
    {
        $amount = $cents / 100;

        if (class_exists(\NumberFormatter::class, false)) {
            try {
                $locale = null;

                try {
                    $language = null;
                    $app      = Factory::getApplication();

                    if ($app && method_exists($app, 'getLanguage')) {
                        $language = $app->getLanguage();
                    } elseif (method_exists(Factory::class, 'getLanguage')) {
                        $language = Factory::getLanguage();
                    }

                    $tag = $language && method_exists($language, 'getTag')
                        ? (string) $language->getTag()
                        : '';

                    if ($tag !== '') {
                        $locale = str_replace('-', '_', $tag);
                    }
                } catch (\Throwable $exception) {
                    // Use default fallback below.
                }

                if ($locale === null || $locale === '') {
                    if (function_exists('locale_get_default')) {
                        $locale = locale_get_default() ?: 'en_US';
                    } else {
                        $locale = 'en_US';
                    }
                }

                $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);

                $formatted = $formatter->formatCurrency($amount, $currency);

                if ($formatted !== false) {
                    return (string) $formatted;
                }
            } catch (\Throwable $exception) {
                // Fallback below.
            }
        }

        return sprintf('%s %.2f', $currency, $amount);
    }
}
