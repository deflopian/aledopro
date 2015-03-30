<?php
namespace Solutions\Mapper;

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

class SolutionMapper {
    private static $instance = null;
    /** @var \Zend\ServiceManager\ServiceLocatorInterface $sl  */
    private $sl = null;

    private function __construct($sl) {
        $this->sl = $sl;
    }

    /**
     * @return \Catalog\Model\SectionTable
     */
    private function getSolutionTable() {
        return $this->sl->get('SolutionsTable');
    }

    /**
     * @param \Zend\ServiceManager\ServiceLocatorInterface $sl
     * @return SolutionMapper
     */
    public static function getInstance($sl) {
        if (is_null(self::$instance)) {
            self::$instance = new SolutionMapper($sl);
        }
        return self::$instance;
    }

    public function fetchAllSolutions() {
        return $this->getSolutionTable()->fetchAll();
    }
}