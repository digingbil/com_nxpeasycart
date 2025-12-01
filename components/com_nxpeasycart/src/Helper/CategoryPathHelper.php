<?php

namespace Joomla\Component\Nxpeasycart\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Utilities for resolving category paths and canonical slugs.
 *
 * @since 0.1.5
 */
class CategoryPathHelper
{
    /**
     * Cached category rows keyed by ID.
     *
     * @var array<int, array{id: int, slug: string, parent_id: int|null}|null>
     *
     * @since 0.1.5
     */
    private static array $categoryCache = [];

    /**
     * Cached category IDs keyed by lower-cased slug.
     *
     * @var array<string, int|null>
     *
     * @since 0.1.5
     */
    private static array $slugCache = [];

    /**
     * Cached slug paths keyed by category ID.
     *
     * @var array<int, array<int, string>>
     *
     * @since 0.1.5
     */
    private static array $pathCache = [];

    /**
     * Cached primary category lookups keyed by product slug.
     *
     * @var array<string, array{category_id: int, path: array<int, string>}|null>
     *
     * @since 0.1.5
     */
    private static array $productPrimaryCache = [];

    /**
     * Normalise a category path into an array of slug segments.
     *
     * @param mixed $path Raw path string, array, or null
     *
     * @return array<int, string>
     *
     * @since 0.1.5
     */
    public static function normalisePathSegments($path): array
    {
        if ($path === null) {
            return [];
        }

        if (\is_string($path)) {
            $path = preg_split('#/#', $path) ?: [];
        }

        if (!\is_array($path)) {
            return [];
        }

        $segments = [];

        foreach ($path as $segment) {
            if (!\is_string($segment)) {
                continue;
            }

            $normalised = trim(urldecode($segment));

            if ($normalised === '') {
                continue;
            }

            $segments[] = $normalised;
        }

        return $segments;
    }

    /**
     * Build the slug path for the given category ID.
     *
     * @return array<int, string>
     *
     * @since 0.1.5
     */
    public static function getPath(DatabaseInterface $db, ?int $categoryId): array
    {
        $categoryId = $categoryId !== null ? (int) $categoryId : 0;

        if ($categoryId <= 0) {
            return [];
        }

        if (isset(self::$pathCache[$categoryId])) {
            return self::$pathCache[$categoryId];
        }

        $segments  = [];
        $currentId = $categoryId;
        $guard     = 0;

        while ($currentId !== null && $currentId > 0 && $guard < 50) {
            $row = self::loadCategory($db, $currentId);

            if ($row === null) {
                break;
            }

            array_unshift($segments, $row['slug']);
            $currentId = $row['parent_id'];
            $guard++;
        }

        self::$pathCache[$categoryId] = $segments;

        return $segments;
    }

