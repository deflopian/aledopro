<?php
namespace Services\Controller;

use Application\Controller\SampleAdminController;
use Info\Service\SeoService;
use Services\Model\Service;

class AdminController extends SampleAdminController
{
    protected $entityName = 'Services\Model\Service';

    public function indexAction()
    {
        $return = parent::indexAction();
        $return['seoData'] = $this->getServiceLocator()->get('SeoDataTable')->find( SeoService::SERVICES, 1 );
        return $return;
    }

}