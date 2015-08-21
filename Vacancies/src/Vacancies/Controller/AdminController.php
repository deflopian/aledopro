<?php
namespace Vacancies\Controller;

use Application\Controller\SampleAdminController;
use Info\Service\SeoService;
use Vacancies\Model\Vacancy;

class AdminController extends SampleAdminController
{
    protected $entityName = 'Vacancies\Model\Vacancy';
	protected $table = "VacanciesTable";
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
	
	public function changeActivityStatusAction()
    {
        $this->setData();

        $request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            $val = $request->getPost('val', false);
            $id = $request->getPost('id', false);
            $success = 0;

            if ($id && $val !== false) {
                $table = $this->getServiceLocator()->get($this->table);
                $vacancy = $table->find($id);
                $vacancy->active = $val;
                $table->save($vacancy);

                $success = 1;
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode(array('success' => $success)));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/vacancies');
    }
}