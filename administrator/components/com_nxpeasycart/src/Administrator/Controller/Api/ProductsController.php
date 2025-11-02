<?php

namespace Joomla\Component\Nxpeasycart\Administrator\Controller\Api;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
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

        Log::addLogger(
            ['text_file' => 'com_nxpeasycart-products.php', 'extension' => 'com_nxpeasycart-products'],
            Log::ALL,
            ['com_nxpeasycart.products']
        );
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
            'update', 'edit' => $this->update(),
            'delete', 'remove' => $this->delete(),
            default => $this->respond(['message' => Text::_('JLIB_APPLICATION_ERROR_TASK_NOT_FOUND')], 404),
        };
    }

    /**
     * List products.
     */
    protected function list(): JsonResponse
    {
        $this->assertCan('core.manage');

        $model = $this->getModel('Products', 'Administrator', ['ignore_request' => true]);

        $search = $this->input->getString('search', '');
        $limit  = $this->input->getInt('limit', 20);
        $start  = $this->input->getInt('start', 0);

        $model->setState('filter.search', $search);
        $model->setState('list.limit', max(0, $limit));
        $model->setState('list.start', max(0, $start));

        $items        = [];
        $productModel = $this->getProductModel();

        foreach ($model->getItems() as $item) {
            $items[] = $this->transformProduct($productModel->hydrateItem($item));
        }

        $pagination = $model->getPagination();

        return $this->respond(
            [
                'items'      => $items,
                'pagination' => [
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
    protected function store(): JsonResponse
    {
        $this->debug('store: entry point reached');
        $this->assertCan('core.create');
        $this->assertToken();

        $data = $this->decodePayload();
        $this->debug('store: incoming payload', $data);

        $model = $this->getProductModel();

        $form = $model->getForm($data, false);

        if ($form === false) {
            $this->debug('store: getForm failed', [$model->getError()]);
            throw new RuntimeException($model->getError() ?: Text::_('COM_NXPEASYCART_ERROR_PRODUCT_SAVE_FAILED'), 500);
        }

        $validData = $model->validate($form, $data);

        if ($validData === false) {
            $errors = $model->getErrors();
            $this->debug('store: validation failed', $errors);

            return $this->respond(['errors' => $errors], 422);
        }

        if (!$model->save($validData)) {
            $this->debug('store: save failed', [$model->getError(), $model->getErrors()]);
            throw new RuntimeException($model->getError() ?: Text::_('COM_NXPEASYCART_ERROR_PRODUCT_SAVE_FAILED'), 500);
        }

        $id = (int) $model->getState($model->getName() . '.id');

        if ($id <= 0) {
            $id = (int) $model->getTable()->id;
        }

        $item = $model->getItem($id);
        $this->debug('store: product created', ['id' => $id]);

        return $this->respond(['item' => $this->transformProduct($item)], 201);
    }

    /**
     * Update a product.
     */
    protected function update(): JsonResponse
    {
        $this->assertCan('core.edit');
        $this->assertToken();

        $id = $this->requireId();

        $data       = $this->decodePayload();
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
    protected function delete(): JsonResponse
    {
        $this->assertCan('core.delete');
        $this->assertToken();

        $payload = $this->decodePayload();
        $ids     = $payload['ids'] ?? [];

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
        $images = [];

        foreach ($item->images ?? [] as $image) {
            $images[] = (string) $image;
        }

        $variants = [];

        foreach ($item->variants ?? [] as $variant) {
            $variant = (array) $variant;

            $priceCents = isset($variant['price_cents']) ? (int) $variant['price_cents'] : 0;

            $variants[] = [
                'id'          => isset($variant['id']) ? (int) $variant['id'] : 0,
                'sku'         => (string) ($variant['sku'] ?? ''),
                'price_cents' => $priceCents,
                'price'       => isset($variant['price']) ? (string) $variant['price'] : $this->formatPrice($priceCents),
                'currency'    => (string) ($variant['currency'] ?? ''),
                'stock'       => isset($variant['stock']) ? (int) $variant['stock'] : 0,
                'options'     => $variant['options'] ?? null,
                'weight'      => $variant['weight']  ?? null,
                'active'      => isset($variant['active']) ? (bool) $variant['active'] : false,
            ];
        }

        $categories = [];

        foreach ($item->categories ?? [] as $category) {
            $category     = (array) $category;
            $categories[] = [
                'id'    => isset($category['id']) ? (int) $category['id'] : 0,
                'title' => (string) ($category['title'] ?? ''),
                'slug'  => (string) ($category['slug'] ?? ''),
            ];
        }

        return [
            'id'         => (int) $item->id,
            'title'      => (string) $item->title,
            'slug'       => (string) $item->slug,
            'short_desc' => $item->short_desc,
            'long_desc'  => $item->long_desc,
            'active'     => (bool) $item->active,
            'featured'   => (bool) $item->featured,
            'images'     => $images,
            'variants'   => $variants,
            'categories' => $categories,
            'summary'    => [
                'variants' => $this->buildVariantSummary($variants),
            ],
            'created'     => (string) $item->created,
            'created_by'  => (int) $item->created_by,
            'modified'    => $item->modified,
            'modified_by' => $item->modified_by ? (int) $item->modified_by : null,
        ];
    }

    /**
     * Build a lightweight summary for variant collections.
     *
     * @param array<int, array<string, mixed>> $variants
     */
    private function buildVariantSummary(array $variants): array
    {
        if (empty($variants)) {
            return [
                'count'               => 0,
                'currency'            => null,
                'multiple_currencies' => false,
                'price_min_cents'     => null,
                'price_max_cents'     => null,
                'price_min'           => null,
                'price_max'           => null,
            ];
        }

        $count      = \count($variants);
        $currencies = [];
        $min        = null;
        $max        = null;

        foreach ($variants as $variant) {
            $currency              = (string) ($variant['currency'] ?? '');
            $currencies[$currency] = true;

            $price = isset($variant['price_cents']) ? (int) $variant['price_cents'] : 0;

            $min = $min === null ? $price : min($min, $price);
            $max = $max === null ? $price : max($max, $price);
        }

        $currencyKeys       = array_keys(array_filter($currencies));
        $multipleCurrencies = \count($currencyKeys) > 1;
        $resolvedCurrency   = $multipleCurrencies ? null : ($currencyKeys[0] ?? null);

        return [
            'count'               => $count,
            'currency'            => $resolvedCurrency,
            'multiple_currencies' => $multipleCurrencies,
            'price_min_cents'     => $min,
            'price_max_cents'     => $max,
            'price_min'           => $min !== null ? $this->formatPrice($min) : null,
            'price_max'           => $max !== null ? $this->formatPrice($max) : null,
        ];
    }

    /**
     * Format cents into a decimal string with two fraction digits.
     */
    private function formatPrice(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    private function debug(string $message, $context = null): void
    {
        if ($context !== null) {
            $message .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        Log::add($message, Log::INFO, 'com_nxpeasycart.products');
    }
}
