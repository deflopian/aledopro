<?php
namespace Catalog\Controller;

use Application\Controller\SampleAdminController;
use Application\Service\ApplicationService;
use Application\Service\MailService;
use Catalog\Mapper\CatalogMapper;
use Catalog\Mapper\LinkToLinkMapper;
use Catalog\Model\DopProd;
use Catalog\Model\EqualParams;
use Catalog\Model\FilterFieldTable;
use Catalog\Model\ProductInMarket;
use Catalog\Model\ProductParam;
use Catalog\Model\SeriesDim;
use Catalog\Model\SeriesDoc;
use Catalog\Model\StoS;
use Catalog\Service\CatalogService;
use Catalog\Model\SeriesImg;
use Info\Service\SeoService;
use Projects\Mapper\ProjectMapper;
use Solutions\Mapper\SolutionMapper;
use Zend\View\Helper\Json;

class AdminController extends SampleAdminController
{
    const SECTION_TABLE = 1;
    const SUBSECTION_TABLE = 2;
    const SERIES_TABLE = 3;
    const PRODUCT_TABLE = 4;
    const DOC_TABLE = 5;
    const POP_SERIES_TABLE = 6;
    const FILTER_BY_SERIES_TABLE = 7;
    const FILTER_PARAM_TABLE = 8;
    const PARAM_TO_SERIES_TABLE = 9;
    const SERIES_DOPPROD_GROUP_TABLE = 10;
    const PRODUCT_IN_MARKET_TABLE = 11;
    const SERIES_DOPPROD_TABLE = 12;
    const USERS_TABLE = 13;
    const PARTNER_GROUP_TABLE = 20;
    const SOLUTION_TABLE = 21;
    const PROJECT_TABLE = 22;
    const FILTER_FIELD_TABLE = 23;
    const DISCOUNT_TABLE = 24;
    const INFO_TABLE = 25;
    const DOCUMENT_TABLE = 26;
    const MAINPAGE_BLOCK_TABLE = 27;
    const MAINPAGE_BLOCK_IMAGE_TABLE = 28;
    const INFO_PARTNERS = 29;
    const INFO_SERVICES = 30;
    const DIM_TABLE = 31;
    const PRODUCT_MAIN_PARAM_TABLE = 32;
    const COMMERCIALS_TABLE = 33;
    const COMMERCIAL_ROOMS_TABLE = 34;
    const COMMERCIAL_PRODS_TABLE = 35;
    const ARTICLE_BLOCKS_TABLE = 36;
    const FOOTER_BLOCKS_TABLE = 37;
    const GEOBANNERS_TABLE = 38;
    const PRICE_REQUEST_TABLE = 39;
    const BY_CATALOG_HIDE_TABLE = 40;

    protected $tableImg = 'SeriesImgTable';
    protected $entityImgName = 'Catalog\Model\SeriesImg';
    protected $url = 'series';

    public function indexAction()
    {

        $sections = $this->getServiceLocator()->get('Catalog\Model\SectionTable')->fetchAll('order asc');
        $seoData = $this->getServiceLocator()->get('SeoDataTable')->find( SeoService::CATALOG_INDEX, 1 );


        /** @var FilterFieldTable $filterFieldTable */
        $filterFieldTable = $this->getServiceLocator()->get('FilterFieldTable');
        $paramsTable = $this->getServiceLocator()->get('Catalog\Model\ProductParamsTable');
        $filters = $filterFieldTable->fetchAll(0, AdminController::SECTION_TABLE, 0, "order ASC");
        $treeDataByLvl = array(1 => array());

        foreach ($filters as $filter) {
            /** @var ProductParam $field */
            $field = $paramsTable->find($filter->field_id);
            $filter->title = $field->title;
            $treeDataByLvl[23][] = $filter;
        }

        $treeDataByLvlJson = \Zend\Json\Json::encode($treeDataByLvl);

        return array(
            'sections' => $sections,
            'treeDataByLvlJson' => $treeDataByLvlJson,
            'seoData'       => $seoData,
			'isDomainZoneBy' => ApplicationService::isDomainZone('by')
        );
    }

