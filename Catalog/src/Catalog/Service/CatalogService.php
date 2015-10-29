<?php
namespace Catalog\Service;

use Application\Model\FooterBlock;
use Application\Model\MainPageBlock;
use Application\Model\MainPageBlockImage;
use Application\Model\PageInfo;
use Application\Model\SampleModel;
use Application\Model\SampleTable;
use Application\Service\ApplicationService;
use Catalog\Controller\BaseController;
use Catalog\Controller\CatalogController;
use Catalog\Model\DopProdGroup;
use Catalog\Model\FilterField;
use Catalog\Model\ProductInMarket;
use Catalog\Model\ProductParam;
use Commercials\Model\Commercial;
use Commercials\Model\CommercialProd;
use Commercials\Model\CommercialRoom;
use Documents\Model\Document;
use IPGeoBase\Model\GeoBanner;
use Zend\Di\ServiceLocatorInterface;
use Zend\Validator\File\ExcludeMimeType;
use Zend\View\Model\ViewModel;

use Catalog\Controller\AdminController;
use Catalog\Model\PopularSeries;
use Catalog\Model\Section;
use Catalog\Model\SeriesDoc;
use Catalog\Model\SubSection;
use Catalog\Model\Series;
use Catalog\Model\Product;

class CatalogService {
    public static $tables = array(
        AdminController::SECTION_TABLE => 'SectionTable',
        AdminController::SUBSECTION_TABLE => 'SubSectionTable',
        AdminController::SERIES_TABLE => 'SeriesTable',
        AdminController::PRODUCT_TABLE => 'ProductTable',
        AdminController::DOC_TABLE => 'SeriesDocTable',
        AdminController::DIM_TABLE => 'SeriesDimTable',
        AdminController::POP_SERIES_TABLE => 'PopularSeriesTable',
        AdminController::FILTER_BY_SERIES_TABLE => 'SeriesParamsTable',
        AdminController::FILTER_PARAM_TABLE => 'FilterParamTable',
        AdminController::SERIES_DOPPROD_GROUP_TABLE => 'DopProdGroupTable',
        AdminController::PRODUCT_IN_MARKET_TABLE => 'ProductInMarketTable',
        AdminController::SERIES_DOPPROD_TABLE => 'DopProdTable',
        AdminController::USERS_TABLE => 'UserTable',
        AdminController::FILTER_FIELD_TABLE => 'FilterFieldTable',
        AdminController::DOCUMENT_TABLE => 'DocumentsTable',
        AdminController::COMMERCIALS_TABLE => 'CommercialsTable',
        AdminController::COMMERCIAL_ROOMS_TABLE => 'CommercialRoomsTable',
        AdminController::COMMERCIAL_PRODS_TABLE => 'CommercialProdsTable',
        AdminController::MAINPAGE_BLOCK_TABLE => 'MainPageBlocksTable',
        AdminController::MAINPAGE_BLOCK_IMAGE_TABLE => 'MainPageBlockImagesTable',
        AdminController::FOOTER_BLOCKS_TABLE => 'FooterBlocksTable',
        AdminController::GEOBANNERS_TABLE => 'GeoBannersTable',
    );

    const DISPLAY_STYLE_DEFAULT = 0;
    const DISPLAY_STYLE_LENTS = 1;      //раздел ленты
    const DISPLAY_STYLE_POWER = 2;      //раздел питание
    const DISPLAY_STYLE_PROFILES = 3;   //раздел профили

    public static $intFields = array(
        'ip_rating','color_of_light','electro_power','equipment_IP','construction','case_color','socle','bulb','color_temperature','cri'
    );

    private static $unEditableParams = array(
        /*'id', */'section_id', 'subsection_id', 'series_id', 'order', 'file'
    );

    /**
     * @param $section \Catalog\Model\Section
     * @return bool | \Catalog\Model\Section
     */
    public static function checkAndPrepareSection($section) {
        if ($section && !is_array($section) && (!$section->deleted || CatalogController::$admin)) {
            return $section;
        } else {
            return false;
        }
    }

    public static function updateLastModified($sl, $type) {
        $pageInfoTable = $sl->get('PageInfoTable');
        $pageInfo = $pageInfoTable->fetchByConds(array('type' => $type));

        if (is_array($pageInfo) && count($pageInfo) > 0) {
        $pageInfo = $pageInfo[0];
        } else {
            $pageInfo = new PageInfo();
            $pageInfo->type = $type;
        }

        $pageInfo->last_modified = time();
        $pageInfoTable->save($pageInfo);
        return true;
    }
	
