<?php
namespace Clients\Controller;

use Application\Controller\SampleAdminController;
use Application\Service\ApplicationService;
use Info\Service\SeoService;
use Clients\Model\Client;

class AdminController extends SampleAdminController
{
    protected $entityName = 'Clients\Model\Client';

    public function indexAction()
    {
        if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
		$return = parent::indexAction();
        return $return;
    }
}