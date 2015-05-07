<?php
namespace Catalog\Controller;

use Application\Service\ApplicationService;
use BjyAuthorize\Guard\Controller;
use Catalog\Mapper\CatalogMapper;
use Catalog\Mapper\LinkToLinkMapper;
use Catalog\Model\FilterField;
use Catalog\Service\CatalogService;
use Catalog\Service\Hierarchy;
use Catalog\Service\ProductsAggregator;
use Catalog\Service\SeriesAggregator;
use Catalog\Service\SubsectionsAggregator;
use Discount\Model\DiscountTable;
use Info\Service\SeoService;
use Catalog\Controller\AdminController;
use User\Service\UserService;
use Zend\Db\ResultSet\ResultSet;
use Zend\Di\ServiceLocatorInterface;
use Zend\EventManager\EventManagerInterface;
use Zend\Mvc\MvcEvent;
use Zend\View\Model\ViewModel;

class CatalogController extends BaseController
{
    protected $pageInfoType = SeoService::CATALOG_INDEX;
    const POPUP_DEFAULT = 1;
    const POPUP_PROFILI = 2;
    public static  $admin = false;

    public function setEventManager(EventManagerInterface $events)
    {
        parent::setEventManager($events);

        $controller = $this;
        $events->attach('dispatch', function ($e) use ($controller) {
            $viewHelper = $controller->getServiceLocator()->get('viewhelpermanager');
            $viewHelper->get('headlink')
                ->prependStylesheet('/Content/css/libs/jquery.nouislider.css')
                ->prependStylesheet('/Content/css/catalog.css')
                ->prependStylesheet('/Content/css/main1.css');

            $viewHelper->get('headscript')
                ->prependFile('/js/libs/jquery.nouislider.js')
                ->prependFile('/js/libs/ZeroClipboard.js')
                ->prependFile('/js/catalog.js');
        }, 100); // execute before executing action logic
    }

    public function onDispatch(MvcEvent $e)
    {

        if ($this->zfcUserAuthentication()->hasIdentity()) {

            $user = $this->zfcUserAuthentication()->getIdentity();
            $roleLinker = $this->getServiceLocator()->get('RoleLinkerTable')->find($user->getId(), 'user_id');
            if ($roleLinker->role_id == 'manager' || $roleLinker->role_id  == 'admin') {
                $this->layout()->setVariable('isManager', true);
                if ($roleLinker->role_id  == 'admin') {
                    self::$admin = true;
                }

                if ($user->getGodModeId()) {

                    $ownedUser = $this->getServiceLocator()->get('UserTable')->find($user->getGodModeId());

                    if ($ownedUser) {
                        $this->layout()->setVariable('godModeId', $ownedUser->user_id);
                        $this->layout()->setVariable('godModeName', ($ownedUser->alias ? $ownedUser->alias : $ownedUser->userNumame));
                        $this->layout()->setVariable('godModePartnerGroupId', $ownedUser->partner_group);
                        UserService::$isManager = true;
                        UserService::$godModeId = $ownedUser->user_id;
                        UserService::$godModeName = ($ownedUser->alias ? $ownedUser->alias : $ownedUser->username);
                        UserService::$godModePartnerGroupId = $ownedUser->partner_group;
                    }
                }
            }

        }
        parent::onDispatch($e);
    }

    public function indexAction()
    {
        if (self::$admin) {
            $sections = $this->getSectionTable()->fetchAll('order asc');
            $subsections = $this->getSubSectionTable()->fetchAll('order asc');
        } else {
            $sections = $this->getSectionTable()->fetchByCond('deleted', '0', 'order asc');
            $subsections = $this->getSubSectionTable()->fetchByCond('deleted', '0', 'order asc');
        }


        $foundSeries = array();
        foreach($sections as $sec){
            if ($sec->display_style == 2 || $sec->display_style == 1) {
                $foundSeries[] = $sec->id;
            }
        }

        $wantedSubsectionsIds = array();

        $subsecs = array();
        foreach($subsections as $sub){
            if (in_array($sub->section_id, $foundSeries)) {
                $wantedSubsectionsIds[] = $sub->id;
            }
            $subsecs[$sub->section_id][] = $sub;
        }
        if (self::$admin) {
            $series = $this->getSeriesTable()->fetchByCond('subsection_id', $wantedSubsectionsIds, 'order asc');
        } else {
            $series = $this->getSeriesTable()->fetchByConds(array('subsection_id' => $wantedSubsectionsIds, 'deleted' => 0), array(), 'order asc');
        }
        $wantedSeries = array();
        $fileTable = $this->getServiceLocator()->get('FilesTable');
        foreach($series as $ser){
            if ($ser->preview) {
                $file = $fileTable->find($ser->preview);
                if ($file) {
                    $ser->previewName = $file->name;
                }
            }
            $wantedSeries[$ser->subsection_id][] = $ser;
        }


        $seoData = $this->getServiceLocator()->get('SeoDataTable')->find( SeoService::CATALOG_INDEX, 1 );
        $this->layout()->seoData = $seoData;

        $this->layout()->pageTitle = 'Каталог';

        return array(
            'seoData' => $seoData,
            'sections' => $sections,
            'subsections' => $subsecs,
            'series' => $wantedSeries,
        );
    }

    public function productsAction()
    {
        $id = $this->params()->fromRoute('id', 0);

        if (!$id) {
            return $this->redirect()->toRoute('catalog');
        }
        $sl = $this->getServiceLocator();

        /** @var \Catalog\Model\Section $section */
        $product = $this->getProductTable()->find($id);
        if (!$product || $product->series_id == 0 || empty($product->type)) $this->redirect()->toRoute('catalog');
        $series = $this->getSeriesTable()->find($product->series_id);
        if (!$series) $this->redirect()->toRoute('catalog');
        $subsection = $this->getSubsectionTable()->find($series->subsection_id);
        if (!$subsection) $this->redirect()->toRoute('catalog');
        $section = $this->getSectionTable()->find($subsection->section_id);
        if (!$section) $this->redirect()->toRoute('catalog');

        if ($series->preview) {
            $fileTable = $this->getServiceLocator()->get('FilesTable');
            $file = $fileTable->find($series->preview);
            if ($file) {
                $series->previewName = $file->name;
            }
        }

        $contacts = $sl->get('ContactsTable')->find(1);

        return array(
            'series' => $series,
            'product' => $product,
            'isDriver' => ($product->type == 'Драйвер тока'),
            'type' => $section->display_style,
            'contacts' => $contacts,
        );
    }

    public function renderSectionDefaultAction() {
        $id = $this->params()->fromRoute('id', 0);
        $view = $this->prepareDividedList($id);
        $view->setTemplate('catalog/catalog/section');
        return $view;
    }

    public function renderSectionProfilesAction() {
        $id = $this->params()->fromRoute('id', 0);
        $view = $this->prepareDividedList($id);
        $view->setTemplate('catalog/catalog/section_profili');
        return $view;
    }

    private function prepareDividedList($id) {
        $sl = $this->getServiceLocator();
        $cm = CatalogMapper::getInstance($sl);

        //раздел со всеми подразделами и сериями для подразделов
        $section = $cm->getSection($id, true, true);
        $view = new ViewModel();
        $view->setVariable('section', $section);
        $view->setVariable('subsections', SubsectionsAggregator::getInstance()->getSubsections($section->id));
        $view->setVariable('seAgg', SeriesAggregator::getInstance());
        $links = LinkToLinkMapper::getInstance($sl)->fetchCatalogSortedBySectionType($id, AdminController::SECTION_TABLE);
        $view->setVariable('links', $links);
        return $view;
    }

    private function preparePlaneProductList($id) {
        $view = new ViewModel();
        $sl = $this->getServiceLocator();
        $cm = CatalogMapper::getInstance($sl);

        //раздел со всеми подразделами и сериями для подразделов
        $section = $cm->getSection($id, true, true, true);

        $return = array(
            'section' => $section,
            'subsections' => SubsectionsAggregator::getInstance()->getSubsections($section->id),
        );

        $hierarchies = Hierarchy::getInstance()->getProducts();

        if ($this->zfcUserAuthentication()->hasIdentity()) {
            $identity = $this->zfcUserAuthentication()->getIdentity();
            $isManager = UserService::$isManager;
            $godModeId = UserService::$godModeId;
            $godModeName = UserService::$godModeName;


            if ($isManager
                && $godModeId
                && $godModeName) {
                $godModePartnerGroupId = UserService::$godModePartnerGroupId;
                /** @var DiscountTable $discountsTable */
                $discountsTable = $sl->get('DiscountTable');
                $discounts = $discountsTable->fetchByUserId($godModeId, $godModePartnerGroupId, false, 0, $sl);

            } else {
                $discounts = $sl->get('DiscountTable')->fetchByUserId($identity->getId(), $identity->getPartnerGroup(), false, 0, $sl);
            }

            $view->setVariable('user', $identity);
            $view->setVariable('discounts', $discounts);
            $view->setVariable('hierarchies', $hierarchies);
        }

        $return['offeredIds'] = $sl->get('OfferContentTable')->fetchAll('', true);
        $return['allSeries'] = SeriesAggregator::getInstance()->getSeries();

        $return['pAgg'] = ProductsAggregator::getInstance();
        $return['params'] = $sl->get('Catalog\Model\ProductParamsTable')->fetchAll(); //только для питание - перенести
        $links = LinkToLinkMapper::getInstance($sl)->fetchCatalogSortedBySectionType($id, AdminController::SECTION_TABLE);
        $view->setVariable('links', $links);
        $view->setVariables($return);
        return $view;
    }

