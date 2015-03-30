<?php
namespace Catalog\Mapper;

use Application\Model\SampleModel;
use Application\Model\SampleTable;
use Application\Service\ApplicationService;
use Catalog\Controller\BaseController;
use Catalog\Controller\CatalogController;
use Catalog\Model\DopProdGroup;
use Catalog\Model\LinkToLink;
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

class LinkToLinkMapper {

    private static $instance = null;
    /** @var \Zend\ServiceManager\ServiceLocatorInterface $sl  */
    private $sl = null;

    private function __construct($sl) {
        $this->sl = $sl;
    }

    /**
     * @return \Catalog\Model\LinkToLinkTable
     */
    private function getLinkToLinkTable() {
        return $this->sl->get('Catalog\Model\LinkToLinkTable');
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
     * @return \Solutions\Model\SolutionTable
     */
    private function getSolutionTable() {
        return $this->sl->get('SolutionsTable');
    }

    /**
     * @return \Projects\Model\ProjectTable
     */
    private function getProjectTable() {
        return $this->sl->get('ProjectsTable');
    }



    /**
     * @param \Zend\ServiceManager\ServiceLocatorInterface $sl
     * @return LinkToLinkMapper
     */
    public static function getInstance($sl) {
        if (is_null(self::$instance)) {
            self::$instance = new LinkToLinkMapper($sl);
        }
        return self::$instance;
    }

    public function removeLink($link_id_1,$link_type_1,$link_id_2,$link_type_2) {
        $l2lTable = $this->getLinkToLinkTable();

        $l2lTable->del(
            array(
                'link_id_1' => $link_id_1,
                'link_type_1' => $link_type_1,
                'link_id_2' => $link_id_2,
                'link_type_2' => $link_type_2,
            )
        );
        return true;
    }

    public function addLinks($link_id_1, $link_type_1, $link_id_2, $link_type_2) {
        $l2lTable = $this->getLinkToLinkTable();
        if (is_string($link_id_2)) {
            $link_id_2 = explode(',', $link_id_2);
        }
        $existed = $l2lTable->fetchByConds(array(
            'link_id_1' => $link_id_1,
            'link_type_1' => $link_type_1,
            'link_id_2' => $link_id_2,
            'link_type_2' => $link_type_2,
        ));

        foreach ($existed as $existLink) {
            $key = array_search($existLink->link_id_2, $link_id_2);
            unset($link_id_2[$key]);
        }

        foreach ($link_id_2 as $id) {
            $link = new LinkToLink();
            $link->link_id_1 = $link_id_1;
            $link->link_type_1 = $link_type_1;
            $link->link_id_2 = $id;
            $link->link_type_2 = $link_type_2;
            $l2lTable->save($link);
        }
        return count($link_id_2);
    }



    public function fetchAll($linkId, $linkType) {
        $l2lTable = $this->getLinkToLinkTable();
        $linksIds = $l2lTable->find($linkId, $linkType);
        $links = array();
        foreach($linksIds as $sid){
            switch($sid->link_type_2) {
                case AdminController::PRODUCT_TABLE :
                    /** @var Product $prod */
                    $prod = $this->getProductTable()->find($sid->link_id_2);
                    /** @var Series $ser */
                    $ser = $this->getSeriesTable()->find($prod->series_id);
                    if ($ser->preview) {
                        $fileTable = $this->sl->get('FilesTable');
                        $file = $fileTable->find($ser->preview);
                        if ($file) {
                            $ser->previewName = $file->name;
                            $prod->img = $ser->previewName;
                        } else {
                            $prod->img = $ser->img;
                        }
                    } else {
                        $prod->img = $ser->img;
                    }

                    $links[] = array($sid->link_type_2, $prod, 'product', 'Продукт');
                    break;
                case AdminController::SERIES_TABLE :
                    $ser = $this->getSeriesTable()->find($sid->link_id_2);

                    if ($ser->preview) {
                        $fileTable = $this->sl->get('FilesTable');
                        $file = $fileTable->find($ser->preview);
                        if ($file) {
                            $ser->previewName = $file->name;
                        }
                    }

                    $links[] = array($sid->link_type_2, $ser, 'series', 'Серия');
                    break;
                case AdminController::SUBSECTION_TABLE :
                    $links[] = array($sid->link_type_2, $this->getSubsectionTable()->find($sid->link_id_2), 'subsection', 'Подраздел');
                    break;
                case AdminController::SECTION_TABLE :
                    $links[] = array($sid->link_type_2, $this->getSectionTable()->find($sid->link_id_2), 'section', 'Раздел');
                    break;
                case AdminController::SOLUTION_TABLE :
                    $links[] = array($sid->link_type_2, $this->getSolutionTable()->find($sid->link_id_2), 'solution', 'Решение');
                    break;
                case AdminController::PROJECT_TABLE :
                    $links[] = array($sid->link_type_2, $this->getProjectTable()->find($sid->link_id_2), 'project', 'Проект');
                    break;
            }
        }

        return $links;
    }

    public function fetchCatalogSortedBySectionType($linkId, $linkType) {
        $l2lTable = $this->getLinkToLinkTable();
        $linksIds = $l2lTable->find($linkId, $linkType);
        $links = array();
        foreach($linksIds as $sid){
            switch($sid->link_type_2) {
                case AdminController::PRODUCT_TABLE :
                    /** @var Product $prod */
                    $prod = $this->getProductTable()->find($sid->link_id_2);
                    /** @var Series $ser */
                    $ser = $this->getSeriesTable()->find($prod->series_id);
                    /** @var $subsec SubSection */
                    $subsec = $this->getSubSectionTable()->find($ser->subsection_id);
                    /** @var $sec Section */
                    $sec = $this->getSectionTable()->find($subsec->section_id);
                    $type = $sec->display_style;

                    if ($ser->preview) {
                        $fileTable = $this->sl->get('FilesTable');
                        $file = $fileTable->find($ser->preview);
                        if ($file) {
                            $ser->previewName = $file->name;
                            $prod->img = $ser->previewName;
                        } else {
                            $prod->img = $ser->img;
                        }
                    } else {
                        $prod->img = $ser->img;
                    }
                    if (empty($type)) {
                        $type = 0;
                    }
                    $links[$type][] = array($sid->link_type_2, $prod, 'product', 'Продукт');
                    break;
                case AdminController::SERIES_TABLE :
                    /** @var Series $ser */
                    $ser = $this->getSeriesTable()->find($sid->link_id_2);

                    if ($ser->preview) {
                        $fileTable = $this->sl->get('FilesTable');
                        $file = $fileTable->find($ser->preview);
                        if ($file) {
                            $ser->previewName = $file->name;
                        }
                    }

                    /** @var $subsec SubSection */
                    $subsec = $this->getSubSectionTable()->find($ser->subsection_id);
                    /** @var $sec Section */
                    $sec = $this->getSectionTable()->find($subsec->section_id);
                    $type = $sec->display_style;

                    if (empty($type)) {
                        $type = 0;
                    }
                    $links[$type][] = array($sid->link_type_2, $ser, 'series', 'Серия');
                    break;
                case AdminController::SUBSECTION_TABLE :
                    /** @var $subsec SubSection */
                    $subsec = $this->getSubsectionTable()->find($sid->link_id_2);
                    /** @var $sec Section */
                    $sec = $this->getSectionTable()->find($subsec->section_id);
                    $type = $sec->display_style;

                    if (empty($type)) {
                        $type = 0;
                    }
                    $links[$type][] = array($sid->link_type_2, $subsec, 'subsection', 'Подраздел');
                    break;
                case AdminController::SECTION_TABLE :
                    /** @var $sec Section */
                    $sec = $this->getSectionTable()->find($sid->link_id_2);
                    $type = $sec->display_style;

                    if (empty($type)) {
                        $type = 0;
                    }
                    $links[$type][] = array($sid->link_type_2, $sec, 'section', 'Раздел');
                    break;
            }
        }

        return $links;
    }
}