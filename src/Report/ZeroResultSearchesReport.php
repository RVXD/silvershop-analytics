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
 * Search terms that returned no results, for a period — high-value for merchandising and SEO (what shoppers want but
 * cannot find). From the search log ({@link \SilverShop\Analytics\Model\SearchLog}).
 */
class ZeroResultSearchesReport extends Report
{
    public function title()
    {
        return _t(__CLASS__ . '.TITLE', 'Zero-result searches');
    }

    public function description()
    {
        return _t(__CLASS__ . '.DESC', 'Searches that returned no products, for a period — what shoppers cannot find.');
    }

    public function group()
    {
        return _t('SilverShop\\Reports.GROUP', 'Shop');
    }

    public function sort()
    {
        return 351;
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
        ]);
        $query->setFrom('"SilverShop_SearchLog"');
        $query->addWhere(['"ResultCount" = ?' => 0]);

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
            ]));
        }

        return $list;
    }

    public function columns()
    {
        return [
            'Term' => _t(__CLASS__ . '.ColTerm', 'Search term'),
            'Searches' => _t(__CLASS__ . '.ColSearches', 'Searches'),
        ];
    }
}
