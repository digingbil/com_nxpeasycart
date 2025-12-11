<?php
/**
 * @package     NXP Easy Cart
 * @subpackage  com_nxpeasycart
 *
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

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
use Joomla\Component\Nxpeasycart\Administrator\Helper\MoneyHelper;
use Joomla\Component\Nxpeasycart\Administrator\Helper\ProductStatus;
use Joomla\Component\Nxpeasycart\Site\Helper\CategoryPathHelper;
use Joomla\Component\Nxpeasycart\Site\Helper\RouteHelper;

/**
 * Model powering the shop landing page.
 *
 * @since 0.1.5
 */
class LandingModel extends BaseDatabaseModel
{
    /**
     * {@inheritDoc}
     *
     * @since 0.1.5
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
     *
     * @since 0.1.5
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

        $ctaEnabled = $params->get('hero_cta_enabled', 1);
        $ctaEnabled = \is_numeric($ctaEnabled) ? (int) $ctaEnabled : (int) (bool) $ctaEnabled;
        $ctaEnabled = $ctaEnabled > 0;

        return [
            'eyebrow' => Text::_('COM_NXPEASYCART_LANDING_EYEBROW'),
            'title'    => $title,
            'subtitle' => $subtitle,
            'cta'      => [
                'enabled' => $ctaEnabled,
                'label' => $ctaLabel,
                'link'  => $ctaLink,
            ],
        ];
    }

    /**
     * Retrieve search configuration.
     *
     * @return array<string, string>
     *
     * @since 0.1.5
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
            'action'      => RouteHelper::getCategoryRoute(null, null, false),
        ];
    }

    /**
     * Retrieve category tiles.
     *
     * @return array<int, array<string, mixed>>
     *
     * @since 0.1.5
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
                'link'  => RouteHelper::getCategoryRoute((string) $row->slug, null, false),
            ];
        }

        return $tiles;
    }

    /**
     * Retrieve section titles organised by key.
     *
     * @return array<string, string>
     *
     * @since 0.1.5
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
     *
     * @since 0.1.5
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
     *
     * @since 0.1.5
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
     *
     * @since 0.1.5
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
        $activeStatus = ProductStatus::ACTIVE;
        $outOfStockStatus = ProductStatus::OUT_OF_STOCK;
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('p.id'),
                $db->quoteName('p.slug'),
                $db->quoteName('p.title'),
                $db->quoteName('p.short_desc'),
                $db->quoteName('p.images'),
                $db->quoteName('p.featured'),
                $db->quoteName('p.created'),
                $db->quoteName('p.active'),
                $db->quoteName('p.primary_category_id') . ' AS ' . $db->quoteName('primary_category_id'),
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
            ->leftJoin(
                $db->quoteName('#__nxp_easycart_categories', 'primary_cat')
                . ' ON ' . $db->quoteName('primary_cat.id') . ' = ' . $db->quoteName('p.primary_category_id')
            )
            ->select($db->quoteName('primary_cat.slug') . ' AS ' . $db->quoteName('primary_category_slug'))
            ->where(
                $db->quoteName('p.active') . ' IN (:activeStatus, :outOfStockStatus)'
            )
            ->bind(':activeStatus', $activeStatus, ParameterType::INTEGER)
            ->bind(':outOfStockStatus', $outOfStockStatus, ParameterType::INTEGER)
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
        // Always use configured base currency (single-currency MVP per INSTRUCTIONS.md 3.1)
        $baseCurrency = ConfigHelper::getBaseCurrency();

        $products = [];

        foreach ($rows as $row) {
            $images   = $this->decodeImages($row->images ?? '');
            // Single-currency MVP: always use configured base currency, not stored variant currency
            $currency = $baseCurrency;
            $status   = ProductStatus::normalise($row->active ?? ProductStatus::INACTIVE);

            $minPrice = $row->min_price_cents !== null ? (int) $row->min_price_cents : null;
            $maxPrice = $row->max_price_cents !== null ? (int) $row->max_price_cents : null;

            $priceLabel = null;

            if ($minPrice !== null && $minPrice > 0) {
                if ($maxPrice !== null && $maxPrice > $minPrice) {
                    // Multiple variants with different prices - show range
                    $priceLabel = Text::sprintf(
                        'COM_NXPEASYCART_PRODUCT_PRICE_RANGE',
                        MoneyHelper::format($minPrice, $currency),
                        MoneyHelper::format($maxPrice, $currency)
                    );
                } else {
                    // Single price (min equals max, or only one variant) - show plain price
                    $priceLabel = MoneyHelper::format($minPrice, $currency);
                }
            }

            $variantCount = $row->variant_count !== null ? (int) $row->variant_count : 0;
            $primaryVariantId = ($variantCount === 1 && $row->primary_variant_id !== null)
                ? (int) $row->primary_variant_id
                : null;
            $primaryCategoryId = $row->primary_category_id !== null ? (int) $row->primary_category_id : 0;
            $primaryPath = [];

            if ($primaryCategoryId > 0) {
                $primaryPath = CategoryPathHelper::getPath($db, $primaryCategoryId);
            } elseif (!empty($row->primary_category_slug)) {
                $primaryPath = CategoryPathHelper::getPathForSlug($db, (string) $row->primary_category_slug);
            }

            $linkCategoryPath = !empty($primaryPath) ? implode('/', $primaryPath) : '';

            $products[] = [
                'id'          => (int) $row->id,
                'title'       => (string) $row->title,
                'slug'        => (string) $row->slug,
                'short_desc'  => (string) ($row->short_desc ?? ''),
                'images'      => $images,
                'featured'    => (bool) $row->featured,
                'status'      => $status,
                'out_of_stock' => ProductStatus::isOutOfStock($status),
                'price_label' => $priceLabel,
                'primary_variant_id' => $primaryVariantId,
                'variant_count' => $variantCount,
                'link'        => RouteHelper::getProductRoute((string) $row->slug, $linkCategoryPath ?: null, false),
            ];
        }

        return $products;
    }

    /**
     * Decode JSON image payload.
     *
     * @return array<int, string>
     *
     * @since 0.1.5
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
     *
     * @since 0.1.5
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
     *
     * @since 0.1.5
     */
    private function clampPositive(int $value, int $minimum): int
    {
        return max($minimum, $value);
    }

}
