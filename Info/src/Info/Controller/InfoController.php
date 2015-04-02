<?php
namespace Info\Controller;

use Application\Service\MailService;
use Catalog\Controller\AdminController;
use Catalog\Mapper\LinkToLinkMapper;
use Documents\Model\DocumentTable;
use Info\Model\PartnerRequest;
use Info\Form\PartnerForm;
use Info\Service\SeoService;
use User\Service\UserService;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Info\Model\InfoTable;

class InfoController extends AbstractActionController
{
    private $infoTable;
    protected $pageInfoType = SeoService::INFO;
    public function indexAction(){
        $sl = $this->getServiceLocator();
        $team = $sl->get("TeamTable")->fetchAll("order ASC");

        $fileTable = $sl->get("FilesTable");
        foreach ($team as &$worker) {
            if ($worker->img) {
                $file = $fileTable->find($worker->img);
                if ($file) {
                    $worker->img_name = $file->name;
                }
            }
        }

        $showRoom = $sl->get('ShowRoomTable')->fetchAll('order asc');

        foreach ($showRoom as &$banner) {
            if ($banner->img) {
                $file = $fileTable->find($banner->img);
                if ($file) {
                    $banner->img_name = $file->name;
                }
            }
        }

        $l2lMapper = LinkToLinkMapper::getInstance($sl);
        $linkedProjects = $l2lMapper->fetchAll(0, AdminController::INFO_TABLE);

        foreach ($linkedProjects as $key => $project) {

            if ($linkedProjects[$key][1]->preview) {
                $file = $fileTable->find($linkedProjects[$key][1]->preview);
                if ($file) {
                    $linkedProjects[$key][1]->preview_name = $file->name;
                }
            }
        }

        $aboutTable = $sl->get('AboutTable');
        $entity = $aboutTable->find(1);

        $imgFields = array('block_2_img_1', 'block_2_img_2', 'block_2_img_3', 'img', 'img_min');
        if ($imgFields) {
            $fileTable = $this->getServiceLocator()->get('FilesTable');
            foreach ($imgFields as $imgField) {
                if ($entity->$imgField) {
                    $file = $fileTable->find($entity->$imgField);
                    if ($file) {
                        $imgFieldAndName = $imgField . "_name";
                        $entity->$imgFieldAndName = $file->name;
                    }
                }
            }
        }

        $documentsTable = $this->getServiceLocator()->get('DocumentsTable');
        $comments = $documentsTable->fetchByCond('type', DocumentTable::TYPE_COMMENT);


        foreach ($comments as &$comment) {
            if ($comment->img) {
                $file = $fileTable->find($comment->img);
                if ($file) {
                    $comment->img_name = $file->name;
                }
            }
            if ($comment->file) {
                $file = $fileTable->find($comment->file);
                if ($file) {
                    $comment->file_name = $file->name;
                }
            }
        }


        return array(
            'comments' => $comments,
            'team' => $team,
            'entity' => $entity,
            'showRoom' => $showRoom,
            'linkedProjects' => $linkedProjects
        );
    }
    public function filesAction(){
        $sl = $this->getServiceLocator();
        $return = array();
        $documentsTable = $this->getServiceLocator()->get('DocumentsTable');
        $catalogs = $documentsTable->fetchByCond('type', DocumentTable::TYPE_CATALOG);
        $comments = $documentsTable->fetchByCond('type', DocumentTable::TYPE_COMMENT);
        $certificates = $documentsTable->fetchByCond('type', DocumentTable::TYPE_CERTIFICATE);
        $instructions = $documentsTable->fetchByCond('type', DocumentTable::TYPE_INSTRUCTION);

        $filesTable = $this->getServiceLocator()->get('InfoFilesTable');
        $entity = $filesTable->find(1);

        $imgFields = array('img_1', 'img_1_1000', 'img_2', 'img_2_1000');
        $fileTable = $this->getServiceLocator()->get('FilesTable');
        if ($imgFields) {

            foreach ($imgFields as $imgField) {
                if ($entity->$imgField) {
                    $file = $fileTable->find($entity->$imgField);
                    if ($file) {
                        $imgFieldAndName = $imgField . "_name";
                        $entity->$imgFieldAndName = $file->name;
                    }
                }
            }
        }

        foreach ($catalogs as &$catalog) {
            if ($catalog->img) {
                $file = $fileTable->find($catalog->img);
                if ($file) {
                    $catalog->img_name = $file->name;
                }
            }
            if ($catalog->file) {
                $file = $fileTable->find($catalog->file);
                if ($file) {
                    $catalog->file_name = $file->name;
                }
            }
        }

        foreach ($comments as &$comment) {
            if ($comment->img) {
                $file = $fileTable->find($comment->img);
                if ($file) {
                    $comment->img_name = $file->name;
                }
            }
            if ($comment->file) {
                $file = $fileTable->find($comment->file);
                if ($file) {
                    $comment->file_name = $file->name;
                }
            }
        }

        foreach ($certificates as &$certificate) {
            if ($certificate->img) {
                $file = $fileTable->find($certificate->img);
                if ($file) {
                    $certificate->img_name = $file->name;
                }
            }
            if ($certificate->file) {
                $file = $fileTable->find($certificate->file);
                if ($file) {
                    $certificate->file_name = $file->name;
                }
            }
        }

        foreach ($instructions as &$instruction) {
            if ($instruction->img) {
                $file = $fileTable->find($instruction->img);
                if ($file) {
                    $instruction->img_name = $file->name;
                }
            }
            if ($instruction->file) {
                $file = $fileTable->find($instruction->file);
                if ($file) {
                    $instruction->file_name = $file->name;
                }
            }
        }

        $return['catalogs'] = $catalogs;
        $return['comments'] = $comments;
        $return['certificates'] = $certificates;
        $return['instructions'] = $instructions;
        $return['entity'] = $entity;

        return $return;
    }
    public function guaranteeAction(){
        $guaranteeTable = $this->getServiceLocator()->get('GuaranteeTable');
        $entity = $guaranteeTable->find(1);

        return array(
            'entity' => $entity
        );
    }
    public function jobAction(){

        $jobsTable = $this->getServiceLocator()->get('JobsTable');
        $entity = $jobsTable->find(1);

        $imgFields = array('img', 'img_1000');
        if ($imgFields) {
            $fileTable = $this->getServiceLocator()->get('FilesTable');
            foreach ($imgFields as $imgField) {
                if ($entity->$imgField) {
                    $file = $fileTable->find($entity->$imgField);
                    if ($file) {
                        $imgFieldAndName = $imgField . "_name";
                        $entity->$imgFieldAndName = $file->name;
                    }
                }
            }
        }
        return array(
            'entity' => $entity
        );
    }
    public function serviceAction(){

        return array(

        );
    }
    public function plusesAction(){

        $plusesTable = $this->getServiceLocator()->get('PlusesTable');
        $entity = $plusesTable->find(1);

        $imgFields = array('img');
        if ($imgFields) {
            $fileTable = $this->getServiceLocator()->get('FilesTable');
            foreach ($imgFields as $imgField) {
                if ($entity->$imgField) {
                    $file = $fileTable->find($entity->$imgField);
                    if ($file) {
                        $imgFieldAndName = $imgField . "_name";
                        $entity->$imgFieldAndName = $file->name;
                    }
                }
            }
        }
        return array(
            'entity' => $entity
        );
    }

