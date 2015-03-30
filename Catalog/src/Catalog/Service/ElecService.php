<?php
namespace Catalog\Service;

use Catalog\Controller\CronController;
use Zend\Session\Container;

class ElecService {
    private static $amIWannaMarketPurchase = '0';
    public static $groups = array(
        4218 => array(
            112377 => array('img' => false),
            47234  => array('img' => false),
            112657 => array('img' => false),
            105673 => array('img' => false),
            105491 => array('img' => false),
            47282  => array('img' => false),
            108716 => array('img' => false),
            138476 => array('img' => false),
            112372 => array('img' => false),
            108816 => array('img' => false),
            112712 => array('img' => false),
            161030 => array('img' => 'http://aledo-pro.ru/images/series/img_53872dfa0af90.jpg'),
            161033 => array('img' => 'http://aledo-pro.ru/images/series/img_537b68f88dbaf.jpg'),
            161456 => array('img' => false),
            161459 => array('img' => false),
            161458 => array('img' => false),
            112547 => array('img' => false),
            112554 => array('img' => false),
            160237 => array('img' => 'http://aledo-pro.ru/images/series/img_5386de12394d0.jpg'),
            109763 => array('img' => 'http://aledo-pro.ru/images/series/img_5408284cea898.jpg'),
            147582 => array('img' => false),
            147589 => array('img' => false),
            112525 => array('img' => false),
            147591 => array('img' => false),
            140456 => array('img' => false),
            147595 => array('img' => false),
            161473 => array('img' => false),
            112767 => array('img' => false),
            161274 => array('img' => false),
            161267 => array('img' => 'http://aledo-pro.ru/images/series/img_53ce73a79d185.jpg'),
            110979 => array('img' => 'http://aledo-pro.ru/images/series/img_53ce73a79d185.jpg'),
            161196 => array('img' => 'http://aledo-pro.ru/images/series/img_53c7ac0af0a49.jpg'),
            161263 => array('img' => false),
        ),
        4166 => array(
            46206  => array('img' => false),
            43336  => array('img' => false),
            47421  => array('img' => false),
            112694 => array('img' => false),
            47447  => array('img' => false),
            47511  => array('img' => false),
            112658 => array('img' => false),
            47457  => array('img' => false),
            112702 => array('img' => false),
            112846 => array('img' => false),
            160286 => array('img' => false),
            112895 => array('img' => false),
            112897 => array('img' => false),
            160497 => array('img' => false),
            53015  => array('img' => false),
        ),
        4216 => array(
            110518 => array('img' => false),
            110509 => array('img' => false),
            110259 => array('img' => false),
            110674 => array('img' => false),
            111156 => array('img' => false),
            110493 => array('img' => false),
            110678 => array('img' => false),
            110500 => array('img' => false),
            110682 => array('img' => false),
            111160 => array('img' => false),
            112122 => array('img' => false),
        ),
    );
    private static $groupsNames = array(
        4218 => 'Светодиодные светильники',
        4166 => 'Светодиодные лампы',
        4216 => 'Светодиодные ленты'
    );


    private static function getHeader()
    {
        return "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<!DOCTYPE elec_market SYSTEM \"elec-aledo.xml\">\n
<elec_market date=\"" . date('Y-m-d H:i:s') . "\">

<currencies>
    <currency id=\"RUR\" />
</currencies>\n";
    }

    private static function getCategories() {
        $categories = "<categories>\n";

        $i = 1;
        foreach (self::$groups as $key => $arr) {
            $categories .= "\t<category id=\"" . $i++ . "\" rubricaId=\"" . $key . "\" unit=\"PCE\">"
                . self::$groupsNames[$key] .
                "</category>\n";
        }

        $categories .= "</categories>\n";

        return $categories;
    }

    private static function getOffers($products) {
        $offers = "<offers>\n";


        $i = 1;
        foreach ($products as $group => $prs) {
            foreach ($prs as $product) {
                $offers .= self::getOneOffer($product, $i, $group);
            }
            $i++;
        }


        $offers .= "</offers>\n";
        return $offers;
    }

    /**
     * @param $product \Catalog\Model\Product
     * @param $i integer
     * @param $groupKey integer
     * @return string
     */
    private static function getOneOffer($product, $i, $groupKey) {

        $offer = "\t<offer id=\"" . $product->id . "\">\n";

        $offer .= "\t\t<categoryId>" . $i . "</categoryId>\n";
        $offer .= "\t\t<keyword>" . (!empty($product->type) ? $product->type : self::$groupsNames[$groupKey]) . "</keyword>\n";
        $offer .= "\t\t<title>" . $product->title . "</title>\n";
        $offer .= "\t\t<url>http://aledo-pro.ru/catalog/products/" . $product->id . "</url>\n";
        $offer .= "\t\t<price>" . CatalogService::getTruePrice($product->price_without_nds) . "</price>\n";
        $offer .= "\t\t<artno>" . $product->id . "</artno>\n";
        $offer .= "\t\t<picture>" . $product->series_img . "</picture>\n";
        $offer .= "\t\t<vendor>" . $product->brand . "</vendor>\n";
        if ($product->text && stripos($product->text, 'pdf') === false) {
            $offer .= "\t\t<tizer>" . $product->text . "</tizer>\n";
            $offer .= "\t\t<description>" . $product->text . "</description>\n";
        }
        $offer .= "\t</offer>\n";
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
        return "</elec_market>\n";
    }

    public static function makeElecFile($products)
    {

         //участвовать в программе Покупка на Маркете (1) или нет (0)
        $catalog = self::getHeader();
        $catalog .= self::getCategories();

        $catalog .= self::getOffers($products);

        $catalog .= self::getFooter();

        return $catalog;

    }
}