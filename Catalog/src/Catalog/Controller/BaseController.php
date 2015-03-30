<?php
namespace Catalog\Controller;

use Catalog\Service\CatalogService;
use Zend\Db\ResultSet\ResultSet;
use Zend\Mvc\Controller\AbstractActionController;

use Catalog\Model\SectionTable;
use Catalog\Model\SubSectionTable;
use Catalog\Model\SeriesTable;
use Catalog\Model\ProductTable;
use Catalog\Model\PopularSeriesTable;
use Catalog\Model\SeriesParamsTable;
use Catalog\Model\FilterParamTable;
use Catalog\Model\FilterParam;
use Catalog\Model\ParamToSeriesTable;

class BaseController extends AbstractActionController
{
    protected  $sectionTable;
    protected $subSectionTable;
    protected $seriesTable;
    protected $productTable;
    protected $popularSeriesTable;
    protected $seriesParamsTable;
    protected $filterParamTable;
    protected $paramToSeriesTable;
    protected $productParamsTable;
    protected $productInMarketTable;

    public static $discreteFilterParams = array(
        'electro_power',
        'color_of_light',
        'construction',
        'ip_rating',
        'equipment_IP',
        'color_temperature',
        'socle',
        'bulb',
        'cri',
        'case_color',
    );

    public static $diapasonFilterParams = array(
        'wholesale_price',
        'power',
        'viewing_angle',
        'luminous_flux',
    );

    public static $activeDiapasonFilterParams = array(
        'wholesale_price',
        'power',
        'viewing_angle',
        'luminous_flux',
    );

    public static $activeDiscreteFilterParams = array(
//        'electro_power',
//        'color_of_light',
//        'case_color',
//        'construction',
//        'ip_rating',
        'electro_power',
        'color_of_light',
        'construction',
        'ip_rating',
        'equipment_IP',
        'color_temperature',
        'socle',
        'bulb',
        'cri',
        'case_color',
    );



    /**
     * @return SectionTable array|object
     */
    public function getSectionTable()
    {
        if (!$this->sectionTable) {
            $sm = $this->getServiceLocator();
            $this->sectionTable = $sm->get('Catalog\Model\SectionTable');
        }
        return $this->sectionTable;
    }

    /**
     * @return SectionTable array|object
     */
    public function getProductInMarketTable()
    {
        if (!$this->productInMarketTable) {
            $sm = $this->getServiceLocator();
            $this->productInMarketTable = $sm->get('Catalog\Model\ProductInMarketTable');
        }
        return $this->productInMarketTable;
    }

    /**
     * @return SubSectionTable array|object
     */
    public function getSubSectionTable()
    {
        if (!$this->subSectionTable) {
            $sm = $this->getServiceLocator();
            $this->subSectionTable = $sm->get('Catalog\Model\SubSectionTable');
        }
        return $this->subSectionTable;
    }

    /**
     * @return ParamToSeriesTable array|object
     */
    public function getParamToSeriesTable()
    {
        if (!$this->paramToSeriesTable) {
            $sm = $this->getServiceLocator();
            $this->paramToSeriesTable = $sm->get('Catalog\Model\ParamToSeriesTable');
        }
        return $this->paramToSeriesTable;
    }

    /**
     * @return SeriesTable array|object
     */
    public function getSeriesTable()
    {
        if (!$this->seriesTable) {
            $sm = $this->getServiceLocator();
            $this->seriesTable = $sm->get('Catalog\Model\SeriesTable');
        }
        return $this->seriesTable;
    }

    /**
     * @return ProductTable array|object
     */
    public function getProductTable()
    {
        if (!$this->productTable) {
            $sm = $this->getServiceLocator();
            $this->productTable = $sm->get('Catalog\Model\ProductTable');
        }
        return $this->productTable;
    }

    /**
     * @return PopularSeriesTable array|object
     */
    public function getPopularSeriesTable()
    {
        if (!$this->popularSeriesTable) {
            $sm = $this->getServiceLocator();
            $this->popularSeriesTable = $sm->get('Catalog\Model\PopularSeriesTable');
        }
        return $this->popularSeriesTable;
    }

    /**
     * @return SeriesParamsTable array|object
     */
    public function getSeriesParamsTable()
    {
        if (!$this->seriesParamsTable) {
            $sm = $this->getServiceLocator();
            $this->seriesParamsTable = $sm->get('Catalog\Model\SeriesParamsTable');
        }
        return $this->seriesParamsTable;
    }

    /**
     * @return SeriesParamsTable array|object
     */
    public function getProductParamsTable()
    {
        if (!$this->productParamsTable) {
            $sm = $this->getServiceLocator();
            $this->productParamsTable = $sm->get('Catalog\Model\ProductParamsTable');
        }
        return $this->productParamsTable;
    }

    /**
     * @return FilterParamTable array|object
     */
    public function getFilterParamTable()
    {
        if (!$this->filterParamTable) {
            $sm = $this->getServiceLocator();
            $this->filterParamTable = $sm->get('Catalog\Model\FilterParamTable');
        }
        return $this->filterParamTable;
    }
}