    /**
     * Resolve a category row by slug.
     *
     * @return array{id: int, slug: string, parent_id: int|null}|null
     *
     * @since 0.1.5
     */
    public static function resolveBySlug(DatabaseInterface $db, string $slug): ?array
    {
        $slugKey = strtolower($slug);

        if (isset(self::$slugCache[$slugKey])) {
            $cachedId = self::$slugCache[$slugKey];

            return $cachedId ? self::$categoryCache[$cachedId] ?? null : null;
        }

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('id'),
                $db->quoteName('slug'),
                $db->quoteName('parent_id'),
            ])
            ->from($db->quoteName('#__nxp_easycart_categories'))
            ->where($db->quoteName('slug') . ' = :slug')
            ->bind(':slug', $slug, ParameterType::STRING)
            ->setLimit(1);

        $db->setQuery($query);

        $row = $db->loadObject();

        if (!$row) {
            self::$slugCache[$slugKey] = null;

            return null;
        }

        $data = [
            'id'        => (int) $row->id,
            'slug'      => (string) $row->slug,
            'parent_id' => $row->parent_id !== null ? (int) $row->parent_id : null,
        ];

        self::$categoryCache[$data['id']] = $data;
        self::$slugCache[$slugKey]        = $data['id'];

        return $data;
    }

    /**
     * Resolve a category path by slug.
     *
     * @return array<int, string>
     *
     * @since 0.1.5
     */
    public static function getPathForSlug(DatabaseInterface $db, string $slug): array
    {
        $category = self::resolveBySlug($db, $slug);

        if (!$category) {
            return [];
        }

        return self::getPath($db, (int) $category['id']);
    }

    /**
     * Resolve a category ID from a slug path.
     *
     * @param array<int, string> $segments
     *
     * @return array{id: int, slug: string, path: array<int, string>}|null
     *
     * @since 0.1.5
     */
    public static function resolveByPath(DatabaseInterface $db, array $segments): ?array
    {
        $segments = self::normalisePathSegments($segments);

        if (empty($segments)) {
            return null;
        }

        $lastSlug = (string) end($segments);
        $category = self::resolveBySlug($db, $lastSlug);

        if (!$category) {
            return null;
        }

        $path = self::getPath($db, (int) $category['id']);

        if (!self::pathsMatch($path, $segments)) {
            return null;
        }

        return [
            'id'   => (int) $category['id'],
            'slug' => (string) $category['slug'],
            'path' => $path,
        ];
    }

    /**
     * Resolve the primary category path for a product.
     *
     * @return array{category_id: int, path: array<int, string>}|null
     *
     * @since 0.1.5
     */
    public static function getPrimaryPathForProduct(DatabaseInterface $db, string $productSlug): ?array
    {
        $cacheKey = strtolower($productSlug);

        if (isset(self::$productPrimaryCache[$cacheKey])) {
            return self::$productPrimaryCache[$cacheKey];
        }

        $query = $db->getQuery(true)
            ->select($db->quoteName('p.primary_category_id'))
            ->from($db->quoteName('#__nxp_easycart_products', 'p'))
            ->where($db->quoteName('p.slug') . ' = :productSlug')
            ->bind(':productSlug', $productSlug, ParameterType::STRING)
            ->setLimit(1);

        $db->setQuery($query);

        $row = $db->loadObject();

        if (!$row || (int) ($row->primary_category_id ?? 0) <= 0) {
            self::$productPrimaryCache[$cacheKey] = null;

            return null;
        }

        $categoryId = (int) $row->primary_category_id;
        $path       = self::getPath($db, $categoryId);

        $resolved = [
            'category_id' => $categoryId,
            'path'        => $path,
        ];

        self::$productPrimaryCache[$cacheKey] = $resolved;

        return $resolved;
    }

    /**
     * Reset caches (useful in tests).
     *
     * @since 0.1.5
     */
    public static function reset(): void
    {
        self::$categoryCache       = [];
        self::$slugCache           = [];
        self::$pathCache           = [];
        self::$productPrimaryCache = [];
    }

    /**
     * Load a category row and cache it.
     *
     * @return array{id: int, slug: string, parent_id: int|null}|null
     *
     * @since 0.1.5
     */
    private static function loadCategory(DatabaseInterface $db, int $categoryId): ?array
    {
        if (isset(self::$categoryCache[$categoryId])) {
            return self::$categoryCache[$categoryId];
        }

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('id'),
                $db->quoteName('slug'),
                $db->quoteName('parent_id'),
            ])
            ->from($db->quoteName('#__nxp_easycart_categories'))
            ->where($db->quoteName('id') . ' = :categoryId')
            ->bind(':categoryId', $categoryId, ParameterType::INTEGER)
            ->setLimit(1);

        $db->setQuery($query);

        $row = $db->loadObject();

        if (!$row) {
            self::$categoryCache[$categoryId] = null;

            return null;
        }

        $data = [
            'id'        => (int) $row->id,
            'slug'      => (string) $row->slug,
            'parent_id' => $row->parent_id !== null ? (int) $row->parent_id : null,
        ];

        self::$categoryCache[$categoryId] = $data;

        return $data;
    }

    /**
     * Compare two slug paths case-insensitively.
     *
     * @param array<int, string> $canonical
     * @param array<int, string> $incoming
     *
     * @since 0.1.5
     */
    private static function pathsMatch(array $canonical, array $incoming): bool
    {
        if (\count($canonical) !== \count($incoming)) {
            return false;
        }

        foreach ($canonical as $index => $segment) {
            if (!isset($incoming[$index]) || strcasecmp($segment, (string) $incoming[$index]) !== 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get all descendant category IDs for a given category (including itself).
     *
     * Uses iterative breadth-first traversal to find all children, grandchildren, etc.
     *
     * @param DatabaseInterface $db
     * @param int $categoryId The parent category ID
     *
     * @return array<int, int> Array of category IDs including the parent
     *
     * @since 0.1.5
     */
    public static function getDescendantIds(DatabaseInterface $db, int $categoryId): array
    {
        if ($categoryId <= 0) {
            return [];
        }

        $descendants = [$categoryId];
        $toProcess   = [$categoryId];
        $guard       = 0;
        $maxIterations = 100; // Prevent infinite loops

        while (!empty($toProcess) && $guard < $maxIterations) {
            $guard++;

            // Build placeholders for current batch
            $placeholders = [];
            $boundValues  = [];

            foreach ($toProcess as $index => $parentId) {
                $placeholder = ':parentId' . $guard . '_' . $index;
                $placeholders[] = $placeholder;
                $boundValues[$placeholder] = (int) $parentId;
            }

            $query = $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__nxp_easycart_categories'))
                ->where($db->quoteName('parent_id') . ' IN (' . implode(',', $placeholders) . ')');

            foreach ($boundValues as $placeholder => $value) {
                $query->bind($placeholder, $value, ParameterType::INTEGER);
            }

            $db->setQuery($query);
            $children = $db->loadColumn() ?: [];

            // Filter out any IDs we've already seen (prevents cycles)
            $newChildren = array_diff(array_map('intval', $children), $descendants);

            if (empty($newChildren)) {
                break;
            }

            $descendants = array_merge($descendants, $newChildren);
            $toProcess   = $newChildren;
        }

        return array_values(array_unique($descendants));
    }
}