    public function renderSectionLentsAction() {
        $id = $this->params()->fromRoute('id', 0);
        $view = $this->preparePlaneProductList($id);
        $view->setTemplate('catalog/catalog/section_lenta');

        return $view;
    }

    public function renderSectionPowerElementsAction() {
        $id = $this->params()->fromRoute('id', 0);
        $view = $this->preparePlaneProductList($id);
        $view->setVariable('seAgg', SeriesAggregator::getInstance());
        $view->setTemplate('catalog/catalog/section_pitanie');

        return $view;
    }

    public function sectionAction()
    {
        $id = $this->params()->fromRoute('id', 0);

        if (!$id) {
            return $this->redirect()->toRoute('catalog');
        }
        $sl = $this->getServiceLocator();
        $cm = CatalogMapper::getInstance($sl);

        $section = $cm->getSection($id);
        if (!$section) return $this->redirect()->toRoute('catalog');

        switch($section->display_style) {
            case CatalogService::DISPLAY_STYLE_DEFAULT :
                $view = $this->forward()->dispatch('catalog', array('action'=>'renderSectionDefault', 'id' => $id));
                break;

            case CatalogService::DISPLAY_STYLE_LENTS:

                $view = $this->forward()->dispatch('catalog', array('action'=>'renderSectionLents', 'id'=>$id));
                break;

            case CatalogService::DISPLAY_STYLE_POWER:
                $view = $this->forward()->dispatch('catalog', array('action'=>'renderSectionPowerElements', 'id'=>$id));
                break;

            case CatalogService::DISPLAY_STYLE_PROFILES:
                $view =  $this->forward()->dispatch('catalog', array('action'=>'renderSectionProfiles', 'id'=>$id));
                break;

            default:
                $view =  $this->forward()->dispatch('catalog', array('action'=>'renderSectionDefault', 'id'=>$id));
                break;

        }

        $seoData = $sl->get('SeoDataTable')->find( SeoService::CATALOG_SECTION, $section->id);
        $filterData = $this->getFilterData($section->id);

        if ($subsecId = $this->params()->fromQuery('subsec')) {
            $view->setVariable('scrollToSubsection', $subsecId);
        }
        if ($serId = $this->params()->fromQuery('series')) {
            $view->setVariable('scrollToSeries', $serId);
        }
        if ($prodId = $this->params()->fromQuery('product')) {
            $view->setVariable('scrollToProduct', $prodId);
        }


        $this->layout()->setVariables(array(

            'seoData' => $seoData,
            'pageTitle' => $section->title,
            'breadCrumbs'  => array(
                array('link'=> $this->url()->fromRoute('catalog'), 'text'=>ucfirst('Каталог'))
            ),
        ));

        $return = array(
            'seoData' => $seoData,
            'filterData' => $filterData['filter'],
            'pageTitle' => $section->title,
            'section' => $section,
            'breadCrumbs'  => array(
                array('link'=> $this->url()->fromRoute('catalog'), 'text'=>ucfirst('Каталог')),
            ),
            'slidersData' => \Zend\Json\Json::encode($filterData['sliders']),
            'postVals' => \Zend\Json\Json::encode($filterData['postVals']),
            'qtexts' => \Zend\Json\Json::encode($filterData['qtexts']),
        );
        $view->setVariables($return);
        return $view;
        ////////////////////////////////////////////////////////////////////////////
/*
        if ($section === false || $subsections === false || $section->deleted == 1) {
            return $this->redirect()->toRoute('catalog');
        }

        foreach ($subsections as $subKey => $subsection) {
            if ($subsection->deleted == 1) {
                unset($subsections[$subKey]);
            }
        }

        $subsections = ApplicationService::makeIdArrayFromObjectArray($subsections);

        if($subsections){
            $subSecIds = $seriesIds = array();
            foreach($subsections as $subsec){
                $subSecIds[] = $subsec->id;
            }
            $series = $this->getSeriesTable()->fetchByCond('subsection_id', $subSecIds, 'order asc');


            foreach($series as $bitchKey => $ser){
                $prods = $this->getProductTable()->fetchByCond('series_id', $ser->id);
                if ($prods) {
                    $seriesIds[] = $ser->id;

                    $subsections[$ser->subsection_id]->series[] = $ser;
                } else {
                    unset($series[$bitchKey]);
                }

            }
        }

        $filterData = $this->getFilterData( $section->id );
        $seoData = $sl->get('SeoDataTable')->find( SeoService::CATALOG_SECTION, $section->id);

        $this->layout()->setVariables(array(

            'seoData' => $seoData,
            'pageTitle' => $section->title,
            'breadCrumbs'  => array(
                array('link'=> $this->url()->fromRoute('catalog'), 'text'=>ucfirst('Каталог'))
            ),
        ));

        $view = new ViewModel();
        $return = array(
            'seoData' => $seoData,
            'section' => $section,
            'subsections' => $subsections,
            'filterData' => $filterData['filter'],
            'slidersData' => \Zend\Json\Json::encode($filterData['sliders']),
            'postVals' => \Zend\Json\Json::encode($filterData['postVals']),
            'qtexts' => \Zend\Json\Json::encode($filterData['qtexts']),
        );


        $hierarchies = array();
        $identity = false;
        if(isset($series) && isset($seriesIds) && $section->display_style){
            list($return['allSeries'], $tmpl, $hierarchies) = $this->getDisplaySortedSeries($section->display_style, $series, $seriesIds);
            $return['offeredIds'] = $sl->get('OfferContentTable')->fetchAll('', true);
            $view->setTemplate($tmpl);

            if($section->display_style == 2) {
                $return['params'] = $sl->get('Catalog\Model\ProductParamsTable')->fetchAll();
            }

            if ($this->zfcUserAuthentication()->hasIdentity()) {
                $identity = $this->zfcUserAuthentication()->getIdentity();
                foreach ($return['allSeries'] as $oneSer) {
                    if ($oneSer->products) {

                        foreach ($oneSer->products as $product) {
                            $hierarchies[$product->id][\Catalog\Controller\AdminController::PRODUCT_TABLE] = $product->id;
                            $hierarchies[$product->id][\Catalog\Controller\AdminController::SERIES_TABLE] = $oneSer->id;
                            $hierarchies[$product->id][\Catalog\Controller\AdminController::SUBSECTION_TABLE] = $oneSer->subsectionId;
                            $hierarchies[$product->id][\Catalog\Controller\AdminController::SECTION_TABLE] = $section->id;
                        }
                    }
                }
            }
        }


        if ($identity && $identity->getisPartner()) {
            $discounts = $sl->get('DiscountTable')->fetchByUserId($identity->getId());
            $view->setVariable('user', $identity);
            $view->setVariable('discounts', $discounts);
            $view->setVariable('hierarchies', $hierarchies);

        }

        $view->setVariables($return);
        return $view;*/
    }

    public function renderSubsectionProfilesAction() {
        $id = $this->params()->fromRoute('id', 0);
        $sl = $this->getServiceLocator();
        $cm = CatalogMapper::getInstance($sl);

        $subsection = $cm->getSubsection($id, true, false);
        $id = $subsection->id;
        $params = $sl->get('Catalog\Model\ProductParamsTable')->fetchAll();

        $view = new ViewModel();
        $view
            ->setVariables(array(
                'series'   => SeriesAggregator::getInstance()->getSeries($id),
                'params'             => $params,
                'view' => $view,
                'sl'       => $sl
            ));
        $links = LinkToLinkMapper::getInstance($sl)->fetchCatalogSortedBySectionType($id, AdminController::SUBSECTION_TABLE);
        $view->setVariable('links', $links);
        $view->setTemplate('catalog/catalog/subection_profili');
        return $view;
    }

