<?php
namespace Application\Controller;

use Api\Model\File;
use Application\Service\ApplicationService;
use Catalog\Service\CatalogService;
use Zend\Crypt\Symmetric\PaddingPluginManager;
use Zend\Http\Header\LastModified;
use Zend\Mvc\Exception;
use Info\Service\SeoService;
use Services\Controller\ServicesController;
use Zend\EventManager\EventManagerInterface;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Mvc\MvcEvent;
use Zend\View\Model\ViewModel;
use ZendTest\Navigation\TestAsset\Page;

class IndexController extends AbstractActionController
{
    protected $pageInfoType = SeoService::INDEX;

    public function indexAction()
    {
        if ($this->params()->fromQuery('route', false) !== false) {
            return $this->redirect()->toRoute('home')->setStatusCode(301);
        }

        $sl = $this->getServiceLocator();

        $btype = $this->params()->fromRoute('btype', false);
        $bid = $this->params()->fromRoute('bid', 0);
        $baction = $this->params()->fromRoute('baction', 'view');

        $robot = $this->params()->fromQuery('_escaped_fragment_', false);
        $isRobot = ($robot !== false);
        if ($isRobot) {
            $popupController = $this->params()->fromRoute('btype', 'solutions');
            $popupId = $this->params()->fromRoute('bid', 0);
        }
        $popupContent = false;
        $solutions = $sl->get('SolutionsTable')->fetchAll('order asc');
        $projects = $sl->get('ProjectsTable')->fetchAll('order asc');
        $rubrics = $sl->get('ProjectRubricTable')->fetchAll();

        $projectsByRubric = array();

        foreach ($projects as $one) {
            $projectsByRubric[$one->rubric_id][] = $one;
        }
        $arrayIds = array();

        if ($btype == 'solutions') {
            foreach ($solutions as $one) {
                $arrayIds = array();
                $arrayIds[] = $one->id;
            }
        } elseif ($btype == 'projects') {
            $arrayIds = array();
            foreach ($projects as $one) {
                $arrayIds[] = $one->id;
            }
        }



        if (isset($popupController) && isset($popupId)) {

            $popupContent = $this->forward()->dispatch($popupController, array(
                'action' => 'getPopupContent',
                'robot' => true,
                'id' => $popupId,
                'nextId' => CatalogService::getNextId($popupId, $arrayIds),
                'prevId' => CatalogService::getPrevId($popupId, $arrayIds))
            );
        }

        $bannerImages = $sl->get('BannerTable')->fetchAll('order asc');
        $aboutText = $sl->get('InfoTable')->find(\Info\Controller\AdminController::ABOUT);
        $teamMembers = $sl->get('TeamTable')->fetchAll('order asc');
        $teamPopups = $this->renderTeamPopups($teamMembers);
        $aboutPopup = $this->renderAboutCompanyPopup();

        $services = $sl->get('ServicesTable')->fetchAll('order asc');
        $servicesPopups = $this->renderServicePopups($services);
        $serviceFormsPopups = $this->renderServiceFormsPopups();
        $aledoServices = $kaledoscopServices = array();
        foreach($services as $service){
            if($service->is_kaledoscop){
                $kaledoscopServices[] = $service;
            } else {
                $aledoServices[] = $service;
            }
        }

        foreach ($projectsByRubric as $pbkey => $pbr) {
            $projectsByRubric[$pbkey] = array_slice($pbr, 0, 6);
        }

        $projects = array_slice($projects, 0, 6);

        $fileTable = $this->getServiceLocator()->get('FilesTable');
        foreach ($projects as &$one) {
            if ($one->preview) {
                $file = $fileTable->find($one->preview);
                if ($file) {
                    $one->previewName = $file->name;
                }
            }
            $arrayIds[] = $one->id;
        }

        $conacts = $sl->get('ContactsTable')->find(1);

        $clients = $sl->get('ClientsTable')->fetchAll();

        $this->layout()->noBottomLine = true;
        if ($isRobot && isset($popupId)) {
            $seoId = $popupId;
            if ($btype == 'projects') {
                $seoType = SeoService::PROJECTS;
            } elseif ($btype == 'solutions') {
                $seoType = SeoService::SOLUTIONS;
            } else {
                $seoType = SeoService::INFO;
                $seoId = 1;
            }



        } else {
            $seoType = SeoService::INFO;
            $seoId = 1;
        }
        $seoData = $this->getServiceLocator()->get('SeoDataTable')->find($seoType, $seoId);
        $this->layout()->seoData = $seoData;
        $this->layout()->hasBanner = true;
        $this->layout()->bannerImages = $bannerImages;
        $this->layout()->robot = $isRobot;
        $this->layout()->popupContent = $popupContent;

        $isAdmin = false;
        if ($this->zfcUserAuthentication()->hasIdentity()) {
            $identity = $this->zfcUserAuthentication()->getIdentity();
            $roleLinker = $this->getServiceLocator()->get('RoleLinkerTable')->find($identity->getId(), 'user_id');
            if ($roleLinker->role_id == 'manager' || $roleLinker->role_id  == 'admin') {
                $isAdmin = true;
            }
        }

        $blocks = $sl->get('MainPageBlocksTable')->fetchAll();
        $blockImageTable = $sl->get('MainPageBlockImagesTable');
        foreach ($blocks as &$block) {
            $images = $blockImageTable->fetchByCond('parentId', $block->id);
            foreach ($images as &$img) {
                if ($img->img) {
                    /** @var File $file */
                    $file = $fileTable->find($img->img);
                    if ($file) {
                        $img->imgName = $file->name;
                        $img->imgRealName = $file->real_name;
                    }
                }
            }

            $block->images = $images;
        }


        return array(
            'bannerImages'  => $bannerImages,
            'blocks'  => $blocks,
            'aboutText'     => $aboutText,
            'teamMembers'   => $teamMembers,
            'teamPopups'    => \Zend\Json\Json::encode($teamPopups),
            'solutions'     => $solutions,
            'aboutPopup'    => ($isRobot !== false) ?  $aboutPopup[1]['text']  : \Zend\Json\Json::encode($aboutPopup),
            'projects'      => $projects,
            'projectsByRubric'      => $projectsByRubric,
            'rubrics'      => $rubrics,
            'isAdmin' => $isAdmin,
            'btype'         => $btype,
            'baction'       => $baction,
            'parentUrl'     => '/',
            'bid'           =>  $bid,
            'conacts'       => $conacts,
            'clients'       => $clients,
            'robot'         => $isRobot,
            'aledoServices'      => $aledoServices,
            'kaledoscopServices' => $kaledoscopServices,
            'servicesPopups' => \Zend\Json\Json::encode($servicesPopups),
            'serviceFormsPopups' => \Zend\Json\Json::encode($serviceFormsPopups),
        );
    }


