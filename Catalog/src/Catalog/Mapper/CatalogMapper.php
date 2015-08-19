<?php
namespace Catalog\Mapper;

use Application\Model\SampleModel;
use Application\Model\SampleTable;
use Application\Service\ApplicationService;
use Catalog\Controller\BaseController;
use Catalog\Controller\CatalogController;
use Catalog\Model\DopProdGroup;
use Catalog\Model\ProductInMarket;
use Catalog\Service\CatalogService;
use Catalog\Controller\AdminController;
use Catalog\Service\Hierarchy;
use Catalog\Service\ProductsAggregator;
use Catalog\Service\SeriesAggregator;
use Catalog\Service\SubsectionsAggregator;
use Zend\Di\ServiceLocatorInterface;
use Zend\Validator\File\ExcludeMimeType;
use Zend\View\Model\ViewModel;

use Catalog\Model\PopularSeries;
use Catalog\Model\Section;
use Catalog\Model\SeriesDoc;
use Catalog\Model\SubSection;
use Catalog\Model\Series;
use Catalog\Model\Product;

class CatalogMapper {
    private static $instance = null;
    /** @var \Zend\ServiceManager\ServiceLocatorInterface $sl  */
    private $sl = null;

    private function __construct($sl) {
        $this->sl = $sl;
    }

    /**
     * @return \Catalog\Model\SectionTable
     */
    private function getSectionTable() {
        return $this->sl->get('Catalog\Model\SectionTable');
    }

    /**
     * @return \Catalog\Model\SubSectionTable
     */
    private function getSubSectionTable() {
        return $this->sl->get('Catalog\Model\SubSectionTable');
    }

    /**
     * @return \Catalog\Model\SeriesTable
     */
    private function getSeriesTable() {
        return $this->sl->get('Catalog\Model\SeriesTable');
    }

    /**
     * @return \Catalog\Model\ProductTable
     */
    private function getProductTable() {
        return $this->sl->get('Catalog\Model\ProductTable');
    }

    /**
     * @param \Zend\ServiceManager\ServiceLocatorInterface $sl
     * @return CatalogMapper
     */
    public static function getInstance($sl) {
        if (is_null(self::$instance)) {
            self::$instance = new CatalogMapper($sl);
        }
        return self::$instance;
    }

    public function getChildTreeIds($sectionType, $sectionId, &$tree = array()) {

        $table = $this->getSectionTable();

        if (is_array($sectionId) && count($sectionId) == 0) return $tree;


        $res = array();
        switch ($sectionType) {
            case AdminController::SECTION_TABLE :
                $table = $this->getSubsectionTable();
                $res = $table->fetchIdsByCond('section_id', $sectionId);

                break;
            case AdminController::SUBSECTION_TABLE :
                $table = $this->getSeriesTable();
                $res = $table->fetchIdsByCond('subsection_id', $sectionId);

                break;
            case AdminController::SERIES_TABLE :
                $table = $this->getProductTable();
                $res = $table->fetchIdsByCond('series_id', $sectionId);

                break;
        }

        //$sectionId = array();
        foreach ($res as $sec) {
            //$sectionId[] = $sec->id;
            $tree[$sectionType+1][] = $sec->id;
        }

        //$tree[$sectionType+1] = $sectionId;
        if ($sectionType < AdminController::SERIES_TABLE) {
            return $this->getChildTreeIds($sectionType+1, $tree[$sectionType+1], $tree);
        } else {
            return $tree;
        }

    }

    public function fetchAllSections() {
        return $this->getSectionTable()->fetchAll();
    }

    public function fetchAllSubsections($onlyWithParents = false) {
        if ($onlyWithParents) {
            return $this->getSubsectionTable()->fetchByConds(array(), array('section_id' => '0'));
        } else {
            return $this->getSubsectionTable()->fetchAll();
        }

    }

    public function fetchAllSeries($onlyWithParents = false) {
        if ($onlyWithParents) {
            return $this->getSeriesTable()->fetchByConds(array(), array('subsection_id' => '0'));
        } else {
            return $this->getSeriesTable()->fetchAll();
        }
    }