    public function renderSubsectionDefaultAction() {
        $id = $this->params()->fromRoute('id', 0);
        $sl = $this->getServiceLocator();
        $cm = CatalogMapper::getInstance($sl);

        $subsection = $cm->getSubsection($id, true, false);
        $id = $subsection->id;

        $params = $sl->get('Catalog\Model\ProductParamsTable')->fetchAll();

        $view = new ViewModel();
        $view
            ->setVariables(array(
                'series'   => SeriesAggregator::getInstance()->getSeries($id),
                'params'             => $params,
                'view' => $view,
                'sl'       => $sl
            ));
        $links = LinkToLinkMapper::getInstance($sl)->fetchCatalogSortedBySectionType($id, AdminController::SUBSECTION_TABLE);
        $view->setVariable('links', $links);
        $view->setTemplate('catalog/catalog/subsection');
        return $view;
    }

    public function subsectionAction()
    {
        $id = $this->params()->fromRoute('id', 0);

        if (!$id) {
            return $this->redirect()->toRoute('catalog');
        }

        $sl = $this->getServiceLocator();
        $cm = CatalogMapper::getInstance($sl);

        $subsection = $cm->getSubsection($id);
        if (!$subsection) return $this->redirect()->toRoute('catalog');

        $section = $cm->getSection($subsection->section_id);
        if (!$section) return $this->redirect()->toRoute('catalog');

        switch($section->display_style) {
            case CatalogService::DISPLAY_STYLE_DEFAULT :
                $view = $this->forward()->dispatch('catalog', array('action'=>'renderSubsectionDefault', 'id'=>$id));
                break;

            case CatalogService::DISPLAY_STYLE_LENTS:
                $url = $this->url()->fromRoute('catalog', array('action'=>'section', 'id'=>$section->id));
                $url .= '?subsec=' . $subsection->id;
                return $this->redirect()->toUrl($url)->setStatusCode(301);

                break;

            case CatalogService::DISPLAY_STYLE_POWER:
                $url = $this->url()->fromRoute('catalog', array('action'=>'section', 'id'=>$section->id));
                $url .= '?subsec=' . $subsection->id;
                return $this->redirect()->toUrl($url)->setStatusCode(301);

                break;

            case CatalogService::DISPLAY_STYLE_PROFILES:
                $view =  $this->forward()->dispatch('catalog', array('action'=>'renderSubsectionProfiles', 'id'=>$id));
                break;

            default:
                $view =  $this->forward()->dispatch('catalog', array('action'=>'renderSubsectionDefault', 'id'=>$id));
                break;

        }

        $seoData = $sl->get('SeoDataTable')->find( SeoService::CATALOG_SUBSECTION, $subsection->id);
        $filterData = $this->getFilterData($section->id, $subsection->id);


        if (empty($section->display_name)) {
            $breadCrumbsSection = array(
                'link'=> $this->url()->fromRoute('catalog', array('action'=>'section', 'id'=>$section->id)),
                'text'=>$section->title
            );
        } else {
            $breadCrumbsSection = array(
                'link'=> $this->url()->fromRoute('catalog', array('action'=>'section', 'id'=>$section->display_name)),
                'text'=>$section->title
            );
        }

        $this->layout()->setVariables(array(
            'seoData' => $seoData,
            'pageTitle' => $subsection->title,
            'breadCrumbs'  => array(
                array('link'=> $this->url()->fromRoute('catalog'), 'text'=>ucfirst('Каталог')),
                $breadCrumbsSection
            ),
        ));


        $return = array(
            'seoData' => $seoData,
            'pageTitle' => $subsection->title,
            'breadCrumbs'  => array(
                array('link'=> $this->url()->fromRoute('catalog'), 'text'=>ucfirst('Каталог')),
                $breadCrumbsSection
            ),
            'filterData' => $filterData['filter'],
            'section' => $section,
            'subsection' => $subsection,
            'slidersData' => \Zend\Json\Json::encode($filterData['sliders']),
            'postVals' => \Zend\Json\Json::encode($filterData['postVals']),
            'qtexts' => \Zend\Json\Json::encode($filterData['qtexts']),
        );
        $view->setVariables($return);
        return $view;
        /*
        $id = $this->params()->fromRoute('id', 0);

        if (!$id) {
            return $this->redirect()->toRoute('catalog');
        }

        if (is_numeric($id)) {
            $subsection = $this->getSubSectionTable()->find($id);
        } else {
            $subsections = $this->getSubSectionTable()->fetchByCond('display_name', $id);

            if ($subsections !== false && count($subsections) > 0) {
                /** @var \Catalog\Model\SubSection $subsection */
                /*$subsection = $subsections[0];
            } else {
                return $this->redirect()->toRoute('catalog');
            }
        }

        if ($subsection === false) {
            return $this->redirect()->toRoute('catalog');
        }

        if ($subsection->deleted == 1) {
            return $this->redirect()->toRoute('catalog');
        }
        /** @var \Catalog\Model\Section $section */
        /*$section = $this->getSectionTable()->find($subsection->section_id);
        if ($section->deleted == 1) {
            return $this->redirect()->toRoute('catalog');
        }
        if($section->display_style && $section->display_style != 3){
            if (empty($section->display_name)) {
                $url = $this->url()->fromRoute('catalog', array('action'=>'section', 'id'=>$section->id));
            } else {
                $url = $this->url()->fromRoute('catalog', array('action'=>'section', 'id'=>$section->display_name));
            }

            if($section->display_style == 2){
                $url .= '?subsec='.$subsection->id;
            }

            if($section->display_style == 1){
                $url .= '?series='.$this->params()->fromQuery('series', 0) . '&prod='.$this->params()->fromQuery('prod', 0);
            }
            return $this->redirect()->toUrl($url)->setStatusCode(301);
        }

        $popupContent = false;

        $series = $this->getSeriesTable()->fetchByCond('subsection_id', $subsection->id, 'order asc');

        $seriesIds = array();
        foreach ($series as $bitchKey => $one) {
            $prods = $this->getProductTable()->fetchByCond('series_id', $one->id);
            if ($prods) {
                $seriesIds[] = $one->id;
            } else {
                unset($series[$bitchKey]);
            }

        }

        $view = $this->getRequest()->getPost('view', false);
        $sl = $this->getServiceLocator();

        //$series = $this->getSeriesTable()->find($id);
        $hierarchies = array();
        $mainHierarchies = array();
        $identity = null;
        $discounts = array();

        if ($this->zfcUserAuthentication()->hasIdentity()) {
            $identity = $this->zfcUserAuthentication()->getIdentity();

            if ($identity->getIsPartner()) {
                $discounts = $sl->get('DiscountTable')->fetchByUserId($identity->getId());


                if ($subsection && $subsection->section_id) {
                    $section = $this->getSectionTable()->find($subsection->section_id);
                    if ($section) {
                        $mainHierarchies[AdminController::SECTION_TABLE] = $section->id;
                    }

                } else {
                    return false;
                }
                $mainHierarchies[AdminController::SUBSECTION_TABLE] = $subsection->id;

                $mainHierarchies[AdminController::SERIES_TABLE] = $series->id;
            }
        }*/
        /*$products = $this->getProductTable()->fetchByConds(array('series_id' => $id), array('type' => 0), 'order asc');
        $products = CatalogService::changeIntParamsWithStringVals($products, $this->getFilterParamTable());
        if (count($products) == 0) {
            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode(array(
                'success' => 0,
                'content' => 'empty series',
            )));
            return $response;
        }

        foreach ($products as $oneProd) {
            $hierarchies[$oneProd->id]=$mainHierarchies;
            $hierarchies[$oneProd->id][AdminController::PRODUCT_TABLE] = $oneProd->id;
        }

        $imgs = $sl->get('Catalog\Model\SeriesImgTable')->fetchByCond('parent_id', $id, 'order asc');
        $docs = $sl->get('Catalog\Model\SeriesDocTable')->fetchByCond('parent_id', $id, 'order asc');
        $relatedSeriesIds = $sl->get('Catalog\Model\StoSTable')->find($id, AdminController::SERIES_TABLE);
        $relatedSeries = array();
        $relatedProds = array();
        foreach($relatedSeriesIds as $rsid){
            if ($rsid[1] == AdminController::SERIES_TABLE) {
                $relatedSeries[] = $this->getSeriesTable()->find($rsid[0]);
            } elseif ($rsid[1] == AdminController::PRODUCT_TABLE) {
                $relatedProds[] =  $this->getProductTable()->find($rsid[0]);
            }
        }

        $nextId = CatalogService::getNextId($id, $seriesIds);
        $prevId = CatalogService::getPrevId($id, $seriesIds);
        $nextSer = $this->getSeriesTable()->find($nextId);
        $prevSer = $this->getSeriesTable()->find($prevId);

        $dopProducts = $this->getDopProdsSorted($id);



        foreach ($dopProducts as $oneDopProductKey => &$oneDopProductGroup) {

            foreach ($oneDopProductGroup['products'] as &$dopProd) {


                $hierarchies[$dopProd->id][AdminController::PRODUCT_TABLE] = $dopProd->id;

                $dopseries = $this->getSeriesTable()->find($dopProd->series_id);
                if (!$dopseries || !$dopseries->subsection_id) {

                    unset($hierarchies[$dopProd->id]);
                    unset($dopProd);
                    continue;
                }

                $hierarchies[$dopProd->id][AdminController::SERIES_TABLE] = $dopseries->id;
                $dopsubsec = $this->getSubSectionTable()->find($dopseries->subsection_id);
                if (!$dopsubsec || !$dopsubsec->section_id || $dopsubsec->deleted == 1) {
                    unset($hierarchies[$dopProd->id]);
                    unset($dopProd);
                    continue;
                }

                $hierarchies[$dopProd->id][AdminController::SUBSECTION_TABLE] = $dopsubsec->id;
                $dopsec = $this->getSectionTable()->find($dopsubsec->section_id);

                if (!$dopsec || $dopsec->deleted == 1) {
                    unset($hierarchies[$dopProd->id]);
                    unset($dopProd);
                    continue;
                }
                $hierarchies[$dopProd->id][AdminController::SECTION_TABLE] = $dopsec->id;


                $type = $dopsec->display_style;
                $dopProducts[$oneDopProductKey]['view'] = $type;

                foreach ($products as $key => $oneProduct) {
                    if (isset($oneProduct->id) && ($dopProd->id == $oneProduct->id)) {
                        unset($products[$key]);
                        continue;
                    }
                }
            }

        }

        $oldProducts = $products;
        $products = array();
        foreach ($oldProducts as $oldProduct) {
            if (isset($oldProduct)) {
                $products[] = $oldProduct;
            }
        }

        $articles = $this->getArticles($id);

        if (count($products) > 0) {
            $equalParameters = CatalogService::findEqualParams($products);
        } else {
            $equalParameters = array();
        }

        $params = $sl->get('Catalog\Model\ProductParamsTable')->fetchAll();
        $shownEqualParams = $sl->get('Catalog\Model\EqualParamsTable')->find($id);

        $offeredIds = $sl->get('OfferContentTable')->fetchAll('', true);

        $seoData = $sl->get('SeoDataTable')->find( SeoService::CATALOG_SERIES, $id );


        $htmlViewPart = new ViewModel();
        $htmlViewPart
            ->setVariables(array(
                'series'   => $series,
                'products' => $products,
                'imgs'     => $imgs,
                'docs'     => $docs,
                'relatedSeries' => $relatedSeries,
                'relatedProds' => $relatedProds,
                'nextSer'  => $nextSer,
                'prevSer'  => $prevSer,
                'selectedProdId' => false, //todome: fix this shit
                'dopProducts' => $dopProducts,
                'articles' => $articles,

                'params'             => $params,
                'equalParameters'    => $equalParameters,
                'shownEqualParams'   => $shownEqualParams ? $shownEqualParams : array(),
                'offeredIds'   => $offeredIds,
                'seoData'   => $seoData,

                'view' => $view,

                'sl'       => $sl
            ));

        if ($identity && $identity->getisPartner()) {
            $htmlViewPart->setVariable('user', $identity);
            $htmlViewPart->setVariable('discounts', $discounts);
            $htmlViewPart->setVariable('hierarchies', $hierarchies);
            $htmlViewPart->setVariable('discountProducts', array_keys($hierarchies));
        }



        $filterData = $this->getFilterData( $section->id, $subsection->id );
        $seoData = $this->getServiceLocator()->get('SeoDataTable')->find( SeoService::CATALOG_SUBSECTION, $subsection->id );

        if (empty($section->display_name)) {
            $breadCrumbsSection = array(
                'link'=> $this->url()->fromRoute('catalog', array('action'=>'section', 'id'=>$section->id)),
                'text'=>$section->title
            );
        } else {
            $breadCrumbsSection = array(
                'link'=> $this->url()->fromRoute('catalog', array('action'=>'section', 'id'=>$section->display_name)),
                'text'=>$section->title
            );
        }


        $this->layout()->setVariables(array(
            'seoData' => $seoData,
            'pageTitle' => $subsection->title,
            'breadCrumbs'  => array(
                array(
                    'link'=> $this->url()->fromRoute('catalog'),
                    'text'=>ucfirst('Каталог')
                ),
                $breadCrumbsSection
            ),
        ));

        $return = array(
            'seoData' => $seoData,
            'subsection' => $subsection,
            'series' => $series,
            'section' => $section,
            'btype' => 'catalog',
            'parentUrl' => '/catalog/subsection/' . (!empty($subsection->display_name) ? $subsection->display_name : $id),
            'filterData' => $filterData['filter'],
            'slidersData' => \Zend\Json\Json::encode($filterData['sliders']),
            'postVals' => \Zend\Json\Json::encode($filterData['postVals']),
            'qtexts' => \Zend\Json\Json::encode($filterData['qtexts']),
        );

        $view = new ViewModel();
        if($section->display_style && $section->display_style == 3 ){
            $view->setTemplate('catalog/catalog/subection_profili');
        }

        $view->setVariables($return);
        return $view;*/
    }

