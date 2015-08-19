<?php
namespace Info\Controller;

use Application\Service\MailService;
use Info\Form\PartnerForm;
use Info\Model\PartnerRequest;
use Info\Model\Partners;
use Services\Controller\ServicesController;
use User\Model\RoleLinker;
use User\Service\UserService;
use Zend\Crypt\Password\Bcrypt;
use Zend\Http\Request;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use ZfcUser\Entity\User;
use ZfcUserTest\Factory\UserMapperFactoryTest;

class RequestController extends AbstractActionController {

    // Это страничка, где будут выводиться ссылки на странички всех запросов
    public function indexAction() { }

    public function reqPartnerAction(){
        $reqs = $this->getServiceLocator()->get('PartnerRequestTable')->fetchAll();
        return array(
            'reqs' => $reqs
        );
    }

    public function registerPartnerAction() {
        /** @var Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $postData = $request->getPost()->toArray();


            $user = new \ZfcUser\Entity\User();

            $form  = $this->getServiceLocator()->get('CustomRegisterForm');
            $userData = array();
            if ($postData['user_is_spamed']) {
                $userData['user_is_spamed'] = $postData['user_is_spamed'] == "true" ? 1 : 0;
            }
            $userData['user_name'] = $postData['partner_lastname'] . " " . $postData['partner_name'] . " " . $postData['partner_fathername'];
            $userData['user_email'] = $postData['partner_email'];
            $userData['user_tel'] = $postData['partner_tel'];
            $userData['user_city'] = $postData['partner_city'];
            $userData['user_cur_password'] = $postData['partner_password'];
            $userData['user_password_repeat'] = $postData['partner_password_repeat'];

            //пытаемся авторизоваться
            if ($this->zfcUserAuthentication()->hasIdentity()) {
                /** @var User $user */
                $user = $this->zfcUserAuthentication()->getIdentity();

                if ($user->getEmail() == $postData['partner_email']) {
                    if ($user->getIsPartner()) {
                        $response = $this->getResponse();

                        $response->setContent(
                            \Zend\Json\Json::encode(
                                array(
                                    'success' => 1,
                                    'errors' => array('main' => 'user already partner')
                                )
                            )
                        );
                        return $response;
                    }
                    /** @var PartnerForm $partnerForm */
                    $partnerForm = $this->getServiceLocator()->get('PartnerForm');


                    $partnerForm->setData($postData);

                    if ($partnerForm->isValid()) {
                        $partner = new PartnerRequest();
                        $partner->exchangeArray($partnerForm->getData());
                        $prTable = $this->getServiceLocator()->get('PartnerRequestTable');
                        $requestId = $prTable->save($partner);

                        $user->setIsPartner(0);
                        $user->setState(1);
                        /** @var \ZfcUser\Mapper\User $userMapper */
                        $userMapper = $this->getServiceLocator()->get('zfcuser_user_mapper');
                        $result = $userMapper->update($user);


                        list($email, $mailView) = MailService::prepareUserRequestPartnershipMailData($this->serviceLocator, $partner, $user);
                        MailService::sendMail($email, $mailView, "Ваша заявка принята");

                        //сообщаем менеджеру детали нового заказа
                        list($email, $mailView, $from) = MailService::prepareManagerRequestPartnershipMailData($this->serviceLocator, $partner, $requestId, $user);
                        MailService::sendMail($email, $mailView, "Новая заявка по партнёрству номер " . $requestId . " на Aledo", $from);



                        if ($user->getIsPartner()) {
                            UserService::addHistoryAction(
                                $this->getServiceLocator(),
                                $user->getId(),
                                UserService::USER_ACTION_REGISTER,
                                "",
                                time()
                            );
                        }

                        $response = $this->getResponse();

                        $response->setContent(
                            \Zend\Json\Json::encode(
                                array(
                                    'success' => 1,
                                    'errors' => $partnerForm->getMessages()
                                )
                            )
                        );
                        return $response;
                    } else {
                        $response = $this->getResponse();

                        $response->setContent(
                            \Zend\Json\Json::encode(
                                array(
                                    'success' => 0,
                                    'errors' => $partnerForm->getMessages()
                                )
                            )
                        );
                        return $response;
                    }
                } else {
                    //пользователь залогинен, но запрашивает партнёрство не для себя
                    $form->setData($userData);
                    if (!$form->isValid()) {
                        /** @var \Zend\Http\Response $response */
                        $response = $this->getResponse();

                        $response->setContent(
                            \Zend\Json\Json::encode(
                                array(
                                    'success' => 0,
                                    'errors' => $form->getMessages()
                                )
                            )
                        );
                        return $response;
                    }
                    $data = $form->getData();
                }
            } else {
                $form->setData($userData);
                if (!$form->isValid()) {
                    /** @var \Zend\Http\Response $response */
                    $response = $this->getResponse();

                    $response->setContent(
                        \Zend\Json\Json::encode(
                            array(
                                'success' => 0,
                                'errors' => $form->getMessages()
                            )
                        )
                    );
                    return $response;
                }
                $data = $form->getData();
            }


            /* @var $user \ZfcUser\Entity\User */

            $bcrypt = new Bcrypt();
            $bcrypt->setCost(4);
            $user->setPassword($bcrypt->create($data['user_cur_password']));
            $user->setUsername($data['user_name']);
            $user->setIsSpamed($data['user_is_spamed']);
            $user->setEmail($data['user_email']);
            $user->setPhone($data['user_tel']);
            $user->setCity($data['user_city']);
            $user->setIsPartner(0);
            $user->setState(1);
            /** @var \ZfcUser\Mapper\User $userMapper */
            $userMapper = $this->getServiceLocator()->get('zfcuser_user_mapper');
            $result = $userMapper->insert($user, 'user');