	public static function getRegularPriceWithNds($product, $isLents = false)
	{
		if ($product->length > 0 && $isLents) {
            return round(self::getTruePrice($product->price_without_nds) / $product->length);
        } else {
            return self::getTruePrice($product->price_without_nds);
        }
	}

    public static function getProductsJSON($products, $fields, $user, $series = 0, $hierarchies=array(), $discounts=null, $hashedFields = array(), $isDriver = true, $isLents = false)
    {
        $jsonString = "";

        foreach ($products as $product) {
            $jsonString .= '{';
            foreach ($fields as $field) {
                if (in_array($field, $hashedFields)) {
                    if ($field == 'power') {
                        $hash = md5(ceil($product->$field));
                    } else {
                        $hash = md5($product->$field);
                    }

                    $jsonString .= '"' . $field . '-hash":"' . $hash . '",';
                }
                if ($field == 'power') {
                    $jsonString .= '"' . $field . '":' . ceil($product->$field) . ',';
                } elseif ($field == 'price_with_nds') {
                    if ($product->length > 0 && $isLents) {

                        $jsonString .= '"' . $field . '":' . round(self::getTruePrice( $product->price_without_nds )/$product->length) . ',';
                    } else {
                        $jsonString .= '"' . $field . '":' . self::getTruePrice( $product->price_without_nds ) . ',';
                    }

                } elseif ($field == 'i_out') {
                    if ($isDriver) {

                        $jsonString .= '"i_out":"' . $product->$field . '",';
                    } else {
                        $jsonString .= '"u_out":"' . $product->$field . '",';
                    }

                }  elseif ($field == 'partner_price' && $user && $user->getIsPartner()) {
                    $truePrice = self::getTruePrice(
                        $product->price_without_nds,
                        $user,
                        $hierarchies[$product->id] ? $hierarchies[$product->id] : array(),
                        $discounts,
                        $product->opt2
                    );
                    if ($product->length > 0 && $isLents) {
                        $jsonString .= '"' . $field . '":' . round($truePrice/$product->length) . ',';
                    } else {
                        $jsonString .= '"' . $field . '":' . $truePrice . ',';
                    }

                } elseif ($field == 'is_offer') {
                    if ($product->free_balance > 0) {
                        if ($product->$field > 0 || (is_object($series) && $series->is_offer == 1)) {
                            $jsonString .= '"is_offer":1,';
                        } else {
                            $jsonString .= '"is_offer":0,';
                        }
                    } else {
                        $jsonString .= '"is_offer":0,';
                    }
                } elseif ($field == 'free_balance') {
                    if ($user && $user->getIsPartner()) {
                        /*if ($product->id == 138528 && $product->free_balance > 7) {
                            $product->free_balance = 7;
                        }*/
                        $jsonString .= '"' . $field . '":' . $product->free_balance . ',';
                    } else {
                        if ($product->$field > 0) {
                            $jsonString .= '"' . $field . '":"-1",';
                        } else {
                            $jsonString .= '"' . $field . '":"0",';
                        }

                    }

                } else {
                    if (is_integer($product->$field)) {
                        $jsonString .= '"' . $field . '":' . $product->$field . ',';
                    } else {
                        $jsonString .= '"' . $field . '":"' . $product->$field . '",';
                    }

                }

            }
            $jsonString = rtrim($jsonString, ', ');
            $jsonString .= "},";
        }
        $jsonString = rtrim($jsonString, ', ');

        return $jsonString;
    }