    public function renderSeriesDefaultAction() {
        $id = $this->params()->fromRoute('id', 0);
        $sl = $this->getServiceLocator();
        $cm = CatalogMapper::getInstance($sl);
        $metaseries = $cm->getSeriesOne($id, false);
        $metasubsection = $cm->getSubsection($metaseries->subsection_id, false, false);

        $parentTree = array(
            AdminController::SUBSECTION_TABLE => $metasubsection->id,
            AdminController::SECTION_TABLE => $metasubsection->section_id,

        );

        $series = $cm->getSeriesOne($id, true, $parentTree);

        $id = $series->id;

        /** @var \Catalog\Model\StoSTable $StoSTable */
        $StoSTable = $sl->get('Catalog\Model\StoSTable');

        $relatedSeriesIds = $StoSTable->find($id, AdminController::SERIES_TABLE);
        $relatedSeries = array();
        $relatedProds = array();
        $fileTable = $this->getServiceLocator()->get('FilesTable');
        foreach($relatedSeriesIds as $rsid){
            if ($rsid[1] == AdminController::SERIES_TABLE) {
                $rser = $this->getSeriesTable()->find($rsid[0]);
                if ($rser->preview) {
                    $file = $fileTable->find($rser->preview);
                    if ($file) {
                        $rser->previewName = $file->name;
                    }
                }
                $relatedSeries[] = $rser;
            } elseif ($rsid[1] == AdminController::PRODUCT_TABLE) {
                $relatedProds[] =  $this->getProductTable()->find($rsid[0]);
            }
        }


        $linkedIds = $StoSTable->fetchByConds(array('series_id_1' => $id, 'catalog_type_1' => AdminController::SERIES_TABLE));
        $linkedElements = array();

        foreach($linkedIds as $lid){

        }

        $params = $sl->get('Catalog\Model\ProductParamsTable')->fetchAll();
        $offeredIds = $sl->get('OfferContentTable')->fetchAll('', true);

        $dopProducts = $series->dopProducts;
        $series->show_scroll_btn = false;
        foreach ($dopProducts as $gr) {
            if (strpos($gr['title'], 'источник') !== false || strpos($gr['title'], 'ИСТОЧНИК') !== false || strpos($gr['title'], 'Источник') !== false) {
                $series->show_scroll_btn = true;
                break;
            }
        }

        $view = new ViewModel();
        $view
            ->setVariables(array(
                'series' => $series,
                'imgs' => $series->imgs,
                'docs' => $series->docs,
                'dims' => $series->dims,
                'offeredIds' => $offeredIds,
                'dopProducts' => $series->dopProducts,
                'equalParameters' => $series->equalParams,
                'shownEqualParams' => $series->shownEqualParams,
                'products' => ProductsAggregator::getInstance()->getProducts($id),
                'relatedSeries' => $relatedSeries,
                'relatedProds' => $relatedProds,
                'selectedProdId' => false, //todome: fix this shit
                'params'             => $params,
                'sl'       => $sl
            ));

        if ($this->zfcUserAuthentication()->hasIdentity()) {
            $identity = $this->zfcUserAuthentication()->getIdentity();
            $isManager = UserService::$isManager;
            $godModeId = UserService::$godModeId;
            $godModeName = UserService::$godModeName;

            if ($isManager
                && $godModeId
                && $godModeName) {
                $godModePartnerGroupId = UserService::$godModePartnerGroupId;
                $discounts = $sl->get('DiscountTable')->fetchByUserId($godModeId, $godModePartnerGroupId, false, 0, $sl);
            } else {
                $discounts = $sl->get('DiscountTable')->fetchByUserId($identity->getId(), $identity->getPartnerGroup(), false, 0, $sl);
            }
            $view->setVariable('discounts', $discounts);
            $hierarchies = Hierarchy::getInstance()->getProducts();
            $view->setVariable('hierarchies', $hierarchies);
            $view->setVariable('discountProducts', array_keys($hierarchies));
            $view->setVariable('user', $identity);
        }
        $links = LinkToLinkMapper::getInstance($sl)->fetchCatalogSortedBySectionType($id, AdminController::SERIES_TABLE);
        $view->setVariable('links', $links);
        $view->setTemplate('catalog/catalog/series');
        return $view;
    }


