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
 * Most-viewed products ranked by recorded page views ({@link \SilverShop\Analytics\Model\ProductView}), for a
 * period. Each row is a product with its view count and how many distinct sessions viewed it. Reads the raw view
 * log directly, so figures are live (no rollup to run); products deleted since are still counted, labelled by ID.
 */
class MostViewedProductsReport extends Report
{
    public function title()
    {
        return _t(__CLASS__ . '.TITLE', 'Most-viewed products');
    }

    public function description()
    {
        return _t(__CLASS__ . '.DESC', 'Products ranked by recorded page views, for a period.');
    }

    public function group()
    {
        return _t('SilverShop\\Reports.GROUP', 'Shop');
    }

    public function sort()
    {
        return 340;
    }

    public function parameterFields()
    {
        return FieldList::create(
            DateField::create('StartDate', _t(__CLASS__ . '.Start', 'From (view date)')),
            DateField::create('EndDate', _t(__CLASS__ . '.End', 'To (view date)'))
        );
    }

    public function sourceRecords($params = null)
    {
        $query = SQLSelect::create();
        $query->setSelect([
            'ProductID' => '"pv"."ProductID"',
            'Title' => '"st"."Title"',
            'Views' => 'COUNT("pv"."ID")',
            'Viewers' => 'COUNT(DISTINCT "pv"."VisitorHash")',
        ]);
        $query->setFrom('"SilverShop_ProductView" AS "pv"');
        $query->addLeftJoin('SiteTree', '"st"."ID" = "pv"."ProductID"', 'st');

        if (!empty($params['StartDate'])) {
            $query->addWhere(['"pv"."Created" >= ?' => $params['StartDate'] . ' 00:00:00']);
        }
        if (!empty($params['EndDate'])) {
            $query->addWhere(['"pv"."Created" <= ?' => $params['EndDate'] . ' 23:59:59']);
        }

        $query->setGroupBy(['"pv"."ProductID"', '"st"."Title"']);
        $query->setOrderBy('COUNT("pv"."ID")', 'DESC');

        $list = ArrayList::create();
        foreach ($query->execute() as $row) {
            $title = $row['Title'] ?: _t(__CLASS__ . '.Deleted', '(deleted product #{id})', ['id' => $row['ProductID']]);
            $list->push(ArrayData::create([
                'Title' => $title,
                'Views' => (int) $row['Views'],
                'Viewers' => (int) $row['Viewers'],
            ]));
        }

        return $list;
    }

    public function columns()
    {
        return [
            'Title' => _t(__CLASS__ . '.ColTitle', 'Product'),
            'Views' => _t(__CLASS__ . '.ColViews', 'Views'),
            'Viewers' => _t(__CLASS__ . '.ColViewers', 'Unique viewers'),
        ];
    }
}
