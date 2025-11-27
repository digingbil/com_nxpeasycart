<?php

namespace Joomla\Component\Nxpeasycart\Administrator\Model;

\defined('_JEXEC') or die;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\Database\ParameterType;
use JsonException;
use Joomla\Component\Nxpeasycart\Administrator\Helper\ConfigHelper;
use Joomla\Component\Nxpeasycart\Administrator\Helper\ProductStatus;
use Joomla\Component\Nxpeasycart\Administrator\Table\CategoryTable;
use Joomla\Component\Nxpeasycart\Administrator\Table\ProductTable;
use Joomla\Component\Nxpeasycart\Administrator\Table\VariantTable;
use RuntimeException;
use Throwable;

/**
 * Product admin model.
 */
class ProductModel extends AdminModel
{
    /**
     * {@inheritDoc}
     *
     * @return ProductTable
     */
    public function getTable($name = 'Product', $prefix = 'Table', $options = [])
    {
        return parent::getTable($name, $prefix, $options);
    }

    /**
     * {@inheritDoc}
     */
    public function getForm($data = [], $loadData = true)
    {
        $form = $this->loadForm('com_nxpeasycart.product', 'product', ['control' => '', 'load_data' => $loadData]);

        if (empty($form)) {
            return false;
        }

        return $form;
    }

    /**
     * {@inheritDoc}
     */
    protected function loadFormData()
    {
        $item = $this->getItem();

        if ($item) {
            return (array) $item;
        }

        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function getItem($pk = null)
    {
        $item = parent::getItem($pk);

        if (!$item) {
            return $item;
        }

        return $this->hydrateItem($item);
    }

    /**
     * {@inheritDoc}
     */
    public function validate($form, $data, $group = null)
    {
        $validated = parent::validate($form, $data, $group);

        if ($validated === false) {
            return false;
        }

        if (empty($validated['title'])) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_PRODUCT_TITLE_REQUIRED'));
        }

        $validated['images']     = $this->filterImages($data['images'] ?? []);
        $validated['variants']   = $this->filterVariants($data['variants'] ?? []);
        $validated['categories'] = $this->filterCategories($data['categories'] ?? []);
        $primaryId               = isset($data['primary_category_id']) ? (int) $data['primary_category_id'] : null;
        $validated['primary_category_id'] = ($primaryId !== null && $primaryId > 0) ? $primaryId : null;
        $validated['featured']   = isset($validated['featured']) ? (int) (bool) $validated['featured'] : 0;
        $validated['status']     = ProductStatus::normalise($data['status'] ?? $data['active'] ?? ProductStatus::ACTIVE);
        $validated['active']     = $validated['status'];

        if (empty($validated['variants'])) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_PRODUCT_VARIANT_REQUIRED'));
        }

