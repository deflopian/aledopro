<?php
namespace News\Controller;

use Application\Controller\SampleAdminController;
use Info\Service\SeoService;
use News\Model\News;

class AdminController extends SampleAdminController
{
    protected $entityName = 'News\Model\News';

    public function indexAction()
    {
        $return = parent::indexAction();
        $return['seoData'] = $this->getServiceLocator()->get('SeoDataTable')->find( SeoService::NEWS, 1 );
        return $return;
    }

    public function viewAction()
    {
        $return = parent::viewAction();

        if(is_array($return)){
            $id = (int) $this->params()->fromRoute('id', 0);
            $seoData = $this->getServiceLocator()->get('SeoDataTable')->find( SeoService::NEWS, $id );
            $return['seoData'] = $seoData;
        }

        return $return;
    }
}