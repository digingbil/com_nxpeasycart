<?php

namespace Joomla\Component\Nxpeasycart\Administrator\Controller\Api;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Response\JsonResponse;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use RuntimeException;

/**
 * Categories management API.
 */
class CategoriesController extends AbstractJsonController
{
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
            'list', 'browse' => $this->list(),
            'store', 'create' => $this->store(),
            'update', 'patch' => $this->update(),
            'delete', 'destroy' => $this->destroy(),
            default => $this->respond(['message' => Text::_('JLIB_APPLICATION_ERROR_TASK_NOT_FOUND')], 404),
        };
    }

    /**
     * Return paginated categories.
     */
    protected function list(): JsonResponse
    {
        $this->assertCan('core.manage');

        $model = $this->getCategoriesModel();

        $search = $this->input->getString('search', '');
        $limit  = $this->input->getInt('limit', 20);
        $start  = $this->input->getInt('start', 0);

        $model->setState('filter.search', $search);
        $model->setState('list.limit', max(0, $limit));
        $model->setState('list.start', max(0, $start));

        $items   = $model->getItems();
        $ids     = array_map(static fn ($item) => (int) $item->id, $items);
        $usage   = $this->getUsageCounts($ids);
        $parents = $this->getParentTitles($items);

        $transformed = array_map(
            function ($item) use ($usage, $parents) {
                $id       = (int) $item->id;
                $parentId = $item->parent_id !== null ? (int) $item->parent_id : null;

                return [
                    'id'           => $id,
                    'title'        => (string) $item->title,
                    'slug'         => (string) $item->slug,
                    'parent_id'    => $parentId,
                    'parent_title' => $parentId ? ($parents[$parentId] ?? null) : null,
                    'sort'         => (int) $item->sort,
                    'usage'        => $usage[$id] ?? 0,
                ];
            },
            $items
        );

        $pagination = $model->getPagination();

        return $this->respond(
            [
                'items'      => $transformed,
                'pagination' => [
                    'total'   => (int) $pagination->total,
                    'limit'   => (int) $pagination->limit,
                    'pages'   => (int) $pagination->pagesTotal,
                    'current' => (int) $pagination->pagesCurrent,
                    'start'   => (int) $pagination->limitstart,
                ],
            ]
        );
    }

    /**
     * Create a category.
     */
    protected function store(): JsonResponse
    {
        $this->assertCan('core.create');
        $this->assertToken();

        $data = $this->decodePayload();

        $model = $this->getCategoryModel();
        $form  = $model->getForm($data, false);

        if ($form === false) {
            throw new RuntimeException($model->getError() ?: Text::_('COM_NXPEASYCART_ERROR_CATEGORY_SAVE_FAILED'), 500);
        }

        $validData = $model->validate($form, $data);

        if ($validData === false) {
            $errors = $model->getErrors() ?: [Text::_('COM_NXPEASYCART_ERROR_CATEGORY_SAVE_FAILED')];

            return $this->respond(['errors' => $errors], 422);
        }

        if (!$model->save($validData)) {
            throw new RuntimeException($model->getError() ?: Text::_('COM_NXPEASYCART_ERROR_CATEGORY_SAVE_FAILED'), 500);
        }

        $id = (int) ($validData['id'] ?? $model->getState($model->getName() . '.id') ?? 0);

        if ($id <= 0) {
            $table = $model->getTable();
            $id    = (int) $table->id;
        }

        $item = $model->getItem($id);

        return $this->respond(['item' => $this->transformItem($item)], 201);
    }

    /**
     * Update a category.
     */
    protected function update(): JsonResponse
    {
        $this->assertCan('core.edit');
        $this->assertToken();

        $id         = $this->requireId();
        $data       = $this->decodePayload();
        $data['id'] = $id;

        $model = $this->getCategoryModel();
        $form  = $model->getForm($data, false);

        if ($form === false) {
            throw new RuntimeException($model->getError() ?: Text::_('COM_NXPEASYCART_ERROR_CATEGORY_SAVE_FAILED'), 500);
        }

        $validData = $model->validate($form, $data);

        if ($validData === false) {
            $errors = $model->getErrors() ?: [Text::_('COM_NXPEASYCART_ERROR_CATEGORY_SAVE_FAILED')];

            return $this->respond(['errors' => $errors], 422);
        }

        if (!$model->save($validData)) {
            throw new RuntimeException($model->getError() ?: Text::_('COM_NXPEASYCART_ERROR_CATEGORY_SAVE_FAILED'), 500);
        }

        $item = $model->getItem($id);

        return $this->respond(['item' => $this->transformItem($item)]);
    }

    /**
     * Delete categories.
     */
    protected function destroy(): JsonResponse
    {
        $this->assertCan('core.delete');
        $this->assertToken();

        $payload = $this->decodePayload();

        $ids = isset($payload['ids']) && \is_array($payload['ids'])
            ? array_map('intval', $payload['ids'])
            : [];

        if (!$ids) {
            $id = $this->input->getInt('id');
            if ($id > 0) {
                $ids = [$id];
            }
        }

        $ids = array_values(array_unique(array_filter($ids)));

        if (empty($ids)) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_CATEGORY_ID_REQUIRED'), 400);
        }

        $db = $this->getDatabase();
        $db->transactionStart();

        try {
            // Detach children
            $placeholders = [];
            foreach ($ids as $index => $categoryId) {
                $placeholders[] = ':parent' . $index;
            }

            if ($placeholders) {
                $query = $db->getQuery(true)
                    ->update($db->quoteName('#__nxp_easycart_categories'))
                    ->set($db->quoteName('parent_id') . ' = NULL')
                    ->where($db->quoteName('parent_id') . ' IN (' . implode(',', $placeholders) . ')');

                foreach ($ids as $index => $categoryId) {
                    $value = (int) $categoryId;
                    $query->bind(':parent' . $index, $value, ParameterType::INTEGER);
                }

                $db->setQuery($query);
                $db->execute();
            }

            // Delete requested categories
            $placeholders = [];
            foreach ($ids as $index => $categoryId) {
                $placeholders[] = ':category' . $index;
            }

            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__nxp_easycart_categories'))
                ->where($db->quoteName('id') . ' IN (' . implode(',', $placeholders) . ')');

            foreach ($ids as $index => $categoryId) {
                $value = (int) $categoryId;
                $query->bind(':category' . $index, $value, ParameterType::INTEGER);
            }

            $db->setQuery($query);
            $db->execute();

            $deleted = (int) $db->getAffectedRows();

            $db->transactionCommit();

            return $this->respond(['deleted' => $deleted, 'ids' => $ids]);
        } catch (\Throwable $exception) {
            $db->transactionRollback();
            throw new RuntimeException($exception->getMessage(), 500);
        }
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

        return (array) $data;
    }

    /**
     * Convert model item to API payload.
     */
    private function transformItem($item): array
    {
        if (!$item) {
            return [];
        }

        $payload = [
            'id'        => (int) $item->id,
            'title'     => (string) $item->title,
            'slug'      => (string) $item->slug,
            'parent_id' => $item->parent_id !== null ? (int) $item->parent_id : null,
            'sort'      => isset($item->sort) ? (int) $item->sort : 0,
        ];

        if ($payload['parent_id']) {
            $parents                 = $this->getParentTitles([(object) ['parent_id' => $payload['parent_id']]]);
            $payload['parent_title'] = $parents[$payload['parent_id']] ?? null;
        } else {
            $payload['parent_title'] = null;
        }

        $usage            = $this->getUsageCounts([$payload['id']]);
        $payload['usage'] = $usage[$payload['id']] ?? 0;

        return $payload;
    }

    /**
     * Shortcut for categories list model.
     */
    private function getCategoriesModel()
    {
        return $this->getModel('Categories', 'Administrator', ['ignore_request' => true]);
    }

    /**
     * Shortcut for category admin model.
     */
    private function getCategoryModel()
    {
        return $this->getModel('Category', 'Administrator', ['ignore_request' => true]);
    }

    /**
     * Fetch usage count for categories.
     *
     * @param array<int> $ids
     *
     * @return array<int, int>
     */
    private function getUsageCounts(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));

        if (empty($ids)) {
            return [];
        }

        $db           = $this->getDatabase();
        $placeholders = [];

        foreach ($ids as $index => $id) {
            $placeholders[] = ':usage' . $index;
        }

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('category_id'),
                'COUNT(*) AS ' . $db->quoteName('total'),
            ])
            ->from($db->quoteName('#__nxp_easycart_product_categories'))
            ->where($db->quoteName('category_id') . ' IN (' . implode(',', $placeholders) . ')')
            ->group($db->quoteName('category_id'));

        foreach ($ids as $index => $id) {
            $value = (int) $id;
            $query->bind(':usage' . $index, $value, ParameterType::INTEGER);
        }

        $db->setQuery($query);

        $rows   = (array) $db->loadAssocList();
        $result = [];

        foreach ($rows as $row) {
            $result[(int) $row['category_id']] = (int) $row['total'];
        }

        return $result;
    }

    /**
     * Fetch parent titles for given items.
     *
     * @param array<int, object> $items
     *
     * @return array<int, string>
     */
    private function getParentTitles(array $items): array
    {
        $parentIds = [];

        foreach ($items as $item) {
            if (!empty($item->parent_id)) {
                $parentIds[] = (int) $item->parent_id;
            }
        }

        $parentIds = array_values(array_unique(array_filter($parentIds)));

        if (empty($parentIds)) {
            return [];
        }

        $db           = $this->getDatabase();
        $placeholders = [];

        foreach ($parentIds as $index => $parentId) {
            $placeholders[] = ':parentTitle' . $index;
        }

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('id'),
                $db->quoteName('title'),
            ])
            ->from($db->quoteName('#__nxp_easycart_categories'))
            ->where($db->quoteName('id') . ' IN (' . implode(',', $placeholders) . ')');

        foreach ($parentIds as $index => $parentId) {
            $value = (int) $parentId;
            $query->bind(':parentTitle' . $index, $value, ParameterType::INTEGER);
        }

        $db->setQuery($query);

        $rows   = (array) $db->loadObjectList();
        $titles = [];

        foreach ($rows as $row) {
            $titles[(int) $row->id] = (string) $row->title;
        }

        return $titles;
    }

    /**
     * Resolve a database instance.
     */
    private function getDatabase(): DatabaseInterface
    {
        $container = Factory::getContainer();

        return $container->get(DatabaseInterface::class);
    }
}