    public function showFooBarPopupAction()
    {
        $request = $this->getRequest();
        if ($request->isXmlHttpRequest()) {
            $type = $request->getPost('type', false);
            $url = $request->getPost('url', false);
            $params = $request->getPost('params', false);
            $success = 0;
            $content = '';

            if ($type) {
                $sl = $this->getServiceLocator();

                $tmplts= array(
                    'login',
                    'register',
                    'registerFromCart',
                    'forgot',
                    'emptycart',
                    'regsuccess',
                    'regsuccessFromCart',
                    'reginfo-update',
                    'change-password',
                    'stuffsend',
                    'error',
                    'cart-buy',
                    'cart-buy-without-register',
                    'ordersend',
                    'passwordsend',
                );

                $template = in_array($type, $tmplts) ? 'application/index/part/'.$type : false;
                if($template){
                    $htmlViewPart = new ViewModel();
                    $htmlViewPart->setTerminal(true)
                        ->setTemplate($template)
                        ->setVariables(array(
                            'url' => $url,
                            'params' => $params,
                        ));

                    if ($type === 'register' || $type === 'registerFromCart') {
                        $registerForm = $sl->get('zfcuser_register_form');
                        $htmlViewPart->setVariable('form', $registerForm);
                    }

                    if ($type === 'regsuccess' || $type === 'regsuccessFromCart') {
                        $identity = $this->zfcUserAuthentication()->getIdentity();
                        $isSpamed = false;
                        if ($identity) {
                            $isSpamed = $identity->getIsSpamed();
                        }
                        $htmlViewPart->setVariable('isSpamed', $isSpamed);
                    }

                    if ($type === 'login') {
                        $loginForm = $sl->get('zfcuser_login_form');
                        $htmlViewPart->setVariable('form', $loginForm);
                    }

                    $content = $sl->get('viewrenderer')->render($htmlViewPart);

                    $success = 1;
                }
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode(array(
                'success' => $success,
                'content' => $content
            )));
            return $response;
        }
        return $this->redirect()->toRoute('home');
    }