    /**
     * @param $products
     * @param $fields
     * @param int $prevProdCount
     * @param ProductParam[] $params
     * @return string
     */
    public static function getValsJSON($products, $fields, $prevProdCount=0, $params = array(), $perMeter = false)
    {
        $jsonString = "";
        $valsArr = array();
        $i = $prevProdCount;

        foreach ($products as $product) {

            foreach ($fields as $field) {

                if ($field == 'power') {
                    $val = '"value" : "' . ceil($product->$field) . '", "hash" : "' . md5(ceil($product->$field)) . '"';
                } else {
                    $val = '"value" : "' . $product->$field . '", "hash" : "' . md5($product->$field) . '"';
                }

                if (is_array($params) && array_key_exists($field, $params)) {
                    if ($perMeter && ($field == 'luminous_flux' || $field == 'power')) {
                        $val .=  ', "measuresPre" : "' . $params[$field]->pre_value . '", "measuresPost" : "' . $params[$field]->post_value . '/м"';
                    } elseif ($field == "warranty") {
                        $val .=  ', "measuresPre" : "' . $params[$field]->pre_value . '", "measuresPost" : "' . self::getYearForm($product->$field) . '"';
                    } else {
                        $val .=  ', "measuresPre" : "' . $params[$field]->pre_value . '", "measuresPost" : "' . $params[$field]->post_value . '"';
                    }
                }

                if (isset($product->origin[$field])) {
                    $val .=  ', "originVal" : "' . $product->origin[$field] . '"';
                }

                if (!in_array($val, (array)$valsArr[$field])) {
                    $valsArr[$field][$i++] = $val;
                }

            }
        }


        $jsonString .= '{';

        foreach ($valsArr as $field => $arr) {

            $jsonString .= '"' . $field . '":[{' . implode('},{', $arr) . '}],';
        }

        $jsonString = rtrim($jsonString, ', ');
        $jsonString .= "}";

        return $jsonString;
    }

    /**
     * @param $subsection \Catalog\Model\SubSection|\Catalog\Model\SubSection[]
     * @return bool | \Catalog\Model\SubSection[] | \Catalog\Model\SubSection
     */
    public static function filterSubsections($subsection) {
        if (!$subsection) {
            return false;
        }

        if (is_array($subsection)) {

            foreach ($subsection as $subKey => $oneSubsection) {
                if (!$oneSubsection || ($oneSubsection->deleted && CatalogController::$admin === false)) {
                    unset($subsection[$subKey]);
                }
            }

            return (count($subsection) > 0) ? $subsection : false;
        } else {
            return ($subsection && (!$subsection->deleted || CatalogController::$admin)) ? $subsection : false;
        }

    }


    /**
     * @param $series \Catalog\Model\Series|\Catalog\Model\Series[]
     * @return bool|\Catalog\Model\Series|\Catalog\Model\Series[]
     */
    public static function filterSeries($series) {
        if (!$series) {
            return false;
        }

        if (is_array($series)) {

            foreach ($series as $subKey => $oneSeries) {
                if (!$oneSeries || ($oneSeries->deleted && CatalogController::$admin === false)) {
                    unset($series[$subKey]);
                }
            }

            return (count($series) > 0) ? $series : false;
        } else {
            return ($series && (!$series->deleted || CatalogController::$admin))? $series : false;
        }

    }

    public static function fillDimensionsWithTags($dimensionString) {
        if (empty($dimensionString)) return "";

        $parsedString = YMLService::parseDimensions($dimensionString);
        $dimensionsArray = explode('/', $parsedString);
        $resultString = "";
        for ($i = 0; $i < count($dimensionsArray); $i++) {
            switch ($i) {
                case 0:
                    $resultString .= '<span itemprop="length">' . $dimensionsArray[$i] . '</span> * ';
                    break;
                case 1:
                    $resultString .= '<span itemprop="width">' . $dimensionsArray[$i] . '</span> * ';
                    break;
                case 2:
                    $resultString .= '<span itemprop="height">' . $dimensionsArray[$i] . '</span> см';
                    break;
            }
        }
        return $resultString;
    }

    public static function getYearForm($year) {
        $res = '';
        if ($year == 0) {
            $res = 'лет';
        } elseif ($year == 1) {
            $res = 'год';
        } elseif ($year >= 2 && $year <= 4) {
            $res = 'года';
        } elseif ($year >= 5 && $year <= 20) {
            $res = 'лет';
        }
        return $res;
    }

    /*
     * RewriteEngine On
RewriteCond %{REQUEST_FILENAME} -s [OR]
RewriteCond %{REQUEST_FILENAME} -l [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^.*$ - [NC,L]
RewriteRule ^.*$ index.php [NC,L]
     */

