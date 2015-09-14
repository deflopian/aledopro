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
	
    public function addEntityAction()
    {
        $this->setData();
        $type = false;
        $request = $this->getRequest();
        if ($request->isXmlHttpRequest()) {
            $title = $request->getPost('title', false);
            $type = $request->getPost('page_info_type', false);
            $success = 0;

            if ($title) {
                $data = array(
					'title' => $title,
					'deleted' => 0
				);

                $entity = new $this->entityName;
                $entity->exchangeArray($data);

                $newId = $this->getServiceLocator()->get($this->table)->save($entity);

                if($newId){
                    $success = 1;
                }
            }

            $returnArr = array('success' => $success);
            if($success){
                $returnArr['newId'] = $newId;
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode($returnArr));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/'.$this->url);
    }
	
	public function hideEntityAction() {
		$this->setData();
		
        $request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            $id = $request->getPost('id', false);
            $type = $request->getPost('type', false);
            $success = 0;

            if ($id) {
				$entity = $this->getServiceLocator()->get($this->table)->find($id);
                $entity->deleted = 1;
                $this->getServiceLocator()->get($this->table)->save($entity);
                $success = 1;
            }

            $returnArr = array('success' => $success);
            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode($returnArr));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/'.$this->url);
    }
	
	public function showEntityAction() {
		$this->setData();
		
        $request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            $id = $request->getPost('id', false);
            $type = $request->getPost('type', false);
            $success = 0;

            if ($id) {
                $entity = $this->getServiceLocator()->get($this->table)->find($id);
                $entity->deleted = 0;
                $this->getServiceLocator()->get($this->table)->save($entity);
                $success = 1;
            }

            $returnArr = array('success' => $success);
            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode($returnArr));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/'.$this->url);
    }
}