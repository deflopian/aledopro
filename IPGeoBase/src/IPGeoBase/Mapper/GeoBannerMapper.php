<?php
namespace IPGeoBase\Mapper;

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
use IPGeoBase\Service\GeoService;
use Zend\Di\ServiceLocatorInterface;
use Zend\Validator\File\ExcludeMimeType;
use Zend\View\Model\ViewModel;

use Catalog\Model\PopularSeries;
use Catalog\Model\Section;
use Catalog\Model\SeriesDoc;
use Catalog\Model\SubSection;
use Catalog\Model\Series;
use Catalog\Model\Product;

class GeoBannerMapper {
    private static $instance = null;
    /** @var \Zend\ServiceManager\ServiceLocatorInterface $sl  */
    private $sl = null;

    private function __construct($sl) {
        $this->sl = $sl;
    }

    /**
     * @return \IPGeoBase\Model\GeoBannerTable
     */
    private function getGeoBannerTable() {
        return $this->sl->get('GeoBannersTable');
    }

    /**
     * @param \Zend\ServiceManager\ServiceLocatorInterface $sl
     * @return GeoBannerMapper
     */
    public static function getInstance($sl) {
        if (is_null(self::$instance)) {
            self::$instance = new GeoBannerMapper($sl);
        }
        return self::$instance;
    }

    public function fetchGeoBanners() {
        return $this->getGeoBannerTable()->fetchAll();
    }

    public function get($ip) {
        $data = geoip_record_by_name($ip);
        $code = GeoService::$defaultCode;
        $region = GeoService::$defaultRegion;
        if (is_array($data) && !empty($data['country_code'])) {
            if (!empty($data['region'])) {
                $code = $data['region'];
            }

            $region = GeoService::getRegionName($data['country_code'], $code);

        }
        //var_dump($region);
        return $region;
    }
}