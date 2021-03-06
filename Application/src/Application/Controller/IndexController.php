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
        $projects = $sl->get('ProjectsTable')->fetchByConds(array('rubric_id' => 1, 'deleted' => 0), false, 'id desc');
        $rubrics = $sl->get('ProjectRubricTable')->fetchAll();

        $projectsByRubric = array();

        foreach ($projects as $one) {
            $projectsByRubric[$one->rubric_id][] = $one;
        }
        $arrayIds = array();

        $bannerImages = $sl->get('BannerTable')->fetchAll('order asc');
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

        $clients = $sl->get('ClientsTable')->fetchAll('order ASC');

        $this->layout()->noBottomLine = true;

        $seoType = SeoService::INFO;
        $seoId = 1;
        $seoData = $this->getServiceLocator()->get('SeoDataTable')->find($seoType, $seoId);
        $this->layout()->seoData = $seoData;
        $this->layout()->hasBanner = true;
        $this->layout()->bannerImages = $bannerImages;

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
            'projects'      => $projects,
            'projectsByRubric'      => $projectsByRubric,
            'rubrics'      => $rubrics,
            'isAdmin' => $isAdmin,
            'parentUrl'     => '/',
            'conacts'       => $conacts,
            'clients'       => $clients,
            'servicesPopups' => \Zend\Json\Json::encode($servicesPopups),
            'serviceFormsPopups' => \Zend\Json\Json::encode($serviceFormsPopups),
        );
    }


    public function showFooBarPopupAction()
    {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $type = $request->getPost('type', false);
            $params = $request->getPost('params', false);
            if (!$type) {
                $rest_json = file_get_contents("php://input");
                $post = json_decode($rest_json, true);
                $type = isset($post['type']) ? $post['type'] : false;
                $params = isset($post['params']) ? $post['params'] : false;
            }
            $url = $request->getPost('url', false);

            $success = 0;
            $content = '';

            if ($type || $type === 0) {
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

                if (is_numeric($type)) {
                    $tempName = ApplicationService::getPopupName($type);
                    $template = 'application/index/part/'.$tempName;
                } else {
                    $template = in_array($type, $tmplts) ? 'application/index/part/'.$type : false;
                }

                if($template){
                    $htmlViewPart = new ViewModel();
                    $htmlViewPart->setTerminal(true)
                        ->setTemplate($template)
                        ->setVariables(array(
                            'url' => $url,
                            'params' => $params,
							'isDomainZoneBy' => ApplicationService::isDomainZone('by')
                        ));

                    if ($type === 'register' || $type === 'registerFromCart' || $type === ApplicationService::ALEDO_POPUP_REGISTER || $type === ApplicationService::ALEDO_POPUP_CART_REGISTER) {
                        $registerForm = $sl->get('zfcuser_register_form');
                        $htmlViewPart->setVariable('form', $registerForm);
                    }


                    if ($type === 'partner-card' || $type == ApplicationService::ALEDO_POPUP_PARTNER_CARD) {
                        $htmlViewPart->setVariable('user', $this->zfcUserAuthentication()->getIdentity());
                    }

                    if ($type === 'regsuccess' || $type === 'regsuccessFromCart' || $type === ApplicationService::ALEDO_POPUP_REGISTER_SUCCESS || $type === ApplicationService::ALEDO_POPUP_CART_REGISTER_SUCCESS) {
                        $identity = $this->zfcUserAuthentication()->getIdentity();
                        $isSpamed = false;
                        if ($identity) {
                            $isSpamed = $identity->getIsSpamed();
                        }
                        $htmlViewPart->setVariable('isSpamed', $isSpamed);
                    }

                    if ($type === 'login' || $type === ApplicationService::ALEDO_POPUP_LOGIN) {
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
