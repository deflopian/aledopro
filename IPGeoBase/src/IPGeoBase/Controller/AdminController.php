<?php
namespace IPGeoBase\Controller;

use Application\Controller\SampleAdminController;
use Application\Service\ApplicationService;
use Catalog\Mapper\CatalogMapper;
use Catalog\Service\CatalogService;
use Documents\Model\DocumentTable;
use Info\Service\SeoService;
use IPGeoBase\Mapper\GeoBannerMapper;
use IPGeoBase\Model\GeoBanner;
use IPGeoBase\Model\ProdToProd;
use IPGeoBase\Model\ProdToProj;
use IPGeoBase\Model\Developer;
use IPGeoBase\Model\DeveloperImg;
use IPGeoBase\Model\ProjToProj;

class AdminController extends SampleAdminController
{
    protected $entityName = 'IPGeoBase\Model\Developer';
    protected $entityImgName = 'IPGeoBase\Model\DeveloperImg';
    protected $memberEntityName = 'IPGeoBase\Model\DeveloperMember';

    public function indexAction()
    {
        $geoBannerMapper = GeoBannerMapper::getInstance($this->getServiceLocator());
        $location = $geoBannerMapper->get($_SERVER['REMOTE_ADDR']);

        $this->table = "GeoBannersTable";
        /*$return = parent::indexAction();
        $return['seoData'] = $this->getServiceLocator()->get('SeoDataTable')->find( SeoService::IPGEOBASE, 1 );*/
        $banners = $this->getServiceLocator()->get($this->table)->fetchAll();
        $return = array(
            'entities' => $banners,
            'location' => $location,
        );

        return $return;
    }

    public function viewAction()
    {
        $geoBannerMapper = GeoBannerMapper::getInstance($this->getServiceLocator());
        $location = $geoBannerMapper->get($_SERVER['REMOTE_ADDR']);
        $id = $this->params()->fromRoute('id', 0);
        $this->table = "GeoBannersTable";
        /*$return = parent::indexAction();
        $return['seoData'] = $this->getServiceLocator()->get('SeoDataTable')->find( SeoService::IPGEOBASE, 1 );*/
        $banner = $this->getServiceLocator()->get($this->table)->find($id);
        $fileTable = $this->getServiceLocator()->get('FilesTable');
        if ($banner->img) {
            $file = $fileTable->find($banner->img);
            if ($file) {
                $imgFieldAndName = "imgName";
                $banner->$imgFieldAndName = $file->name;
            }
        }
		$countries = array();
		$couns = $this->getServiceLocator()->get('GeoCountriesTable')->fetchByCond('id', 1);
		foreach ($couns as $item) {
			$countries[$item->code] = $item->title;
		}
		$regions = array();
		$regs = $this->getServiceLocator()->get('GeoRegionsTable')->fetchByCond('country_id', 1, 'id ASC');
		foreach ($regs as $item) {
			$regions[$item->code] = $item->title . ' (' . $item->code . ')';
		}
        $return = array(
            'entity' => $banner,
            'location' => $location,
			'countries' => $countries,
			'regions' => $regions,
        );

        return $return;
    }



    public function updateEditableAction()
    {
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

                $table = 'GeoBannersTable';

                $entity = new GeoBanner();
                $entity->exchangeArray($data);

                $this->getServiceLocator()->get($table)->save($entity);
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
        $this->setData();

        $request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            $title = $request->getPost('title', false);

            $success = 0;
            $newId = 0;
            if ($title) {
                $data = array('title' => $title);

                $table = $this->getServiceLocator()->get('GeoBannersTable');

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
        $this->setData();

        $request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            $id = $request->getPost('id', false);
            $success = 0;

            if ($id) {
                $table = $this->getServiceLocator()->get('GeoBannersTable');

                $table->del($id);
                $success = 1;
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode(array('success' => $success)));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/'.$this->url);
    }

}