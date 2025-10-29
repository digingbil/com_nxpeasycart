<?php

namespace Nxp\EasyCart\Admin\Controller\Api;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Pagination\Pagination;
use Joomla\Utilities\ArrayHelper;

\defined('_JEXEC') or die;

/**
 * JSON controller for catalog product operations.
 */
class ProductsController extends AbstractJsonController
{
    /**
     * Constructor.
     *
     * @param array|null                   $config  Controller config
     * @param MVCFactoryInterface|null     $factory MVC factory
     * @param CMSApplicationInterface|null $app     Application instance
     */
    public function __construct($config = [], MVCFactoryInterface $factory = null, CMSApplicationInterface $app = null)
    {
        parent::__construct($config, $factory, $app);

        $this->registerTask('list', 'browse');
        $this->registerTask('create', 'store');
        $this->registerTask('update', 'update');
        $this->registerTask('delete', 'destroy');
    }

    /**
     * Return a paginated collection of products.
     *
     * @return void
     */
    public function browse(): void
    {
        $this->requirePermission('core.manage');
        $this->requireToken();

        $model = $this->getModel('Products', 'Administrator', ['ignore_request' => true]);
        $input = $this->app->getInput();

        $limit = $input->getInt('limit', 20);
        $start = $input->getInt('start', 0);
        $search = trim($input->getString('search', ''));
        $active = $input->getString('active', '');

        $model->setState('list.limit', $limit > 0 ? $limit : 20);
        $model->setState('list.start', $start >= 0 ? $start : 0);
        $model->setState('filter.search', $search);

        if ($active !== '') {
            $model->setState('filter.active', (int) ($active === '1' || $active === 'true'));
        }

        $items = [];

        foreach ($model->getItems() as $item) {
            $items[] = $this->transformProduct($item);
        }

        $pagination = $model->getPagination();

        $this->succeed(
            [
                'data' => $items,
                'pagination' => $this->formatPagination($pagination),
            ]
        );
    }

    /**
     * Persist a new product.
     *
     * @return void
     */
    public function store(): void
    {
        $this->requirePermission('core.create');
        $this->requireToken();

        $payload = $this->getJsonBody();
        $model = $this->getModel('Product', 'Administrator', ['ignore_request' => true]);

        if (!$model->save($payload)) {
            $this->fail(
                $model->getError() ?: Text::_('COM_NXPEASYCART_ERROR_PRODUCT_SAVE_FAILED'),
                422,
                $model->getErrors()
            );
        }

        $item = $model->getItem();

        $this->succeed(
            [
                'data' => $this->transformProduct($item),
            ],
            201
        );
    }

    /**
     * Update an existing product.
     *
     * @return void
     */
    public function update(): void
    {
        $this->requirePermission('core.edit');
        $this->requireToken();

        $input = $this->app->getInput();
        $id = $input->getInt('id') ?: null;
        $this->requireValue($id, Text::_('COM_NXPEASYCART_ERROR_PRODUCT_ID_REQUIRED'), 400);

        $payload = $this->getJsonBody();
        $payload['id'] = $id;

        $model = $this->getModel('Product', 'Administrator', ['ignore_request' => true]);

        if (!$model->save($payload)) {
            $this->fail(
                $model->getError() ?: Text::_('COM_NXPEASYCART_ERROR_PRODUCT_SAVE_FAILED'),
                422,
                $model->getErrors()
            );
        }

        $item = $model->getItem($id);

        $this->succeed(
            [
                'data' => $this->transformProduct($item),
            ]
        );
    }

    /**
     * Delete a set of products.
     *
     * @return void
     */
    public function destroy(): void
    {
        $this->requirePermission('core.delete');
        $this->requireToken();

        $ids = $this->app->getInput()->get('id', [], 'array');
        $ids = ArrayHelper::toInteger((array) $ids);
        $ids = array_values(array_filter($ids));

        $this->requireValue($ids, Text::_('COM_NXPEASYCART_ERROR_PRODUCT_ID_REQUIRED'), 400);

        $model = $this->getModel('Product', 'Administrator', ['ignore_request' => true]);

        if (!$model->delete($ids)) {
            $this->fail(
                $model->getError() ?: Text::_('COM_NXPEASYCART_ERROR_PRODUCT_DELETE_FAILED'),
                422,
                $model->getErrors()
            );
        }

        $this->succeed(
            [
                'data' => [
                    'deleted' => $ids,
                ],
            ]
        );
    }

    /**
     * Prepare product data for JSON responses.
     *
     * @param object $item Product database row
     *
     * @return array<string,mixed>
     */
    private function transformProduct(object $item): array
    {
        return [
            'id' => (int) $item->id,
            'title' => (string) $item->title,
            'slug' => (string) $item->slug,
            'active' => (bool) $item->active,
            'created' => $item->created,
            'modified' => $item->modified ?? null,
        ];
    }

    /**
     * Summarise pagination data.
     *
     * @param Pagination $pagination Pagination instance
     *
     * @return array<string,int>
     */
    private function formatPagination(Pagination $pagination): array
    {
        return [
            'total' => (int) $pagination->total,
            'limit' => (int) $pagination->limit,
            'pages' => (int) $pagination->pagesTotal,
            'current' => (int) $pagination->pagesCurrent,
        ];
    }
}
