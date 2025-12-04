<?php

namespace Joomla\Component\Nxpeasycart\Site\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\ParameterType;
use Joomla\Component\Nxpeasycart\Administrator\Helper\ConfigHelper;
use Joomla\Component\Nxpeasycart\Administrator\Helper\MoneyHelper;
use Joomla\Component\Nxpeasycart\Administrator\Helper\ProductStatus;
use Joomla\Component\Nxpeasycart\Site\Helper\CategoryPathHelper;
use Joomla\Component\Nxpeasycart\Site\Helper\RouteHelper;

/**
 * Frontend category model.
 *
 * @since 0.1.5
 */
class CategoryModel extends BaseDatabaseModel
{
    /**
     * Currently loaded category payload.
     *
     * @var array<string, mixed>|null
     *
     * @since 0.1.5
     */
    protected ?array $item = null;

    /**
     * Cached product listing for the category.
     *
     * @var array<int, array<string, mixed>>|null
     *
     * @since 0.1.5
     */
    protected ?array $products = null;

    /**
     * Pagination metadata for the current listing.
     *
     * @var array<string, int>
     *
     * @since 0.1.5
     */
    protected array $pagination = [];

    /**
     * {@inheritDoc}
     *
     * @since 0.1.5
     */
    protected function populateState($ordering = null, $direction = null)
    {
        $app   = Factory::getApplication();
        $input = $app->input;

        $this->setState('category.id', $input->getInt('id'));
        $this->setState('category.slug', $input->getCmd('slug', ''));
        $this->setState('filter.search', trim($input->getString('q', '')));
        $this->setState('category.pagination_mode', ConfigHelper::getCategoryPaginationMode());

        $limit = $input->getInt('limit', ConfigHelper::getCategoryPageSize());
        $start = $input->getInt('start', 0);

        $limit = $limit > 0 ? $limit : ConfigHelper::getCategoryPageSize();
        $start = $start >= 0 ? $start : 0;

        $this->setState('list.limit', $limit);
        $this->setState('list.start', $start);

        $rootSelection = [];

        $menu = $app->getMenu()->getActive();

        if ($menu) {
            $params = $menu->getParams();
            $raw    = $params->get('root_categories', []);

            if (is_string($raw) && str_starts_with(trim($raw), '[')) {
                $decoded = json_decode($raw, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    $raw = $decoded;
                }
            }

            $rootSelection = array_values(
                array_unique(
                    array_filter(
                        array_map('intval', (array) $raw)
                    )
                )
            );
        }

        $this->setState('category.root_ids', $rootSelection);

        // When no explicit category is requested we keep the state empty so the
        // view can render an 'all products' overview scoped to the selected roots.
    }

    /**
     * Retrieve category metadata.
     *
     * @since 0.1.5
     */
    public function getItem(): ?array
    {
        if ($this->item !== null) {
            return $this->item;
        }

        $db   = $this->getDatabase();
        $id   = (int) $this->getState('category.id');
        $slug = (string) $this->getState('category.slug');

        if ($id > 0 || $slug !== '') {
            $query = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__nxp_easycart_categories'))
                ->order($db->quoteName('title') . ' ASC');

            if ($id > 0) {
                $query->where($db->quoteName('id') . ' = :categoryId')
                    ->bind(':categoryId', $id, ParameterType::INTEGER);
            } else {
                $query->where($db->quoteName('slug') . ' = :categorySlug')
                    ->bind(':categorySlug', $slug, ParameterType::STRING);
            }

            $db->setQuery($query);
            $row = $db->loadObject();

            if ($row) {
                $this->item = [
                    'id'        => (int) $row->id,
                    'title'     => (string) $row->title,
                    'slug'      => (string) $row->slug,
                    'parent_id' => $row->parent_id !== null ? (int) $row->parent_id : null,
                    'sort'      => (int) $row->sort,
                ];

                return $this->item;
            }
        }

        $this->item = [
            'id'        => null,
            'title'     => Text::_('COM_NXPEASYCART_CATEGORY_ALL'),
            'slug'      => '',
            'parent_id' => null,
            'sort'      => 0,
        ];

        return $this->item;
    }