    public function fetchAllProducts($onlyWithParents = false) {
        if ($onlyWithParents) {
            return $this->getProductTable()->fetchByConds(array(), array('series_id' => '0'));
        } else {
            return $this->getProductTable()->fetchAll();
        }
    }

    /**
     * @param integer|string $id
     * @param bool $getAllSubSection
     * @param bool $getAllSeries
     * @param bool $getAllProducts
     * @param array $parentsTree
     * @param bool $isExcludeIds
     * @return Section $section
     */
    public function getSection($id, $getAllSubSection = false, $getAllSeries = false, $getAllProducts = false, $parentsTree = array(), $isExcludeIds = true) {

        if (is_numeric($id)) {
            $section = $this->getSectionTable()->find($id);
        } else {
            $section = $this->getSectionTable()->fetchByCond('display_name', $id);
            $section = (is_array($section) && count($section) > 0) ? reset($section) : $section;
        }

        $section = CatalogService::checkAndPrepareSection($section);
        if ($getAllProducts) {
            $parentsTree[AdminController::SECTION_TABLE] = $section->id;
        }
        if ($getAllSubSection) {
            $ssAg = SubsectionsAggregator::getInstance();
            $ssAg->addSubsections($this->getSubsections($section->id, $getAllSeries, $getAllProducts, $parentsTree, $isExcludeIds));
        }



        return $section;
    }

    /**
     * @param mixed array $ids
     * @param bool $getAllSubSection
     * @param bool $getAllSeries
     * @param bool $getAllProducts
     * @param array $parentsTree
     * @return Section[] $sections
     */
    public function getSections($ids, $getAllSubSection = false, $getAllSeries = false, $getAllProducts = false, $parentsTree = array()) {
        $sections = array();
        foreach ($ids as $id) {
            $sections[] = $this->getSection($id, $getAllSubSection, $getAllSeries, $getAllProducts, $parentsTree);
        }

        return $sections;
    }



    /**
     * @param $parentId
     * @param bool $getAllSeries
     * @param bool $getAllProducts
     * @param array $parentsTree
     * @param bool $isExcludeIds
     * @return bool|SubSection|SubSection[]
     */
    public function getSubsections($parentId, $getAllSeries = false, $getAllProducts = false, $parentsTree = array(), $isExcludeIds = true) {
        if (is_numeric($parentId)) {
            $subsections = $this->getSubSectionTable()->fetchByCond('section_id', $parentId, 'order asc');
        } else {
            return array();
        }

        $subsections = CatalogService::filterSubsections($subsections);
        if (!$subsections) return array();

        if ($getAllSeries) {
            $seAg = SeriesAggregator::getInstance();
            foreach ($subsections as $oneSubsection) {
                if ($getAllProducts) {
                    $parentsTree[AdminController::SUBSECTION_TABLE] = $oneSubsection->id;
                    $parentsTree[AdminController::SECTION_TABLE] = $oneSubsection->section_id;
                }

                $seAg->addSeries($this->getSeries($oneSubsection->id, $getAllProducts, $parentsTree, $isExcludeIds));
            }

        }

        return $subsections;
    }

    /**
     * @param $prodId
     * @param int $seriesId айдишник серии, на основании которой выбираем стиль отображения
     * @return array
     */
    public function getParentTree($prodId, $seriesId = 0) {
        $product = $this->getProduct($prodId);
        $series =  $this->getSeriesOne($product->series_id);
        $subsection =  $this->getSubsection($series->subsection_id);
        $section =  $this->getSection($subsection->section_id);
        $tree = array();
        $tree[AdminController::PRODUCT_TABLE] = $prodId;
        $tree[AdminController::SERIES_TABLE] = $series->id;
        $tree[AdminController::SUBSECTION_TABLE] = $subsection->id;
        $tree[AdminController::SECTION_TABLE] = $section->id;

        $type = 0;
        if ($series && $subsection && $section) {

            $type = $section->display_style;
        } else {
            $series =  $this->getSeriesOne($seriesId);
            $subsection =  $this->getSubsection($series->subsection_id);
            $section =  $this->getSection($subsection->section_id);
            $type = $section->display_style;
        }
        return array($tree, $type);
    }