    /**
     * @param $param
     * @param $seriesId
     * @param $ordnung
     * @param $sl
     * @return bool
     */
    public static function makesort($param, $seriesId, $ordnung, $sl, $isCron = false) {



        /** @var \Catalog\Model\SeriesTable $seriesTable */
        $seriesTable = $sl->get('Catalog\Model\SeriesTable' );
        $series = $seriesTable->find($seriesId);
        $sortedFieldName = $series->sorted_field;
        $sortedFieldOrder = $series->sorted_order;
        $tableName = 'ProductTable';
        /** @var \Catalog\Model\ProductTable $table */
        $table = $sl->get('Catalog\Model\\'. $tableName );

        if ($ordnung !== false) {
            $neueOrdnung = $ordnung == 1 ? 'ASC' : 'DESC';
            $neueOrdnungNum = $ordnung == 1 ? 1 : 2;
        } else {
            $neueOrdnung = 'ASC';
            if ($sortedFieldName == $param) {
                if ($sortedFieldOrder == 2) {
                    $neueOrdnung = 'ASC';
                    $neueOrdnungNum = 1;
                } else {
                    $neueOrdnung = 'DESC';
                    $neueOrdnungNum = 2;
                }
            } else {
                $neueOrdnungNum = 1;
            }
        }

        $series->sorted_field = $param;
        $series->sorted_order = $neueOrdnungNum;
        $seriesTable->save($series);

        $productParams = $sl->get('Catalog\Model\FilterParamTable')->fetchByCond('field', $param, "value $neueOrdnung");
        if ($productParams) {
            $valuesKeys = array();
            foreach ($productParams as $oneParamValue) {
                $valuesKeys[] = $oneParamValue->id;
            }
            $valuesKeys = array_flip($valuesKeys);

            $products = $table->fetchByCond('series_id', $seriesId);

            foreach ($products as &$oneProduct) {
                if (!$isCron) {
                    $oneProduct->sorted_by_user = 0;
                    $oneProduct->order = $valuesKeys[$oneProduct->$param];
                    $table->save($oneProduct);
                } else {
//                    if ($oneProduct->sorted_by_user == 0) {
                        $oneProduct->order = $valuesKeys[$oneProduct->$param];
                        $table->save($oneProduct);
//                    }
                }
            }
        } else {
            $products = $table->fetchByCond('series_id', $seriesId, "$param $neueOrdnung");
//
//            if ($isCron) {
//
//                usort ($products, function($first, $second) {
//                    if ($first->sorted_by_user == 1 || $second->sorted_by_user == 1) {
//                        if ($first->order == $second->order) {
//                            return 0;
//                        } elseif ($first->order < $second->order) {
//                            return -1;
//                        } else {
//                            return 1;
//                        }
//                    }
//                    return 0;
//                });
//            }

            foreach ($products as $key => &$product) {
                if ($isCron) {
//                    if (!$product->sorted_by_user) {
                        $products[$key]->order = $key;
                        $table->save($product);
//                    }
                } else {
                    $products[$key]->order = $key;
                    $table->save($product);
                }

            }
        }
        return true;
    }

    public static function getElementsByIdAndType($sl, $infos) {
        $prodIds = array();
        $seriesIds = array();
        $subsectionIds = array();
        $sectionIds = array();
        $solutionIds = array();
        $projectIds = array();
        $docIds = array();
        $userIds = array();

        /** @var \Catalog\Model\ProductTable $prodTable */
        $prodTable = $sl->get('Catalog\Model\ProductTable');
        /** @var \Catalog\Model\SeriesTable $seriesTable */
        $seriesTable = $sl->get('Catalog\Model\SeriesTable');
        /** @var \Catalog\Model\SubSectionTable $subsectionTable */
        $subsectionTable = $sl->get('Catalog\Model\SubsectionTable');
        /** @var \Catalog\Model\SectionTable $sectionTable */
        $sectionTable = $sl->get('Catalog\Model\SectionTable');
        /** @var \Solutions\Model\SolutionTable $solutionTable */
        $solutionTable = $sl->get('Solutions\Model\SolutionTable');
        /** @var \Projects\Model\ProjectTable $projectTable */
        $projectTable = $sl->get('Projects\Model\ProjectTable');
        /** @var \Catalog\Model\SeriesDocTable $docTable */
        $docTable = $sl->get('Catalog\Model\SeriesDocTable');
        $dimTable = $sl->get('Catalog\Model\SeriesDimTable');
        /** @var \User\Model\UserTable $userTable */
        $userTable = $sl->get('User\Model\UserTable');

        $element = array();

        foreach ($infos as $stos) {
            switch ($stos->series_type_2) {
                case AdminController::PRODUCT_TABLE :
                    $prod = $prodTable->find($stos->series_id_2);
                    $template = 'catalog/catalog/part/series-tile';
                    if ($prod) {
                        $element[] = array($prod, $template, true);
                    }
                    break;
                case AdminController::SERIES_TABLE :
                    $prod = $seriesTable->find($stos->series_id_2);
                    if ($prod->preview) {
                        $fileTable = $sl->get('FilesTable');
                        $file = $fileTable->find($prod->preview);
                        if ($file) {
                            $prod->previewName = $file->name;
                        }
                    }
                    $template = 'catalog/catalog/part/series-tile';
                    if ($prod) {
                        $element[] = array($prod, $template, false);
                    }
                    break;
                case AdminController::SUBSECTION_TABLE :

                    $prod = $subsectionTable->find($stos->series_id_2);
                    $template = 'catalog/catalog/part/series-tile';
                    if ($prod) {
                        $element[] = array($prod, $template);
                    }
                    break;
                case AdminController::SECTION_TABLE :
                    $sectionIds[]   = $stos->series_id_2;
                    break;
                case AdminController::SOLUTION_TABLE :
                    $solutionIds[]  = $stos->series_id_2;
                    break;
                case AdminController::PROJECT_TABLE :
                    $projectIds[]   = $stos->series_id_2;
                    break;
                case AdminController::DOC_TABLE :
                    $docIds[]       = $stos->series_id_2;
                    break;
                case AdminController::DIM_TABLE :
                    $dimIds[]       = $stos->series_id_2;
                    break;
                case AdminController::USERS_TABLE :
                    $userIds[]      = $stos->series_id_2;
                    break;
            }
        }




    }