    public function priceRequestAction()
    {
		$sl = $this->getServiceLocator();
		
        $priceRequestTable = $sl->get('PriceRequestTable');
        $sortedDiscounts = $priceRequestTable->fetchAllSorted();



        $cm = CatalogMapper::getInstance($this->getServiceLocator());
        $sections = $cm->fetchAllSections();
        $subsections = $cm->fetchAllSubsections(true);
        $series = $cm->fetchAllSeries(true);
        $products = $cm->fetchAllProducts(true);
        $treeDateByLvl = array();
        $treeHierarchy = array();
        foreach ($sections as $section) {
            $discVal = 0;
            $originalId = 0;
            if (isset($sortedDiscounts[\Catalog\Controller\AdminController::SECTION_TABLE][$section->id])) {
                $discVal = $sortedDiscounts[\Catalog\Controller\AdminController::SECTION_TABLE][$section->id]->is_requestable;
                $originalId = $sortedDiscounts[\Catalog\Controller\AdminController::SECTION_TABLE][$section->id]->id;
            }
            $treeDateByLvl[\Catalog\Controller\AdminController::SECTION_TABLE][$section->id] = array('title' => $section->title, 'discount' => $discVal, 'inherited' => 0, 'dId' => ($originalId > 0 ? $originalId : false));
            $treeHierarchy[$section->id] = array();
        }
        foreach ($subsections as $subsection) {
            if (isset($treeHierarchy[$subsection->section_id])) {
                $treeHierarchy[$subsection->section_id][$subsection->id] = array();
                $discVal = 0;
                $inherited = 0;
                $originalId = 0;
                if (isset($sortedDiscounts[\Catalog\Controller\AdminController::SUBSECTION_TABLE][$subsection->id])) {
                    $discVal = $sortedDiscounts[\Catalog\Controller\AdminController::SUBSECTION_TABLE][$subsection->id]->is_requestable;
                    $originalId = $sortedDiscounts[\Catalog\Controller\AdminController::SUBSECTION_TABLE][$subsection->id]->id;
                } else {
                    $discVal = $treeDateByLvl[\Catalog\Controller\AdminController::SECTION_TABLE][$subsection->section_id]['discount'];
                    $inherited = 1;
                }
                $treeDateByLvl[\Catalog\Controller\AdminController::SUBSECTION_TABLE][$subsection->id] = array('title' => $subsection->title, 'parentId' => $subsection->section_id, 'discount' => $discVal, 'inherited' => $inherited, 'dId' => ($originalId > 0 ? $originalId : false));
                if (!$inherited) {
                    $treeDateByLvl[\Catalog\Controller\AdminController::SECTION_TABLE][$subsection->section_id]['shown'] = true;
//                    $treeDateByLvl[\Catalog\Controller\AdminController::SECTION_TABLE][$subsection->section_id]['dId'] = $sortedDiscounts[\Catalog\Controller\AdminController::SUBSECTION_TABLE][$subsection->id]->discount;

                }
            }
        }
        foreach ($series as $oneser) {

            $subsection = $treeDateByLvl[\Catalog\Controller\AdminController::SUBSECTION_TABLE][$oneser->subsection_id];
            if ($subsection) {
                $sectionId = $subsection['parentId'];
                if (isset($treeHierarchy[$sectionId][$oneser->subsection_id])) {
                    $treeHierarchy[$sectionId][$oneser->subsection_id][$oneser->id] = array();
                    $discVal = 0;
                    $originalId = 0;
                    $inherited = 0;
                    if (isset($sortedDiscounts[\Catalog\Controller\AdminController::SERIES_TABLE][$oneser->id])) {
                        $discVal = $sortedDiscounts[\Catalog\Controller\AdminController::SERIES_TABLE][$oneser->id]->is_requestable;
                        $originalId = $sortedDiscounts[\Catalog\Controller\AdminController::SERIES_TABLE][$oneser->id]->id;
                    } else {
                        $discVal = $treeDateByLvl[\Catalog\Controller\AdminController::SUBSECTION_TABLE][$oneser->subsection_id]['discount'];
                        $inherited = 1;
                    }
                    $treeDateByLvl[\Catalog\Controller\AdminController::SERIES_TABLE][$oneser->id] = array('title' => $oneser->title, 'parentId' => $oneser->subsection_id, 'discount' => $discVal, 'inherited' => $inherited, 'dId' => ($originalId > 0 ? $originalId : false));
                    if (!$inherited) {
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
                    $discVal = 0;
                    $inherited = 0;
                    $originalId = 0;
                    if (isset($sortedDiscounts[\Catalog\Controller\AdminController::PRODUCT_TABLE][$product->id])) {
                        $discVal = $sortedDiscounts[\Catalog\Controller\AdminController::PRODUCT_TABLE][$product->id]->is_requestable;
                        $originalId = $sortedDiscounts[\Catalog\Controller\AdminController::PRODUCT_TABLE][$product->id]->id;
                    } else {
                        $discVal = $treeDateByLvl[\Catalog\Controller\AdminController::SERIES_TABLE][$product->series_id]['discount'];
                        $inherited = 1;
                    }

                    $treeDateByLvl[\Catalog\Controller\AdminController::PRODUCT_TABLE][$product->id] = array('title' => $product->title, 'parentId' => $product->series_id, 'discount' => $discVal, 'inherited' => $inherited, 'dId' => ($originalId > 0 ? $originalId : false));
                    if (!$inherited) {
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
		
		return array(
            'treeDateByLvlJson' => $treeDateByLvlJson,
			'treeHierarchyJson' => $treeHierarchyJson,
        );
    }
	
	public function catalogHideAction()
    {
		$sl = $this->getServiceLocator();
		
        $byCatalogHideTable = $sl->get('byCatalogHideTable');
        $sortedDiscounts = $byCatalogHideTable->fetchAllSorted();



        $cm = CatalogMapper::getInstance($this->getServiceLocator());
        $sections = $cm->fetchAllSections();
        $subsections = $cm->fetchAllSubsections(true);
        $series = $cm->fetchAllSeries(true);
        $products = $cm->fetchAllProducts(true);
        $treeDateByLvl = array();
        $treeHierarchy = array();
        foreach ($sections as $section) {
            $discVal = 0;
            $originalId = 0;
            if (isset($sortedDiscounts[\Catalog\Controller\AdminController::SECTION_TABLE][$section->id])) {
                $discVal = $sortedDiscounts[\Catalog\Controller\AdminController::SECTION_TABLE][$section->id]->is_hidden;
                $originalId = $sortedDiscounts[\Catalog\Controller\AdminController::SECTION_TABLE][$section->id]->id;
            }
            $treeDateByLvl[\Catalog\Controller\AdminController::SECTION_TABLE][$section->id] = array('title' => $section->title, 'discount' => $discVal, 'inherited' => 0, 'dId' => ($originalId > 0 ? $originalId : false), 'deleted' => $section->deleted);
            $treeHierarchy[$section->id] = array();
        }
        foreach ($subsections as $subsection) {
            if (isset($treeHierarchy[$subsection->section_id])) {
                $treeHierarchy[$subsection->section_id][$subsection->id] = array();
                $discVal = 0;
                $inherited = 0;
                $originalId = 0;
                if (isset($sortedDiscounts[\Catalog\Controller\AdminController::SUBSECTION_TABLE][$subsection->id])) {
                    $discVal = $sortedDiscounts[\Catalog\Controller\AdminController::SUBSECTION_TABLE][$subsection->id]->is_hidden;
                    $originalId = $sortedDiscounts[\Catalog\Controller\AdminController::SUBSECTION_TABLE][$subsection->id]->id;
                } else {
                    $discVal = $treeDateByLvl[\Catalog\Controller\AdminController::SECTION_TABLE][$subsection->section_id]['discount'];
                    $inherited = 1;
                }
                $treeDateByLvl[\Catalog\Controller\AdminController::SUBSECTION_TABLE][$subsection->id] = array('title' => $subsection->title, 'parentId' => $subsection->section_id, 'discount' => $discVal, 'inherited' => $inherited, 'dId' => ($originalId > 0 ? $originalId : false), 'deleted' => $subsection->deleted);
                if (!$inherited) {
                    $treeDateByLvl[\Catalog\Controller\AdminController::SECTION_TABLE][$subsection->section_id]['shown'] = true;
//                    $treeDateByLvl[\Catalog\Controller\AdminController::SECTION_TABLE][$subsection->section_id]['dId'] = $sortedDiscounts[\Catalog\Controller\AdminController::SUBSECTION_TABLE][$subsection->id]->discount;

                }
            }
        }
        foreach ($series as $oneser) {

            $subsection = $treeDateByLvl[\Catalog\Controller\AdminController::SUBSECTION_TABLE][$oneser->subsection_id];
            if ($subsection) {
                $sectionId = $subsection['parentId'];
                if (isset($treeHierarchy[$sectionId][$oneser->subsection_id])) {
                    $treeHierarchy[$sectionId][$oneser->subsection_id][$oneser->id] = array();
                    $discVal = 0;
                    $originalId = 0;
                    $inherited = 0;
                    if (isset($sortedDiscounts[\Catalog\Controller\AdminController::SERIES_TABLE][$oneser->id])) {
                        $discVal = $sortedDiscounts[\Catalog\Controller\AdminController::SERIES_TABLE][$oneser->id]->is_hidden;
                        $originalId = $sortedDiscounts[\Catalog\Controller\AdminController::SERIES_TABLE][$oneser->id]->id;
                    } else {
                        $discVal = $treeDateByLvl[\Catalog\Controller\AdminController::SUBSECTION_TABLE][$oneser->subsection_id]['discount'];
                        $inherited = 1;
                    }
                    $treeDateByLvl[\Catalog\Controller\AdminController::SERIES_TABLE][$oneser->id] = array('title' => $oneser->title, 'parentId' => $oneser->subsection_id, 'discount' => $discVal, 'inherited' => $inherited, 'dId' => ($originalId > 0 ? $originalId : false), 'deleted' => $oneser->deleted);
                    if (!$inherited) {
                        $treeDateByLvl[\Catalog\Controller\AdminController::SUBSECTION_TABLE][$oneser->subsection_id]['shown'] = true;
                        $treeDateByLvl[\Catalog\Controller\AdminController::SECTION_TABLE][$treeDateByLvl[\Catalog\Controller\AdminController::SUBSECTION_TABLE][$oneser->subsection_id]['parentId']]['shown'] = true;
                    }
                }
            }
        }

        $treeDateByLvlJson = \Zend\Json\Json::encode($treeDateByLvl);
        $treeHierarchyJson = \Zend\Json\Json::encode($treeHierarchy);
		
		return array(
            'treeDateByLvlJson' => $treeDateByLvlJson,
			'treeHierarchyJson' => $treeHierarchyJson,
        );
    }
	
    public function getTagsByTypeAction() {
		if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
        $request = $this->getRequest();
        $sl = $this->getServiceLocator();
        if ($request->isXmlHttpRequest()) {
            $type = $request->getPost('type', false);
            $results = array();
            switch ($type) {
                case self::SECTION_TABLE :
                    $mapper = CatalogMapper::getInstance($sl);
                    $results = $mapper->fetchAllSections();
                    break;
                case self::SUBSECTION_TABLE :
                    $mapper = CatalogMapper::getInstance($sl);
                    $results = $mapper->fetchAllSubsections();
                    break;
                case self::SERIES_TABLE :
                    $mapper = CatalogMapper::getInstance($sl);
                    $results = $mapper->fetchAllSeries();
                    break;
                case self::PRODUCT_TABLE :
                    $mapper = CatalogMapper::getInstance($sl);
                    $results = $mapper->fetchAllProducts();
                    break;
                case self::SOLUTION_TABLE :
                    $mapper = SolutionMapper::getInstance($sl);
                    $results = $mapper->fetchAllSolutions();
                    break;
                case self::PROJECT_TABLE :
                    $mapper = ProjectMapper::getInstance($sl);
                    $results = $mapper->fetchAllProjects();
                    break;
            }
            $response = $this->getResponse();
            $results = CatalogService::getSeriesAndTags($results);
            $response->setContent(\Zend\Json\Json::encode($results['tags']));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/catalog');
    }

    public function getMinByTypeAction() {
		if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
        $request = $this->getRequest();
        $sl = $this->getServiceLocator();
        if ($request->isXmlHttpRequest()) {
            $type = $request->getPost('type', false);
            $min = array();
            switch ($type) {
                case self::SECTION_TABLE :
                    $min = 1;
                    break;
                case self::SUBSECTION_TABLE :
                    $min = 1;
                    break;
                case self::SERIES_TABLE :
                    $min = 2;
                    break;
                case self::PRODUCT_TABLE :
                    $min = 3;
                    break;
                case self::SOLUTION_TABLE :
                    $min = 1;
                    break;
                case self::PROJECT_TABLE :
                    $min = 1;
                    break;
            }
            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode($min));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/catalog');
    }

    public function delEntityImgAction()
    {
        if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
		/** @var \Zend\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isXmlHttpRequest()) {
            $entityId = $request->getPost('id', false);
            $entityType = $request->getPost('type', false);

            $entity = CatalogService::getEntityByType($this->getServiceLocator(), $entityId, $entityType);
            $success = 0;
            if ($entity) {

                $folder = false;
                if ($entityType == self::SECTION_TABLE) {
                    $folder = 'section';
                } elseif ($entityType == self::SUBSECTION_TABLE) {
                    $folder = 'subsections';
                }

                $path = false;
                if (isset($entity->url)) {

                    $path = $entity->url;
                    $entity->url = '';
                } elseif (isset($entity->img)) {
                    $path = $entity->img;
                    $entity->img = '';
                }

                if ($path && $folder && file_exists($_SERVER['DOCUMENT_ROOT'] . '/images/'.$folder.'/'.$path)) {
                    $this->unlinkFile('/images/'.$folder.'/'.$path);
                }

                $result = CatalogService::saveEntityByType($this->getServiceLocator(), $entity, $entityType);

                if ($result) {
                    $success = 1;
                }
            }
            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode(array('success' => $success)));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/catalog');
    }

    public function deleteProdFileAction()
    {
        if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
		$request = $this->getRequest();
        $return = array();
        if ($this->getRequest()->isXmlHttpRequest()) {
            $id = $request->getPost('id', false);
            $return['success'] = 0;

            if($id){

                $table = $this->getServiceLocator()->get('Catalog\Model\ProductTable');
                $product = $table->find($id);

                if($product->file_custom){
                    if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/images/product_docs/'. $product->file_custom)) {
                        $this->unlinkFile('/images/product_docs/'. $product->file_custom);
                    }
                }

                $product->file_custom = "";
                $table->save($product);

                $return['success'] = 1;
                $pi_type = $request->getPost('page_info_type', false);
                if ($pi_type !== false) {
                    $this->updateLastModified($pi_type);
                }

            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode( $return ));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/catalog');
    }

    public function sortparamAction() {
		if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
        $request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            $param = $request->getPost('param', false);
            $seriesId = $request->getPost('seriesId', false);
            $ordnung = $request->getPost('ordnung', false);
            $success = 0;

            if ($param && $seriesId) {
                $res = CatalogService::makesort($param, $seriesId, $ordnung, $this->getServiceLocator());
                if ($res) {
                    $success = 1;
                } else {
                    $success = 0;
                }
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode(array('success' => $success)));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/catalog');
    }

    public function sectionAction()
    {
        if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
		$id = (int) $this->params()->fromRoute('id', 0);
        if (!$id) {
            return $this->redirect()->toRoute('zfcadmin/catalog');
        }

        $sl = $this->getServiceLocator();
        $section = $sl->get('Catalog\Model\SectionTable')->find($id);
        $subsections = $sl->get('Catalog\Model\SubSectionTable')->fetchByCond('section_id', $id, 'order asc');
        $seriesTable = $sl->get('Catalog\Model\SeriesTable');

        $ssids = $allSeries = $tags = array();
        if($subsections){
            foreach($subsections as $ss){
                $ssids[] = $ss->id;
            }
            $allSeries = $seriesTable->fetchByCond('subsection_id', $ssids);
        }
        $allSubsections = $sl->get('Catalog\Model\SubSectionTable')->fetchAll('order asc');
        $data = CatalogService::getSeriesAndTags($allSubsections, $id);
        $seoData = $sl->get('SeoDataTable')->find( SeoService::CATALOG_SECTION, $id );
        if ($seoData === false) {
            return $this->redirect()->toRoute('zfcadmin/catalog');
        }

        /** @var FilterFieldTable $filterFieldTable */
        $filterFieldTable = $this->getServiceLocator()->get('FilterFieldTable');
        $paramsTable = $this->getServiceLocator()->get('Catalog\Model\ProductParamsTable');
        $filters = $filterFieldTable->fetchAll($id, AdminController::SECTION_TABLE, 0, "order ASC");
        $treeDataByLvl = array(1 => array());

        foreach ($filters as $filter) {
            /** @var ProductParam $field */
            $field = $paramsTable->find($filter->field_id);

            $filter->title = $field->title;
            $treeDataByLvl[23][] = $filter;
        }

        $treeDateByLvlJson = \Zend\Json\Json::encode($treeDataByLvl);


//        $productParamsByLvl = array(1 => array());
//
//        $allParams = $paramsTable->fetchAll();
//        foreach ($allParams as $oneParam) {
//
//
//            $productParamsByLvl[self::PRODUCT_MAIN_PARAM_TABLE][] = $oneParam;
//        }
//        foreach ($mainParamsList as $mainParams) {
//            /** @var ProductParam $field */
//            $field = $paramsTable->find($mainParams->field_id);
//            $mainParams->title = $field->title;
//            $productParamsByLvl[23][] = $filter;
//        }


//        $productParamsByLvlJson = \Zend\Json\Json::encode($productParamsByLvl);


        return array(
            'section'       => $section,
            'treeDataByLvlJson' => $treeDateByLvlJson,
//            'productParamsByLvlJson' => $productParamsByLvlJson,
            'subsections'   => $subsections,
            'tags' => \Zend\Json\Json::encode($data['tags']),
            'seoData'       => $seoData,
            'links'       => LinkToLinkMapper::getInstance($sl)->fetchAll($id, self::SECTION_TABLE),
        );
    }

    public function marketAction()
    {
        if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
		$sl = $this->getServiceLocator();
        $products = $sl->get('Catalog\Model\ProductInMarketTable')->fetchAll('order ASC');
        $allProds = ApplicationService::makeIdArrayFromObjectArray($sl->get('Catalog\Model\ProductTable')->fetchByConds(array(), array('series_id' => 0)));

        foreach ($products as &$oneProd) {
            if (array_key_exists($oneProd->id, $allProds)) {
                $oneProd->title = $allProds[$oneProd->id]->title;
            } else {
                unset($oneProd);
            }
        }

        $data = CatalogService::getSeriesAndTags($allProds); // там просто сортировка, переименовывать лень
        $tags = \Zend\Json\Json::encode($data['tags']);

        return array(
            'products' => $products,
            'tags' => $tags,
        );
    }

    public function subsectionAction()
    {
        if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
		$id = (int) $this->params()->fromRoute('id', 0);
        if (!$id) {
            return $this->redirect()->toRoute('zfcadmin/catalog');
        }

        $sl = $this->getServiceLocator();
        $subsection = $sl->get('Catalog\Model\SubSectionTable')->find($id);
        if ($subsection === false) {
            return $this->redirect()->toRoute('zfcadmin/catalog');
        }
        $section = $sl->get('Catalog\Model\SectionTable')->find($subsection->section_id);

//        $series = $sl->get('Catalog\Model\SeriesTable')->fetchByCond('subsection_id', $id, 'order asc');
        // todome: обсудить: может стоит выводить в качестве тегов только серии без subsection_id

        $allSeries = $sl->get('Catalog\Model\SeriesTable')->fetchAll('order asc');
        $data = CatalogService::getSeriesAndTags($allSeries, $id);

        $seoData = $sl->get('SeoDataTable')->find( SeoService::CATALOG_SUBSECTION, $id );

        if ($seoData === false) {
            return $this->redirect()->toRoute('zfcadmin/catalog');
        }

        /** @var FilterFieldTable $filterFieldTable */
        $filterFieldTable = $this->getServiceLocator()->get('FilterFieldTable');
        $paramsTable = $this->getServiceLocator()->get('Catalog\Model\ProductParamsTable');
        $filters = $filterFieldTable->fetchAll($id, AdminController::SUBSECTION_TABLE, $subsection->section_id, "order ASC");
        $treeDataByLvl = array(1 => array());

        foreach ($filters as $filter) {
            /** @var ProductParam $field */
            $field = $paramsTable->find($filter->field_id);
            $filter->title = $field->title;
            $treeDataByLvl[23][] = $filter;
        }


        $treeDateByLvlJson = \Zend\Json\Json::encode($treeDataByLvl);

        return array(
            'section' => $section,
            'subsection' => $subsection,
            'treeDataByLvlJson' => $treeDateByLvlJson,
            'series' => $data['series'],
            'tags' => \Zend\Json\Json::encode($data['tags']),
            'seoData'       => $seoData,
            'links'       => LinkToLinkMapper::getInstance($sl)->fetchAll($id, self::SUBSECTION_TABLE),
        );
    }

    public function seriesAction()
    {
        if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
		$id = (int) $this->params()->fromRoute('id', 0);
        if (!$id) {
            return $this->redirect()->toRoute('zfcadmin/catalog');
        }

        $sl = $this->getServiceLocator();
        $seriesTable = $sl->get('Catalog\Model\SeriesTable');
        $productTable = $sl->get('Catalog\Model\ProductTable');
        $series = $seriesTable->find($id);
        if ($series === false) {
            return $this->redirect()->toRoute('zfcadmin/catalog');
        }

        $subsection = $section = array();
        if($series->subsection_id){
            $subsection = $sl->get('Catalog\Model\SubSectionTable')->find($series->subsection_id);
            if($subsection){
                $section = $sl->get('Catalog\Model\SectionTable')->find($subsection->section_id);
            }
        }

        $products = $productTable->fetchByCond('series_id', $id, 'order asc');

        $products = CatalogService::changeIntParamsWithStringVals($products, $sl->get('Catalog\Model\FilterParamTable'));

        $imgs = $sl->get('Catalog\Model\SeriesImgTable')->fetchByCond('parent_id', $id, 'order asc');

        $relatedSeriesIds = $sl->get('Catalog\Model\StoSTable')->find($id, self::SERIES_TABLE);
        $relatedSeries = array();
        $relatedProds = array();
        foreach($relatedSeriesIds as $sid){
            if ($sid[1] ==  self::SERIES_TABLE) {
                $relatedSeries[] = $seriesTable->find($sid[0]);
            } elseif ($sid[1] ==  self::PRODUCT_TABLE) {
                $relatedProds[] = $productTable->find($sid[0]);
            }

        }

        //todome
        $allSeries = $seriesTable->fetchAll();
        array_unshift($relatedSeriesIds, $id);
        $data = CatalogService::getSeriesAndTags($allSeries, 0, $relatedSeriesIds);

        $allProds = ApplicationService::makeIdArrayFromObjectArray($sl->get('Catalog\Model\ProductTable')->fetchAll());
        $data = array_merge_recursive($data, CatalogService::getSeriesAndTags($allProds)); // там просто сортировка, переименовывать лень

        $docs = $sl->get('Catalog\Model\SeriesDocTable')->fetchByCond('parent_id', $id, 'order asc');
        $dims = $sl->get('Catalog\Model\SeriesDimTable')->fetchByCond('parent_id', $id, 'order asc');
        $dopProdGroups = $sl->get('Catalog\Model\DopProdGroupTable')->fetchByCond('series_id', $id, 'order asc');

        $seoData = $sl->get('SeoDataTable')->find( SeoService::CATALOG_SERIES, $id );

        $equalParameters = CatalogService::findEqualParams($products);

        $params = $sl->get('Catalog\Model\ProductParamsTable')->fetchAll();
        $shownEqualParams = $sl->get('Catalog\Model\EqualParamsTable')->find($id);

        if ($series->preview) {
            $fileTable = $sl->get('FilesTable');
            $file = $fileTable->find($series->preview);
            if ($file) {
                $series->previewName = $file->name;
            }
        }
        $productsJson = \Zend\Json\Json::encode($products);
        return array(
            'series' => $series,
            'section' => $section,
            'subsection' => $subsection,
            'products' => $products,
            'imgs' => $imgs,
            'relatedSeries' => $relatedSeries,
            'productsJson' => $productsJson,
            'relatedProds' => $relatedProds,
            'docs' => $docs,
            'dims' => $dims,
            'dopProdGroups' => $dopProdGroups,
            'tags' => \Zend\Json\Json::encode($data['tags']),
            'seoData'       => $seoData,
            'equalParameters'        => $equalParameters,
            'params'                 => $params,
            'shownEqualParams'       => $shownEqualParams ? $shownEqualParams : array(),
            'links'       => LinkToLinkMapper::getInstance($sl)->fetchAll($id, self::SERIES_TABLE),
        );
    }

    public function productAction()
    {
        if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
		$id = (int) $this->params()->fromRoute('id', 0);
        if (!$id) {
            return $this->redirect()->toRoute('zfcadmin/catalog');
        }

        $sl = $this->getServiceLocator();
        $product = $sl->get('Catalog\Model\ProductTable')->find($id);
        if ($product === false) {
            return $this->redirect()->toRoute('zfcadmin/catalog');
        }
        $series = $sl->get('Catalog\Model\SeriesTable')->find($product->series_id);
        $subsection = $sl->get('Catalog\Model\SubSectionTable')->find($series->subsection_id);
        $section = $sl->get('Catalog\Model\SectionTable')->find($subsection->section_id);

        $params = $sl->get('Catalog\Model\ProductParamsTable')->fetchAll();

        //превьюшка для товара
        $fileTable = $sl->get('FilesTable');
        $file = $fileTable->fetchByCond('uid', $id);
        $file = reset($file);

        if ($file) {

            $product->previewName = $file->name;
            $product->preview = $file->id;
        }

        return array(
            'product' => $product,
            'series' => $series,
            'section' => $section,
            'subsection' => $subsection,
            'uneditableParams' => CatalogService::getUnEditableParams(),
            'paramsDescr' => $params,
            'links'       => LinkToLinkMapper::getInstance($sl)->fetchAll($id, self::PRODUCT_TABLE),
        );
    }

    public function addEntityAction()
    {
        if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
		$request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            $title = $request->getPost('title', false);
            $type = $request->getPost('type', false);
            $pi_type = $request->getPost('page_info_type', false);
            $parent_id = $request->getPost('parentId', false);

            $success = 0;

            if ($title && $type) {
                $data = array(
                    'title' => $title,
                    'parent_id' => $parent_id,
                    'deleted' => 0,
                    'sorted_by_user' => 0
                );

                $entity = CatalogService::createAndFillEntity($type, $data);

                $tableName = CatalogService::getTableName($type);

                if($entity && $tableName){
                    $newId = $this->getServiceLocator()->get('Catalog\Model\\'. $tableName )->save($entity);
                    if ($pi_type !== false) {
                        $this->updateLastModified($pi_type);
                    }
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
        return $this->redirect()->toRoute('zfcadmin/catalog');
    }

    public function hideEntityAction() {
        if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
		$request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            $id = $request->getPost('id', false);
            $type = $request->getPost('type', false);
            $success = 0;

            if ($id && $type) {
                if ($type == self::SECTION_TABLE || $type == self::SUBSECTION_TABLE || $type == self::SERIES_TABLE) {
                    $tableName = CatalogService::getTableName($type);
                    if($tableName){
                        $entity = $this->getServiceLocator()->get('Catalog\Model\\'. $tableName )->find($id);
                        $entity->deleted = 1;
                        $this->getServiceLocator()->get('Catalog\Model\\'. $tableName )->save($entity);
                        $success = 1;
                    }
                }
            }

            $returnArr = array('success' => $success);
            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode($returnArr));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/catalog');
    }

    public function productInMarketAction()
    {
        if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
		$id = (int) $this->params()->fromRoute('id', 0);
        if (!$id) {
            return $this->redirect()->toRoute('zfcadmin/market');
        }

        $sl = $this->getServiceLocator();
        $prodInMarket = $sl->get('Catalog\Model\ProductInMarketTable')->find($id);
        $prod = $sl->get('Catalog\Model\ProductTable')->find($id);

        if (!$prodInMarket || !$prod) {
            return $this->redirect()->toRoute('zfcadmin/market');
        }
        $paramsDescr = array();

        $paramsDescr['bid'] = 'Стоимость одного клика по товару. Минимальное значение: 0.1 (10 центов)';
        $paramsDescr['purchase'] = 'Участвует ли товар в программе "Покупка на Маркете". Пока опция неактивна';

        $prodInMarket->title = $prod->title;
        return array('product' => $prodInMarket, 'paramsDescr' => $paramsDescr);
    }

    public function showEntityAction() {
		if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
        $request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            $id = $request->getPost('id', false);
            $type = $request->getPost('type', false);
            $success = 0;

            if ($id && $type) {
                if ($type == self::SECTION_TABLE || $type == self::SUBSECTION_TABLE || $type == self::SERIES_TABLE) {
                    $tableName = CatalogService::getTableName($type);
                    if($tableName){
                        $entity = $this->getServiceLocator()->get('Catalog\Model\\'. $tableName )->find($id);
                        $entity->deleted = 0;
                        $this->getServiceLocator()->get('Catalog\Model\\'. $tableName )->save($entity);
                        $success = 1;
                    }
                }
            }

            $returnArr = array('success' => $success);
            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode($returnArr));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/catalog');
    }
	
	public function sendNotificationAction() {
		if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
		$request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            $id = $request->getPost('id', false);
            $type = $request->getPost('type', false);
            $success = 0;
			
			if ($id && $type) {
				if ($type == self::SERIES_TABLE) {
                    $tableName = CatalogService::getTableName($type);
                    if ($tableName) {
						$entity = $this->getServiceLocator()->get('Catalog\Model\\'. $tableName)->find($id);
						
						list($email, $mailView) = MailService::prepareNotificationMailData($this->getServiceLocator(), $entity, MailService::NOTIFICATION_SERIES);
						MailService::sendMail($email, $mailView, "Новая серия добавлена на сайт!");
						
						$success = 1;
					}
				}
			}
			
			$response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode(array('success' => $success)));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/'.$this->url);
	}


    public function delEntityAction()
    {
        if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
		$request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            $id = $request->getPost('id', false);
            $type = $request->getPost('type', false);
            $success = 0;

            if ($id && $type) {
                $tableName = CatalogService::getTableName($type);
                if($tableName){
                    $this->getServiceLocator()->get('Catalog\Model\\'. $tableName )->del($id);
                    $success = 1;
                }
            }

            $returnArr = array('success' => $success);
            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode($returnArr));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/catalog');
    }

    public function changeOrderAction()
    {
        if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
		$request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            $order = $request->getPost('order', false);
            $type = $request->getPost('type', false);
            $isImg = $request->getPost('isImg', false);
            $parentId = $request->getPost('parentId', false);
            $success = 0;

            if ($order && ($type || $isImg)) {
                $tableName = $isImg ? $this->tableImg : CatalogService::getTableName($type);
                if($tableName){
                    $table = $this->getServiceLocator()->get('Catalog\Model\\'. $tableName );

                    foreach($order as $id=>$serialNum){
                        if ($type != self::SERIES_DOPPROD_TABLE) {
                            $entity = $table->find($id);
                        } else {
                            $entity = $table->find($id, $parentId);
                        }
                        if($entity){
                            $entity->order = $serialNum;

                            if ($type != self::SERIES_DOPPROD_TABLE) {
                                $entity->sorted_by_user = 1;
                                $table->save($entity);
                            } else {
                                $table->save($entity, true);
                            }



                            // если меняем порядок картинок в серии
                            // ставим нулевую (первую) картинку, как обложку для серии
                            if($isImg){
                                if($serialNum == 0){
                                    $seriesTable = $this->getServiceLocator()->get('Catalog\Model\SeriesTable');
                                    $series = $seriesTable->find($entity->parent_id);
                                    if (!$series->preview) {
                                        $series->img = $entity->url;
                                        $seriesTable->save($series);
                                    }

                                } else if($serialNum == 1){
                                    $seriesTable = $this->getServiceLocator()->get('Catalog\Model\SeriesTable');
                                    $series = $seriesTable->find($entity->parent_id);
                                    if (!$series->preview) {
                                        $series->img_gallery = $entity->url;
                                        $seriesTable->save($series);
                                    }
                                }
                            }
                        }
                    }
                    $success = 1;
                }
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode(array('success' => $success)));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/catalog');
    }

    public function updateEditableAction()
    {
        if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
		$request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            $post = $request->getPost()->toArray();
            $success = 0;

            if ($post['pk']) {
                $typeNid = explode('-',$post['pk']);
                $type = $typeNid[0];
                $data['id'] = $typeNid[1];
                $data[$post['name']] = $post['value'];

                $entity = CatalogService::createAndFillEntity($type, $data);
                $tableName = CatalogService::getTableName($type);

                if($entity && $tableName){
                    $this->getServiceLocator()->get('Catalog\Model\\'. $tableName )->save($entity);
                    $success = 1;
                }
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode(array('success' => $success)));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/catalog');
    }

    public function saveTagitAction()
    {
        if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
		$request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            $id = $request->getPost('id', false);
            $seriesIds = $request->getPost('tagitIds', false);
            $type = $request->getPost('type', false);

            $success = 0;

            if ($id && $seriesIds) {
                $sl = $this->getServiceLocator();
                if($type && $type == 'stos'){
                    $table = $sl->get('Catalog\Model\StoSTable');

                    foreach(explode(',', $seriesIds) as $sid){
                        //todome: просто пиздец костыль, из-за того, что на странице нельзя сделать несколько разных тагитов
                        $tagitCatalogType = ($sid < 20000) ? self::SERIES_TABLE : self::PRODUCT_TABLE;

                        $stos = new StoS();
                        $stos->exchangeArray(array(
                                'series_id_1' => $id,
                                'series_id_2' => $sid,
                                'catalog_type_1' => self::SERIES_TABLE,
                                'catalog_type_2' => $tagitCatalogType,
                            ));
                            $table->save($stos);
                        }


//                } else if($type && $type == 'popularSeries'){
//                    $table = $sl->get('Catalog\Model\PopularSeriesTable');
//                    foreach(explode(',', $seriesIds) as $sid){
//                        $popSer = new PopularSeries();
//                        $popSer->exchangeArray(array(
//                            'section_id' => $id,
//                            'series_id' => $sid
//                        ));
//                        // база(или пхп) почему то сохраняли с одним и тем же айди, хотя это поле - автоинкремент.
//                        if(isset($lastId)){
//                            $popSer->id = $lastId+1;
//                        }
//                        $lastId = $table->save($popSer);
//                    }
                } else if($type && $type == 'dopprods'){
                    $table = $sl->get('Catalog\Model\DopProdTable');
                    foreach(explode(',', $seriesIds) as $sid){
                        $dopprod = new DopProd();
                        $dopprod->exchangeArray(array(
                            'dopprod_group_id' => $id,
                            'product_id' => $sid
                        ));
                        $table->save($dopprod);
                    }
                } else if($type && $type == self::PRODUCT_IN_MARKET_TABLE){

                    /** @var \Catalog\Model\ProductInMarketTable $table */
                    $table = $sl->get('Catalog\Model\ProductInMarketTable');
                    foreach(explode(',', $seriesIds) as $sid){
                        $prod = new ProductInMarket();
                        $prod->exchangeArray(array(
                            'id' => $sid,
                            'bid' => 0,
                            'purchase' => 0,
                        ));
                        $table->save($prod);
                    }
                } else if($type && $type == self::SUBSECTION_TABLE){
                    $table = $sl->get('Catalog\Model\SubsectionTable');
                    foreach(explode(',', $seriesIds) as $seriesId){
                        $subsection = $table->find($seriesId);
                        if($subsection){
                            $subsection->section_id = $id;
                            $table->save($subsection);
                        }
                    }
                } else if($type && $type == self::USERS_TABLE){;
                    $table = $sl->get(CatalogService::getTableName(self::USERS_TABLE));

                    foreach(explode(',', $seriesIds) as $seriesId){
                        $user = $table->find($seriesId);
                        if($user){

                            $prevIsPartner = $user->is_partner;
                            $user->is_partner = 1;

                            $table->save($user);

                            if (!$prevIsPartner) {
                                list($email, $mailView) = MailService::prepareUserPartnershipMailData($this->getServiceLocator(), $user);
                                MailService::sendMail($email, $mailView, "Вы подключены к партнёрскому сервису на Aledo");
                            }
                        }
                    }
                } else {
                    $table = $sl->get('Catalog\Model\SeriesTable');
                    foreach(explode(',', $seriesIds) as $seriesId){
                        $series = $table->find($seriesId);
                        if($series){
                            $series->subsection_id = $id;
                            $table->save($series);
                        }
                    }
                }


                $success = 1;
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode(array('success' => $success)));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/catalog');
    }

    public function saveLinkTagitAction()
    {
        if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
		$request = $this->getRequest();


        if ($this->getRequest()->isXmlHttpRequest()) {
            $link_id_1 = $request->getPost('link_id_1', false);
            $link_id_2 = $request->getPost('link_id_2', false);
            $link_type_1 = $request->getPost('link_type_1', false);
            $link_type_2 = $request->getPost('link_type_2', false);

            $success = 0;
            if ($link_id_1!==false && $link_type_1 && $link_id_2!==false && $link_type_2) {
                $sl = $this->getServiceLocator();
                LinkToLinkMapper::getInstance($sl)->addLinks($link_id_1, $link_type_1, $link_id_2, $link_type_2);
                $success = 1;
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode(array('success' => $success)));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/catalog');
    }

    public function removeParentTagitAction()
    {
        if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
		$request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            $id = $request->getPost('id', false);
            $seriesId = $request->getPost('parentId', false);
            $type = $request->getPost('type', false);
            $success = 0;

            if ($id) {
                $sl = $this->getServiceLocator();
                if($type){
                    switch($type){
                        case'stos':
                            $table = $sl->get('Catalog\Model\StoSTable');
                            $table->del(array('series_id_1' => $seriesId, 'series_id_2' => $id));
                            $success = 1;
                            break;
                        case'dopprods':
                            $table = $sl->get('Catalog\Model\DopProdTable');
                            $table->del(array('dopprod_group_id' => $seriesId, 'product_id' => $id));
                            $success = 1;
                            break;
                        case self::SUBSECTION_TABLE:
                            $table = $sl->get('Catalog\Model\SubSectionTable');
                            $subsection = $table->find($id);
                            if($subsection){
                                $subsection->section_id = 0;
                                $table->save($subsection);
                                $success = 1;
                            }
                            break;
                        case self::USERS_TABLE:
                            $table = $sl->get(CatalogService::getTableName(self::USERS_TABLE));
                            $user = $table->find($id);
                            if($user){
                                $user->is_partner = 0;
                                $table->save($user);
                                $success = 1;
                            }
                            break;
                        case self::PRODUCT_IN_MARKET_TABLE:
                            $table = $sl->get('Catalog\Model\ProductInMarketTable');
                            $table->del($id);
                            $success = 1;

                            break;
                    }
                } else {
                    $table = $sl->get('Catalog\Model\SeriesTable');
                    $series = $table->find($id);
                    if($series){
                        $series->subsection_id = 0;
                        $table->save($series);
                        $success = 1;
                    }
                }
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode(array('success' => $success)));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/catalog');
    }

    public function removeParentLinkTagitAction()
    {
        if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
		$request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            $link_id_1 = $request->getPost('link_id_1', false);
            $link_id_2 = $request->getPost('link_id_2', false);
            $link_type_1 = $request->getPost('link_type_1', false);
            $link_type_2 = $request->getPost('link_type_2', false);
            $success = 0;

            if ($link_id_1!==false && $link_id_2 !==false && $link_type_1 && $link_type_2) {
                $sl = $this->getServiceLocator();
                LinkToLinkMapper::getInstance($sl)->removeLink($link_id_1, $link_type_1, $link_id_2, $link_type_2);
                $success = 1;
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode(array('success' => $success)));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/catalog');
    }

    public function addDocAction()
    {
        if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
		$request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            $title = $request->getPost('title', false);
            $parent_id = $request->getPost('parentId', false);

            $success = 0;

            if ($title && $parent_id) {
                $data = array(
                    'title' => $title,
                    'parent_id' => $parent_id
                );

                $entity = new SeriesDoc();
                $entity->exchangeArray($data);

                $newId = $this->getServiceLocator()->get('Catalog\Model\SeriesDocTable')->save($entity);
                $success = 1;
            }

            $returnArr = array('success' => $success);
            if($success){
                $returnArr['newId'] = $newId;
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode($returnArr));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/catalog');
    }

    public function addDimAction()
    {
        if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
		$request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            $title = $request->getPost('title', false);
            $parent_id = $request->getPost('parentId', false);

            $success = 0;

            if ($title && $parent_id) {
                $data = array(
                    'title' => $title,
                    'parent_id' => $parent_id
                );

                $entity = new SeriesDim();
                $entity->exchangeArray($data);

                $newId = $this->getServiceLocator()->get('Catalog\Model\SeriesDimTable')->save($entity);
                $success = 1;
            }

            $returnArr = array('success' => $success);
            if($success){
                $returnArr['newId'] = $newId;
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode($returnArr));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/catalog');
    }

    public function delDocAction()
    {
        if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
		$request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            $id = $request->getPost('id', false);
            $success = 0;

            if ($id) {
                $this->getServiceLocator()->get('Catalog\Model\SeriesDocTable')->del($id);
                $success = 1;
            }

            $returnArr = array('success' => $success);
            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode($returnArr));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/catalog');
    }

    public function delDimAction()
    {
        if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
		$request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            $id = $request->getPost('id', false);
            $success = 0;

            if ($id) {
                $this->getServiceLocator()->get('Catalog\Model\SeriesDimTable')->del($id);
                $success = 1;
            }

            $returnArr = array('success' => $success);
            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode($returnArr));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/catalog');
    }

    public function viewDocAction()
    {
        if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
		$id = (int) $this->params()->fromRoute('id', 0);
        if (!$id) {
            return $this->redirect()->toRoute('zfcadmin/catalog');
        }

        $sl = $this->getServiceLocator();
        $doc = $sl->get('Catalog\Model\SeriesDocTable')->find($id);
        if ($doc === false) {
            return $this->redirect()->toRoute('zfcadmin/catalog');
        }
        $series = $sl->get('Catalog\Model\SeriesTable')->find($doc->parent_id);
        $subsection = $sl->get('Catalog\Model\SubSectionTable')->find($series->subsection_id);
        $section = $sl->get('Catalog\Model\SectionTable')->find($subsection->section_id);

        return array(
            'doc' => $doc,
            'series' => $series,
            'section' => $section,
            'subsection' => $subsection,
        );
    }

    public function viewDimAction()
    {
        if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
		$id = (int) $this->params()->fromRoute('id', 0);
        if (!$id) {
            return $this->redirect()->toRoute('zfcadmin/catalog');
        }

        $sl = $this->getServiceLocator();
        $doc = $sl->get('Catalog\Model\SeriesDimTable')->find($id);
        if ($doc === false) {
            return $this->redirect()->toRoute('zfcadmin/catalog');
        }
        $series = $sl->get('Catalog\Model\SeriesTable')->find($doc->parent_id);
        $subsection = $sl->get('Catalog\Model\SubSectionTable')->find($series->subsection_id);
        $section = $sl->get('Catalog\Model\SectionTable')->find($subsection->section_id);

        return array(
            'doc' => $doc,
            'series' => $series,
            'section' => $section,
            'subsection' => $subsection,
        );
    }

    public function saveImgAction()
    {
        if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
		$request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            $id = $request->getPost('id', false);
            $type = $request->getPost('type', false);
            $return['success'] = 0;

            if($id){
                $data = $this->getRequest()->getFiles()->toArray();

                if($type=='section'){
                    $folder = 'section';
                    $table = $this->getServiceLocator()->get('Catalog\Model\SectionTable');
                } elseif($type=='subsection'){
                    $folder = 'subsections';

                    $table = $this->getServiceLocator()->get('Catalog\Model\SubSectionTable');

                } elseif($type=='dims') {
                    $folder = 'series_docs';
                    $table = $this->getServiceLocator()->get('Catalog\Model\SeriesDimTable');
                } else {
                    $folder = 'series_docs';
                    $table = $this->getServiceLocator()->get('Catalog\Model\SeriesDocTable');
                }

                $filename = $data['image']['name'];
                if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/images/'.$folder . '/'. $filename)) {
                    $this->unlinkFile('/images/' . $folder . '/'. $filename);
                }

                $adapter = new \Zend\File\Transfer\Adapter\Http();
                $adapter->setDestination($this->getFoler('/images/'.$folder));
                $adapter->setFilters(
                    array(
                        new \Zend\Filter\File\Rename(
                            array(
                                "target"    => $_SERVER['DOCUMENT_ROOT'] . '/images/' . $folder . '/'. $filename,
                                "randomize" => true,
                            )
                        )
                    )
                );
                if($adapter->receive($filename)){
                    $entity = $table->find($id);

                    if($entity->url){
                        if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/images/'.$folder.'/'.$entity->url)) {
                            $this->unlinkFile('/images/'.$folder.'/'.$entity->url);
                        }
                    }

                    if ($folder == 'series_docs') {
                        $entity->original_name = $filename;
                    }
                    $entity->url = $adapter->getFileName(null, false);
                    if ($type=='subsection') {
                        $parentSection = $this->getServiceLocator()->get('Catalog\Model\SectionTable')->find($entity->section_id);
                        if ($parentSection && $parentSection->display_style == 1) {
                            $lentsTable = $this->getServiceLocator()->get('Catalog\Model\SeriesTable');
                            $lents = $lentsTable->fetchByCond('subsection_id', $entity->id);
                            foreach ($lents as $lent) {
                                $lent->img = $entity->url;
                                if(copy($_SERVER['DOCUMENT_ROOT'] . '/images/'.$folder.'/'.$entity->url,
                                        $_SERVER['DOCUMENT_ROOT'] . '/images/series/'.$entity->url)) {
                                    $lentsTable->save($lent);
                                }

                            }
                        }
                    }
                    $table->save($entity);

                    $return['success'] = 1;
                    $return['imgs'] = array(
                        array(
                            'url' => $entity->url,
                        )
                    );
                }
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode( $return ));
            return $response;
        }

        return $this->redirect()->toRoute('zfcadmin');
    }