    public function partnersAction(){
        $partnersTable = $this->getServiceLocator()->get('PartnersTable');
        $entity = $partnersTable->find(1);

        $imgFields = array('img1', 'img2', 'img1_min', 'img2_min');
        if ($imgFields) {
            $fileTable = $this->getServiceLocator()->get('FilesTable');
            foreach ($imgFields as $imgField) {
                if ($entity->$imgField) {
                    $file = $fileTable->find($entity->$imgField);
                    if ($file) {
                        $imgFieldAndName = $imgField . "_name";
                        $entity->$imgFieldAndName = $file->name;
                    }
                }
            }
        }
        return array(
            'entity' => $entity
        );
    }


    public function deliveryAction() {
        $deliveryTable = $this->getServiceLocator()->get('DeliveryTable');
        $entity = $deliveryTable->find(1);

        return array(
            'entity' => $entity
        );
    }
    public function partnerAction() {

        return $this->renderPage(\Info\Controller\AdminController::PARTNER);
    }
    public function showroomAction() { return $this->renderPage(\Info\Controller\AdminController::SHOWROOM); }

    public function rateAction() {
        $token = $this->params()->fromQuery('token', null);

        if (is_null($token) || $token != 'fae6e2bf570') {
            /** @var \Zend\Http\Response $response */
            $response = $this->getResponse();
            $response->setStatusCode(404);
            return new ViewModel();
        }

        $url = 'http://planar.spb.ru/ekdb/stockcsv.ini';

        @ $headers = get_headers($url, 1);
        if ($headers === false) {
            echo 'Не удалось достучаться до адреса <a href="' . $url . '">' . $url . '</a>. Хост недоступен. Возможно, следует проверить настройки прокси-сервера?';
            return array();
        }


        $rateContent = file_get_contents($url);
        $needle = 'RATE=';
        $position = strpos($rateContent, $needle);
        $rate = substr($rateContent, $position + strlen($needle));
        $this->layout()->pageTitle = 'Текущий курс — ' . $rate . '<span class="b-rub">Р</span>';
        $this->layout()->rate = true;
        $this->layout()->noBottomLine = true;
        return array('rateString' => $rateContent);

    }