    public static function getNextId($currentId, $seriesIds) {
        $currentIndex = array_search($currentId, $seriesIds);
        $nextIndex = ($currentIndex + 1 > count($seriesIds) - 1)
            ? 0
            : $currentIndex + 1;
        return $seriesIds[$nextIndex];
    }

    public static function getPrevId($currentId, $seriesIds) {
        $currentIndex = array_search($currentId, $seriesIds);
        $prevIndex = $currentIndex == 0
            ? count($seriesIds) - 1
            : $currentIndex - 1;
        return $seriesIds[$prevIndex];
    }

    public static function getTableName($id)
    {
        return isset(self::$tables[$id]) ? self::$tables[$id] : null;
    }

    public static function getUnEditableParams()
    {
        return self::$unEditableParams;
    }

    public static function createAndFillEntity($type, $data)
    {
        switch($type){
            case AdminController::SECTION_TABLE:
                $entity = new Section();
                break;
            case AdminController::PRODUCT_IN_MARKET_TABLE:
                $entity = new ProductInMarket();
                break;

            case AdminController::SUBSECTION_TABLE:
                if(isset($data['parent_id'])){
                    $data['section_id'] = $data['parent_id'];
                }
                $entity = new SubSection();
                break;

            case AdminController::SERIES_TABLE:
                if(isset($data['parent_id'])){
                    $data['subsection_id'] = $data['parent_id'];
                }
                $entity = new Series();
                break;

            case AdminController::PRODUCT_TABLE:
                if(isset($data['parent_id'])){
                    $data['series_id'] = $data['parent_id'];
                    $data['add_by_user'] = 1;
                }
                $entity = new Product();
                break;

            case AdminController::DOC_TABLE:
                $entity = new SeriesDoc();
                break;

            case AdminController::POP_SERIES_TABLE:
                if(isset($data['parent_id'])){
                    $data['section_id'] = $data['parent_id'];
                }
                $entity = new PopularSeries();
                break;

            case AdminController::SERIES_DOPPROD_GROUP_TABLE:
                if(isset($data['parent_id'])){
                    $data['series_id'] = $data['parent_id'];
                }
                $entity = new DopProdGroup();
                break;
            case AdminController::DOCUMENT_TABLE:
                $entity = new Document();
                break;
            case AdminController::COMMERCIALS_TABLE:
                $entity = new Commercial();
                break;
            case AdminController::COMMERCIAL_ROOMS_TABLE:
                $entity = new CommercialRoom();
                break;
            case AdminController::COMMERCIAL_PRODS_TABLE:
                $entity = new CommercialProd();
                break;

            case AdminController::MAINPAGE_BLOCK_TABLE:
                $entity = new MainPageBlock();
                break;
            case AdminController::FOOTER_BLOCKS_TABLE:
                $entity = new FooterBlock();
                break;
            case AdminController::GEOBANNERS_TABLE:
                $entity = new GeoBanner();
                break;
            case AdminController::MAINPAGE_BLOCK_IMAGE_TABLE:
                $entity = new MainPageBlockImage();
                break;

            case AdminController::FILTER_FIELD_TABLE:
                if(isset($data['parent_id'])){
                    $data['series_id'] = $data['parent_id'];
                }
                $entity = new FilterField();
                break;
        }

        if(isset($entity)){
            $entity->exchangeArray($data);
            return $entity;
        }

        return null;
    }