    public function saveProdFileAction()
    {
        if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
		$request = $this->getRequest();
        if ($this->getRequest()->isXmlHttpRequest()) {
            $id = $request->getPost('id', false);
            $return['success'] = 0;



            if($id){
                $data = $this->getRequest()->getFiles()->toArray();

                $filename = $data['image']['name'];
                if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/images/product_docs/'. $filename)) {
                    $this->unlinkFile('/images/product_docs/'. $filename);
                }
                $adapter = new \Zend\File\Transfer\Adapter\Http();
                $adapter->setDestination($this->getFoler('/images/product_docs'));
                $adapter->setFilters(
                    array(
                        new \Zend\Filter\File\Rename(
                            array(
                                "target"    => $_SERVER['DOCUMENT_ROOT'] . '/images/product_docs/'. $filename,
                                "randomize" => true,
                            )
                        )
                    )
                );
                if($adapter->receive($filename)){
                    $table = $this->getServiceLocator()->get('Catalog\Model\ProductTable');
                    $product = $table->find($id);

                    if($product->file_custom){
                        if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/images/product_docs/'. $product->file_custom)) {
                            $this->unlinkFile('/images/product_docs/'. $product->file_custom);
                        }
                    }

                    $product->file_custom = $adapter->getFileName(null, false);
                    $table->save($product);

                    $return['success'] = 1;
                    $return['imgs'] = array(
                        array(
                            'url' => $product->file_custom,
                        )
                    );
                }
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode( $return ));
            return $response;
        }

        return $this->redirect()->toRoute('zfcadmin');
    }

    public function viewDopProdAction()
    {
        if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
		$id = (int) $this->params()->fromRoute('id', 0);
        if (!$id) {
            return $this->redirect()->toRoute('zfcadmin/catalog');
        }

        $sl = $this->getServiceLocator();

        $dopProdGroup = $sl->get('Catalog\Model\DopProdGroupTable')->find($id);
        if ($dopProdGroup === false) {
            return $this->redirect()->toRoute('zfcadmin/catalog');
        }
        $series = $sl->get('Catalog\Model\SeriesTable')->find($dopProdGroup->series_id);
        $subsection = $sl->get('Catalog\Model\SubSectionTable')->find($series->subsection_id);
        $section = $sl->get('Catalog\Model\SectionTable')->find($subsection->section_id);

        $allProds = ApplicationService::makeIdArrayFromObjectArray($sl->get('Catalog\Model\ProductTable')->fetchAll());
        $data = CatalogService::getSeriesAndTags($allProds); // там просто сортировка, переименовывать лень
        $tags = \Zend\Json\Json::encode($data['tags']);

        $dopprods = $sl->get('Catalog\Model\DopProdTable')->fetchByCond('dopprod_group_id', $id, 'order ASC');
        $dopProducts = array();
        foreach($dopprods as $dp)
        {
            if (!isset($allProds[$dp->product_id])) continue;
			$dopProducts[] = $allProds[$dp->product_id];
        }

        $displayStyles = array(
            '-1' => '',
            CatalogService::DISPLAY_STYLE_DEFAULT => 'На основе серии (стандарт)',
            CatalogService::DISPLAY_STYLE_LENTS => 'Светодиодная лента',
            CatalogService::DISPLAY_STYLE_POWER => 'Источник питания',
            CatalogService::DISPLAY_STYLE_PROFILES => 'Профиль',
        );
        $tabsList = array(
            '-1' => '',
            '1' => 'Первая вкладка',
            '4' => 'Четвёртая вкладка'
        );

        return array(
            'dopProdGroup' => $dopProdGroup,
            'series' => $series,
            'section' => $section,
            'subsection' => $subsection,
            'tags' => $tags,
            'dopProducts' => $dopProducts,
            'displayStyles' => $displayStyles,
            'tabsList' => $tabsList,
        );
    }

    public function setEqualFieldsAction()
    {
        if (ApplicationService::isDomainZone('by')) {
			return $this->redirect()->toRoute('zfcadmin');
		}
		
		$request = $this->getRequest();
        if ($request->isXmlHttpRequest()) {
            $id = $request->getPost('id', false);
            $fields = $request->getPost('fields', array());
            $success = 0;

            if ($id) {
                $sl = $this->getServiceLocator();
                $data = new EqualParams();
                $data->exchangeArray(array(
                    'series_id' => $id,
                    'fields' => \Zend\Json\Json::encode($fields)
                ));

                $sl->get('Catalog\Model\EqualParamsTable')->save($data);
                $success = 1;
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode(array('success' => $success)));
            return $response;
        }
        return $this->redirect()->toRoute('zfcadmin/catalog');
    }
}