<?php
namespace Contacts\Controller;

use Application\Controller\SampleAdminController;
use Application\Service\ApplicationService;
use Contacts\Model\Contact;
use Info\Service\SeoService;

class AdminController extends SampleAdminController
{
    protected $entityName = 'Contacts\Model\Contact';

    public function indexAction()
    {
        $this->setData();
		$contacts = $this->getServiceLocator()->get($this->table)->fetchAll('id ASC');
		
		$our_contacts = array();
		if (!ApplicationService::isDomainZone('by')) {
			$our_contacts = $this->getServiceLocator()->get("AledoContactsTable")->fetchAll('id ASC');
		}
		
        return array(
            'subsections' => $contacts,
			'our_contacts' => $our_contacts,
        );
    }
	
	public function viewAction()
    {
        $this->setData();
		$id = $this->params()->fromRoute('id', 0);
        if (!$id) {
            return $this->redirect()->toRoute('zfcadmin/'.$this->url);
        }		
        $contact = $this->getServiceLocator()->get($this->table)->find($id);
        return array(
            'contact' => $contact,
        );
    }
	
	public function addEntityAction()
    {
        $this->setData();

        $request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            $title = $request->getPost('title', false);
            $type = $request->getPost('type', false);
            $parentId = $request->getPost('parentId', false);

            $success = 0;

            if ($title) {
                $data = array(
					'title' => $title,
					'adress' => '',
					'work_time' => '',
					'phone' => '',
					'fax' => '',
					'add_phone_1' => '',
					'add_phone_2' => '',
					'mail' => '',
					'gps_zoom' => 17
				);

                if($parentId){
                    $data['parent_id'] = $parentId;
                }

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
}