    public function seriesAction()
    {
        $id = $this->params()->fromRoute('id', 0);

        $request = $this->getRequest();
        $referer = $request->getHeader('referer');

        if (!$id) {
            return $this->redirect()->toRoute('catalog');
        }

        $sl = $this->getServiceLocator();
        $cm = CatalogMapper::getInstance($sl);
        $series = $cm->getSeriesOne($id);
        if (!$series) return $this->redirect()->toRoute('catalog');

        $subsection = $cm->getSubsection($series->subsection_id);
        if (!$subsection) return $this->redirect()->toRoute('catalog');



        $section = $cm->getSection($subsection->section_id);
        if (!$section) return $this->redirect()->toRoute('catalog');

        switch($section->display_style) {
            case CatalogService::DISPLAY_STYLE_DEFAULT :
                $view = $this->forward()->dispatch('catalog', array('action'=>'renderSeriesDefault', 'id'=>$id));
                break;

            case CatalogService::DISPLAY_STYLE_LENTS:
                $url = $this->url()->fromRoute('catalog', array('action'=>'section', 'id'=>$section->id));
                $url .= '?series=' . $series->id;
                return $this->redirect()->toUrl($url)->setStatusCode(301);

                break;

            case CatalogService::DISPLAY_STYLE_POWER:
                $url = $this->url()->fromRoute('catalog', array('action'=>'section', 'id'=>$section->id));
                $url .= '?series=' . $series->id;
                return $this->redirect()->toUrl($url)->setStatusCode(301);

                break;

            case CatalogService::DISPLAY_STYLE_PROFILES:
                $view =  $this->forward()->dispatch('catalog', array('action'=>'renderSeriesDefault', 'id'=>$id));
//                $view =  $this->forward()->dispatch('catalog', array('action'=>'renderSeriesProfiles', 'id'=>$id));
                break;

            default:
                $view =  $this->forward()->dispatch('catalog', array('action'=>'renderSubsectionDefault', 'id'=>$id));
                break;

        }

        $seoData = $sl->get('SeoDataTable')->find( SeoService::CATALOG_SERIES, $series->id);
        if (!$seoData->keywords || !$seoData->description) {
            $seoDataNew = $sl->get('SeoDataTable')->find( SeoService::CATALOG_SUBSECTION, $subsection->id);

            if (!$seoData->keywords) {
                $seoData->keywords = $seoDataNew->keywords;
            }
            if (!$seoData->description) {
                $seoData->description = $seoDataNew->description;
            }

        }
        if ($prodId = $this->params()->fromQuery('product')) {
            $view->setVariable('scrollToProduct', $prodId);
        }

        $allSeries = $cm->getSeries($subsection->id, false);
        $allSeriesIds = array();
        foreach ($allSeries as $oneser) {
            $allSeriesIds[] = $oneser->id;
        }
        $nextSerId = CatalogService::getNextId($id, $allSeriesIds);
        $prevSerId = CatalogService::getPrevId($id, $allSeriesIds);
        $nextProd = $cm->getSeriesOne($nextSerId);
        $prevProd = $cm->getSeriesOne($prevSerId);
        $fileTable = $this->getServiceLocator()->get('FilesTable');
        if ($nextProd && $nextProd->preview) {
            $file = $fileTable->find($nextProd->preview);
            if ($file) {
                $nextProd->previewName = $file->name;
            }
        }
        if ($prevProd && $prevProd->preview) {
            $file = $fileTable->find($prevProd->preview);
            if ($file) {
                $prevProd->previewName = $file->name;
            }
        }

        if (empty($section->display_name)) {
            $breadCrumbsSection = array(
                'link'=> $this->url()->fromRoute('catalog', array('action'=>'section', 'id'=>$section->id)),
                'text'=>$section->title
            );
        } else {
            $breadCrumbsSection = array(
                'link'=> $this->url()->fromRoute('catalog', array('action'=>'section', 'id'=>$section->display_name)),
                'text'=>$section->title
            );
        }

        if (empty($subsection->display_name)) {
            $breadCrumbsSubsection = array(
                'link'=> $this->url()->fromRoute('catalog', array('action'=>'subsection', 'id'=>$subsection->id)),
                'text'=>$subsection->title
            );
        } else {
            $breadCrumbsSubsection = array(
                'link'=> $this->url()->fromRoute('catalog', array('action'=>'subsection', 'id'=>$subsection->display_name)),
                'text'=>$subsection->title
            );
        }

        $this->layout()->setVariables(array(
            'seoData' => $seoData,
            'pageTitle' => $series->visible_title ? $series->visible_title : $series->title,
            'breadCrumbs'  => array(
                array('link'=> $this->url()->fromRoute('catalog'), 'text'=>ucfirst('Каталог')),
                $breadCrumbsSection,
                $breadCrumbsSubsection
            ),
        ));


        $return = array(
            'seoData' => $seoData,
            'view'  => $section->display_style,
            'nextProd' => $nextProd,
            'prevProd' => $prevProd,
            'pageTitle' => $series->visible_title ? $series->visible_title : $series->title,
            'breadCrumbs'  => array(
                array('link'=> $this->url()->fromRoute('catalog'), 'text'=>ucfirst('Каталог')),
                $breadCrumbsSection,
                $breadCrumbsSubsection
            ),
        );
        $view->setVariables($return);
        return $view;
    }

    public function productAction()
    {
        $id = $this->params()->fromRoute('id', 0);

        if (!$id) {
            return $this->redirect()->toRoute('catalog');
        }

        $sl = $this->getServiceLocator();
        $cm = CatalogMapper::getInstance($sl);
        $product = $cm->getProduct($id);
        if (!$product) return $this->redirect()->toRoute('catalog');

        $series = $cm->getSeriesOne($product->series_id);
        if (!$series) return $this->redirect()->toRoute('catalog');

        $subsection = $cm->getSubsection($series->subsection_id);
        if (!$subsection) return $this->redirect()->toRoute('catalog');

        $section = $cm->getSection($subsection->section_id);
        if (!$section) return $this->redirect()->toRoute('catalog');

        switch($section->display_style) {
            case CatalogService::DISPLAY_STYLE_DEFAULT :
                $url = $this->url()->fromRoute('catalog', array('action'=>'series', 'id'=>$series->id));
                $url .= '?product=' . $id;
                return $this->redirect()->toUrl($url)->setStatusCode(301);
                break;

            case CatalogService::DISPLAY_STYLE_LENTS:
                $url = $this->url()->fromRoute('catalog', array('action'=>'section', 'id'=>$section->id));
                $url .= '?product=' . $id;
                return $this->redirect()->toUrl($url)->setStatusCode(301);

                break;

            case CatalogService::DISPLAY_STYLE_POWER:
                $url = $this->url()->fromRoute('catalog', array('action'=>'section', 'id'=>$section->id));
                $url .= '?product=' . $id;
                return $this->redirect()->toUrl($url)->setStatusCode(301);

                break;

            case CatalogService::DISPLAY_STYLE_PROFILES:
                $url = $this->url()->fromRoute('catalog', array('action'=>'series', 'id'=>$series->id));
                $url .= '?product=' . $id;
                return $this->redirect()->toUrl($url)->setStatusCode(301);
                break;

            default:
                return $this->redirect()->toRoute('catalog');
                break;

        }

    }



