<?php
namespace Projects\Mapper;

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

class ProjectMapper {
    private static $instance = null;
    /** @var \Zend\ServiceManager\ServiceLocatorInterface $sl  */
    private $sl = null;

    private function __construct($sl) {
        $this->sl = $sl;
    }

    /**
     * @return \Projects\Model\ProjectTable
     */
    private function getProjectTable() {
        return $this->sl->get('ProjectsTable');
    }

    /**
     * @param \Zend\ServiceManager\ServiceLocatorInterface $sl
     * @return ProjectMapper
     */
    public static function getInstance($sl) {
        if (is_null(self::$instance)) {
            self::$instance = new ProjectMapper($sl);
        }
        return self::$instance;
    }

    public function fetchAllProjects() {
        return $this->getProjectTable()->fetchAll();
    }
}