<?php
namespace Offers\Controller;

use Application\Controller\SampleAdminController;
use Application\Service\ApplicationService;
use Catalog\Service\CatalogService;
use Info\Service\SeoService;
use Offers\Model\Offer;
use Catalog\Mapper\CatalogMapper;
use Offers\Model\OfferContent;

class AdminController extends SampleAdminController
{
    protected $entityName = 'Offers\Model\Offer';
    private $contentTable = 'OfferContentTable';

    public function indexAction()
    {
        if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
		$return = parent::indexAction();
        $return['seoData'] = $this->getServiceLocator()->get('SeoDataTable')->find( SeoService::OFFERS, 1 );

        $cm = CatalogMapper::getInstance($this->getServiceLocator());
        $sections = $cm->fetchAllSections();
        $subsections = $cm->fetchAllSubsections(true);
        $series = $cm->fetchAllSeries(true);
        $products = $cm->fetchAllProducts(true);
        $treeDateByLvl = array();
        $treeHierarchy = array();
        foreach ($sections as $section) {
            $treeDateByLvl[\Catalog\Controller\AdminController::SECTION_TABLE][$section->id] = array('title' => $section->title, 'is_offer' => $section->is_offer);
            $treeHierarchy[$section->id] = array();
        }
        foreach ($subsections as $subsection) {
            if (isset($treeHierarchy[$subsection->section_id])) {
                $treeHierarchy[$subsection->section_id][$subsection->id] = array();
                $treeDateByLvl[\Catalog\Controller\AdminController::SUBSECTION_TABLE][$subsection->id] = array('title' => $subsection->title, 'parentId' => $subsection->section_id, 'is_offer' => $subsection->is_offer);
                if ($subsection->is_offer > 0) {
                    $treeDateByLvl[\Catalog\Controller\AdminController::SECTION_TABLE][$subsection->section_id]['shown'] = true;
                }
            }
        }
        foreach ($series as $oneser) {

            $subsection = $treeDateByLvl[\Catalog\Controller\AdminController::SUBSECTION_TABLE][$oneser->subsection_id];
            if ($subsection) {
                $sectionId = $subsection['parentId'];
                if (isset($treeHierarchy[$sectionId][$oneser->subsection_id])) {
                    $treeHierarchy[$sectionId][$oneser->subsection_id][$oneser->id] = array();

                    $treeDateByLvl[\Catalog\Controller\AdminController::SERIES_TABLE][$oneser->id] = array('title' => $oneser->title, 'parentId' => $oneser->subsection_id, 'is_offer' => $oneser->is_offer);
                    if ($oneser->is_offer > 0) {
                        $treeDateByLvl[\Catalog\Controller\AdminController::SUBSECTION_TABLE][$oneser->subsection_id]['shown'] = true;
                        $treeDateByLvl[\Catalog\Controller\AdminController::SECTION_TABLE][$treeDateByLvl[\Catalog\Controller\AdminController::SUBSECTION_TABLE][$oneser->subsection_id]['parentId']]['shown'] = true;
                    }
                }
            }


        }
        foreach ($products as $product) {
            $series = $treeDateByLvl[\Catalog\Controller\AdminController::SERIES_TABLE][$product->series_id];
            $subsectionId = $series['parentId'];
            $subsection = $treeDateByLvl[\Catalog\Controller\AdminController::SUBSECTION_TABLE][$subsectionId];
            if ($subsection) {
                $sectionId = $subsection['parentId'];
                if (isset($treeHierarchy[$sectionId][$subsectionId][$product->series_id])) {
                    $treeHierarchy[$sectionId][$subsectionId][$product->series_id][$product->id] = $product->id;
                    $treeDateByLvl[\Catalog\Controller\AdminController::PRODUCT_TABLE][$product->id] = array('title' => $product->title, 'parentId' => $product->series_id, 'is_offer' => $product->is_offer);
                    if ($product->is_offer > 0) {
                        $treeDateByLvl[\Catalog\Controller\AdminController::SERIES_TABLE][$product->series_id]['shown'] = true;
                        $prevSer = $treeDateByLvl[\Catalog\Controller\AdminController::SERIES_TABLE][$product->series_id];
                        $treeDateByLvl[\Catalog\Controller\AdminController::SUBSECTION_TABLE][$prevSer['parentId']]['shown'] = true;
                        $prevSS = $treeDateByLvl[\Catalog\Controller\AdminController::SUBSECTION_TABLE][$prevSer['parentId']];
                        $treeDateByLvl[\Catalog\Controller\AdminController::SECTION_TABLE][$prevSS['parentId']]['shown'] = true;
                    }
                }
            }
        }

        $treeDateByLvlJson = \Zend\Json\Json::encode($treeDateByLvl);
        $treeHierarchyJson = \Zend\Json\Json::encode($treeHierarchy);

        $return['treeDateByLvlJson'] = $treeDateByLvlJson;

        $return['treeHierarchyJson'] = $treeHierarchyJson;
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

            $prodTable = $this->getServiceLocator()->get('Catalog\Model\ProductTable');
            $allProds = $prodTable->fetchAll();
            $data = CatalogService::getSeriesAndTags($allProds); // там просто сортировка, переименовывать лень
            $return['tags'] = \Zend\Json\Json::encode($data['tags']);

            $relatedProdsIds = $this->getServiceLocator()->get($this->contentTable)->fetchByCond('offer_id', $id);
            $relatedProds = array();
            if($relatedProdsIds){
                foreach($relatedProdsIds as $ptp){
                    $relatedProds[] = $prodTable->find($ptp->product_id);
                }
            }
            $return['relatedProds'] = $relatedProds;
        }

