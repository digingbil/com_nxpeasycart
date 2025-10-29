<?php

namespace Nxp\EasyCart\Admin\Administrator\Controller\Api;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Session\Session;
use RuntimeException;

/**
 * Products API controller.
 */
class ProductsController extends AbstractJsonController
{
    /**
     * Constructor.
     *
     * @param array                        $config  Controller configuration
     * @param MVCFactoryInterface|null     $factory MVC factory
     * @param CMSApplicationInterface|null $app     Application instance
     */
    public function __construct($config = [], MVCFactoryInterface $factory = null, CMSApplicationInterface $app = null)
    {
        parent::__construct($config, $factory, $app);
    }

    /**
     * {@inheritDoc}
     */
    public function execute($task)
    {
        $task = strtolower((string) $task ?: 'list');

        return match ($task) {
            'list', 'browse'   => $this->list(),
            'store', 'create'  => $this->store(),
            'update', 'edit'   => $this->update(),
            'delete', 'remove' => $this->delete(),
            default            => $this->respond(['message' => Text::_('JLIB_APPLICATION_ERROR_TASK_NOT_FOUND')], 404),
        };
    }

    /**
     * List products.
     */
    private function list(): JsonResponse
    {
        $this->assertCan('core.manage');

        $model = $this->getModel('Products', 'Administrator', ['ignore_request' => true]);

        $search = $this->input->getString('search', '');
        $limit  = $this->input->getInt('limit', 20);
        $start  = $this->input->getInt('start', 0);

        $model->setState('filter.search', $search);
        $model->setState('list.limit', max(0, $limit));
        $model->setState('list.start', max(0, $start));

        $items = array_map(
            fn ($item) => $this->transformProduct($item),
            $model->getItems()
        );

        $pagination = $model->getPagination();

        return $this->respond(
            [
                'items'       => $items,
                'pagination'  => [
                    'total'   => (int) $pagination->total,
                    'limit'   => (int) $pagination->limit,
                    'pages'   => $pagination->pagesTotal,
                    'current' => $pagination->pagesCurrent,
                    'start'   => (int) $pagination->limitstart,
                ],
            ]
        );
    }

    /**
     * Create a product.
     */
    private function store(): JsonResponse
    {
        $this->assertCan('core.create');
        $this->assertToken();

        $data = $this->decodePayload();

        $model = $this->getProductModel();

        $form = $model->getForm($data, false);

        if ($form === false) {
            throw new RuntimeException($model->getError() ?: Text::_('COM_NXPEASYCART_ERROR_PRODUCT_SAVE_FAILED'), 500);
        }

        $validData = $model->validate($form, $data);

        if ($validData === false) {
            return $this->respond(['errors' => $model->getErrors()], 422);
        }

        if (!$model->save($validData)) {
            throw new RuntimeException($model->getError() ?: Text::_('COM_NXPEASYCART_ERROR_PRODUCT_SAVE_FAILED'), 500);
        }

        $item = $model->getItem((int) $model->getState($model->getName() . '.id'));

        return $this->respond(['item' => $this->transformProduct($item)], 201);
    }

    /**
     * Update a product.
     */
    private function update(): JsonResponse
    {
        $this->assertCan('core.edit');
        $this->assertToken();

        $id = $this->requireId();

        $data = $this->decodePayload();
        $data['id'] = $id;

        $model = $this->getProductModel();

        $form = $model->getForm($data, false);

        if ($form === false) {
            throw new RuntimeException($model->getError() ?: Text::_('COM_NXPEASYCART_ERROR_PRODUCT_SAVE_FAILED'), 500);
        }

        $validData = $model->validate($form, $data);

        if ($validData === false) {
            return $this->respond(['errors' => $model->getErrors()], 422);
        }

        if (!$model->save($validData)) {
            throw new RuntimeException($model->getError() ?: Text::_('COM_NXPEASYCART_ERROR_PRODUCT_SAVE_FAILED'), 500);
        }

        $item = $model->getItem($id);

        return $this->respond(['item' => $this->transformProduct($item)]);
    }

    /**
     * Delete products.
     */
    private function delete(): JsonResponse
    {
        $this->assertCan('core.delete');
        $this->assertToken();

        $payload = $this->decodePayload();
        $ids = $payload['ids'] ?? [];

        if (!\is_array($ids) || empty($ids)) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_PRODUCT_ID_REQUIRED'), 400);
        }

        $ids = array_values(array_filter(array_map('intval', $ids)));

        if (empty($ids)) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_PRODUCT_ID_REQUIRED'), 400);
        }

        $model = $this->getProductModel();

        if (!$model->delete($ids)) {
            throw new RuntimeException($model->getError() ?: Text::_('COM_NXPEASYCART_ERROR_PRODUCT_DELETE_FAILED'), 500);
        }

        return $this->respond(['deleted' => $ids]);
    }

    /**
     * Decode JSON payload.
     */
    private function decodePayload(): array
    {
        $raw = $this->input->json->getRaw();

        if ($raw === null || $raw === '') {
            return [];
        }

        $data = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_INVALID_JSON'), 400);
        }

        return \is_array($data) ? $data : [];
    }

    /**
     * Ensure the user has permission.
     */
    private function assertCan(string $action): void
    {
        $user = $this->app->getIdentity();

        if (!$user->authorise($action, 'com_nxpeasycart')) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_NOT_AUTHORISED'), 403);
        }
    }

    /**
     * Ensure CSRF token is valid.
     */
    private function assertToken(): void
    {
        if (!Session::checkToken('request')) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_INVALID_TOKEN'), 403);
        }
    }

    /**
     * Get the product admin model.
     */
    private function getProductModel()
    {
        return $this->getModel('Product', 'Administrator', ['ignore_request' => true]);
    }

    /**
     * Transform product row to array.
     */
    private function transformProduct($item): array
    {
        return [
            'id'          => (int) $item->id,
            'title'       => (string) $item->title,
            'slug'        => (string) $item->slug,
            'short_desc'  => $item->short_desc,
            'long_desc'   => $item->long_desc,
            'active'      => (bool) $item->active,
            'created'     => (string) $item->created,
            'created_by'  => (int) $item->created_by,
            'modified'    => $item->modified,
            'modified_by' => $item->modified_by ? (int) $item->modified_by : null,
        ];
    }
}