    private function getDopProdsSorted($id)
    {
        $res = array();

        $dopProdGroups = $this->sl->get('Catalog\Model\DopProdGroupTable')->fetchByCond('series_id', $id, 'order asc');
        foreach($dopProdGroups as $dpgroup){
            $dopprods = $this->sl->get('Catalog\Model\DopProdTable')->fetchByCond('dopprod_group_id', $dpgroup->id, 'order ASC');

            $h = Hierarchy::getInstance();
            if($dopprods){
                $dopProducts = array();

                $type = 0;
                $tree = array();
                $i = 0;
                foreach($dopprods as $dp) {
                    $dpp = $this->getProductTable()->find($dp->product_id);
                    $dopProducts[] = $dpp;

                    if ($i == 0) {
                        list( $tree, $type) = $this->getParentTree($dp->product_id, $id);
                        $i++;
                    }
                    $tree[AdminController::PRODUCT_TABLE] = $dp->product_id;
                    $tree[AdminController::SERIES_TABLE] = $dpp->series_id;
                    $h->setProductHierarchy($dp->product_id, $tree);

                }
                $res[] = array(
                    'id' => $dpgroup->id,
                    'title' => $dpgroup->title,
                    'placement' => $dpgroup->placement,
                    'series_id' => $dpgroup->series_id,
                    'products' => $dopProducts,
                    'display_style' => $dpgroup->display_style,
                    'view' => $type,
                );
            }
        }

        return $res;
    }

    /**
     * @param $parentId
     * @param bool $getAllProducts
     * @param array $parentsTree
     * @param bool $isExcludeIds
     * @return bool|Series|Series[]
     */
    public function getSeries($parentId, $getAllProducts = false, $parentsTree = array(), $isExcludeIds = true) {
        if (is_numeric($parentId)) {
            $series = $this->getSeriesTable()->fetchByCond('subsection_id', $parentId, 'order asc');
        } else {
            return array();
        }

        $series = CatalogService::filterSeries($series);
        if (!$series) return array();


        $imgTable = $this->sl->get('Catalog\Model\SeriesImgTable');
        $docsTable = $this->sl->get('Catalog\Model\SeriesDocTable');
        $dimsTable = $this->sl->get('Catalog\Model\SeriesDimTable');
        $equalParamsTable = $this->sl->get('Catalog\Model\EqualParamsTable');
        $fileTable = $this->sl->get('FilesTable');
        foreach($series as &$ser){
            $ser->imgs = $imgTable->fetchByCond('parent_id', $ser->id, 'order asc');
            $ser->docs = $docsTable->fetchByCond('parent_id', $ser->id, 'order asc');
            $ser->dims = $dimsTable->fetchByCond('parent_id', $ser->id, 'order asc');
            if ($ser->preview) {
                $file = $fileTable->find($ser->preview);
                if ($file) {
                    $ser->previewName = $file->name;
                }
            }
        }

        if ($getAllProducts) {
            $pAg = ProductsAggregator::getInstance();
            foreach ($series as &$oneSeries) {
                $dopProducts = $this->getDopProdsSorted($oneSeries->id);

                $excludeIds = array();

                if($dopProducts){
                    foreach ($dopProducts as $dopGroup) {
                        foreach ($dopGroup['products'] as $prod) {
                            $excludeIds[] = $prod->id;
                        }
                    }
                    $oneSeries->dopProducts = $dopProducts;
                }
                $parentsTree[AdminController::SERIES_TABLE] = $oneSeries->id;
                $parentsTree[AdminController::SUBSECTION_TABLE] = $oneSeries->subsection_id;
                if ($isExcludeIds) {
                    $prods = $this->getProducts($oneSeries->id, $parentsTree, $excludeIds);
                } else {
                    $prods = $this->getProducts($oneSeries->id, $parentsTree);
                }

                $prods = CatalogService::changeIntParamsWithStringVals($prods, $this->sl->get('Catalog\Model\FilterParamTable'));
                $pAg->addProducts($prods);

                $oneSeries->equalParams = CatalogService::findEqualParams($prods);

                $shown = $equalParamsTable->find($oneSeries->id);

                $oneSeries->shownEqualParams = $shown ? $shown : array();
            }
        }


        return $series;
    }

