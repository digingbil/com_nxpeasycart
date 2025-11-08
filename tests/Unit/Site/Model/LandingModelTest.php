<?php

declare(strict_types=1);

namespace Tests\Unit\Site\Model;

use Joomla\Component\Nxpeasycart\Site\Model\LandingModel;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;
use PHPUnit\Framework\TestCase;
use Tests\Stubs\TrackingQuery;

final class LandingModelTest extends TestCase
{
    public function testCategoryTilesBindConfiguredRootIds(): void
    {
        $query = new TrackingQuery();

        $db = $this->createMock(DatabaseInterface::class);
        $db->method('getQuery')->willReturn($query);
        $db->method('quoteName')->willReturnCallback(static fn ($value) => (string) $value);
        $db->method('setQuery')->willReturnSelf();
        $db->method('loadObjectList')->willReturn([
            (object) ['id' => 5, 'title' => 'Apparel', 'slug' => 'apparel'],
            (object) ['id' => 9, 'title' => 'Shoes', 'slug' => 'shoes'],
        ]);

        $model = new class ($db) extends LandingModel {
            public function __construct(DatabaseInterface $db)
            {
                $this->setDatabase($db);
                $this->__state_set = true;
            }
        };

        $model->setState('params', new Registry());
        $model->setState('landing.category_limit', 2);
        $model->setState('landing.category_ids', [5, 9]);

        $tiles = $model->getCategoryTiles();

        $this->assertSame([
            ':landingRoot0' => 5,
            ':landingRoot1' => 9,
        ], $query->bindings);

        $this->assertCount(2, $tiles);
        $this->assertSame('apparel', $tiles[0]['slug']);
        $this->assertSame('shoes', $tiles[1]['slug']);
    }
}