    private function renderTeamPopups($workers)
    {
        $res = array();
        foreach($workers as $worker)
        {
            $htmlViewPart = new ViewModel();
            $htmlViewPart->setTerminal(true)
                ->setTemplate('team/team/part/popup')
                ->setVariables(array(
                    'worker'   => $worker,
                ));

            $res[$worker->id]['text'] = $this->getServiceLocator()->get('viewrenderer')->render($htmlViewPart);
        }

        return $res;
    }

    private function renderAboutCompanyPopup()
    {
        $htmlViewPart = new ViewModel();
        $res = array();
        $htmlViewPart->setTerminal(true)
            ->setTemplate('info/info/part/popup')
            ->setVariables(array(
            ));

        $res[1]['text'] = $this->getServiceLocator()->get('viewrenderer')->render($htmlViewPart);

        return $res;
    }

    private function renderServicePopups($services)
    {
        $res = array();
        foreach($services as $service)
        {
            $htmlViewPart = new ViewModel();
            $htmlViewPart->setTerminal(true)
                ->setTemplate('services/services/part/popup')
                ->setVariables(array(
                    'service'   => $service,
                ));

            $res[$service->id]['text'] = $this->getServiceLocator()->get('viewrenderer')->render($htmlViewPart);
        }

        return $res;
    }

    private function renderServiceFormsPopups()
    {
        $res = array();

        // заявка на консультацию
        $htmlViewPart = new ViewModel();
        $htmlViewPart->setTerminal(true)->setTemplate('services/services/part/form-consult');
        $htmlViewPart->setVariable('isRegistered', $this->zfcUserAuthentication()->hasIdentity());
        $htmlViewPart->setVariable('user', $this->zfcUserAuthentication()->getIdentity());
        $res[ServicesController::CONSULT_ORDER_FORM]['text'] = $this->getServiceLocator()->get('viewrenderer')->render($htmlViewPart);

        // заявка на проект
        $htmlViewPart = new ViewModel();
        $htmlViewPart->setTerminal(true)->setTemplate('services/services/part/form-project');
        $htmlViewPart->setVariable('isRegistered', $this->zfcUserAuthentication()->hasIdentity());
        $htmlViewPart->setVariable('user', $this->zfcUserAuthentication()->getIdentity());
        $res[ServicesController::PROJECT_ORDER_FORM]['text'] = $this->getServiceLocator()->get('viewrenderer')->render($htmlViewPart);

        // запрос на расчет
        $htmlViewPart = new ViewModel();
        $htmlViewPart->setTerminal(true)->setTemplate('services/services/part/form-calculation');
        $htmlViewPart->setVariable('isRegistered', $this->zfcUserAuthentication()->hasIdentity());
        $htmlViewPart->setVariable('user', $this->zfcUserAuthentication()->getIdentity());
        $res[ServicesController::CALCULATION_FORM]['text'] = $this->getServiceLocator()->get('viewrenderer')->render($htmlViewPart);

        // запрос на энергоаудит
        $htmlViewPart = new ViewModel();
        $htmlViewPart->setTerminal(true)->setTemplate('services/services/part/form-modernisation');
        $htmlViewPart->setVariable('isRegistered', $this->zfcUserAuthentication()->hasIdentity());
        $htmlViewPart->setVariable('user', $this->zfcUserAuthentication()->getIdentity());
        $res[ServicesController::MODERNISATION_FORM]['text'] = $this->getServiceLocator()->get('viewrenderer')->render($htmlViewPart);

        // запрос на монтаж
        $htmlViewPart = new ViewModel();
        $htmlViewPart->setTerminal(true)->setTemplate('services/services/part/form-montaj');
        $htmlViewPart->setVariable('isRegistered', $this->zfcUserAuthentication()->hasIdentity());
        $htmlViewPart->setVariable('user', $this->zfcUserAuthentication()->getIdentity());
        $res[ServicesController::MONTAJ_FORM]['text'] = $this->getServiceLocator()->get('viewrenderer')->render($htmlViewPart);

        return $res;
    }
}
