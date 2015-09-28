<?php
namespace IPGeoBase\Controller;

use Catalog\Controller\AdminController;
use Catalog\Mapper\LinkToLinkMapper;
use Catalog\Service\CatalogService;
use IPGeoBase\Model\Developer;
use Documents\Model\DocumentTable;
use Info\Service\SeoService;
use Zend\Mvc\Controller\AbstractActionController;
use IPGeoBase\Model\DeveloperTable;
use IPGeoBase\Model\DeveloperImgTable;
use IPGeoBase\Model\DeveloperMemberTable;
use IPGeoBase\Model\ProdToProjTable;
use Zend\View\Model\ViewModel;

class GeoBannerController extends AbstractActionController
{
    private $developerTable;
    private $imgTable;
    protected $pageInfoType = SeoService::DEVELOPERS;

    public function viewAction() {
        $ip = $_SERVER['REMOTE_ADDR']; // узнаем IP посетителя
        $sl = $this->getServiceLocator();

        $id = $this->params()->fromRoute('id', 0);


        $htmlViewPart = new ViewModel();
        $htmlViewPart->setVariables(array(

                'sl'        => $sl,
            ));
        return $htmlViewPart;
    }



    public function indexAction()
    {


        return array(
        );
    }
}