    public function getPopupContentAction()
    {

        /** @var \Zend\Http\Request $request */
        $request = $this->getRequest();
        if (($robot = $this->params()->fromRoute('robot', false)) || $this->getRequest()->isXmlHttpRequest()) {
            if ($robot) {
                $id = $this->params()->fromRoute('id', 0);
                $baction = $this->params()->fromRoute('baction', false);
                $pid = $this->params()->fromRoute('pid', false);
                $nextId = $this->params()->fromRoute('nextId', false);
                $prevId = $this->params()->fromRoute('prevId', false);
            } else {
                $id = $request->getPost('id', false);
                $baction = $request->getPost('baction', false);
                $pid = $request->getPost('pid', false);

                $nextId = $request->getPost('nextid', false);
                $prevId = $request->getPost('previd', false);
            }
            $view = $request->getPost('view', false);
            $success = 0;
            $content = '';
//var_dump($id);
            if ($baction == 'product') {
                $prodid = $pid;
                /*$prod = $this->getProductTable()->find($prodid);
                if ($prod) {
                    $id = $prod->series_id;
                }*/
            }

            if ($id) {
                $sl = $this->getServiceLocator();

                $series = $this->getSeriesTable()->find($id);
                $hierarchies = array();
                $mainHierarchies = array();
                $identity = null;
                $discounts = array();
                if ($this->zfcUserAuthentication()->hasIdentity()) {
                    $identity = $this->zfcUserAuthentication()->getIdentity();

                    if ($identity->getIsPartner()) {
                        $discounts = $sl->get('DiscountTable')->fetchByUserId($identity->getId(), $identity->getPartnerGroup(), false, 0, $sl);

                        if ($series->subsection_id) {
                            $subsection = $this->getSubSectionTable()->find($series->subsection_id);
                            if ($subsection && $subsection->section_id) {
                                $section = $this->getSectionTable()->find($subsection->section_id);
                                if ($section) {
                                    $mainHierarchies[AdminController::SECTION_TABLE] = $section->id;
                                }

                            } else {
                                return false;
                            }
                            $mainHierarchies[AdminController::SUBSECTION_TABLE] = $subsection->id;
                        } else {
                            return false;
                        }
                        $mainHierarchies[AdminController::SERIES_TABLE] = $series->id;
                    }
                }
                $products = $this->getProductTable()->fetchByConds(array('series_id' => $id), array('type' => 0), 'order asc');
                $products = CatalogService::changeIntParamsWithStringVals($products, $this->getFilterParamTable());
                if (count($products) == 0) {
                    $response = $this->getResponse();
                    $response->setContent(\Zend\Json\Json::encode(array(
                        'success' => 0,
                        'content' => 'empty series',
                    )));
                    return $response;
                }

                foreach ($products as $oneProd) {
                    $hierarchies[$oneProd->id]=$mainHierarchies;
                    $hierarchies[$oneProd->id][AdminController::PRODUCT_TABLE] = $oneProd->id;
                }

                $imgs = $sl->get('Catalog\Model\SeriesImgTable')->fetchByCond('parent_id', $id, 'order asc');
                $docs = $sl->get('Catalog\Model\SeriesDocTable')->fetchByCond('parent_id', $id, 'order asc');
                $dims = $sl->get('Catalog\Model\SeriesDimTable')->fetchByCond('parent_id', $id, 'order asc');
                $relatedSeriesIds = $sl->get('Catalog\Model\StoSTable')->find($id, AdminController::SERIES_TABLE);
                $relatedSeries = array();
                $relatedProds = array();
                foreach($relatedSeriesIds as $rsid){
                    if ($rsid[1] == AdminController::SERIES_TABLE) {
                        $relatedSeries[] = $this->getSeriesTable()->find($rsid[0]);
                    } elseif ($rsid[1] == AdminController::PRODUCT_TABLE) {
                        $relatedProds[] =  $this->getProductTable()->find($rsid[0]);
                    }
                }

                $nextSer = $this->getSeriesTable()->find($nextId);
                $prevSer = $this->getSeriesTable()->find($prevId);

                $dopProducts = $this->getDopProdsSorted($id);



                foreach ($dopProducts as $oneDopProductKey => &$oneDopProductGroup) {

                    foreach ($oneDopProductGroup['products'] as &$dopProd) {


                        $hierarchies[$dopProd->id][AdminController::PRODUCT_TABLE] = $dopProd->id;

                        $dopseries = $this->getSeriesTable()->find($dopProd->series_id);
                        if (!$dopseries || !$dopseries->subsection_id) {

                            unset($hierarchies[$dopProd->id]);
                            unset($dopProd);
                            continue;
                        }

                        $hierarchies[$dopProd->id][AdminController::SERIES_TABLE] = $dopseries->id;
                        $dopsubsec = $this->getSubSectionTable()->find($dopseries->subsection_id);
                        if (!$dopsubsec || !$dopsubsec->section_id || ($dopsubsec->deleted == 1 && self::$admin === false) ) {
                            unset($hierarchies[$dopProd->id]);
                            unset($dopProd);
                            continue;
                        }

                        $hierarchies[$dopProd->id][AdminController::SUBSECTION_TABLE] = $dopsubsec->id;
                        $dopsec = $this->getSectionTable()->find($dopsubsec->section_id);

                        if (!$dopsec || ($dopsec->deleted == 1 && self::$admin === false)) {
                            unset($hierarchies[$dopProd->id]);
                            unset($dopProd);
                            continue;
                        }
                        $hierarchies[$dopProd->id][AdminController::SECTION_TABLE] = $dopsec->id;


                        $type = $dopsec->display_style;
                        $dopProducts[$oneDopProductKey]['view'] = $type;

                        foreach ($products as $key => $oneProduct) {
                            if (isset($oneProduct->id) && ($dopProd->id == $oneProduct->id)) {
                                unset($products[$key]);
                                continue;
                            }
                        }
                    }


//
//
//
//
//                    if (count($oneDopProductGroup['products'])>0) {
//                        $dopProduct = $oneDopProductGroup['products'][0];
//
//                        /** @var \Catalog\Model\Series $dopseries */
//                        $dopseries = $this->getSeriesTable()->find($dopProduct->series_id);
//
//                        /** @var \Catalog\Model\SubSection $subsection */
//                        $subsection = $this->getSubSectionTable()->find($dopseries->subsection_id);
//                        if ($subsection->deleted == 1) {
//                            return $this->redirect()->toRoute('catalog');
//                        }
//                        /** @var \Catalog\Model\Section $section */
//                        $section = $this->getSectionTable()->find($subsection->section_id);
//                        if ($section->deleted == 1) {
//                            return $this->redirect()->toRoute('catalog');
//                        }
//                        //var_dump($dopProduct->series_id, $dopseries->subsection_id, $subsection->section_id);
//
//
//
//                    }

                }

                $oldProducts = $products;
                $products = array();
                foreach ($oldProducts as $oldProduct) {
                    if (isset($oldProduct)) {
                        $products[] = $oldProduct;
                    }
                }

                $articles = $this->getArticles($id);

                if (count($products) > 0) {
                    $equalParameters = CatalogService::findEqualParams($products);
                } else {
                    $equalParameters = array();
                }

                $params = $sl->get('Catalog\Model\ProductParamsTable')->fetchAll();
                $shownEqualParams = $sl->get('Catalog\Model\EqualParamsTable')->find($id);

                $offeredIds = $sl->get('OfferContentTable')->fetchAll('', true);

                $seoData = $sl->get('SeoDataTable')->find( SeoService::CATALOG_SERIES, $id );


                $htmlViewPart = new ViewModel();
                $htmlViewPart->setTerminal(true)
                    ->setTemplate('catalog/catalog/part/series-popup')
                    ->setVariables(array(
                        'series'   => $series,
                        'products' => $products,
                        'imgs'     => $imgs,
                        'docs'     => $docs,
                        'relatedSeries' => $relatedSeries,
                        'relatedProds' => $relatedProds,
                        'nextSer'  => $nextSer,
                        'prevSer'  => $prevSer,
                        'robot' => $robot,
                        'selectedProdId' => $baction == 'product' ? $prodid : false,
                        'dopProducts' => $dopProducts,
                        'articles' => $articles,

                        'params'             => $params,
                        'equalParameters'    => $equalParameters,
                        'shownEqualParams'   => $shownEqualParams ? $shownEqualParams : array(),
                        'offeredIds'   => $offeredIds,
                        'seoData'   => $seoData,

                        'view' => $view,

                        'sl'       => $sl
                    ));

                if ($identity && $identity->getisPartner()) {
                    $htmlViewPart->setVariable('user', $identity);
                    $htmlViewPart->setVariable('discounts', $discounts);
                    $htmlViewPart->setVariable('hierarchies', $hierarchies);
                    $htmlViewPart->setVariable('discountProducts', array_keys($hierarchies));
                }




                $content = $sl->get('viewrenderer')->render($htmlViewPart);

                $success = 1;
            }


            $response = $this->getResponse();
            if ($robot) {
                return $content;
            }

            $response->setContent(\Zend\Json\Json::encode(array(
                'success' => $success,
                'content' => $content,
            )));

            return $response;
        }
        return $this->redirect()->toRoute('catalog')->setStatusCode(503);
    }

