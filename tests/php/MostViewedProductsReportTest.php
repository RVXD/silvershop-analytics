<?php

namespace SilverShop\Analytics\Tests;

use SilverShop\Analytics\Model\ProductView;
use SilverShop\Analytics\Report\MostViewedProductsReport;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DB;

class MostViewedProductsReportTest extends SapphireTest
{
    protected $usesDatabase = true;

    private function view(int $productID, ?string $hash, string $created): void
    {
        $view = ProductView::record($productID, $hash);
        DB::prepared_query('UPDATE "SilverShop_ProductView" SET "Created" = ? WHERE "ID" = ?', [$created, $view->ID]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Product 10: three in-range views from two distinct sessions, plus one out-of-range (tomorrow) view.
        $this->view(10, 'sess-a', '2026-02-01 10:00:00');
        $this->view(10, 'sess-a', '2026-02-02 10:00:00');
        $this->view(10, 'sess-b', '2026-02-03 10:00:00');
        $this->view(10, 'sess-d', date('Y-m-d H:i:s', strtotime('+1 day')));

        // Product 20: a single view.
        $this->view(20, 'sess-c', '2026-02-01 11:00:00');
    }

    public function testRanksProductsByViewCount(): void
    {
        $rows = (new MostViewedProductsReport())->sourceRecords();

        $this->assertSame(2, $rows->count(), 'one row per product');
        $first = $rows->first();
        $this->assertSame(4, (int) $first->Views, 'product 10 leads with four total views');
        $this->assertSame(3, (int) $first->Viewers, 'three distinct sessions (a, b, d)');
    }

    public function testDateRangeFiltersViews(): void
    {
        $rows = (new MostViewedProductsReport())->sourceRecords([
            'StartDate' => '2026-02-01',
            'EndDate' => '2026-02-28',
        ]);

        $first = $rows->first();
        $this->assertSame(3, (int) $first->Views, 'the tomorrow view is excluded by the range');
        $this->assertSame(2, (int) $first->Viewers, 'only sessions a and b fall in range');
    }

    public function testLabelsProductsWithNoSiteTreeRow(): void
    {
        $first = (new MostViewedProductsReport())->sourceRecords()->first();

        // No published product exists for these ids, so the report falls back to an id-labelled title.
        $this->assertStringContainsString('10', (string) $first->Title);
    }
}
