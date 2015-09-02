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
            $return['seoData'] = $this->getServiceLocator()->get('SeoDataTable')->find( SeoService::JOB, 1 );
        }

        return $return;
    }
	
	public function viewAction()
    {
        $this->setData();

        $id = $this->params()->fromRoute('id', 0);
        if (!$id) {
            return $this->redirect()->toRoute('zfcadmin/'.$this->url);
        }
        if (is_numeric($id)) {
            $entitу = $this->getServiceLocator()->get($this->table)->find($id);
        } else {
            $entitу = $this->getServiceLocator()->get($this->table)->fetchByCond('alias', $id);
            $entitу = reset($entitу);
        }

        if ($entitу === false) {
            return $this->redirect()->toRoute('zfcadmin/'.$this->url);
        }
		
		$seoData = $this->getServiceLocator()->get('SeoDataTable')->find( SeoService::VACANCIES, $id );

        if ($this->imgFields) {
            $fileTable = $this->getServiceLocator()->get('FilesTable');
            foreach ($this->imgFields as $imgField) {
                if ($entitу->$imgField) {
                    $file = $fileTable->find($entitу->$imgField);
                    if ($file) {
                        $imgFieldAndName = $imgField . "_name";
                        $entitу->$imgFieldAndName = $file->name;
                    }
                }
            }
        }


        return array(
            'entity' => $entitу,
			'seoData' => $seoData,
        );
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