    /** ajaxAction
     * фильтрует \Catalog\Model\Product по параметрам
     * в случае успеха возвращает массив айдишек продуктов
     * array('success', 'error', 'count', 'series_ids' = array(), 'all')
     */
    public function getproductsAction()
    {
        $request = $this->getRequest();

        /** @var \Zend\Http\Request $request */
        if ($request->isPost()) {
            $post = $request->getPost()->toArray();

            if (isset($post['ajax']) && $post['ajax']) {
                //если запрос пришёл из подраздела, ищем все серии,
                //собираем абсолютный min и max и формируем общий запрос
                if (isset($post['subsection_id']) && $post['subsection_id'] != 0) {
                    $section = false;
                    $sectionId = $post['subsection_id'];
                } else {
                    $section = true;
                    $sectionId = $post['section_id'];
                }

                //получаем абсолютные мин/максы по нужному разделу. $section - маркер, показывает раздел это или подраздел
                list($seriesParams, $seriesIds) = $this->getDiapasonByName($sectionId, $section, null);
                /** @var \Catalog\Model\PopularSeries $offers */
                $offerProdsIds = array();

                $activeOffers = $this->getServiceLocator()->get('OffersTable')->fetchByCond('active', 1);

                $activeOffersIds = array();
                foreach ($activeOffers as $activeOffer) {
                    $activeOffersIds[] = $activeOffer->id;
                }
                if ($activeOffersIds) {
                    $offers = $this->getServiceLocator()->get('OfferContentTable')->fetchByCond('offer_id', $activeOffersIds);

                    foreach ($offers as $offer) {
                        $offerProdsIds[] = $offer->product_id;
                    }
                }


                if($seriesIds){
                    $query = CatalogService::getFilterQuery($post, $seriesParams, $seriesIds, $offerProdsIds);
                    if ($query === false) {
                        $response = array(
                            'success' => 1,
                            'error' => 'There are not any results for your query, sorry',
                            'count_series' => 0,
                            'count_products' => 0
                        );

                        echo json_encode($response);
                        return $this->getResponse();
                    }
                    /** @var \Catalog\Model\ProductTable $productsTable */
                    $productsTable = $this->getProductTable();

                    $select = $productsTable->getSql()->select()->where($query);

                    $results = $productsTable->selectWith($select);
                    $resultSet = new ResultSet();
                    $resultSet->initialize($results);
                    if (count($results) > 0) {
                        $response = array(
                            'success' => 1,
                            'error' => '',
                            'count_series' => 0,
                            'count_products' => count($results)
                        );
                        /** @var \Catalog\Model\Product $oneResult */
                        foreach ($results as $oneResult) {
                            if ($oneResult->series_id != 0) {

                                if (!isset($response['series_ids'][$oneResult->series_id])) {
                                    $response['count_series']++;
                                }
                                $response['series_ids'][$oneResult->series_id][] = $oneResult->id;

                            }

                            $response['all'][] = $oneResult->id;
                        }
                    } else {
                        $response = array(
                            'success' => 1,
                            'error' => 'There are not any results for your query, sorry',
                            'count_series' => 0,
                            'count_products' => 0
                        );
                    }
                } else {
                    $response = array(
                        'success' => 0,
                        'error' => 'Series Ids not found',
                    );
                }


            } else {
                $response = array(
                    'success' => 0,
                    'error' => 'Ajax index does not set',
                );

            }
            echo json_encode($response);
        }

        return $this->getResponse();
    }


    private function getSeriesMinMax($seriesId) {
        $filterTable = $this->getSeriesParamsTable();
        $newSeriesMinMax = $filterTable->fetchByCond('series_id', $seriesId);
        if (!$newSeriesMinMax && !isset($newSeriesMinMax[0])) {
            return false;
        }

        return $newSeriesMinMax[0];
    }


    private function getFilterParams($sectionId, $isSection = false, $paramName = null)
    {
        $paramToSeriesTable = $this->getParamToSeriesTable();
        $filterParamsTable = $this->getFilterParamTable();
        $data = array();
        $seriesIds = array();
        if ($isSection) {
            $subsectionBySectionId = $this->getSubSectionTable()->fetchByCond('section_id', $sectionId, 'order asc');

            foreach($subsectionBySectionId as $oneSubsection) {
                if ($oneSubsection->deleted == 1 && self::$admin === false) {
                    continue;
                }
                if (self::$admin) {
                    $seriesBySubSectionId = $this->getSeriesTable()->fetchByCond('subsection_id', $oneSubsection->id, 'order asc');
                } else {
                    $seriesBySubSectionId = $this->getSeriesTable()->fetchByConds(array('subsection_id' => $oneSubsection->id, 'deleted' => 0), array(), 'order asc');
                }

                foreach($seriesBySubSectionId as $oneSeries) {
                    $seriesIds[] = $oneSeries->id;
                }
            }
        } else {
            if (self::$admin) {
                $seriesBySubSectionId = $this->getSeriesTable()->fetchByCond('subsection_id', $sectionId, 'order asc');
            } else {
                $seriesBySubSectionId = $this->getSeriesTable()->fetchByConds(array('subsection_id' => $sectionId, 'deleted' => 0), array(), 'order asc');
            }

            foreach($seriesBySubSectionId as $oneSeries) {
                $seriesIds[] = $oneSeries->id;
            }

        }


        if (count($seriesIds) == 0) {
            return array();
        }

        $paramIds = array();

        $possibleParams = $paramToSeriesTable->fetchByCond('series_id', $seriesIds);

        foreach ($possibleParams as $possibleParam) {
            $paramIds[] = $possibleParam->param_id;
        }

        /** @var \Catalog\Model\FilterParam[] $data */
        if($paramIds){
            $data = $filterParamsTable->fetchByCond('id', $paramIds);
        }



        //data = CatalogService::getFilterMinMax($sectionMinMax);

        if (!isset($data) || !is_array($data) || count($data) <= 0) {
            return array();
        }

        $params = array();

        if (is_null($paramName)) {
            foreach ($data as $obj) {
                $params[$obj->field][$obj->id] = $obj->value;
            }
        } else {
            if (is_string($paramName)) {

                foreach ($data as $obj) {

                    if ($obj->field == $paramName) {
                        $params[$obj->id] = $obj->value;
                        //$paramMasks[$obj->id] =
                    }

                }
            } else if (is_array($paramName)) {
                foreach ($paramName as $oneParam) {
                    if (!is_string($oneParam)) {
                        continue;
                    }

                    foreach ($data as $obj) {
                        if ($obj->field == $paramName) {
                            $params[$obj->field][$obj->id] = $obj->value;
                        }
                    }
                }
            }
        }

        return array($params, $paramIds);
    }


    /**
     * @param int $sectionId
     * @param bool $isSection
     * @param null|string|array $diapasonName
     * @return array
     */
    private function getDiapasonByName($sectionId, $isSection = false, $diapasonName = null) {
        $params = array();
        $sectionMinMax = array();
        $seriesIds = array();

        /** @var \Catalog\Model\FilterParam[] $data */

        if ($isSection) {
            $subsectionBySectionId = $this->getSubSectionTable()->fetchByCond('section_id', $sectionId, 'order asc');

            foreach($subsectionBySectionId as $oneSubsection) {
                $seriesBySubSectionId = $this->getSeriesTable()->fetchByCond('subsection_id', $oneSubsection->id, 'order asc');
                if ($oneSubsection->deleted == 1 && self::$admin === false) {
                    continue;
                }
                foreach($seriesBySubSectionId as $oneSeries) {
                    $newSeriesMinMax = $this->getSeriesMinMax($oneSeries->id);
                    if ($newSeriesMinMax !== false) {
                        $sectionMinMax[] = $newSeriesMinMax;
                        $seriesIds[] = $oneSeries->id;
                    }
                }
            }
        } else {
            $seriesBySubSectionId = $this->getSeriesTable()->fetchByCond('subsection_id', $sectionId, 'order asc');

            if ($seriesBySubSectionId && count($seriesBySubSectionId) > 0) {
                foreach($seriesBySubSectionId as $oneSeries) {
                    $newSeriesMinMax = $this->getSeriesMinMax($oneSeries->id);
                    if ($newSeriesMinMax !== false) {
                        $sectionMinMax[] = $newSeriesMinMax;
                        $seriesIds[] = $oneSeries->id;
                    }

                }
            }
        }

        $data = CatalogService::getFilterMinMax($sectionMinMax);

        if (!is_array($data) || count($data) <= 0) {
            return array(array(), array());
        }

        if (is_null($diapasonName)) {
            $params = $data;
        } else {
            if (is_string($diapasonName)) {
                if (array_key_exists($diapasonName, $data)) {
                    $params = $data[$diapasonName];
                }
            } else if (is_array($diapasonName)) {
                foreach ($diapasonName as $oneParam) {
                    if (!is_string($oneParam)) {
                        continue;
                    }

                    if (array_key_exists($oneParam, $data)) {
                        $params[$oneParam] = $data[$oneParam];
                    }
                }
            }
        }

        return array($params, $seriesIds);
    }

