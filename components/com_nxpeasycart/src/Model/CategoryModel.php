<?php

namespace Nxp\EasyCart\Site\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Router\Route;
use Joomla\Database\ParameterType;

/**
 * Frontend category model.
 */
class CategoryModel extends BaseDatabaseModel
{
    /**
     * Currently loaded category payload.
     *
     * @var array<string, mixed>|null
     */
    protected ?array $item = null;

    /**
     * Cached product listing for the category.
     *
     * @var array<int, array<string, mixed>>|null
     */
    protected ?array $products = null;

    /**
     * {@inheritDoc}
     */
    protected function populateState($ordering = null, $direction = null)
    {
        $app   = Factory::getApplication();
        $input = $app->input;

        $this->setState('category.id', $input->getInt('id'));
        $this->setState('category.slug', $input->getCmd('slug', ''));

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
     */
    public function getProducts(): array
    {
        if ($this->products !== null) {
            return $this->products;
        }

        $category = $this->getItem();

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('p.id'),
                $db->quoteName('p.slug'),
                $db->quoteName('p.title'),
                $db->quoteName('p.short_desc'),
                $db->quoteName('p.featured'),
                $db->quoteName('p.images'),
            ])
            ->from($db->quoteName('#__nxp_easycart_products', 'p'))
            ->where($db->quoteName('p.active') . ' = 1')
            ->order($db->quoteName('p.title') . ' ASC')
            ->group($db->quoteName('p.id'));

        if (!empty($category['id'])) {
            $categoryIdFilter = (int) $category['id'];
            $query->innerJoin(
                $db->quoteName('#__nxp_easycart_product_categories', 'pc')
                . ' ON ' . $db->quoteName('pc.product_id') . ' = ' . $db->quoteName('p.id')
            )
                ->where($db->quoteName('pc.category_id') . ' = :categoryId')
                ->bind(':categoryId', $categoryIdFilter, ParameterType::INTEGER);
        } else {
            $rootIds = (array) $this->getState('category.root_ids', []);

            if (!empty($rootIds)) {
                $placeholders = [];

                foreach ($rootIds as $index => $rootId) {
                    $placeholder    = ':rootCat' . $index;
                    $placeholders[] = $placeholder;
                    $query->bind($placeholder, (int) $rootId, ParameterType::INTEGER);
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

        $db->setQuery($query);
        $rows = $db->loadObjectList() ?: [];

        $products = [];

        foreach ($rows as $row) {
            $images = [];

            if (!empty($row->images)) {
                $decoded = json_decode($row->images, true);

                if (json_last_error() === JSON_ERROR_NONE && \is_array($decoded)) {
                    $images = array_values(
                        array_filter(
                            array_map(
                                static fn ($url) => \is_string($url) ? trim($url) : null,
                                $decoded
                            )
                        )
                    );
                }
            }

            $products[] = [
                'id'         => (int) $row->id,
                'title'      => (string) $row->title,
                'slug'       => (string) $row->slug,
                'short_desc' => (string) ($row->short_desc ?? ''),
                'images'     => $images,
                'featured'   => (bool) ($row->featured ?? 0),
                'link'       => Route::_(
                    'index.php?option=com_nxpeasycart&view=product&slug=' . rawurlencode((string) $row->slug)
                ),
            ];
        }

        $this->products = $products;

        return $this->products;
    }

    /**
     * Retrieve all categories for navigation.
     *
     * @return array<int, array<string, mixed>>
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
                $placeholder    = ':navRoot' . $index;
                $placeholders[] = $placeholder;
                $query->bind($placeholder, (int) $rootId, ParameterType::INTEGER);
            }

            $query->where(
                $db->quoteName('id') . ' IN (' . implode(',', $placeholders) . ')'
            );
        } else {
            $query->where($db->quoteName('parent_id') . ' IS NULL');
            $query->orWhere($db->quoteName('parent_id') . ' = 0');
        }

        $db->setQuery($query);

        $rows = $db->loadObjectList() ?: [];

        $categories = [[
            'id'    => null,
            'title' => Text::_('COM_NXPEASYCART_CATEGORY_FILTER_ALL'),
            'slug'  => '',
            'link'  => Route::_('index.php?option=com_nxpeasycart&view=category'),
        ]];

        foreach ($rows as $row) {
            $categories[] = [
                'id'    => (int) $row->id,
                'title' => (string) $row->title,
                'slug'  => (string) $row->slug,
                'link'  => Route::_('index.php?option=com_nxpeasycart&view=category&slug=' . rawurlencode((string) $row->slug)),
            ];
        }

        return $categories;
    }
}