    public function notifyAction()
    {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $post = $request->getPost()->toArray();

            //сообщаем менеджеру о новой заявке
            list($email, $mailView) = MailService::prepareManagerKaledoscopNotifyRequest($this->serviceLocator, $post);
            MailService::sendMail($email, $mailView, "Запрос с kaledoscop");
        }

        return $this->redirect()->toRoute('home');
    }

    private function renderPage($id)
    {
        /** @var \Info\Model\Info $entity */
        $entity = $this->getInfoTable()->find($id);

        $view = new ViewModel();
        $seoData = $this->getServiceLocator()->get('SeoDataTable')->find( \Info\Service\SeoService::INFO, $id);

        $items = array();
        if ($id == \Info\Controller\AdminController::PARTNER) {
            if (($start = strpos($entity->text3, '<ol>'))  !== false) {
                $end = strpos($entity->text3, '</ol>');
                $list = substr($entity->text3, $start+4, $end-$start-4); //4 - длина <ol>, 9 = 4 + 5 (длина </ol>)

                $i=0;

                while ((strlen($list) > 9) && (($listart = strpos($list, '<li>')) !== false)) {

                    $liend = strpos($list, '</li>');
                    $items[++$i] = substr($list, $listart+4, $liend-$listart-4); //4 - длина <li>, 9 = 4 + 5 (длина </li>)

                    $list = substr($list, $liend+5);
                }
            }
        }

        $entity->items = $items;

        $view->setTemplate('info/info/info')
            ->setVariables(array(
                'seoData' => $seoData,
                'entity' => $entity,
                'page'   => $id,
            ));

        $this->layout()->noBottomLine = true;
        $this->layout()->seoData = $seoData;
        $this->layout()->pageTitle = $entity->title;

        return $view;
    }

    public function getPopupContentAction()
    {
        $request = $this->getRequest();
        if ($this->getRequest()->isPost()) {
            $id = $request->getPost('id', false);
            $success = 0;
            $content = '';

            if ($id) {
                $info = $this->getInfoTable()->find($id);

                $htmlViewPart = new ViewModel();
                $htmlViewPart->setTerminal(true)
                    ->setTemplate('info/info/part/popup')
                    ->setVariables(array(
                        'info'   => $info
                    ));

                $content = $this->getServiceLocator()->get('viewrenderer')->render($htmlViewPart);
                $success = 1;
            }


            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode(array(
                'success' => $success,
                'content' => $content,
            )));
            return $response;
        }
        return $this->redirect()->toRoute('info');
    }

    public function saveFormAjaxAction()
    {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $sl = $this->getServiceLocator();
            $post = $request->getPost();
            $success = 0;
            $messages = array();

            $form = new PartnerForm();
            $form->setData($post);

            if ($form->isValid()) {
                $saveData = $form->getData();
                $entity = new PartnerRequest();
                $entity->exchangeArray($saveData);

                $requestId = $sl->get('PartnerRequestTable')->save($entity);

                list($email, $mailView) = MailService::prepareUserRequestPartnershipMailData($this->serviceLocator, $entity);
                MailService::sendMail($email, $mailView, "Ваша заявка принята");

                //сообщаем менеджеру детали нового заказа
                list($email, $mailView, $from) = MailService::prepareManagerRequestPartnershipMailData($this->serviceLocator, $entity, $requestId);
                MailService::sendMail($email, $mailView, "Новая заявка по партнёрству номер " . $requestId . " на Aledo", $from);

                if ($this->zfcUserAuthentication()->hasIdentity()) {
                    $user = $this->zfcUserAuthentication()->getIdentity();
                    if (!$user->getIsPartner()) {
                        UserService::addHistoryAction(
                            $this->getServiceLocator(),
                            $user->getId(),
                            UserService::USER_ACTION_SENT_REQUEST_PARTNERSHIP,
                            "admin/requests/",
                            time()
                        );
                    }
                }

                $success = 1;
            } else {
                $messages = $form->getMessages();
            }

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode(
                array(
                    'success' => $success,
                    'messages' => $messages,
                )
            ));
            return $response;
        }
        return $this->redirect()->toRoute('home');
    }

    /**
     * @return InfoTable object
     */
    private function getInfoTable()
    {
        if (!$this->infoTable) {
            $sm = $this->getServiceLocator();
            $this->infoTable = $sm->get('InfoTable');
        }
        return $this->infoTable;
    }
}