    /**
     * @param $serviceLocator \Zend\ServiceManager\ServiceLocatorInterface
     * @param $id integer
     * @param $type integer
     * @return SampleModel|false
     */
    public static function getEntityByType($serviceLocator, $id, $type)
    {
        $entity = $serviceLocator->get('Catalog\Model\\' . self::$tables[$type])->find($id);

        if(isset($entity)){
            return $entity;
        }

        return false;
    }
    /**
     * @param $serviceLocator \Zend\ServiceManager\ServiceLocatorInterface
     * @param $entity SampleModel
     * @param $type integer
     * @return SampleModel|false
     */
    public static function saveEntityByType($serviceLocator, $entity, $type)
    {

        $lastId = $serviceLocator->get('Catalog\Model\\' . self::$tables[$type])->save($entity);

        return $lastId;
    }

    public static function getSeriesAndTags($allSeries, $id = 0, $excludeIds = array())
    {
        $thisSubsecSeries = $sids = $snames = array();
        foreach($allSeries as $series){
            if($excludeIds && in_array($series->id, $excludeIds)){
                continue;
            }

            if($id && $series->subsection_id == $id){
                $thisSubsecSeries[] = $series;
            } else {
                $name['label'] = $series->title;
                $name['value'] = $series->id;
                $snames[] = $name;

                $sid['label'] = $series->id;
                $sid['value'] = $series->id;
                $sids[] = $sid;
            }
        }

        $seriesTags = array_merge($snames,$sids);

        return array(
            'series' => $thisSubsecSeries,
            'tags'   => $seriesTags
        );
    }

    public static function renderSeriesTile($serviceLocator, $series, $isProduct = false)
    {
        $htmlViewPart = new ViewModel();
        $htmlViewPart->setTerminal(true)
            ->setTemplate('catalog/catalog/part/series-tile');

        if ($isProduct) {
            $trulySeries = $serviceLocator->get('Catalog\Model\SeriesTable')->find($series->series_id);

            if ($trulySeries->preview) {
                $fileTable = $serviceLocator->get('FilesTable');
                $file = $fileTable->find($trulySeries->preview);
                if ($file) {
                    $trulySeries->previewName = $file->name;
                }
            }

            $htmlViewPart->setVariables(array(
                'series' => $trulySeries,
                'product' => $series,
            ));
        } else {
            $htmlViewPart->setVariables(array(
                'series' => $series,
                'product' => false,
            ));
        }
        return $serviceLocator->get('viewrenderer')->render($htmlViewPart);
    }
	
	 public static function renderSubsectionTile($serviceLocator, $subsection)
    {
        $htmlViewPart = new ViewModel();
        $htmlViewPart->setTerminal(true)
            ->setTemplate('catalog/catalog/part/subsection-tile');
        
            $htmlViewPart->setVariables(array(
                'subsection' => $subsection,
            ));
        return $serviceLocator->get('viewrenderer')->render($htmlViewPart);
    }

    public static function renderSeoTextBlock($sl, $entity)
    {
        $htmlViewPart = new ViewModel();
        $htmlViewPart->setTerminal(true)
            ->setTemplate('catalog/catalog/part/seo-text-block')
            ->setVariables(array(
                'entity' => $entity,
            ));
        return $sl->get('viewrenderer')->render($htmlViewPart);
    }

    private static function getPartnerPrice($price, $hierarchy, $discounts, $minPrice = 0) {
        //
        $currentDiscount = 0;

        for ($i = AdminController::PRODUCT_TABLE; $i >= AdminController::SECTION_TABLE; $i--) {
            if (array_key_exists($i, $discounts) && array_key_exists($i, $hierarchy)) {
                $hKey = $hierarchy[$i];
                if (array_key_exists($hKey, $discounts[$i])) {
                    $currentDiscount = $discounts[$i][$hKey];
                    break;
                }
            }
        }

        return max($price * (1 - $currentDiscount * 0.01), $minPrice);
    }

