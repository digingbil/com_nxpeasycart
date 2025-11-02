<?php

namespace Nxp\EasyCart\Admin\Administrator\Model;

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
use Nxp\EasyCart\Admin\Administrator\Helper\ConfigHelper;
use Nxp\EasyCart\Admin\Administrator\Table\CategoryTable;
use Nxp\EasyCart\Admin\Administrator\Table\ProductTable;
use Nxp\EasyCart\Admin\Administrator\Table\VariantTable;
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
            $this->setError(Text::_('COM_NXPEASYCART_ERROR_PRODUCT_TITLE_REQUIRED'));

            return false;
        }

        $validated['images']     = $this->filterImages($data['images'] ?? []);
        $validated['variants']   = $this->filterVariants($data['variants'] ?? []);
        $validated['categories'] = $this->filterCategories($data['categories'] ?? []);

        if (empty($validated['variants'])) {
            $this->setError(Text::_('COM_NXPEASYCART_ERROR_PRODUCT_VARIANT_REQUIRED'));

            return false;
        }

        return $validated;
    }

    /**
     * {@inheritDoc}
     */
    public function save($data)
    {
        $db = $this->getDbo();

        $images     = (array) ($data['images'] ?? []);
        $variants   = (array) ($data['variants'] ?? []);
        $categories = (array) ($data['categories'] ?? []);

        try {
            $data['images'] = $this->encodeImages($images);
        } catch (RuntimeException $exception) {
            $this->setError($exception->getMessage());

            return false;
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
            $this->syncCategories($id, $categories);

            $db->transactionCommit();

            return true;
        } catch (Throwable $exception) {
            $db->transactionRollback();
            $this->setError($exception->getMessage());

            return false;
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

        $table->active = (int) (bool) $table->active;

        if (\is_array($table->images)) {
            try {
                $table->images = $this->encodeImages($table->images);
            } catch (RuntimeException $exception) {
                $this->setError($exception->getMessage());

                return false;
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
        $item->active     = (bool) $item->active;
        $item->images     = $this->decodeImages($item->images ?? null);
        $item->variants   = $this->loadVariants((int) $item->id);
        $item->categories = $this->loadCategories((int) $item->id);

        return $item;
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
            $input = preg_split('/[\r\n,]+/', $input) ?: [];
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
                $this->setError(Text::_('COM_NXPEASYCART_ERROR_VARIANT_PAYLOAD_INVALID'));

                return [];
            }
        }

        if (!\is_array($input)) {
            $this->setError(Text::_('COM_NXPEASYCART_ERROR_VARIANT_PAYLOAD_INVALID'));

            return [];
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
                $this->setError(Text::_('COM_NXPEASYCART_ERROR_VARIANT_SKU_REQUIRED'));

                return [];
            }

            $skuKey = strtoupper($sku);

            if (isset($seenSkus[$skuKey])) {
                $this->setError(Text::_('COM_NXPEASYCART_ERROR_VARIANT_SKU_DUPLICATE'));

                return [];
            }

            $seenSkus[$skuKey] = true;

            try {
                $priceCents = $this->resolvePriceCents($variant);
            } catch (RuntimeException $exception) {
                $this->setError($exception->getMessage());

                return [];
            }

            $currency = strtoupper(trim((string) ($variant['currency'] ?? '')));

            if ($currency === '') {
                $this->setError(Text::_('COM_NXPEASYCART_ERROR_VARIANT_CURRENCY_REQUIRED'));

                return [];
            }

            if ($currency !== $baseCurrency) {
                $this->setError(Text::sprintf('COM_NXPEASYCART_ERROR_VARIANT_CURRENCY_MISMATCH', $baseCurrency));

                return [];
            }

            $options = $variant['options'] ?? null;

            if (\is_string($options) && $options !== '') {
                try {
                    $decodedOptions = json_decode($options, true, 512, JSON_THROW_ON_ERROR);
                    $options        = \is_array($decodedOptions) ? $decodedOptions : null;
                } catch (JsonException $exception) {
                    $this->setError(Text::_('COM_NXPEASYCART_ERROR_VARIANT_OPTIONS_INVALID'));

                    return [];
                }
            }

            if ($options !== null && !\is_array($options)) {
                $this->setError(Text::_('COM_NXPEASYCART_ERROR_VARIANT_OPTIONS_INVALID'));

                return [];
            }

            $weight = $variant['weight'] ?? null;

            if ($weight !== null && $weight !== '') {
                try {
                    $weight = (string) BigDecimal::of((string) $weight)->toScale(3, RoundingMode::HALF_UP);
                } catch (MathException $exception) {
                    $this->setError(Text::_('COM_NXPEASYCART_ERROR_VARIANT_WEIGHT_INVALID'));

                    return [];
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

        foreach ($input as $category) {
            if (\is_int($category) || (\is_numeric($category) && (int) $category > 0)) {
                $categories[] = [
                    'id'    => (int) $category,
                    'title' => null,
                    'slug'  => null,
                ];

                continue;
            }

            if (\is_array($category)) {
                $categories[] = [
                    'id'    => isset($category['id']) ? (int) $category['id'] : 0,
                    'title' => isset($category['title']) ? trim((string) $category['title']) : '',
                    'slug'  => isset($category['slug']) ? trim((string) $category['slug']) : '',
                ];

                continue;
            }

            $title = trim((string) $category);

            if ($title === '') {
                continue;
            }

            $categories[] = [
                'id'    => 0,
                'title' => $title,
                'slug'  => '',
            ];
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
        $db = $this->getDbo();

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
     * @return void
     */
    private function syncCategories(int $productId, array $categories): void
    {
        $db = $this->getDbo();

        $categoryIds = [];

        foreach ($categories as $category) {
            $id = isset($category['id']) ? (int) $category['id'] : 0;

            if ($id > 0) {
                if ($this->categoryExists($id)) {
                    $categoryIds[] = $id;
                }

                continue;
            }

            $title = trim((string) ($category['title'] ?? ''));

            if ($title === '') {
                continue;
            }

            $slug          = trim((string) ($category['slug'] ?? ''));
            $categoryIds[] = $this->findOrCreateCategory($title, $slug);
        }

        $categoryIds = array_values(array_unique(array_filter($categoryIds)));

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
            $columns = [$db->quoteName('product_id'), $db->quoteName('category_id')];
            $query   = $db->getQuery(true)
                ->insert($db->quoteName('#__nxp_easycart_product_categories'))
                ->columns($columns);

            foreach (array_values($toInsert) as $index => $categoryId) {
                $query->values(':productIdInsert, :categoryInsert' . $index);
                $categoryInsertId = (int) $categoryId;
                $query->bind(':categoryInsert' . $index, $categoryInsertId, ParameterType::INTEGER);
            }

            $query->bind(':productIdInsert', $productId, ParameterType::INTEGER);

            $db->setQuery($query);
            $db->execute();
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
    }

    /**
     * Determine whether a category already exists.
     */
    private function categoryExists(int $categoryId): bool
    {
        $db    = $this->getDbo();
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
        $db = $this->getDbo();

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

        $db    = $this->getDbo();
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
     * Load categories assigned to the product.
     *
     * @return array<int, array<string, mixed>>
     */
    private function loadCategories(int $productId): array
    {
        if ($productId <= 0) {
            return [];
        }

        $db    = $this->getDbo();
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('c.id'),
                $db->quoteName('c.title'),
                $db->quoteName('c.slug'),
            ])
            ->from($db->quoteName('#__nxp_easycart_categories', 'c'))
            ->innerJoin(
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
            ];
        }

        return $categories;
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