    public function updatefieldsAction()
    {
        /** @var \Zend\Http\Request $request */
        $request = $this->getRequest();
        if (!$request->isPost()) {
            echo 'error: request is not Post';
            return array();
        }

        $content = $request->getPost()->toArray();

        if (isset($content['field']) && !empty($content['field'])) {
//            $field = $content['field'];
//            $isSlider = true;
        } else {
            echo 'error: field is empty';
        }

        return $this->getResponse();
    }


    private function getFilterData( $section_id, $subsection_id = null)
    {
        $filterData = $slidersData = $postVals = $qtexts = array();
        $filterFields = CatalogService::getFilterFields();
        $prodParams = $this->getServiceLocator()->get('Catalog\Model\ProductParamsTable');
        $termsTable = $this->getServiceLocator()->get('Terms\Model\Terms');
        $filterFieldTable = $this->getServiceLocator()->get('FilterFieldTable');
        /** @var FilterField[] $filterFields */
        if (!$subsection_id) {
            $filterFields = $filterFieldTable->fetchAll($section_id, AdminController::SECTION_TABLE, 0, "order ASC");
        } else {
            $filterFields = $filterFieldTable->fetchAll($subsection_id, AdminController::SUBSECTION_TABLE, $section_id, "order ASC");
        }

        foreach($filterFields as $id=>$field){
            if ($field->hidden) continue;
            $isSlider = $field->is_slider;
            $term = $prodParams->find($id);
            $term->is_slider = $isSlider;
            $term->open = $field->open;

            if ($isSlider) {
                $params = $this->getDiapasonByName(($subsection_id == 0) ? $section_id : $subsection_id, ($subsection_id == 0) ? true : false, $term->field);
            } else {
                $params = $this->getFilterParams(($subsection_id == 0) ? $section_id : $subsection_id, ($subsection_id == 0) ? true : false, $term->field);
            }
            if ($params[0] !== false) {
                uasort($params[0], function($a, $b) {
                    $a = preg_replace("/[^0-9]/", '', $a);
                    $b = preg_replace("/[^0-9]/", '', $b);
                    if ((int)$a == (int)$b) {
                        return 0;
                    }
                    return ((int)$a < (int)$b) ? -1 : 1;
                });

                if(isset($params[0])){
                    $term->values = $params[0];
                }

                $filterData[] = $term;
                if ($term->term_id) {
                    $termText = $termsTable->find($term->term_id);
                    if ($termText) {
                        $qtexts[$term->id]['text'] = $termText->text;
                    } else {
                        $qtexts[$term->id]['text'] = $term->text;
                    }
                } else {
                    $qtexts[$term->id]['text'] = $term->text;
                }



                if($isSlider){
                    $slidersData[$term->field] = $term->values;
                    $postVals[$term->field] = $term->post_value;
                }
            }
        }



        return array(
            'filter'   => $filterData,

            'sliders'  => $slidersData,
            'postVals' => $postVals,
            'qtexts'   => $qtexts,
        );
    }

    private function getDopProdsSorted($id)
    {
        $sl = $this->getServiceLocator();
        $res = array();

        $dopProdGroups = $sl->get('Catalog\Model\DopProdGroupTable')->fetchByCond('series_id', $id, 'order asc');
        foreach($dopProdGroups as $dpgroup){
            $dopprods = $sl->get('Catalog\Model\DopProdTable')->fetchByCond('dopprod_group_id', $dpgroup->id, 'order ASC');

            if($dopprods){
                $dopProducts = array();
                foreach($dopprods as $dp) {
                    $dopProducts[] = $this->getProductTable()->find($dp->product_id);
                }
                $res[] = array(
                    'title' => $dpgroup->title,
                    'series_id' => $dpgroup->series_id,
                    'products' => $dopProducts,
                );
            }
        }

        return $res;
    }

    private function getArticles($id)
    {
        $articles = array();
        $sl = $this->getServiceLocator();
        $articlesLinks = $sl->get('SeriesToArticlesTable')->fetchByCond('series_id', $id);
        if($articlesLinks){
            $articlesIds = array();
            foreach($articlesLinks as $link){
                $articlesIds[] = $link->article_id;
            }
            $articles = $sl->get('ArticlesTable')->fetchByCond('id', $articlesIds);
        }

        return $articles;
    }

    private function getDisplaySortedSeries($display_style, $series, $seriesIds)
    {
        $sl = $this->getServiceLocator();
        $sortedSeries = array();
        $tmpl = '';
        $hierarchies = array();
        if($seriesIds){
            switch($display_style){
                case 1:
                    $tmpl = 'catalog/catalog/section_lenta';
                    $products = $this->getProductTable()->fetchByConds(array('series_id' => $seriesIds), array('type' => 0), 'order asc');


                    $products = CatalogService::changeIntParamsWithStringVals($products, $this->getFilterParamTable());
                    $imgs = $sl->get('Catalog\Model\SeriesImgTable')->fetchByCond('parent_id', $seriesIds, 'order asc');
                    foreach($series as $ser){
                        $dopProducts = $this->getDopProdsSorted($ser->id);
                        if($dopProducts){
                            $ser->dopProducts = $dopProducts;
                        }
                        $sortedSeries[$ser->id] = $ser;
                    }
                    if($products){
                        foreach($products as $prod){
                            $sortedSeries[$prod->series_id]->products[] = $prod;
                            $hierarchies[$prod->id][\Catalog\Controller\AdminController::PRODUCT_TABLE] = $prod->id;
                            $hierarchies[$prod->id][\Catalog\Controller\AdminController::SERIES_TABLE] = $prod->series_id;
                        }
                    }



                    if($imgs){
                        foreach($imgs as $img){
                            $sortedSeries[$img->parent_id]->imgs[] = $img;
                        }
                    }

                    foreach ($sortedSeries as &$serr) {
                        if (!isset($serr->products) || count($serr->products) == 0) {
                            unset($serr);
                        }
                    }
                    break;

                case 2:
                    $tmpl = 'catalog/catalog/section_pitanie';
                    $products = $this->getProductTable()->fetchByConds(array('series_id' => $seriesIds), array('type' => 0), 'order asc');
                    $products = CatalogService::changeIntParamsWithStringVals($products, $this->getFilterParamTable());
                    $justSeries = array();
                    foreach($series as $ser){
                        $dopProducts = $this->getDopProdsSorted($ser->id);
                        if($dopProducts){
                            $ser->dopProducts = $dopProducts;
                        }
                        $sortedSeries[$ser->subsection_id][$ser->id] = $ser;

                        $justSeries[$ser->id] = $ser;
                    }

                    if($products){
                        foreach ($products as $prod) {
                            $sortedSeries[$justSeries[$prod->series_id]->subsection_id][$prod->series_id]->products[] = $prod;

                            $hierarchies[$prod->id][\Catalog\Controller\AdminController::PRODUCT_TABLE] = $prod->id;
                            $hierarchies[$prod->id][\Catalog\Controller\AdminController::SERIES_TABLE] = $prod->series_id;
                            $hierarchies[$prod->id][\Catalog\Controller\AdminController::SUBSECTION_TABLE] = $justSeries[$prod->series_id]->subsection_id;
                        }
                    }

                    $equalParamsTable = $sl->get('Catalog\Model\EqualParamsTable');
                    foreach($sortedSeries as &$subsecSeries){
                        foreach($subsecSeries as &$ser){
                            $ser->equalParams = CatalogService::findEqualParams($ser->products);
                            $shown = $equalParamsTable->find($ser->id);
                            $ser->shownEqualParams = $shown ? $shown : array();
                        }
                    }
                    foreach ($sortedSeries as &$sec) {
                        foreach ($sec as &$serr) {
                            if (!isset($serr->products) || count($serr->products) == 0) {
                                unset($serr);
                            }
                        }
                    }
                    break;

                case 3:
                    $tmpl = 'catalog/catalog/section_profili';
                    break;
            }
        }

        return array($sortedSeries,$tmpl, $hierarchies);
    }

    /**
     * @param $sl ServiceLocatorInterface
     * @return mixed
     */
    public static function getSections($sl)
    {
        if (self::$admin) {
            $sections = $sl->get('Catalog/Model/SectionTable')->fetchAll('order asc');
        } else {
            $sections = $sl->get('Catalog/Model/SectionTable')->fetchByCond('deleted', '0', 'order asc');
        }

        return $sections;
    }
}