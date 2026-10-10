<?php

declare(strict_types=1);

namespace SilverShop\Analytics;

use SilverShop\Analytics\Model\SearchLog;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Security\Security;

/**
 * The entry point a search module calls to record what shoppers search for: `SearchInsights::record($term, $count)`.
 * It normalises the term and stores a {@link SearchLog} row, which powers the search-term and zero-result reports.
 *
 * silvershop/search calls this through a guarded hook, so search terms are captured automatically when both modules
 * are present — and nothing happens (no error) when analytics is absent.
 */
class SearchInsights
{
    use Configurable;

    private static bool $enabled = true;

    private static bool $track_members = false;

    /**
     * Record a search. Returns the written log row, or null when disabled or the term is empty.
     */
    public static function record(string $term, int $resultCount): ?SearchLog
    {
        if (!self::config()->get('enabled')) {
            return null;
        }

        $term = self::normalise($term);
        if ($term === '') {
            return null;
        }

        $memberID = 0;
        if (self::config()->get('track_members')) {
            $member = Security::getCurrentUser();
            $memberID = $member ? (int) $member->ID : 0;
        }

        return SearchLog::record($term, $resultCount, $memberID);
    }

    /**
     * Lower-case and collapse whitespace so "Red  Shoes" and "red shoes" aggregate together.
     */
    private static function normalise(string $term): string
    {
        return mb_strtolower((string) preg_replace('/\s+/', ' ', trim($term)));
    }
}
