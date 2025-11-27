<?php

declare(strict_types=1);

namespace Tests\Unit\Site\Model;

use Joomla\Component\Nxpeasycart\Site\Model\CategoryModel;
use Joomla\Database\DatabaseInterface;
use PHPUnit\Framework\TestCase;
use Tests\Stubs\TrackingQuery;
use Joomla\Component\Nxpeasycart\Site\Helper\CategoryPathHelper;

final class CategoryModelTest extends TestCase
{
    public function testBindsRootCategoriesWithoutReferenceErrors(): void
    {
        CategoryPathHelper::reset();

        $query = new TrackingQuery();

        $db = $this->createMock(DatabaseInterface::class);
        $db->method('getQuery')->willReturn($query);
        $db->method('quoteName')->willReturnCallback(static fn ($value) => (string) $value);
        $db->method('setQuery')->willReturnSelf();
        $db->method('loadObject')->willReturnOnConsecutiveCalls(
            (object) ['id' => 3, 'slug' => 'apparel', 'parent_id' => null],
            (object) ['id' => 3, 'slug' => 'apparel', 'parent_id' => null]
        );
        $db->method('loadObjectList')->willReturn([
            (object) [
                'id'                    => 42,
                'slug'                  => 'demo-shirt',
                'title'                 => 'Demo Shirt',
                'short_desc'            => 'Short copy',
                'images'                => json_encode(['hero.jpg'], JSON_THROW_ON_ERROR),
                'featured'              => 0,
                'price_min'             => 1299,
                'price_max'             => 1599,
                'price_currency'        => 'USD',
                'variant_count'         => 1,
                'primary_variant_id'    => 99,
                'primary_category_slug' => 'apparel',
                'primary_category_id'   => 3,
            ],
        ]);

        $model = new class ($db) extends CategoryModel {
            public function __construct(DatabaseInterface $db)
            {
                $this->setDatabase($db);
                $this->__state_set = true;
            }

            public function primeCategory(array $category): void
            {
                $this->item = $category;
            }
        };

        $model->primeCategory([
            'id'        => null,
            'title'     => 'All',
            'slug'      => '',
            'parent_id' => null,
            'sort'      => 0,
        ]);

        $model->setState('category.root_ids', [5, 9]);

        $products = $model->getProducts();

        $this->assertSame(5, $query->bindings[':rootCat0']);
        $this->assertSame(9, $query->bindings[':rootCat1']);

        $this->assertCount(1, $products);
        $this->assertSame('apparel', $products[0]['category_slug']);
        $this->assertSame('apparel', $products[0]['category_path']);
        $this->assertSame('Demo Shirt', $products[0]['title']);
    }
}
