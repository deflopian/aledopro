<?php
namespace Blog\Controller;

use Info\Service\SeoService;
use Zend\Mvc\Controller\AbstractActionController;
use Application\Service\ApplicationService;

class AdminController extends AbstractActionController
{
    public function indexAction()
    {
        if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
		$seoData = $this->getServiceLocator()->get('SeoDataTable')->find( SeoService::BLOG, 1 );
        return array(
            'seoData' => $seoData
        );
    }
}