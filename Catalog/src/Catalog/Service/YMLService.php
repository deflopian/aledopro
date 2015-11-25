<?php
namespace Catalog\Service;

use Catalog\Controller\CronController;
use Zend\Session\Container;

class YMLService {
    private static $amIWannaMarketPurchase = '0';
    private static function getHeader()
    {
        return '<?xml version="1.0" encoding="utf-8"?>
<!DOCTYPE yml_catalog SYSTEM "aledo-shop.dtd">
<yml_catalog date="' . date('Y-m-d H:i') . '">
<shop>
        ';
    }

    private static function getShopDescription()
    {
        //todome нужно полное название компании
        return "
            <name>Aledo</name>
            <company>Aledo</company>
            <url>http://aledo-pro.ru/</url>
            <email>info@aledo-pro.ru</email>
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

    private static function getOffers($products, $allParams, $categoriesIds, $sl) {
        $offers = "<offers>";
        foreach ($products as $product) {
            $offers .= self::getOneOffer($product, $allParams, $categoriesIds, $sl);
        }
        $offers .= "</offers>";

        return $offers;
    }

    /**
     * @param $product \Catalog\Model\Product
     * @param $allParams array
     * @return string
     */
    private static function getOneOffer($product, $allParams, $categoriesIds, $sl) {
        //todome здесь следует добавить ставки (bid) на кошерные продукты
		
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
		
        $offer = '
            <offer id="' . $product->id . '" available="' . ($product->free_balance ? 'true' : 'false') . '" type="vendor.model"' . ($product->bid ? ' bid="' . $product->bid . '"' : '') . '>
        ';

        $offer .= '
            <url>http://aledo-pro.ru/catalog/product/' . $product->id . '</url>
            <price>' . CatalogService::getTruePrice($product->price_without_nds, null, $hierarchies[$product->id], null, 0, $requests) . '</price>
            <currencyId>RUR</currencyId>
            <categoryId>' . $categoriesIds[$product->series_id] . '</categoryId >
        ';

        if ($product->series_img) {
            $offer .= '
            <picture>http://aledo-pro.ru/images/series/' . $product->series_img . '</picture>
            ';
        }

        $offer .= '
            <store>true</store>
            <pickup>true</pickup>
            <delivery>true</delivery>
            <local_delivery_cost>600</local_delivery_cost>
            <typePrefix> ' . $product->type . ' </typePrefix>
            <vendor> ' . $product->brand . ' </vendor>
            <model> ' . $product->title . ' </model>
            <description>' . $product->text . '</description>
            <seller_warranty>P' . round($product->warranty) . 'Y</seller_warranty>
            <cpa>' . $product->purchase . '</cpa>';

        if ($product->controller_type) {
            $offer .= '
            <dimensions>' . self::parseDimensions($product->controller_type) . '</dimensions>
            ';
        }
        foreach ($allParams as $paramType => $paramDescription) {
            if ($paramType == 'id' || $paramType == 'type' || $paramType == 'title' || $paramType == 'text' || $paramType == 'warranty' || $paramType == 'brand' ) {
                continue;
            }

            if ($product->$paramType) {
                $offer .= '
                <param name="' . $paramDescription['name'] . '" ' . ($paramDescription['unit'] ? ' unit="' . $paramDescription['unit'] . '"' : '') . '> ' . $product->$paramType . ' </param>';
            }
        }

        $offer .= '
            </offer>
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
                </shop>
            </yml_catalog>
        ';
    }

    public static function makeYMLFile($sections, $subsections, $series, $products, $allParams, $sl)
    {
         //участвовать в программе Покупка на Маркете (1) или нет (0)
        $catalog = self::getHeader();

        $catalog .= self::getShopDescription(); //общая инфа о магазине

        $catalog .= self::getCurrencies(); //виды и курсы валют

        list($categories, $catsIds) = self::getCategories($sections, $subsections, $series);
        $catalog .= $categories;

        unset($sections);
        unset($subsections);
        unset($series);

        $catalog .= '
                    <cpa>' . self::$amIWannaMarketPurchase . '</cpa>
        ';

        $catalog .= self::getOffers($products, $allParams, $catsIds, $sl);

        $catalog .= self::getFooter();

        return array('catalog' => $catalog);

    }
}