            $user->setId($result->getGeneratedValue());
            $userRole = new RoleLinker();
            $userRole->user_id = $user->getId();
            $userRole->role_id = 'user';
            $sm = $this->getServiceLocator();
            $roleLinker = $sm->get('RoleLinkerTable');
            $roleLinker->save($userRole, "user_id");

            $partner = new PartnerRequest();
            $partner->exchangeArray($postData);
            $prTable = $this->getServiceLocator()->get('PartnerRequestTable');
            $requestId = $prTable->save($partner);

            //добро пожаловать на сайт, логин, пароль
            list($email, $mailView) = MailService::prepareRegisterUserMailData($this->serviceLocator, $user, $data['user_cur_password']);
            MailService::sendMail($email, $mailView, "Добро пожаловать на Aledo!");

            if ($user->getIsSpamed()) {
                list($email, $mailView) = MailService::prepareNewSpamedRegisterManagerData($this->serviceLocator, $user);
                MailService::sendMail($email, $mailView, "Новый подписчик на Aledo номер " . $user->getId());
            }

            //зарегался новый юзер. Имя, почта, телефон
            list($email, $mailView) = MailService::prepareRegisterManagerMailData($this->serviceLocator, $user);
            MailService::sendMail($email, $mailView, "На Aledo новый пользователь номер " . $user->getId());

            list($email, $mailView) = MailService::prepareUserRequestPartnershipMailData($this->serviceLocator, $partner, $user);
            MailService::sendMail($email, $mailView, "Ваша заявка принята");

            //сообщаем менеджеру детали нового заказа
            list($email, $mailView, $from) = MailService::prepareManagerRequestPartnershipMailData($this->serviceLocator, $partner, $requestId, $user);
            MailService::sendMail($email, $mailView, "Новая заявка по партнёрству номер " . $requestId . " на Aledo", $from);

            if ($user->getIsPartner()) {
                UserService::addHistoryAction(
                    $this->getServiceLocator(),
                    $user->getId(),
                    UserService::USER_ACTION_REGISTER,
                    "",
                    time()
                );
            }

        }
        $response = $this->getResponse();

        $response->setContent(
            \Zend\Json\Json::encode(
                array(
                    'success' => 1
                )
            )
        );
        return $response;
    }

    public function reqPartnerViewAction(){
        $id = (int) $this->params()->fromRoute('id', 0);
        $req = $this->getServiceLocator()->get('PartnerRequestTable')->find($id);
        if ($req === false) {
            return $this->redirect()->toRoute('info');
        }
        return array(
            'req' => $req
        );
    }

    public function newpasswordAction() {
        return new ViewModel();
    }

    public function reqConsultAction()
    {
        $reqs = $this->getServiceLocator()->get('ConsultRequestTable')->fetchAll();
        return array(
            'reqs' => $reqs
        );
    }
    public function reqConsultViewAction(){
        $id = (int) $this->params()->fromRoute('id', 0);
        $req = $this->getServiceLocator()->get('ConsultRequestTable')->find($id);
        if ($req === false) {
            return $this->redirect()->toRoute('info');
        }
        return array(
            'req' => $req
        );
    }

    public function reqProjectAction()
    {
        $reqs = $this->getServiceLocator()->get('ProjRequestTable')->fetchAll();
        return array(
            'reqs' => $reqs
        );
    }
    public function reqProjectViewAction(){
        $id = (int) $this->params()->fromRoute('id', 0);
        $req = $this->getServiceLocator()->get('ProjRequestTable')->find($id);
        if ($req === false) {
            return $this->redirect()->toRoute('info');
        }
        return array(
            'req' => $req
        );
    }

    public function reqCalcAction()
    {
        $reqs = $this->getServiceLocator()->get('CalcRequestTable')->fetchAll();
        return array(
            'reqs' => $reqs
        );
    }
    public function reqCalcViewAction(){
        $id = (int) $this->params()->fromRoute('id', 0);
        $req = $this->getServiceLocator()->get('CalcRequestTable')->find($id);
        if ($req === false) {
            return $this->redirect()->toRoute('info');
        }
        $goals = ServicesController::getGoals();
        return array(
            'req' => $req,
            'goals' => $goals
        );
    }

    public function reqModernAction()
    {
        $reqs = $this->getServiceLocator()->get('ModernRequestTable')->fetchAll();
        return array(
            'reqs' => $reqs
        );
    }
    public function reqModernViewAction(){
        $id = (int) $this->params()->fromRoute('id', 0);
        $req = $this->getServiceLocator()->get('ModernRequestTable')->find($id);
        if ($req === false) {
            return $this->redirect()->toRoute('info');
        }
        return array(
            'req' => $req
        );
    }

    public function reqMontajAction()
    {
        $reqs = $this->getServiceLocator()->get('MontajRequestTable')->fetchAll();
        return array(
            'reqs' => $reqs
        );
    }
    public function reqMontajViewAction(){
        $id = (int) $this->params()->fromRoute('id', 0);
        $req = $this->getServiceLocator()->get('MontajRequestTable')->find($id);
        if ($req === false) {
            return $this->redirect()->toRoute('info');
        }
        return array(
            'req' => $req
        );
    }
}