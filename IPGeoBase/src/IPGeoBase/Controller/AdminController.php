<?php
namespace IPGeoBase\Controller;

use Application\Controller\SampleAdminController;
use Application\Service\ApplicationService;
use Info\Service\SeoService;
use IPGeoBase\Mapper\GeoBannerMapper;
use IPGeoBase\Service\GeoService;
use IPGeoBase\Model\GeoBanner;

class AdminController extends SampleAdminController
{
	protected $table = 'GeoBannersTable';
	
    public function indexAction()
    {
        if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
		/*$return = parent::indexAction();
        $return['seoData'] = $this->getServiceLocator()->get('SeoDataTable')->find( SeoService::IPGEOBASE, 1 );*/
        
		$geoBannerMapper = GeoBannerMapper::getInstance($this->getServiceLocator());
		$banners = $geoBannerMapper->fetchGeoBanners();
		
        $return = array(
            'entities' => $banners,
        );

        return $return;
    }

    public function viewAction()
    {
        if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
		$id = $this->params()->fromRoute('id', 0);
        
        /*$return = parent::indexAction();
        $return['seoData'] = $this->getServiceLocator()->get('SeoDataTable')->find( SeoService::IPGEOBASE, 1 );*/
        
		$geoBannerMapper = GeoBannerMapper::getInstance($this->getServiceLocator());
		$banner = $geoBannerMapper->fetchGeoBanner($id);
		
        $fileTable = $this->getServiceLocator()->get('FilesTable');
		
        if ($banner->img) {
            $file = $fileTable->find($banner->img);
			
            if ($file) {
                $imgFieldAndName = "imgName";
                $banner->$imgFieldAndName = $file->name;
            }
        }
		
		$countries = GeoService::getCountriesList($this->getServiceLocator());		
		$regions = GeoService::getRegionsList($this->getServiceLocator(), $banner->country_code);
		
        $return = array(
            'entity' => $banner,
			'countries' => $countries,
			'regions' => $regions,
        );

        return $return;
    }



    public function updateEditableAction()
    {
        if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
		$this->setData();

        $request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            $post = $request->getPost()->toArray();
            $success = 0;

            if ($post['pk']) {
                $pkData = explode('-',$post['pk']);
                $type = false;
                $data['id'] = $pkData[0];
                $data[$post['name']] = $post['value'];
				
				$entity = new GeoBanner();
                $entity->exchangeArray($data);
				
				if ($post['name'] == 'country_code') {
					$entity->region_code = '';
				}

                $this->getServiceLocator()->get($this->table)->save($entity);
                $success = 1;
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode(array('success' => $success)));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/'.$this->url);
    }

    public function addEntityAction()
    {
        if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
		$this->setData();

        $request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            $title = $request->getPost('title', false);

            $success = 0;
            $newId = 0;
            if ($title) {
                $data = array(
					'title' => $title,
					'region_code' => '',
					'deleted' => 0,
					);

                $table = $this->getServiceLocator()->get($this->table);

                $entity = new GeoBanner();
                $entity->exchangeArray($data);
                $newId = $table->save($entity);
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

    public function delEntityAction()
    {
        if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
		$this->setData();

        $request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            $id = $request->getPost('id', false);
            $success = 0;

            if ($id) {
                $table = $this->getServiceLocator()->get($this->table);

                $table->del($id);
                $success = 1;
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode(array('success' => $success)));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/'.$this->url);
    }
	
	public function hideEntityAction() {
		if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
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
		if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
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
	
    public function changeOrderAction() {
        if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
		$this->setData();

        $request = $this->getRequest();
        if ($request->isXmlHttpRequest()) {
            $order = $request->getPost('order', false);
            $isImg = $request->getPost('isImg', false);
            $success = 0;

            if ($order) {
                $tableName = $isImg ? $this->tableImg : $this->table;
                $table = $this->getServiceLocator()->get($tableName);
                foreach($order as $id=>$serialNum){
                    $entity = $table->find($id);
                    if($entity){
                        $entity->order = $serialNum;
                        $table->save($entity);
                    }
                }

                $success = 1;
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode(array('success' => $success)));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/'.$this->url);
    }
}