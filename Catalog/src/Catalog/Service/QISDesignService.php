<?php
namespace Catalog\Service;

use Catalog\Controller\CronController;
use Zend\Session\Container;

class QISDesignService {

    private static function getHeader() {
        return '<?xml version="1.0" encoding="utf-8"?>
<catalog>
';
    }

    private static function getProducts($products, $sl) {
        $items = "    <products>\n";

        foreach ($products as $product) {
            $items .= self::getOneProduct($product, $sl);
        }
		
		$items .= "    </products>\n";

        return $items;
    }

    private static function getOneProduct($product, $sl) {
		$hierarchies = array();

		$series = $sl->get('Catalog/Model/SeriesTable')->find($product->series_id);
		$subsection = $sl->get('Catalog/Model/SubSectionTable')->find($series->subsection_id);
		$section = $sl->get('Catalog/Model/SectionTable')->find($subsection->section_id);

		$hierarchies[$product->id][\Catalog\Controller\AdminController::PRODUCT_TABLE] = $product->id;
		$hierarchies[$product->id][\Catalog\Controller\AdminController::SERIES_TABLE] = $series->id;
		$hierarchies[$product->id][\Catalog\Controller\AdminController::SUBSECTION_TABLE] = $subsection->id;
		$hierarchies[$product->id][\Catalog\Controller\AdminController::SECTION_TABLE] = $section->id;

		$priceRequestTable = $sl->get('PriceRequestTable');
		$requests = $priceRequestTable->fetchAllSorted();
	
        $item = '        <product id="' .$product->id . '">
';
        $item .= '            <title>' . $product->title . '</title>
';
        $item .= '            <free_balance>' . ((int)$product->free_balance) . '</free_balance>
';
        $item .= '            <price>' . CatalogService::getTruePrice($product->price_without_nds, null, $hierarchies[$product->id], null, 0, $requests) . '</price>
';
        $item .= '            <currency>RUR</currency>
';


        $item .= '        </product>
';
        return $item;
    }

    private static function getFooter() {
        return '</catalog>';
    }

    public static function makeQISDesignFile($products, $sl) {
        $catalog = self::getHeader();
        $catalog .= self::getProducts($products, $sl);
        $catalog .= self::getFooter();

        return array('catalog' => $catalog);

    }
}