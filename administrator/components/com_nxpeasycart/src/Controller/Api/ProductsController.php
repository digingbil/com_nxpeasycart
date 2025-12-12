<?php
/**
 * @package     NXP Easy Cart
 * @subpackage  com_nxpeasycart
 *
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Nxpeasycart\Administrator\Controller\Api;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Session\Session;
use Joomla\Component\Nxpeasycart\Administrator\Helper\ConfigHelper;
use Joomla\Component\Nxpeasycart\Administrator\Helper\ProductStatus;
use Joomla\Component\Nxpeasycart\Administrator\Service\DigitalFileService;
use RuntimeException;

/**
 * Products API controller.
 *
 * @since 0.1.5
 */
class ProductsController extends AbstractJsonController
{
    private const LOW_STOCK_THRESHOLD = 5;

    /**
     * Constructor.
     *
     * @param array                        $config  Controller configuration
     * @param MVCFactoryInterface|null     $factory MVC factory
     * @param CMSApplicationInterface|null $app     Application instance
     *
     * @since 0.1.5
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
     *
     * @return JsonResponse Paginated products.
     * @since 0.1.5
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

        $productModel = $this->getProductModel();
        $items        = $productModel->hydrateItems($model->getItems());

        // Ensure relations are always available; if bulk hydration missed them, reload the product.
        foreach ($items as $index => $item) {
            $hasVariants   = !empty($item->variants);
            $hasCategories = !empty($item->categories);

            if ($hasVariants && $hasCategories) {
                continue;
            }

            $id = isset($item->id) ? (int) $item->id : 0;

            if ($id <= 0) {
                continue;
            }

            $items[$index] = $productModel->getItem($id);
        }

        $items        = array_map(fn ($item) => $this->transformProduct($item), $items);

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
     *
     * @return JsonResponse Created product.
     *
     * @throws \Exception
     * @since 0.1.5
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
     *
     * @return JsonResponse Updated product.
     * @since 0.1.5
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
     *
     * @return JsonResponse Deleted products.
     * @since 0.1.5
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
     *
     * @return array Decoded JSON payload.
     * @throws RuntimeException When JSON is invalid.
     * @since 0.1.5
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
     *
     * @since 0.1.5
     */
    private function getProductModel()
    {
        return $this->getModel('Product', 'Administrator', ['ignore_request' => true]);
    }

    /**
     * Transform product row to array.
     *
     * @param   object  $item  Product row.
     *
     * @return array Transformed product row.
     * @since 0.1.5
     */
    private function transformProduct(object $item): array
    {
        $images = [];
        $productType = isset($item->product_type) ? (string) $item->product_type : 'physical';

        foreach ($item->images ?? [] as $image) {
            $images[] = (string) $image;
        }

        $variants = [];
        $baseCurrency = ConfigHelper::getBaseCurrency();

        foreach ($item->variants ?? [] as $variant) {
            $variant = (array) $variant;

            $priceCents = isset($variant['price_cents']) ? (int) $variant['price_cents'] : 0;

            $variants[] = [
                'id'          => isset($variant['id']) ? (int) $variant['id'] : 0,
                'sku'         => (string) ($variant['sku'] ?? ''),
                'price_cents' => $priceCents,
                'price'       => isset($variant['price']) ? (string) $variant['price'] : $this->formatPrice($priceCents),
                'currency'    => $baseCurrency,
                'stock'       => isset($variant['stock']) ? (int) $variant['stock'] : 0,
                'options'     => $variant['options'] ?? null,
                'weight'      => $variant['weight']  ?? null,
                'active'      => isset($variant['active']) ? (bool) $variant['active'] : false,
                'is_digital'  => !empty($variant['is_digital']),
            ];
        }

        $categories = [];

        foreach ($item->categories ?? [] as $category) {
            $category     = (array) $category;
            $categories[] = [
                'id'    => isset($category['id']) ? (int) $category['id'] : 0,
                'title' => (string) ($category['title'] ?? ''),
                'slug'  => (string) ($category['slug'] ?? ''),
                'primary' => !empty($category['primary']),
            ];
        }

        $status     = property_exists($item, 'status')
            ? ProductStatus::normalise($item->status)
            : ProductStatus::normalise($item->active ?? ProductStatus::ACTIVE);
        $isActive   = ProductStatus::isPurchasable($status);
        $outOfStock = ProductStatus::isOutOfStock($status);
        $digitalFiles = [];

        $digitalService = $this->getDigitalFileService();

        if ($digitalService !== null) {
            try {
                $digitalFiles = $digitalService->getFilesForProduct((int) $item->id);
            } catch (\Throwable $exception) {
                $digitalFiles = [];
            }
        }

        return [
            'id'         => (int) $item->id,
            'title'      => (string) $item->title,
            'slug'       => (string) $item->slug,
            'short_desc' => $item->short_desc,
            'long_desc'  => $item->long_desc,
            'product_type' => $productType,
            'status'     => $status,
            'active'     => $isActive,
            'out_of_stock' => $outOfStock,
            'featured'   => (bool) $item->featured,
            'images'     => $images,
            'variants'   => $variants,
            'digital_files' => $digitalFiles,
            'categories' => $categories,
            'primary_category_id' => isset($item->primary_category_id) && (int) $item->primary_category_id > 0
                ? (int) $item->primary_category_id
                : null,
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
     *
     * @return array<string, mixed>
     * @since 0.1.5
     */
    private function buildVariantSummary(array $variants): array
    {
        $baseCurrency = ConfigHelper::getBaseCurrency();

        if (empty($variants)) {
            return [
                'count'               => 0,
                'currency'            => $baseCurrency,
                'multiple_currencies' => false,
                'price_min_cents'     => null,
                'price_max_cents'     => null,
                'price_min'           => null,
                'price_max'           => null,
                'stock_total'         => 0,
                'stock_low'           => false,
                'stock_zero'          => true,
            ];
        }

        $count      = \count($variants);
        $min        = null;
        $max        = null;
        $stockTotal = 0;

        foreach ($variants as $variant) {
            $price = isset($variant['price_cents']) ? (int) $variant['price_cents'] : 0;
            $stock = isset($variant['stock']) ? (int) $variant['stock'] : 0;

            $min = $min === null ? $price : min($min, $price);
            $max = $max === null ? $price : max($max, $price);
            $stockTotal += $stock;
        }

        return [
            'count'               => $count,
            'currency'            => $baseCurrency,
            'multiple_currencies' => false,
            'price_min_cents'     => $min,
            'price_max_cents'     => $max,
            'price_min'           => $min !== null ? $this->formatPrice($min) : null,
            'price_max'           => $max !== null ? $this->formatPrice($max) : null,
            'stock_total'         => $stockTotal,
            'stock_low'           => $stockTotal > 0 && $stockTotal <= self::LOW_STOCK_THRESHOLD,
            'stock_zero'          => $stockTotal <= 0,
        ];
    }

    /**
     * Format cents into a decimal string with two fraction digits.
     *
     * @param int $cents Cents to format.
     * @return string Formatted price.
     * @since 0.1.5
     */
    private function formatPrice(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    private function getDigitalFileService(): ?DigitalFileService
    {
        $container = Factory::getContainer();

        if ($container->has(DigitalFileService::class)) {
            return $container->get(DigitalFileService::class);
        }

        return null;
    }

    /*
     * Debugging helper.
     *
     * @param string $message Message to log.
     * @param mixed $context Optional context to log.
     */
    private function debug(string $message, $context = null): void
    {
        if ($context !== null) {
            $message .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        Log::add($message, Log::INFO, 'com_nxpeasycart.products');
    }
}