    /**
     * Retrieve published products in the current category.
     *
     * @return array<int, array<string, mixed>>
     *
     * @since 0.1.5
     */
    public function getProducts(): array
    {
        if ($this->products !== null) {
            return $this->products;
        }

        $category = $this->getItem();

        $db    = $this->getDatabase();
        $activeStatus = ProductStatus::ACTIVE;
        $outOfStockStatus = ProductStatus::OUT_OF_STOCK;
        $limit = (int) $this->getState('list.limit', ConfigHelper::getCategoryPageSize());
        $start = (int) $this->getState('list.start', 0);
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('p.id'),
                $db->quoteName('p.slug'),
                $db->quoteName('p.title'),
                $db->quoteName('p.short_desc'),
                $db->quoteName('p.featured'),
                $db->quoteName('p.images'),
                $db->quoteName('p.active'),
                $db->quoteName('p.primary_category_id') . ' AS ' . $db->quoteName('primary_category_id'),
                'COUNT(DISTINCT ' . $db->quoteName('v.id') . ') AS ' . $db->quoteName('variant_count'),
                'MIN(' . $db->quoteName('v.id') . ') AS ' . $db->quoteName('primary_variant_id'),
                'MIN(' . $db->quoteName('v.price_cents') . ') AS ' . $db->quoteName('price_min'),
                'MAX(' . $db->quoteName('v.price_cents') . ') AS ' . $db->quoteName('price_max'),
                'MAX(' . $db->quoteName('v.currency') . ') AS ' . $db->quoteName('price_currency'),
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
            ->order($db->quoteName('p.title') . ' ASC')
            ->group($db->quoteName('p.id'));

        $searchTerm = (string) $this->getState('filter.search', '');

        if ($searchTerm !== '') {
            $searchLike = '%' . $db->escape($searchTerm, true) . '%';
            $query->where(
                '(' .
                $db->quoteName('p.title') . ' LIKE :productSearch' .
                ' OR ' . $db->quoteName('p.short_desc') . ' LIKE :productSearch' .
                ' OR ' . $db->quoteName('p.long_desc') . ' LIKE :productSearch' .
                ' OR ' . $db->quoteName('v.sku') . ' LIKE :productSearch' .
                ')'
            )->bind(':productSearch', $searchLike, ParameterType::STRING);
        }

        if (!empty($category['id'])) {
            // Get the category and all its descendants (subcategories)
            $categoryIds = CategoryPathHelper::getDescendantIds($db, (int) $category['id']);

            if (\count($categoryIds) === 1) {
                // Single category - use simple binding
                $categoryIdFilter = (int) $category['id'];
                $query->innerJoin(
                    $db->quoteName('#__nxp_easycart_product_categories', 'pc')
                    . ' ON ' . $db->quoteName('pc.product_id') . ' = ' . $db->quoteName('p.id')
                )
                    ->where($db->quoteName('pc.category_id') . ' = :categoryId')
                    ->bind(':categoryId', $categoryIdFilter, ParameterType::INTEGER);
            } else {
                // Multiple categories (parent + descendants) - use IN clause with escaped values
                // We use direct value injection here because Joomla's bind() uses references
                // and loop variables get overwritten
                $escapedIds = array_map(
                    static fn ($id) => (int) $id,
                    $categoryIds
                );

                $query->innerJoin(
                    $db->quoteName('#__nxp_easycart_product_categories', 'pc')
                    . ' ON ' . $db->quoteName('pc.product_id') . ' = ' . $db->quoteName('p.id')
                )
                    ->where($db->quoteName('pc.category_id') . ' IN (' . implode(',', $escapedIds) . ')');
            }
        } else {
            $rootIds = (array) $this->getState('category.root_ids', []);

            if (!empty($rootIds)) {
                $placeholders = [];

                foreach ($rootIds as $index => $rootId) {
                    $placeholder     = ':rootCat' . $index;
                    $placeholders[]  = $placeholder;
                    $rootIdParameter = (int) $rootId;
                    $query->bind($placeholder, $rootIdParameter, ParameterType::INTEGER);
                }

                $query->innerJoin(
                    $db->quoteName('#__nxp_easycart_product_categories', 'pc')
                    . ' ON ' . $db->quoteName('pc.product_id') . ' = ' . $db->quoteName('p.id')
                )
                    ->where(
                        $db->quoteName('pc.category_id') . ' IN (' . implode(',', $placeholders) . ')'
                    );
            }
        }

        $countQuery = clone $query;
        $countQuery->clear('select')
            ->clear('order')
            ->clear('group')
            ->select('COUNT(DISTINCT ' . $db->quoteName('p.id') . ')');

        $db->setQuery($countQuery);
        $total = (int) $db->loadResult();

        $query->setLimit($limit, $start);
        $db->setQuery($query);
        $rows = $db->loadObjectList() ?: [];

        $products = [];

        foreach ($rows as $row) {
            $images = [];

            if (!empty($row->images)) {
                $decoded = json_decode($row->images, true);

                if (json_last_error() === JSON_ERROR_NONE && \is_array($decoded)) {
                    $images = array_values(array_filter(array_map(
                        static function ($url) {
                            if (!\is_string($url)) {
                                return null;
                            }

                            $trimmed = trim($url);

                            if ($trimmed === '') {
                                return null;
                            }

                            // Ensure relative media paths resolve correctly in nested routes.
                            if (
                                !str_starts_with($trimmed, 'http://')
                                && !str_starts_with($trimmed, 'https://')
                                && !str_starts_with($trimmed, '//')
                            ) {
                                $base    = rtrim(Uri::root(true), '/');
                                $relative = '/' . ltrim($trimmed, '/');

                                // Uri::root(true) can return an empty string when site lives at web root.
                                $trimmed = ($base === '' ? '' : $base) . $relative;
                            }

                            return $trimmed;
                        },
                        $decoded
                    )));
                }
            }

            $minCents = $row->price_min !== null ? (int) $row->price_min : null;
            $maxCents = $row->price_max !== null ? (int) $row->price_max : null;
            // Always use base currency from config (Option A - single currency source of truth)
            $currency = ConfigHelper::getBaseCurrency();
            $price    = [
                'currency'  => $currency,
                'min_cents' => $minCents,
                'max_cents' => $maxCents,
                'label'     => $this->formatPriceLabel($minCents, $maxCents, $currency),
            ];

            $primaryPath = [];
            $primaryCategoryId = $row->primary_category_id !== null ? (int) $row->primary_category_id : 0;

            if ($primaryCategoryId > 0) {
                $primaryPath = CategoryPathHelper::getPath($db, $primaryCategoryId);
            } elseif (!empty($row->primary_category_slug)) {
                $primaryPath = CategoryPathHelper::getPathForSlug($db, (string) $row->primary_category_slug);
            } elseif (!empty($category['slug'])) {
                $primaryPath = CategoryPathHelper::getPathForSlug($db, (string) $category['slug']);
            }

            $linkCategorySlug = !empty($primaryPath) ? (string) end($primaryPath) : '';
            $linkCategoryPath = !empty($primaryPath) ? implode('/', $primaryPath) : '';

            if ($linkCategorySlug === '' && !empty($category['slug'])) {
                $linkCategorySlug = (string) $category['slug'];
                $linkCategoryPath = $linkCategorySlug;
            }

            $variantCount = $row->variant_count !== null ? (int) $row->variant_count : 0;
            $primaryVariantId = ($variantCount === 1 && $row->primary_variant_id !== null)
                ? (int) $row->primary_variant_id
                : null;
            $status = ProductStatus::normalise($row->active ?? ProductStatus::INACTIVE);

            $products[] = [
                'id'         => (int) $row->id,
                'title'      => (string) $row->title,
                'slug'       => (string) $row->slug,
                'short_desc' => (string) ($row->short_desc ?? ''),
                'images'     => $images,
                'featured'   => (bool) ($row->featured ?? 0),
                'status'     => $status,
                'out_of_stock' => ProductStatus::isOutOfStock($status),
                'price'      => $price,
                'price_label' => $price['label'],
                'category_slug' => $linkCategorySlug,
                'category_path' => $linkCategoryPath,
                'primary_category_id' => $primaryCategoryId ?: null,
                'primary_variant_id' => $primaryVariantId,
                'variant_count' => $variantCount,
                'link'       => RouteHelper::getProductRoute((string) $row->slug, $linkCategoryPath ?: $linkCategorySlug ?: null),
            ];
        }

        $this->products = $products;
        $pages          = $limit > 0 ? (int) ceil($total / $limit) : 1;
        $currentPage    = $limit > 0 ? (int) floor($start / $limit) + 1 : 1;

        if ($currentPage > $pages) {
            $currentPage = $pages;
        }

        $this->pagination = [
            'total'   => $total,
            'limit'   => $limit,
            'start'   => $start,
            'pages'   => max(1, $pages),
            'current' => max(1, $currentPage),
        ];

        return $this->products;
    }