    public static function renderPopupNav($serviceLocator, $prevEntity, $nextEntity, $folder, $robot = false)
    {
        $htmlViewPart = new ViewModel();
        $htmlViewPart->setTerminal(true)
            ->setTemplate('catalog/catalog/part/part/popup-nav')
            ->setVariables(array(
                'prevEntity' => $prevEntity,
                'nextEntity' => $nextEntity,
                'robot' => $robot,
                'folder' => $folder
            ));

        return $serviceLocator->get('viewrenderer')->render($htmlViewPart);
    }

    public static function findEqualParams($products){
        if (count($products) == 0) {
            return array();
        }

        $standart = reset($products)->toArray();
        foreach($products as $product){
            foreach($product->toArray() as $field=>$value){
                if(
                    isset($standart[$field])
                    &&
                    ($standart[$field] != $value || $standart[$field] == '' || $standart[$field] == '0' ||
                        $field=='file' || $field=='series_id' || $field=='seriesName'
                        || $field=='type' || $field=='brand' || $field=='group_code'|| $field=='file_custom')
                ){
                    unset($standart[$field]);
                }
            }
        }

        return $standart;
    }

    public static function changeIntParamsWithStringVals($products, $filterParamsTable)
    {
                $params = $filterParamsTable->fetchAll();
        $sortedParams = ApplicationService::makeIdArrayFromObjectArray($params);
        foreach($products as &$prod){
            foreach(self::$intFields as $filedName){
                $prod->origin[$filedName] = $prod->$filedName;
                $prod->$filedName = isset($sortedParams[$prod->$filedName]) ? $sortedParams[$prod->$filedName]->value : $prod->$filedName;

            }
        }

        return $products;
    }

    public static function getFilterFields()
    {
        return array(
            5 => 1, // Moshnost'
            6 => 0, // Pitanie'
            8 => 1, // Ugol Svechenia'
            9 => 1, // Svetovoy potol'
            24 => 0, // IP rating'
            10 => 0, // Cvet svechenia
            17 => 0, // Construkcia korpusa
        );
    }

    /**
     * оформляет приходящие из базы результаты в массив
     * + если передан массив серий, выбирает min/max по всем сериям этого массива
     * @param array $seriesMinMax
     * @return array
     */
    public static function getFilterMinMax($seriesMinMax) {
        $seriesParams = array();
        $diapasonsMinMax = BaseController::$activeDiapasonFilterParams;
        foreach($seriesMinMax as $oneSeries) {

            foreach ($diapasonsMinMax as $diapasonName) {
                $minName = 'min_' . $diapasonName;
                $maxName = 'max_' . $diapasonName;
                if (isset($seriesParams[$diapasonName])) {
                    $seriesParams[$diapasonName]['min'] = min($seriesParams[$diapasonName]['min'], $oneSeries->$minName);
                    $seriesParams[$diapasonName]['max'] = max($seriesParams[$diapasonName]['max'], $oneSeries->$maxName);
                } else {
                    $seriesParams[$diapasonName]['min'] = $oneSeries->$minName;
                    $seriesParams[$diapasonName]['max'] = $oneSeries->$maxName;
                }
            }

        }
        return $seriesParams;
    }

