<?php

declare(strict_types=1);

namespace SilverShop\Analytics\Report;

use SilverStripe\Forms\DateField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\Reports\Report;

/**
 * Most frequent storefront search terms for a period, from the search log ({@link \SilverShop\Analytics\Model\SearchLog}),
 * with how many searches each term got and the average number of results it returned.
 */
class SearchTermsReport extends Report
{
    public function title()
    {
        return _t(__CLASS__ . '.TITLE', 'Top search terms');
    }

    public function description()
    {
        return _t(__CLASS__ . '.DESC', 'Most frequent storefront searches and their average result count, for a period.');
    }

    public function group()
    {
        return _t('SilverShop\\Reports.GROUP', 'Shop');
    }

    public function sort()
    {
        return 350;
    }

    public function parameterFields()
    {
        return FieldList::create(
            DateField::create('StartDate', _t(__CLASS__ . '.Start', 'From (search date)')),
            DateField::create('EndDate', _t(__CLASS__ . '.End', 'To (search date)'))
        );
    }

    public function sourceRecords($params = null)
    {
        $query = SQLSelect::create();
        $query->setSelect([
            'Term' => '"Term"',
            'Searches' => 'COUNT("ID")',
            'AvgResults' => 'AVG("ResultCount")',
        ]);
        $query->setFrom('"SilverShop_SearchLog"');

        if (!empty($params['StartDate'])) {
            $query->addWhere(['"Created" >= ?' => $params['StartDate'] . ' 00:00:00']);
        }
        if (!empty($params['EndDate'])) {
            $query->addWhere(['"Created" <= ?' => $params['EndDate'] . ' 23:59:59']);
        }

        $query->setGroupBy('"Term"');
        $query->setOrderBy('COUNT("ID")', 'DESC');

        $list = ArrayList::create();
        foreach ($query->execute() as $row) {
            $list->push(ArrayData::create([
                'Term' => $row['Term'],
                'Searches' => (int) $row['Searches'],
                'AvgResults' => round((float) $row['AvgResults'], 1),
            ]));
        }

        return $list;
    }

    public function columns()
    {
        return [
            'Term' => _t(__CLASS__ . '.ColTerm', 'Search term'),
            'Searches' => _t(__CLASS__ . '.ColSearches', 'Searches'),
            'AvgResults' => _t(__CLASS__ . '.ColAvgResults', 'Avg results'),
        ];
    }
}