    /**
     * Pagination metadata for the current category listing.
     *
     * @return array<string, int>
     *
     * @since 0.1.5
     */
    public function getPagination(): array
    {
        if ($this->products === null) {
            $this->getProducts();
        }

        return $this->pagination;
    }

    /**
     * Retrieve all categories for navigation.
     *
     * @return array<int, array<string, mixed>>
     *
     * @since 0.1.5
     */
    public function getCategories(): array
    {
        $rootIds = (array) $this->getState('category.root_ids', []);

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('id'),
                $db->quoteName('title'),
                $db->quoteName('slug'),
            ])
            ->from($db->quoteName('#__nxp_easycart_categories'))
            ->order($db->quoteName('sort') . ' ASC')
            ->order($db->quoteName('title') . ' ASC');

        if (!empty($rootIds)) {
            $placeholders = [];

            foreach ($rootIds as $index => $rootId) {
                $placeholder     = ':navRoot' . $index;
                $placeholders[]  = $placeholder;
                $rootIdParameter = (int) $rootId;
                $query->bind($placeholder, $rootIdParameter, ParameterType::INTEGER);
            }

            $query->where(
                $db->quoteName('id') . ' IN (' . implode(',', $placeholders) . ')'
            );
        } else {
            $query->where(
                '(' . $db->quoteName('parent_id') . ' IS NULL OR ' . $db->quoteName('parent_id') . ' = 0)'
            );
        }

        $db->setQuery($query);

        $rows = $db->loadObjectList() ?: [];

        $categories = [[
            'id'    => null,
            'title' => Text::_('COM_NXPEASYCART_CATEGORY_FILTER_ALL'),
            'slug'  => '',
            'link'  => RouteHelper::getCategoryRoute(),
        ]];

        foreach ($rows as $row) {
            $categories[] = [
                'id'    => (int) $row->id,
                'title' => (string) $row->title,
                'slug'  => (string) $row->slug,
                'link'  => RouteHelper::getCategoryRoute((string) $row->slug),
            ];
        }

        return $categories;
    }

    /**
     * Build a human readable price label for product cards.
     *
     * @since 0.1.5
     */
    private function formatPriceLabel(?int $minCents, ?int $maxCents, string $currency): string
    {
        if ($minCents === null || $maxCents === null) {
            return '';
        }

        if ($minCents === $maxCents) {
            return MoneyHelper::format($minCents, $currency);
        }

        return Text::sprintf(
            'COM_NXPEASYCART_PRODUCT_PRICE_RANGE',
            MoneyHelper::format($minCents, $currency),
            MoneyHelper::format($maxCents, $currency)
        );
    }
}
