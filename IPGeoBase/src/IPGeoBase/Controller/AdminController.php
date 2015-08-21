<?php
namespace IPGeoBase\Controller;

use Application\Controller\SampleAdminController;
use Application\Service\ApplicationService;
use Catalog\Mapper\CatalogMapper;
use Catalog\Service\CatalogService;
use Documents\Model\DocumentTable;
use Info\Service\SeoService;
use IPGeoBase\Mapper\GeoBannerMapper;
use IPGeoBase\Model\ProdToProd;
use IPGeoBase\Model\ProdToProj;
use IPGeoBase\Model\Developer;
use IPGeoBase\Model\DeveloperImg;
use IPGeoBase\Model\ProjToProj;

class AdminController extends SampleAdminController
{
    protected $entityName = 'IPGeoBase\Model\Developer';
    protected $entityImgName = 'IPGeoBase\Model\DeveloperImg';
    protected $memberEntityName = 'IPGeoBase\Model\DeveloperMember';

    public function indexAction()
    {
        $geoBannerMapper = GeoBannerMapper::getInstance($this->getServiceLocator());
        $location = $geoBannerMapper->get($_SERVER['REMOTE_ADDR']);

        $this->table = "GeoBannersTable";
        /*$return = parent::indexAction();
        $return['seoData'] = $this->getServiceLocator()->get('SeoDataTable')->find( SeoService::IPGEOBASE, 1 );*/
        $banners = $this->getServiceLocator()->get($this->table)->fetchAll();
        $return = array(
            'entities' => $banners,
            'location' => $location,
        );

        return $return;
    }

    public function viewAction()
    {
        $return = parent::viewAction();

        return $return;
    }
}