<?php

declare(strict_types=1);

namespace SilverShop\Analytics\Model;

use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\Security\Member;

/**
 * One row per storefront search — the raw log behind the search-term and zero-result reports. Populated by
 * {@link \SilverShop\Analytics\SearchInsights::record()}, which a search module calls on every query.
 *
 * Privacy by design: stores the (normalised) search term and the result count, no raw PII. The member link is only
 * populated when SearchInsights.track_members is enabled. Rows are pruned after {@link $retention_days} by
 * {@link \SilverShop\Analytics\Task\PruneSearchLogTask}.
 *
 * @property string $Term
 * @property int $ResultCount
 */
class SearchLog extends DataObject
{
    private static string $table_name = 'SilverShop_SearchLog';

    private static array $db = [
        'Term' => 'Varchar(255)',
        'ResultCount' => 'Int',
    ];

    private static array $has_one = [
        'Member' => Member::class,
    ];

    private static array $indexes = [
        'Term' => true,
        'Created' => true,
    ];

    private static string $default_sort = '"Created" DESC';

    /**
     * How many days of raw search rows to keep. The reports read these rows directly. 0 disables pruning.
     */
    private static int $retention_days = 365;

    /**
     * Record one search. Returns the written row, or null when there is nothing to record.
     */
    public static function record(string $term, int $resultCount, int $memberID = 0): ?self
    {
        $term = trim($term);
        if ($term === '') {
            return null;
        }

        $log = self::create();
        $log->Term = $term;
        $log->ResultCount = max(0, $resultCount);
        $log->MemberID = $memberID;
        $log->write();

        return $log;
    }

    /**
     * Delete rows older than $days (defaults to the configured retention). Returns the number removed.
     */
    public static function prune(?int $days = null): int
    {
        $days = $days ?? (int) self::config()->get('retention_days');
        if ($days <= 0) {
            return 0;
        }

        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $count = (int) self::get()->filter('Created:LessThan', $cutoff)->count();

        if ($count > 0) {
            DB::prepared_query('DELETE FROM "SilverShop_SearchLog" WHERE "Created" < ?', [$cutoff]);
        }

        return $count;
    }
}
