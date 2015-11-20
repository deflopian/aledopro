<?php
namespace News\Controller;

use Application\Controller\SampleAdminController;
use Application\Service\ApplicationService;
use Info\Service\SeoService;
use News\Model\News;

class AdminController extends SampleAdminController
{
    protected $entityName = 'News\Model\News';

    public function indexAction()
    {
        if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
		$return = parent::indexAction();
        $return['seoData'] = $this->getServiceLocator()->get('SeoDataTable')->find( SeoService::NEWS, 1 );
        return $return;
    }

    public function viewAction()
    {
        if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
		$return = parent::viewAction();

        if(is_array($return)){
            $id = (int) $this->params()->fromRoute('id', 0);
            $seoData = $this->getServiceLocator()->get('SeoDataTable')->find( SeoService::NEWS, $id );
            $return['seoData'] = $seoData;
        }

        return $return;
    }
}