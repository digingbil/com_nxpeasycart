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
        $app = Factory::getApplication();
        $input = $app->input;

        $this->setState('category.id', $input->getInt('id'));
        $this->setState('category.slug', $input->getCmd('slug', ''));
    }

    /**
     * Retrieve category metadata.
     */
    public function getItem(): ?array
    {
        if ($this->item !== null) {
            return $this->item;
        }

        $db = $this->getDatabase();
        $id = (int) $this->getState('category.id');
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
                    'id' => (int) $row->id,
                    'title' => (string) $row->title,
                    'slug' => (string) $row->slug,
                    'parent_id' => $row->parent_id !== null ? (int) $row->parent_id : null,
                    'sort' => (int) $row->sort,
                ];

                return $this->item;
            }
        }

        $this->item = [
            'id' => null,
            'title' => Text::_('COM_NXPEASYCART_CATEGORY_ALL'),
            'slug' => '',
            'parent_id' => null,
            'sort' => 0,
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

        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('p.id'),
                $db->quoteName('p.slug'),
                $db->quoteName('p.title'),
                $db->quoteName('p.short_desc'),
                $db->quoteName('p.images'),
            ])
            ->from($db->quoteName('#__nxp_easycart_products', 'p'))
            ->where($db->quoteName('p.active') . ' = 1')
            ->order($db->quoteName('p.title') . ' ASC');

        if (!empty($category['id'])) {
            $categoryIdFilter = (int) $category['id'];
            $query->innerJoin(
                $db->quoteName('#__nxp_easycart_product_categories', 'pc')
                . ' ON ' . $db->quoteName('pc.product_id') . ' = ' . $db->quoteName('p.id')
            )
                ->where($db->quoteName('pc.category_id') . ' = :categoryId')
                ->bind(':categoryId', $categoryIdFilter, ParameterType::INTEGER);
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
                'id' => (int) $row->id,
                'title' => (string) $row->title,
                'slug' => (string) $row->slug,
                'short_desc' => (string) ($row->short_desc ?? ''),
                'images' => $images,
                'link' => Route::_(
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
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('id'),
                $db->quoteName('title'),
                $db->quoteName('slug'),
            ])
            ->from($db->quoteName('#__nxp_easycart_categories'))
            ->order($db->quoteName('title') . ' ASC');

        $db->setQuery($query);

        $rows = $db->loadObjectList() ?: [];

        return array_map(
            static fn ($row) => [
                'id' => (int) $row->id,
                'title' => (string) $row->title,
                'slug' => (string) $row->slug,
                'link' => Route::_('index.php?option=com_nxpeasycart&view=category&slug=' . rawurlencode((string) $row->slug)),
            ],
            $rows
        );
    }
}