        return $return;
    }

    public function saveTagitAction()
    {
        if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
		$request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            $offerId = $request->getPost('id', false);
            $prodIds = $request->getPost('tagitIds', false);
            $success = 0;

            if ($offerId && $prodIds) {
                $table = $this->getServiceLocator()->get($this->contentTable);

                foreach(explode(',', $prodIds) as $pid){
                    $oc = new OfferContent();
                    $oc->exchangeArray(array(
                        'offer_id' => $offerId,
                        'product_id'  => $pid,
                    ));

                    $table->save($oc);
                }

                $success = 1;
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode(array('success' => $success)));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/offers');
    }



    public function makeOfferAction() {
        if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
		$request = $this->getRequest();
        $success = 0;
        if ($this->getRequest()->isXmlHttpRequest()) {
            $type = $request->getPost('type', false);
            $itemId = $request->getPost('itemId', false);
            $sl = $this->getServiceLocator();
            $item = CatalogService::getEntityByType($sl, $itemId, $type);

            if ($item) {
                $item->is_offer = 1;
                CatalogService::saveEntityByType($sl, $item, $type);
                $success = 1;
            }

            $returnArr = array('success' => $success);

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode($returnArr));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/'.$this->url);
    }

    public function removeOfferAction() {
        if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
		$request = $this->getRequest();
        $success = 0;
        if ($this->getRequest()->isXmlHttpRequest()) {
            $type = $request->getPost('type', false);
            $itemId = $request->getPost('itemId', false);
            $sl = $this->getServiceLocator();
            $item = CatalogService::getEntityByType($sl, $itemId, $type);

            if ($item) {
                $item->is_offer = 0;
                CatalogService::saveEntityByType($sl, $item, $type);
                $success = 1;
            }

            $returnArr = array('success' => $success);

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode($returnArr));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/'.$this->url);
    }


    public function removeParentTagitAction()
    {
        if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
		$request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            $offerId = $request->getPost('parentId', false);
            $prodId = $request->getPost('id', false);
            $success = 0;

            if ($offerId && $prodId) {
                $table = $this->getServiceLocator()->get($this->contentTable);
                $table->del(array('offer_id' => $offerId, 'product_id' => $prodId));
                $success = 1;
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode(array('success' => $success)));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/offers');
    }

    public function changeActivityStatusAction()
    {
        if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
		$this->setData();

        $request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            $val = $request->getPost('val', false);
            $id = $request->getPost('id', false);
            $success = 0;

            if ($id && $val) {
                $table = $this->getServiceLocator()->get($this->table);
                $offer = $table->find($id);
                $offer->active = $val;
                $table->save($offer);

                $success = 1;
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode(array('success' => $success)));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/offers');
    }
}