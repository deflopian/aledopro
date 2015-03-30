<?php
namespace Vacancies\Controller;

use Application\Controller\SampleAdminController;
use Info\Service\SeoService;
use Vacancies\Model\Vacancy;

class AdminController extends SampleAdminController
{
    protected $entityName = 'Vacancies\Model\Vacancy';
    private $requestTable = 'VacancyRequestTable';

    public function indexAction()
    {
        $return = parent::indexAction();
        if(is_array($return)){
            $vacancyRequests = $this->getServiceLocator()->get($this->requestTable)->fetchAll();
            $return['requests'] = $vacancyRequests;
            $return['seoData'] = $this->getServiceLocator()->get('SeoDataTable')->find( SeoService::VACANCIES, 1 );
        }

        return $return;
    }
}