        return $validated;
    }

    /**
     * {@inheritDoc}
     */
    public function save($data)
    {
        $db = $this->getDatabase();

        $images     = (array) ($data['images'] ?? []);
        $variants   = (array) ($data['variants'] ?? []);
        $categories = (array) ($data['categories'] ?? []);
        $primaryPreferredId = isset($data['primary_category_id']) ? (int) $data['primary_category_id'] : null;
        $primaryPreferredId = ($primaryPreferredId !== null && $primaryPreferredId > 0) ? $primaryPreferredId : null;

        try {
            $data['images'] = $this->encodeImages($images);
        } catch (RuntimeException $exception) {
            throw $exception;
        }

        $db->transactionStart();

        try {
            if (!parent::save($data)) {
                $db->transactionRollback();

                return false;
            }

            $id = (int) ($data['id'] ?? $this->getState($this->getName() . '.id') ?? 0);

            if ($id <= 0) {
                /** @var ProductTable $table */
                $table = $this->getTable();
                $id    = (int) $table->id;
            }

            $this->syncVariants($id, $variants);
            $resolvedCategories = $this->syncCategories($id, $categories);
            $primaryCategoryId  = $this->selectPrimaryCategoryId($resolvedCategories, $primaryPreferredId);
            $this->persistPrimaryCategory($id, $primaryCategoryId);

            $db->transactionCommit();

            return true;
        } catch (Throwable $exception) {
            $db->transactionRollback();
            throw new RuntimeException($exception->getMessage(), 0, $exception);
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function prepareTable($table)
    {
        if (empty($table->slug) && !empty($table->title)) {
            $table->slug = ApplicationHelper::stringURLSafe($table->title);
        }

        if (!empty($table->slug)) {
            $table->slug = ApplicationHelper::stringURLSafe($table->slug);
        }

        $table->active   = ProductStatus::normalise($table->active ?? $table->status ?? ProductStatus::ACTIVE);
        $table->featured = (int) (bool) $table->featured;
        $primaryCategoryId = isset($table->primary_category_id) ? (int) $table->primary_category_id : 0;
        $table->primary_category_id = $primaryCategoryId > 0 ? $primaryCategoryId : null;

        if (\is_array($table->images)) {
            try {
                $table->images = $this->encodeImages($table->images);
            } catch (RuntimeException $exception) {
                throw $exception;
            }
        }

        $date = Factory::getDate();
        $user = Factory::getApplication()->getIdentity();

        if (empty($table->id)) {
            $table->created    = $date->toSql();
            $table->created_by = (int) $user->id;
        } else {
            $table->modified    = $date->toSql();
            $table->modified_by = (int) $user->id;
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function canDelete($record)
    {
        $user = Factory::getApplication()->getIdentity();

        return (bool) $user->authorise('core.delete', 'com_nxpeasycart');
    }

    /**
     * {@inheritDoc}
     */
    protected function canEdit($record)
    {
        $user = Factory::getApplication()->getIdentity();

        return (bool) $user->authorise('core.edit', 'com_nxpeasycart');
    }

    /**
     * Hydrate a product row with related data (images, variants, categories).
     *
     * @param object $item Product row
     *
     * @return object
     */
    public function hydrateItem(object $item): object
    {
        return $this->hydrateItems([$item])[0];
    }

    /**
     * Hydrate a collection of products with images, variants, and categories in bulk.
     *
     * @param array<int, object> $items
     *
     * @return array<int, object>
     */
    public function hydrateItems(array $items): array
    {
        if (empty($items)) {
            return [];
        }

        $productIds = array_values(
            array_filter(
                array_map(static fn ($item) => (int) ($item->id ?? 0), $items),
                static fn ($id) => $id > 0
            )
        );

        $primaryCategoryMap = [];

        foreach ($items as $item) {
            $id = (int) ($item->id ?? 0);

            if ($id > 0) {
                $primaryId = isset($item->primary_category_id) ? (int) $item->primary_category_id : null;
                $primaryCategoryMap[$id] = $primaryId > 0 ? $primaryId : null;
            }
        }

        $variants   = $this->loadVariantsForProducts($productIds);
        $categories = $this->loadCategoriesForProducts($productIds, $primaryCategoryMap);

        foreach ($items as $index => $item) {
            $id                = (int) ($item->id ?? 0);
            $status            = ProductStatus::normalise($item->active ?? 0);
            $item->status      = $status;
            $item->active      = ProductStatus::isPurchasable($status);
            $item->out_of_stock = ProductStatus::isOutOfStock($status);
            $item->featured    = (bool) $item->featured;
            $item->images      = $this->decodeImages($item->images ?? null);
            $item->variants    = $variants[$id]   ?? [];
            $item->categories  = $categories[$id] ?? [];
            $item->primary_category_id = $primaryCategoryMap[$id] ?? null;
            $items[$index]     = $item;
        }

        return $items;
    }

    /**
     * Convert a stored images payload into an array.
     */
    private function decodeImages(?string $payload): array
    {
        if ($payload === null || $payload === '') {
            return [];
        }

        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            return [];
        }

        if (!\is_array($decoded)) {
            return [];
        }

        return $this->filterImages($decoded);
    }

    /**
     * Ensure the images payload can be stored as JSON.
     *
     * @param array $images Sanitised images
     *
     * @return string|null
     */
    private function encodeImages(array $images): ?string
    {
        if (empty($images)) {
            return null;
        }

        try {
            return json_encode(array_values($images), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_PRODUCT_IMAGES_INVALID'));
        }
    }

    /**
     * Normalise images input into a deduplicated array.
     *
     * @param mixed $input Raw images input
     *
     * @return array<int, string>
     */
    private function filterImages($input): array
    {
        if ($input === null) {
            return [];
        }

        if (\is_string($input)) {
            $trimmed = trim($input);

            // If a JSON array was provided as a string, decode it first.
            if ($trimmed !== '' && ($trimmed[0] === '[')) {
                try {
                    $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
                    if (\is_array($decoded)) {
                        $input = $decoded;
                    }
                } catch (JsonException $exception) {
                    // Fall back to delimiter split below
                    $input = null; // force split path
                }
            }

            if ($input === null || \is_string($input)) {
                $input = preg_split('/[\r\n,]+/', (string) $input) ?: [];
            }
        }

        if (!\is_array($input)) {
            return [];
        }

        $images = [];

        foreach ($input as $value) {
            if (\is_array($value)) {
                $value = $value['url'] ?? $value['src'] ?? '';
            }

            $url = trim((string) $value);

            if ($url === '') {
                continue;
            }

            if (strlen($url) > 2048) {
                continue;
            }

            $images[$url] = $url;
        }

        return array_values($images);
    }

    /**
     * Normalise variants payload.
     *
     * @param mixed $input Raw variants payload
     *
     * @return array<int, array<string, mixed>>
     */
    private function filterVariants($input): array
    {
        if ($input === null) {
            return [];
        }

        if (\is_string($input) && $input !== '') {
            try {
                $input = json_decode($input, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_VARIANT_PAYLOAD_INVALID'));
            }
        }

        if (!\is_array($input)) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_VARIANT_PAYLOAD_INVALID'));
        }

        $variants     = [];
        $seenSkus     = [];
        $baseCurrency = ConfigHelper::getBaseCurrency();

        foreach ($input as $variant) {
            if (!\is_array($variant)) {
                continue;
            }

            $sku = trim((string) ($variant['sku'] ?? ''));

            if ($sku === '') {
                throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_VARIANT_SKU_REQUIRED'));
            }

            $skuKey = strtoupper($sku);

            if (isset($seenSkus[$skuKey])) {
                throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_VARIANT_SKU_DUPLICATE'));
            }

            $seenSkus[$skuKey] = true;

            try {
                $priceCents = $this->resolvePriceCents($variant);
            } catch (RuntimeException $exception) {
                throw $exception;
            }

            $currency = strtoupper(trim((string) ($variant['currency'] ?? '')));

            if ($currency === '') {
                throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_VARIANT_CURRENCY_REQUIRED'));
            }

            if ($currency !== $baseCurrency) {
                throw new RuntimeException(Text::sprintf('COM_NXPEASYCART_ERROR_VARIANT_CURRENCY_MISMATCH', $baseCurrency));
            }

            $options = $variant['options'] ?? null;

            if (\is_string($options) && $options !== '') {
                try {
                    $decodedOptions = json_decode($options, true, 512, JSON_THROW_ON_ERROR);
                    $options        = \is_array($decodedOptions) ? $decodedOptions : null;
                } catch (JsonException $exception) {
                    throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_VARIANT_OPTIONS_INVALID'));
                }
            }

            if ($options !== null && !\is_array($options)) {
                throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_VARIANT_OPTIONS_INVALID'));
            }

            $weight = $variant['weight'] ?? null;

            if ($weight !== null && $weight !== '') {
                try {
                    $weight = (string) BigDecimal::of((string) $weight)->toScale(3, RoundingMode::HALF_UP);
                } catch (MathException $exception) {
                    throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_VARIANT_WEIGHT_INVALID'));
                }
            } else {
                $weight = null;
            }

            $variants[] = [
                'id'          => isset($variant['id']) ? (int) $variant['id'] : 0,
                'sku'         => $sku,
                'price_cents' => $priceCents,
                'currency'    => $currency,
                'stock'       => max(0, (int) ($variant['stock'] ?? 0)),
                'options'     => $options,
                'weight'      => $weight,
                'active'      => isset($variant['active']) ? (bool) $variant['active'] : true,
            ];
        }

        return $variants;
    }

    /**
     * Resolve incoming categories into a structured array.
     *
     * @param mixed $input Raw categories payload
     *
     * @return array<int, array<string, mixed>>
     */
    private function filterCategories($input): array
    {
        if ($input === null) {
            return [];
        }

        if (\is_string($input)) {
            $input = preg_split('/[\r\n,]+/', $input) ?: [];
        }

        if (!\is_array($input)) {
            return [];
        }

        $categories = [];
        $seen       = [];

        foreach ($input as $category) {
            if (\is_int($category) || (\is_numeric($category) && (int) $category > 0)) {
                $normalised = [
                    'id'    => (int) $category,
                    'title' => null,
                    'slug'  => null,
                    'primary' => false,
                ];

                $key = 'id:' . $normalised['id'];

                if (isset($seen[$key])) {
                    if ($normalised['primary']) {
                        $categories[$seen[$key]]['primary'] = true;
                    }

                    continue;
                }

                $seen[$key]   = \count($categories);
                $categories[] = $normalised;

                continue;
            }

            if (\is_array($category)) {
                $normalised = [
                    'id'    => isset($category['id']) ? (int) $category['id'] : 0,
                    'title' => isset($category['title']) ? trim((string) $category['title']) : '',
                    'slug'  => isset($category['slug']) ? trim((string) $category['slug']) : '',
                    'primary' => !empty($category['primary']),
                ];

                $key = $normalised['id'] > 0
                    ? 'id:' . $normalised['id']
                    : 'slug:' . strtolower($normalised['slug'] ?: $normalised['title']);

                if ($key === 'slug:' && $normalised['id'] === 0) {
                    continue;
                }

                if (isset($seen[$key])) {
                    if ($normalised['primary']) {
                        $categories[$seen[$key]]['primary'] = true;
                    }

                    continue;
                }

                $seen[$key]   = \count($categories);
                $categories[] = $normalised;

                continue;
            }

            $title = trim((string) $category);

            if ($title === '') {
                continue;
            }

            $normalised = [
                'id'    => 0,
                'title' => $title,
                'slug'  => '',
                'primary' => false,
            ];

            $key = 'slug:' . strtolower($title);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key]   = \count($categories);
            $categories[] = $normalised;
        }

        return $categories;
    }

    /**
     * Convert price input to integer minor units.
     *
     * @throws RuntimeException If the price cannot be converted
     */
    private function resolvePriceCents(array $variant): int
    {
        if (isset($variant['price_cents']) && $variant['price_cents'] !== '') {
            $price = (int) $variant['price_cents'];

            if ($price < 0) {
                throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_VARIANT_PRICE_INVALID'));
            }

            return $price;
        }

        $raw = $variant['price'] ?? $variant['amount'] ?? null;

        if ($raw === null || $raw === '') {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_VARIANT_PRICE_REQUIRED'));
        }

        try {
            return (int) BigDecimal::of((string) $raw)
                ->withPointMovedRight(2)
                ->toScale(0, RoundingMode::HALF_UP)
                ->toInt();
        } catch (MathException $exception) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_VARIANT_PRICE_INVALID'));
        }
    }

    /**
     * Persist variants for the product.
     *
     * @param int   $productId Product identifier
     * @param array $variants  Sanitised variant payload
     *
     * @return void
     */
    private function syncVariants(int $productId, array $variants): void
    {
        $db = $this->getDatabase();

        // Fetch existing variant IDs for the product to determine deletions.
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__nxp_easycart_variants'))
            ->where($db->quoteName('product_id') . ' = :productId')
            ->bind(':productId', $productId, ParameterType::INTEGER);

        $db->setQuery($query);
        $existingIds = array_map('intval', (array) $db->loadColumn());

        $processedIds = [];

        foreach ($variants as $variant) {
            /** @var VariantTable $table */
            $table = new VariantTable($db);

            if (!empty($variant['id'])) {
                $table->load((int) $variant['id']);

                if ((int) $table->product_id !== $productId) {
                    throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_VARIANT_PRODUCT_MISMATCH'));
                }
            }

            $payload = [
                'id'          => $variant['id'] ?? 0,
                'product_id'  => $productId,
                'sku'         => $variant['sku'],
                'price_cents' => (int) $variant['price_cents'],
                'currency'    => $variant['currency'],
                'stock'       => (int) $variant['stock'],
                'options'     => $this->encodeOptions($variant['options'] ?? null),
                'weight'      => $variant['weight'],
                'active'      => (int) (bool) $variant['active'],
            ];

            if (!$table->bind($payload)) {
                throw new RuntimeException($table->getError() ?: Text::_('COM_NXPEASYCART_ERROR_VARIANT_SAVE_FAILED'));
            }

            if (!$table->check() || !$table->store()) {
                throw new RuntimeException($table->getError() ?: Text::_('COM_NXPEASYCART_ERROR_VARIANT_SAVE_FAILED'));
            }

            $processedIds[] = (int) $table->id;
        }

        $idsToDelete = array_diff($existingIds, $processedIds);

        if (!empty($idsToDelete)) {
            $placeholders = [];

            foreach (array_values($idsToDelete) as $index => $variantId) {
                $placeholders[] = ':variantDelete' . $index;
            }

            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__nxp_easycart_variants'))
                ->where($db->quoteName('product_id') . ' = :productId')
                ->where($db->quoteName('id') . ' IN (' . implode(',', $placeholders) . ')');

            $query->bind(':productId', $productId, ParameterType::INTEGER);

            foreach (array_values($idsToDelete) as $index => $variantId) {
                $variantDeleteId = (int) $variantId;
                $query->bind(':variantDelete' . $index, $variantDeleteId, ParameterType::INTEGER);
            }

            $db->setQuery($query);
            $db->execute();
        }
    }

    /**
     * Persist product-category assignments.
     *
     * @param int   $productId Product identifier
     * @param array $categories Sanitised categories payload
     *
     * @return array<int, array{id: int, primary: bool}>
     */
    private function syncCategories(int $productId, array $categories): array
    {
        $db = $this->getDatabase();

        $resolved = [];
        $seen      = [];

        foreach ($categories as $category) {
            $id = isset($category['id']) ? (int) $category['id'] : 0;
            $title = trim((string) ($category['title'] ?? ''));
            $slug  = trim((string) ($category['slug'] ?? ''));
            $isPrimary = !empty($category['primary']);
            $key = $id > 0 ? 'id:' . $id : 'slug:' . strtolower($slug ?: $title);

            if ($key === 'slug:') {
                continue;
            }

            if (isset($seen[$key])) {
                if ($isPrimary) {
                    $resolved[$seen[$key]]['primary'] = true;
                }

                continue;
            }

            if ($id > 0) {
                if ($this->categoryExists($id)) {
                    $seen[$key]   = \count($resolved);
                    $resolved[]   = ['id' => $id, 'primary' => $isPrimary];
                }

                continue;
            }

            if ($title === '') {
                continue;
            }

            $categoryId = $this->findOrCreateCategory($title, $slug);

            $seen[$key] = \count($resolved);
            $resolved[] = [
                'id'      => $categoryId,
                'primary' => $isPrimary,
            ];
        }

        $categoryIds = array_values(
            array_filter(
                array_unique(
                    array_map(static fn ($item) => (int) $item['id'], $resolved),
                    SORT_NUMERIC
                ),
                static fn ($id) => $id > 0
            )
        );

        $query = $db->getQuery(true)
            ->select($db->quoteName('category_id'))
            ->from($db->quoteName('#__nxp_easycart_product_categories'))
            ->where($db->quoteName('product_id') . ' = :productId')
            ->bind(':productId', $productId, ParameterType::INTEGER);

        $db->setQuery($query);
        $current = array_map('intval', (array) $db->loadColumn());

        $toInsert = array_diff($categoryIds, $current);
        $toDelete = array_diff($current, $categoryIds);

        if (!empty($toInsert)) {
            $query = $db->getQuery(true);
            $query->insert($db->quoteName('#__nxp_easycart_product_categories'))
                ->columns([$db->quoteName('product_id'), $db->quoteName('category_id')]);

            // Build value placeholders for each category to insert
            foreach (array_values($toInsert) as $index => $categoryId) {
                $query->values(':productIdInsert' . $index . ', :categoryInsert' . $index);
            }

            // Bind parameters for each category
            foreach (array_values($toInsert) as $index => $categoryId) {
                $productIdBound = (int) $productId;
                $categoryIdBound = (int) $categoryId;
                $query->bind(':productIdInsert' . $index, $productIdBound, ParameterType::INTEGER);
                $query->bind(':categoryInsert' . $index, $categoryIdBound, ParameterType::INTEGER);
            }

            // Note: Joomla's query builder doesn't support INSERT IGNORE natively.
            // We need to handle duplicate key conflicts at the application level or use raw SQL with bindings.
            // For now, we wrap in a try-catch to gracefully handle duplicate key errors.
            try {
                $db->setQuery($query);
                $db->execute();
            } catch (\RuntimeException $e) {
                // Ignore duplicate key errors (23000 = Integrity constraint violation)
                if (strpos($e->getMessage(), '23000') === false && strpos($e->getMessage(), 'Duplicate entry') === false) {
                    throw $e;
                }
            }
        }

        if (!empty($toDelete)) {
            $placeholders = [];

            foreach (array_values($toDelete) as $index => $categoryId) {
                $placeholders[] = ':categoryDelete' . $index;
            }

            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__nxp_easycart_product_categories'))
                ->where($db->quoteName('product_id') . ' = :productIdDelete')
                ->where($db->quoteName('category_id') . ' IN (' . implode(',', $placeholders) . ')');

            $query->bind(':productIdDelete', $productId, ParameterType::INTEGER);

            foreach (array_values($toDelete) as $index => $categoryId) {
                $categoryDeleteId = (int) $categoryId;
                $query->bind(':categoryDelete' . $index, $categoryDeleteId, ParameterType::INTEGER);
            }

            $db->setQuery($query);
            $db->execute();
        }

        return $resolved;
    }

    /**
     * Select the canonical primary category ID for a product.
     *
     * @param array<int, array{id: int, primary: bool}> $resolvedCategories
     */
    private function selectPrimaryCategoryId(array $resolvedCategories, ?int $preferredId = null): ?int
    {
        if ($preferredId !== null && $preferredId > 0) {
            foreach ($resolvedCategories as $resolved) {
                if ((int) $resolved['id'] === $preferredId) {
                    return $preferredId;
                }
            }
        }

        foreach ($resolvedCategories as $resolved) {
            if (!empty($resolved['primary'])) {
                return (int) $resolved['id'];
            }
        }

        return isset($resolvedCategories[0]) ? (int) $resolvedCategories[0]['id'] : null;
    }

    /**
     * Persist the primary category mapping for a product.
     */
    private function persistPrimaryCategory(int $productId, ?int $categoryId): void
    {
        if ($categoryId !== null && $categoryId <= 0) {
            $categoryId = null;
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__nxp_easycart_products'))
            ->set($db->quoteName('primary_category_id') . ' = :primaryCategoryId')
            ->where($db->quoteName('id') . ' = :productId');

        $query->bind(':primaryCategoryId', $categoryId, $categoryId !== null ? ParameterType::INTEGER : ParameterType::NULL);
        $query->bind(':productId', $productId, ParameterType::INTEGER);

        $db->setQuery($query);
        $db->execute();
    }

    /**
     * Determine whether a category already exists.
     */
    private function categoryExists(int $categoryId): bool
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('1')
            ->from($db->quoteName('#__nxp_easycart_categories'))
            ->where($db->quoteName('id') . ' = :categoryId')
            ->setLimit(1)
            ->bind(':categoryId', $categoryId, ParameterType::INTEGER);

        $db->setQuery($query);

        return (bool) $db->loadResult();
    }

    /**
     * Find an existing category or create a new one.
     */
    private function findOrCreateCategory(string $title, string $slug): int
    {
        $db = $this->getDatabase();

        if ($slug === '') {
            $slug = ApplicationHelper::stringURLSafe($title);
        } else {
            $slug = ApplicationHelper::stringURLSafe($slug);
        }

        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__nxp_easycart_categories'))
            ->where($db->quoteName('slug') . ' = :slug')
            ->bind(':slug', $slug, ParameterType::STRING);

        $db->setQuery($query);
        $existing = (int) $db->loadResult();

        if ($existing > 0) {
            return $existing;
        }

        /** @var CategoryTable $table */
        $table   = new CategoryTable($db);
        $payload = [
            'title' => $title,
            'slug'  => $slug,
            'sort'  => 0,
        ];

        if (!$table->bind($payload)) {
            throw new RuntimeException($table->getError() ?: Text::_('COM_NXPEASYCART_ERROR_CATEGORY_SAVE_FAILED'));
        }

        if (!$table->check() || !$table->store()) {
            throw new RuntimeException($table->getError() ?: Text::_('COM_NXPEASYCART_ERROR_CATEGORY_SAVE_FAILED'));
        }

        return (int) $table->id;
    }

    /**
     * Convert a variants options payload into JSON.
     *
     * @param mixed $options Options payload
     */
    private function encodeOptions($options): ?string
    {
        if ($options === null) {
            return null;
        }

        if ($options === '' || $options === []) {
            return null;
        }

        try {
            return json_encode($options, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_VARIANT_OPTIONS_INVALID'));
        }
    }

    /**
     * Load variants for the product.
     *
     * @return array<int, array<string, mixed>>
     */
    private function loadVariants(int $productId): array
    {
        if ($productId <= 0) {
            return [];
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('id'),
                $db->quoteName('sku'),
                $db->quoteName('price_cents'),
                $db->quoteName('currency'),
                $db->quoteName('stock'),
                $db->quoteName('options'),
                $db->quoteName('weight'),
                $db->quoteName('active'),
            ])
            ->from($db->quoteName('#__nxp_easycart_variants'))
            ->where($db->quoteName('product_id') . ' = :productId')
            ->bind(':productId', $productId, ParameterType::INTEGER)
            ->order($db->quoteName('id') . ' ASC');

        $db->setQuery($query);
        $rows     = (array) $db->loadObjectList();
        $variants = [];

        foreach ($rows as $row) {
            $variants[] = [
                'id'          => (int) $row->id,
                'sku'         => (string) $row->sku,
                'price_cents' => (int) $row->price_cents,
                'price'       => $this->formatPriceCents((int) $row->price_cents),
                'currency'    => (string) $row->currency,
                'stock'       => (int) $row->stock,
                'options'     => $this->decodeOptions($row->options ?? null),
                'weight'      => $row->weight !== null ? (string) $row->weight : null,
                'active'      => (bool) $row->active,
            ];
        }

        return $variants;
    }

    /**
     * Load variants for multiple products in a single query.
     *
     * @param array<int> $productIds
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function loadVariantsForProducts(array $productIds): array
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));

        if (empty($productIds)) {
            return [];
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('product_id'),
                $db->quoteName('id'),
                $db->quoteName('sku'),
                $db->quoteName('price_cents'),
                $db->quoteName('currency'),
                $db->quoteName('stock'),
                $db->quoteName('options'),
                $db->quoteName('weight'),
                $db->quoteName('active'),
            ])
            ->from($db->quoteName('#__nxp_easycart_variants'))
            ->where($db->quoteName('product_id') . ' IN (' . implode(',', array_fill(0, \count($productIds), '?')) . ')')
            ->order([$db->quoteName('product_id') . ' ASC', $db->quoteName('id') . ' ASC']);

        foreach ($productIds as $index => $productId) {
            $query->bind($index + 1, $productId, ParameterType::INTEGER);
        }

        $db->setQuery($query);
        $rows         = (array) $db->loadObjectList();
        $variantsById = [];

        foreach ($rows as $row) {
            $productId = (int) $row->product_id;

            $variantsById[$productId] ??= [];
            $variantsById[$productId][] = [
                'id'          => (int) $row->id,
                'sku'         => (string) $row->sku,
                'price_cents' => (int) $row->price_cents,
                'price'       => $this->formatPriceCents((int) $row->price_cents),
                'currency'    => (string) $row->currency,
                'stock'       => (int) $row->stock,
                'options'     => $this->decodeOptions($row->options ?? null),
                'weight'      => $row->weight !== null ? (string) $row->weight : null,
                'active'      => (bool) $row->active,
            ];
        }

        return $variantsById;
    }

    /**
     * Load categories assigned to the product.
     *
     * @return array<int, array<string, mixed>>
     */
    private function loadCategories(int $productId, ?int $primaryCategoryId = null): array
    {
        if ($productId <= 0) {
            return [];
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('c.id'),
                $db->quoteName('c.title'),
                $db->quoteName('c.slug'),
            ])
            ->from($db->quoteName('#__nxp_easycart_categories', 'c'))
            ->join(
                'INNER',
                $db->quoteName('#__nxp_easycart_product_categories', 'pc') .
                ' ON ' . $db->quoteName('c.id') . ' = ' . $db->quoteName('pc.category_id')
            )
            ->where($db->quoteName('pc.product_id') . ' = :productId')
            ->bind(':productId', $productId, ParameterType::INTEGER)
            ->order($db->quoteName('c.sort') . ' ASC, ' . $db->quoteName('c.title') . ' ASC');

        $db->setQuery($query);
        $rows       = (array) $db->loadObjectList();
        $categories = [];

        foreach ($rows as $row) {
            $categories[] = [
                'id'    => (int) $row->id,
                'title' => (string) $row->title,
                'slug'  => (string) $row->slug,
                'primary' => $primaryCategoryId !== null && (int) $row->id === $primaryCategoryId,
            ];
        }

        return $categories;
    }

    /**
     * Load categories for multiple products in one query.
     *
     * @param array<int> $productIds
     * @param array<int, int|null> $primaryCategoryMap
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function loadCategoriesForProducts(array $productIds, array $primaryCategoryMap = []): array
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));

        if (empty($productIds)) {
            return [];
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('pc.product_id'),
                $db->quoteName('c.id'),
                $db->quoteName('c.title'),
                $db->quoteName('c.slug'),
            ])
            ->from($db->quoteName('#__nxp_easycart_product_categories', 'pc'))
            ->join(
                'INNER',
                $db->quoteName('#__nxp_easycart_categories', 'c') .
                ' ON ' . $db->quoteName('c.id') . ' = ' . $db->quoteName('pc.category_id')
            )
            ->where($db->quoteName('pc.product_id') . ' IN (' . implode(',', array_fill(0, \count($productIds), '?')) . ')')
            ->order([
                $db->quoteName('pc.product_id') . ' ASC',
                $db->quoteName('c.sort') . ' ASC',
                $db->quoteName('c.title') . ' ASC',
            ]);

        foreach ($productIds as $index => $productId) {
            $query->bind($index + 1, $productId, ParameterType::INTEGER);
        }

        $db->setQuery($query);
        $rows            = (array) $db->loadObjectList();
        $categoriesById  = [];

        foreach ($rows as $row) {
            $productId = (int) $row->product_id;
            $primaryId = $primaryCategoryMap[$productId] ?? null;

            $categoriesById[$productId] ??= [];
            $categoriesById[$productId][] = [
                'id'    => (int) $row->id,
                'title' => (string) $row->title,
                'slug'  => (string) $row->slug,
                'primary' => $primaryId !== null && (int) $row->id === (int) $primaryId,
            ];
        }

        return $categoriesById;
    }

    /**
     * Convert a JSON options payload to an array.
     *
     * @param string|null $payload Stored options JSON
     */
    private function decodeOptions(?string $payload): ?array
    {
        if ($payload === null || $payload === '') {
            return null;
        }

        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            return null;
        }

        return \is_array($decoded) ? $decoded : null;
    }

    /**
     * Format cents as a string with two decimal places.
     */
    private function formatPriceCents(int $cents): string
    {
        try {
            return (string) BigDecimal::of($cents)
                ->withPointMovedLeft(2)
                ->toScale(2, RoundingMode::HALF_UP);
        } catch (MathException $exception) {
            return '0.00';
        }
    }
}
