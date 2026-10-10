<?php

namespace SilverShop\Analytics\Tests;

use SilverShop\Analytics\Model\ProductView;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DB;

class PruneProductViewsTest extends SapphireTest
{
    protected $usesDatabase = true;

    private function viewAged(int $productID, string $created): void
    {
        $view = ProductView::record($productID, 'h');
        DB::prepared_query('UPDATE "SilverShop_ProductView" SET "Created" = ? WHERE "ID" = ?', [$created, $view->ID]);
    }

    public function testPrunesOnlyRowsOlderThanTheWindow(): void
    {
        $this->viewAged(1, date('Y-m-d H:i:s', strtotime('-60 days')));
        $this->viewAged(2, date('Y-m-d H:i:s', strtotime('-1 day')));

        $removed = ProductView::prune(30);

        $this->assertSame(1, $removed, 'the 60-day-old row is pruned');
        $this->assertSame(1, ProductView::get()->count());
        $this->assertSame(2, (int) ProductView::get()->first()->ProductID, 'the recent row survives');
    }

    public function testZeroRetentionPrunesNothing(): void
    {
        $this->viewAged(1, date('Y-m-d H:i:s', strtotime('-1000 days')));

        $this->assertSame(0, ProductView::prune(0), 'retention of 0 disables pruning');
        $this->assertSame(1, ProductView::get()->count());
    }
}
