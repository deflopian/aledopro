<?php
namespace IPGeoBase\Controller;

use Zend\Mvc\Controller\AbstractActionController;

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
            'sl' => $sl,
        ));
        return $htmlViewPart;
    }



    public function indexAction()
    {
        return array(
        );
    }
}