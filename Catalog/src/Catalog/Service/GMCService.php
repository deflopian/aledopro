<?php
namespace Catalog\Service;

use Catalog\Controller\CronController;
use Zend\Session\Container;

class GMCService {
    private static $amIWannaMarketPurchase = '0';
    private static function getHeader()
    {
        return '<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">
<channel>
<title>Aledo</title>
<link>http://aledo-pro.ru</link>
        ';
    }

    private static function getShopDescription()
    {
        //todome нужно полное название компании
        return "
<description></description>
        ";
    }

    private static function getCurrencies()
    {
        return '
                <currencies>
                    <currency id="RUR" rate="1"/>
                </currencies>
        ';
    }

    private static function getCategories($sections, $subsections, $series) {
        $categories = "<categories>";

        list($newCats, $newCatsIds, $catId) = self::getCategoriesFromSections($sections);
        $categories .= $newCats;
        list($newCats, $newCatsIds, $catId) = self::getCategoriesFromSubSections($subsections, $newCatsIds, $catId);
        $categories .= $newCats;
        list($newCats, $newCatsIds, $catId) = self::getCategoriesFromSeries($series, $newCatsIds, $catId);
        $categories .= $newCats;

        $categories .= "</categories>";

        return array($categories, $newCatsIds);
    }

    private static function getCategoriesFromSections($sections) {
        $categories = '';
        $categoriesIds = array();
        $catId = 1;
        foreach ($sections as $section) {
            $categories .= '
                <category id="' . $catId . '">' . $section->title . '</category>
            ';
            $categoriesIds[$section->id] = $catId++;
        }

        return array($categories, $categoriesIds, $catId);
    }

    private static function getCategoriesFromSubSections($subsections, $prevCategoriesIds,  $catId) {
        $categories = '';
        $categoriesIds = array();
        $catId++;
        foreach ($subsections as $subsection) {
            $categories .= '
                <category id="' . $catId . '" parentId="' . $prevCategoriesIds[$subsection->section_id] . '">' . $subsection->title . '</category>
            ';
            $categoriesIds[$subsection->id] = $catId++;
        }

        return array($categories, $categoriesIds, $catId);
    }

    private static function getCategoriesFromSeries($series, $prevCategoriesIds, $catId) {
        $categories = '';
        $categoriesIds = array();
        $catId++;
        foreach ($series as $ser) {
            $categories .= '
                <category id="' . $catId . '" parentId="' . $prevCategoriesIds[$ser->subsection_id] . '">' . ($ser->visible_title ? $ser->visible_title : $ser->title) . '</category>
            ';
            $categoriesIds[$ser->id] = $catId++;
        }

        return array($categories, $categoriesIds, $catId);
    }

    private static function getOffers($products, $series, $sl) {
        $offers = "";

        foreach ($products as $product) {
            $offers .= self::getOneOffer($product, $sl);
        }

        return $offers;
    }

    /**
     * @param $product \Catalog\Model\Product
     * @return string
     */
    private static function getOneOffer($product, $sl) {
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
	
        $offer = '<item>';

        $offer .= '<title>' . $product->title . '</title>';
        $offer .= '<link>http://aledo-pro.ru/catalog/product/' . $product->id . '</link>';

        if ($product->text && stripos($product->text, 'pdf') === false) {
            $offer .= '<description>' . $product->text . '</description>';
        }
        $offer .= '<g:id>' . $product->id . '</g:id>';
        $offer .= '<g:condition>new</g:condition>';
        $offer .= '<g:price>' . CatalogService::getTruePrice($product->price_without_nds, null, $hierarchies[$product->id], null, 0, $requests) . ' RUB </g:price>';
        $offer .= '<g:availability>' . ($product->free_balance ? 'in stock' : 'preorder') . '</g:availability>';
        $offer .= '<g:image_link>http://aledo-pro.ru/images/series/' . $product->series_img . '</g:image_link>';
        $offer .= '<g:brand>' . $product->brand . '</g:brand>';

        $offer .= '
            </item>
        ';
        return $offer;
    }


    public static function parseDimensions($dimensions)
    {
        $dimensionArray = array();
        preg_match_all('|\d+|', $dimensions, $dimensionArray);

        foreach ($dimensionArray[0] as &$oneDimension) {
            $oneDimension = $oneDimension/10;
        }

        $newStr = implode('/', $dimensionArray[0]);

        return $newStr;
    }


    private static function getFooter() {
        return '
                </channel>
            </rss>
        ';
    }

    public static function makeGMCFile($series, $products, $sl)
    {

         //участвовать в программе Покупка на Маркете (1) или нет (0)
        $catalog = self::getHeader();

        $catalog .= self::getShopDescription(); //общая инфа о магазине


        $catalog .= self::getOffers($products, $series, $sl);

        $catalog .= self::getFooter();

        return array('catalog' => $catalog);

    }
}