    /**
     * @param $parentId
     * @param array $parentsTree
     * @return bool|Product[]
     */
    public function getProducts($parentId, $parentsTree = array(), $excludeIds = array()) {
        if (is_numeric($parentId)) {

            $products = $this->getProductTable()->fetchByConds(array('series_id' => $parentId), array('id' => $excludeIds), 'order asc');

        } else {
            return array();
        }

        $hierarchy = Hierarchy::getInstance();

        foreach ($products as $one) {
            $parentsTree[AdminController::PRODUCT_TABLE] = $one->id;
            $hierarchy->setProductHierarchy($one->id, $parentsTree);
        }

        if (!$products) return array();

        return $products;
    }

    /**
     * @param $id
     * @param bool $getAllSeries
     * @param bool $getAllProducts
     * @param array $parentsTree
     * @return bool|SubSection
     */
    public function getSubsection($id, $getAllSeries = false, $getAllProducts = false, $parentsTree = array()) {
        if (is_numeric($id)) {
            $subsection = $this->getSubSectionTable()->find($id);
        } else {
            return false;
        }

        $subsection = CatalogService::filterSubsections($subsection);

        if ($getAllSeries) {
            $seAg = SeriesAggregator::getInstance();
            if ($getAllProducts) {
                $parentsTree[AdminController::SUBSECTION_TABLE] = $subsection->id;
                $parentsTree[AdminController::SECTION_TABLE] = $subsection->section_id;
            }
            $seAg->addSeries($this->getSeries($id, $getAllProducts, $parentsTree));
        }

        return $subsection;
    }

    /**
     * @param $id
     * @param bool $getAllProducts
     * @param array $parentsTree
     * @return bool|Series
     */
    public function getSeriesOne($id, $getAllProducts = false, $parentsTree = array()) {
        if (is_numeric($id)) {
            $series = $this->getSeriesTable()->find($id);
        } else {
            return false;
        }

        $series = CatalogService::filterSeries($series);

        $imgTable = $this->sl->get('Catalog\Model\SeriesImgTable');
        $docsTable = $this->sl->get('Catalog\Model\SeriesDocTable');
        $dimsTable = $this->sl->get('Catalog\Model\SeriesDimTable');
        $equalParamsTable = $this->sl->get('Catalog\Model\EqualParamsTable');
        $series->imgs = $imgTable->fetchByCond('parent_id', $series->id, 'order asc');
        $series->docs = $docsTable->fetchByCond('parent_id', $series->id, 'order asc');
        $series->dims = $dimsTable->fetchByCond('parent_id', $series->id, 'order asc');

        if ($series->preview) {
            $fileTable = $this->sl->get('FilesTable');
            $file = $fileTable->find($series->preview);
            if ($file) {
                $series->previewName = $file->name;
            }
        }

        if ($getAllProducts) {

            $dopProducts = $this->getDopProdsSorted($series->id);
            $excludeIds = array();

            if($dopProducts){
                foreach ($dopProducts as $dopGroup) {
                    foreach ($dopGroup['products'] as $prod) {
                        $excludeIds[] = $prod->id;
                    }
                }
                $series->dopProducts = $dopProducts;
            } else {
                $series->dopProducts = array();
            }

            $parentsTree[AdminController::SERIES_TABLE] = $series->id;
            $parentsTree[AdminController::SUBSECTION_TABLE] = $series->subsection_id;
            $pAg = ProductsAggregator::getInstance();
            $products = $this->getProducts($id, $parentsTree, $excludeIds);
            $prods = CatalogService::changeIntParamsWithStringVals($products, $this->sl->get('Catalog\Model\FilterParamTable'));

            foreach ($dopProducts as $dopP) {

            }

            $pAg->addProducts($prods);

            $series->equalParams = CatalogService::findEqualParams($prods);

            $shown = $equalParamsTable->find($series->id);

            $series->shownEqualParams = $shown ? $shown : array();
        }

        return $series;
    }

    /**
     * @param $id
     * @return bool|Product
     */
    public function getProduct($id) {
        if (is_numeric($id)) {
            $product = $this->getProductTable()->find($id);
        } else {
            return false;
        }

        return $product;
    }
}