    /**
     * Собирает запрос для фильтра по продуктам
     * @param $post - сырые данные, которые пришли айксом от юзера
     * @param $diapasonMinMax - набор минимумов/максимумов, в пределах которого можем искать
     * @param array $seriesIds - если фильтр проводится из подраздела, сюда передаются айдишки серий этого подраздела
     * @param array $offerIds - если ищет по спецпредложениям
     * @return array|bool - массив параметров для select
     */
    public static function getFilterQuery($post, $diapasonMinMax, $seriesIds = array(), $offerIds = array()) {
        $validatedData = self::validateFilterData($post, $diapasonMinMax);

        if ($validatedData === false) {
            return false;
        }
        $seriesParam = 0;
        if (is_array($seriesIds) && count($seriesIds) > 0) {
            $seriesParam = $seriesIds;
        } else {
            $seriesParam = $post['series_id'];
        }

        $queryParams = array(
            'series_id' => $seriesParam,
        );
        $diapasons = BaseController::$activeDiapasonFilterParams;
        $discreteValues = BaseController::$activeDiscreteFilterParams;

        if (isset($post['instock']) && $post['instock'] == 1) {
            $queryParams['free_balance != ?'] = 0;
        }
/*
        if (isset($post['offers']) && $post['offers'] == 1) {
            $queryParams['is_offer'] = 1;
        }*/

//        if (isset($post['offers']) && $post['offers'] == 1) {
//            if (!$offerIds) {
//                return false;
//            }
//
//        }

        foreach ($diapasons as $diapasonName) {
            if (array_key_exists($diapasonName, $post)) {
                if(isset($validatedData[$diapasonName]) && is_array($validatedData[$diapasonName])){
                    if ($diapasonName == 'luminous_flux') {
                        $queryParams['lumfx_abs >= ?'] = $validatedData['luminous_flux']['min'];
                        $queryParams['lumfx_abs <= ?'] = $validatedData['luminous_flux']['max'];
                    } elseif ($diapasonName == 'viewing_angle') {
                        $queryParams['vangl_abs >= ?'] = $validatedData['viewing_angle']['min'];
                        $queryParams['vangl_abs <= ?'] = $validatedData['viewing_angle']['max'];
                    } else {
                        $queryParams[$diapasonName . ' >= ?'] = $validatedData[$diapasonName]['min'];
                        $queryParams[$diapasonName . ' <= ?'] = $validatedData[$diapasonName]['max'];
                    }

                }
            }
        }
        foreach ($discreteValues as $valueName) {
            if (array_key_exists($valueName, $post)) {
                if (isset($validatedData[$valueName])) {
                    if ($validatedData[$valueName] !== 0) {
                        $queryParams[$valueName] = $validatedData[$valueName];
                    }
                }
            }
        }
        return $queryParams;

    }

    /**
     * проверяем юзера на честность. Не ищем среди отрицательных значений и проверяем на соответствие запроса min/max
     * @param $post
     * @param $diapasonMinMax
     * @return array|bool
     */
    public static function validateFilterData($post, $diapasonMinMax) {

        if (isset($post['section_id'])) {
            $seriesId = $post['section_id'];
        } else {
            echo array('error' => 'section id does not set');
            return false;
        }

        $validatedParams = array();

        $filterDiapasons = BaseController::$activeDiapasonFilterParams;
        foreach ($filterDiapasons as $diapasonName) {
            if ( isset($post[$diapasonName])
                && is_array($post[$diapasonName])
                && is_numeric($post[$diapasonName][0])
                && is_numeric($post[$diapasonName][1])

            ) {
                $minRequest = min($post[$diapasonName][0], $post[$diapasonName][1]);
                $minRequest = max($diapasonMinMax[$diapasonName]['min'], $minRequest);
                if ($minRequest < 0) {
                    $minRequest = 0;
                }
                $maxRequest = max($post[$diapasonName][0], $post[$diapasonName][1]);
                $maxRequest = min($diapasonMinMax[$diapasonName]['max'], $maxRequest);
                if ($maxRequest < 0) {
                    $maxRequest = 0;
                }

                $validatedParams[$diapasonName] = array('min' => $minRequest, 'max' => $maxRequest);

            } else {
                if (isset($diapasonMinMax[$diapasonName])) {
                    $minRequest = $diapasonMinMax[$diapasonName]['min'];
                    $maxRequest = $diapasonMinMax[$diapasonName]['max'];
                    $validatedParams[$diapasonName] = array('min' => $minRequest, 'max' => $maxRequest);
                }
            }
        }


        $filterFields = BaseController::$activeDiscreteFilterParams;
        foreach ($filterFields as $fieldName) {
            if ( isset($post[$fieldName])) {
                $request = $post[$fieldName];
            } else {
                $request = 0;
            }
            $validatedParams[$fieldName] = $request;
        }


        return $validatedParams;
    }

    public static function getTruePrice($price, $user = null, $hierarchy = array(), $discounts = null, $minPrice = 0)
    {
        if ($user && $user->getIsPartner()) {
            return round(self::getPartnerPrice($price * 1.18, $hierarchy, $discounts, $minPrice));
        }

        return round($price * 1.18);
    }
	
	public static function getTruePriceUser($price, $user = null, $hierarchy = array(), $discounts = null, $minPrice = 0)
    {
        if ($user && $user->is_partner) {
            return round(self::getPartnerPrice($price * 1.18, $hierarchy, $discounts, $minPrice));
        }

        return round($price * 1.18);
    }
}