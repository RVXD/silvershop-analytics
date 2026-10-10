<?php

namespace SilverShop\Analytics\Tests;

use SilverShop\Analytics\Model\SearchLog;
use SilverShop\Analytics\Report\SearchTermsReport;
use SilverShop\Analytics\Report\ZeroResultSearchesReport;
use SilverShop\Analytics\SearchInsights;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DB;

class SearchInsightsTest extends SapphireTest
{
    protected $usesDatabase = true;

    public function testRecordNormalisesAndStores(): void
    {
        SearchInsights::record('  Red   SHOES ', 4);

        $this->assertSame(1, SearchLog::get()->count());
        $log = SearchLog::get()->first();
        $this->assertSame('red shoes', $log->Term, 'lower-cased and whitespace-collapsed');
        $this->assertSame(4, (int) $log->ResultCount);
    }

    public function testRecordSkipsEmptyAndDisabled(): void
    {
        $this->assertNull(SearchInsights::record('   ', 3), 'blank term is ignored');

        Config::modify()->set(SearchInsights::class, 'enabled', false);
        $this->assertNull(SearchInsights::record('shoes', 3), 'nothing recorded when disabled');

        $this->assertSame(0, SearchLog::get()->count());
    }

    public function testSearchTermsReportRanksByFrequency(): void
    {
        SearchInsights::record('shoes', 5);
        SearchInsights::record('Shoes', 5);
        SearchInsights::record('hat', 0);

        $first = (new SearchTermsReport())->sourceRecords()->first();
        $this->assertSame('shoes', $first->Term, 'most-searched term first (case-folded together)');
        $this->assertSame(2, (int) $first->Searches);
        $this->assertSame(5.0, (float) $first->AvgResults);
    }

    public function testZeroResultReportListsOnlyNoResultTerms(): void
    {
        SearchInsights::record('shoes', 5);
        SearchInsights::record('umbrella', 0);
        SearchInsights::record('umbrella', 0);

        $rows = (new ZeroResultSearchesReport())->sourceRecords();
        $this->assertCount(1, $rows, 'only the zero-result term appears');
        $this->assertSame('umbrella', $rows->first()->Term);
        $this->assertSame(2, (int) $rows->first()->Searches);
    }

    public function testPruneRemovesOldRows(): void
    {
        $log = SearchInsights::record('shoes', 1);
        DB::prepared_query('UPDATE "SilverShop_SearchLog" SET "Created" = ? WHERE "ID" = ?', ['2000-01-01 00:00:00', $log->ID]);
        SearchInsights::record('hat', 1);

        $this->assertSame(1, SearchLog::prune(30));
        $this->assertSame(1, SearchLog::get()->count());
    }
}
