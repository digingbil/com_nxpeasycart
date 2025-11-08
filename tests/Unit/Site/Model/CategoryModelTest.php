<?php

declare(strict_types=1);

namespace Tests\Unit\Site\Model;

use Joomla\Component\Nxpeasycart\Site\Model\CategoryModel;
use Joomla\Database\DatabaseInterface;
use PHPUnit\Framework\TestCase;
use Tests\Stubs\TrackingQuery;

final class CategoryModelTest extends TestCase
{
    public function testBindsRootCategoriesWithoutReferenceErrors(): void
    {
        $query = new TrackingQuery();

        $db = $this->createMock(DatabaseInterface::class);
        $db->method('getQuery')->willReturn($query);
        $db->method('quoteName')->willReturnCallback(static fn ($value) => (string) $value);
        $db->method('setQuery')->willReturnSelf();
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
                'primary_category_slug' => 'apparel',
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

        $this->assertSame([
            ':rootCat0' => 5,
            ':rootCat1' => 9,
        ], $query->bindings);

        $this->assertCount(1, $products);
        $this->assertSame('apparel', $products[0]['category_slug']);
        $this->assertSame('Demo Shirt', $products[0]['title']);
    }
}
