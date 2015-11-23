<?php
namespace User\Controller;

use Application\Service\ApplicationService;
use Application\Service\MailService;
use Cart\Model\ProdToOrder;
use Catalog\Service\CatalogService;
use Commercials\Mapper\CommercialMapper;
use User\Model\RoleLinker;
use User\Model\UserHistoryTable;
use User\Service\UserService;
use Zend\Crypt\Password\Bcrypt;
use Zend\Http\Header\SetCookie;
use Zend\Http\Response;
use Zend\Mvc\Controller\AbstractActionController;
use User\Model\UserTable;
use User\Model\RoleLinkerTable;
use User\Model\User;
use Zend\View\Helper\Json;

class UserController extends AbstractActionController
{
    private $userTable;


    public function godModeOnAction()
    {
        $id = $this->params()->fromRoute('id', 0);
        $user = $this->getServiceLocator()->get('UserTable')->find($id);

        $god = $this->zfcUserAuthentication()->getIdentity();
        $userId = $god->getId();
        if (!$user || !$god || !$user->is_partner || $id == $userId) return $this->redirect()->toRoute('zfcadmin');
        $from = $this->params()->fromQuery('from', 'user');
        //$roleLinkers = $this->getServiceLocator()->get('RoleLinkerTable')->find($userId, 'user_id');
//
//        if ($roleLinkers->role_id == 'manager') {
//            if ($user->manager_id != $userId) {
//                 return $this->redirect()->toRoute('admin');
//            }
//        }

        $header = new SetCookie();
        $header->setName('godModeFrom');
        if ($from == 'user') {
            $header->setValue(urlencode('/admin/user/view/' . $id . '/'));
        } elseif ($from == 'discounts') {
            $header->setValue(urlencode('/admin/discounts/partners/' . $id . '/'));
        }

        $header->setDomain($_SERVER['HTTP_HOST']);
        $header->setPath('/');
        $header->setExpires(time() + 86400);
        $this->getResponse()->getHeaders()->addHeader($header);

        $god->setGodModeId($id);
        $user = $this->getServiceLocator()->get('UserTable')->find($god->getId());
        $user->god_mode_id = $id;
        $this->getServiceLocator()->get('UserTable')->save($user);
        return $this->redirect()->toRoute('catalog');
    }

    public function godModeOffAction()
    {
        $god = $this->zfcUserAuthentication()->getIdentity();
        $user = $this->getServiceLocator()->get('UserTable')->find($god->getId());
        $user->god_mode_id = 0;
        $this->getServiceLocator()->get('UserTable')->save($user);
        if (isset($_COOKIE['godModeFrom'])) {
            $from = urldecode($_COOKIE['godModeFrom']);
            setcookie ("godModeFrom", "", time() - 86400);

            return $this->redirect()->toUrl($from);
        } else {
            return $this->redirect()->toRoute('catalog');
        }

    }

    public function editOrderAction() {
        $id = $this->params()->fromRoute('id', 0);
        $user = $this->zfcUserAuthentication()->getIdentity();
        $userId = $user->getId();
        $sl = $this->getServiceLocator();
        $hierarchies = array();
        if (!$id || !$userId) $this->redirect()->toRoute('cabinet');

        $order = $sl->get('OrderTable')->fetchByConds(array('user_id' => $userId, 'id' => $id));
        if (is_array($order)) {
            $order = reset($order);
        }

        if ($order->finished) {
            return $this->redirect()->toRoute('cabinet');
        }

        $inProds = $sl->get('ProdToOrderTable')->fetchByCond('order_id', $order->id);
        $prodIds = array();
        foreach($inProds as $inProd){
            $prodIds[] = $inProd->product_id;
        }
        $products = $sl->get('Catalog/Model/ProductTable')->fetchByCond('id', $prodIds);



        foreach ($products as $prod) {
            $series = $sl->get('Catalog/Model/SeriesTable')->find($prod->series_id);
            $subsection = $sl->get('Catalog/Model/SubSectionTable')->find($series->subsection_id);
            $section = $sl->get('Catalog/Model/SectionTable')->find($subsection->section_id);
            if ($this->zfcUserAuthentication()->hasIdentity()) {
                $hierarchies[$prod->id][\Catalog\Controller\AdminController::PRODUCT_TABLE] = $prod->id;
                $hierarchies[$prod->id][\Catalog\Controller\AdminController::SERIES_TABLE] = $series->id;
                $hierarchies[$prod->id][\Catalog\Controller\AdminController::SUBSECTION_TABLE] = $subsection->id;
                $hierarchies[$prod->id][\Catalog\Controller\AdminController::SECTION_TABLE] = $section->id;
            }
        }

        $prods = ApplicationService::makeIdArrayFromObjectArray($products);

        foreach($inProds as $inProd){
            $prods[$inProd->product_id]->order_price = $inProd->price;
            $prods[$inProd->product_id]->order_count = $inProd->count;
            $order->products[] = $prods[$inProd->product_id];
        }

        $return = array(
            'user' => $user,
            'order' => $order,
        );
        if ($user && $user->getisPartner()) {
            $discounts = $sl->get('DiscountTable')->fetchByUserId($user->getId(), $user->getPartnerGroup(), false, 0, $sl);
            $return['discounts'] = $discounts;
        }
		
		$priceRequestTable = $sl->get('PriceRequestTable');
		$requests = $priceRequestTable->fetchAllSorted();
		
		$return['hierarchies'] = $hierarchies;
		$return['requests'] = $requests;

        return $return;
    }

    public function saveOrderAction()
    {
        $request = $this->getRequest();
        if (!$request->isPost()) return $this->redirect()->toRoute('cabinet');

        $post = $request->getPost()->toArray();

        $orderId = $this->params()->fromRoute('id', 0);
        if (!$orderId) return $this->redirect()->toRoute('cabinet');
        $comment = $post['comment'];
        $products = $post['products'];

        $prods = array();
        $j = 0;
        for ($i=0; $i<count($products); $i++) {
            if ($i%2 == 0) {
                $prods[$j]['id'] = $products[$i]['id'];
            } else {
                $prods[$j]['count'] = $products[$i]['count'];
                $j++;
            }

        }
        $user = $this->zfcUserAuthentication()->getIdentity();
        $sl = $this->getServiceLocator();

        $order = $sl->get('OrderTable')->fetchByConds(array('user_id' => $user->getId(), 'id' => $orderId));
        if (is_array($order)) {
            $order = reset($order);
        }

        $productsInfo = array();
        $hierarchies = array();
        $discounts = $sl->get('DiscountTable')->fetchByUserId($user->getId(), $user->getPartnerGroup(), false, 0, $sl);
        $orderSumm = 0;
        foreach ($prods as $prod) {
            $prodCount = $prod['count'];
            $prodId = $prod['id'];

            $productsInfo[$prodId] = $sl->get('Catalog/Model/ProductTable')->find($prodId);
            $series = $sl->get('Catalog/Model/SeriesTable')->find($productsInfo[$prodId]->series_id);
            $subsection = $sl->get('Catalog/Model/SubSectionTable')->find($series->subsection_id);
            $section = $sl->get('Catalog/Model/SectionTable')->find($subsection->section_id);

            if ($this->zfcUserAuthentication()->hasIdentity()) {
                $hierarchies[$prodId][\Catalog\Controller\AdminController::PRODUCT_TABLE] = $prodId;
                $hierarchies[$prodId][\Catalog\Controller\AdminController::SERIES_TABLE] = $series->id;
                $hierarchies[$prodId][\Catalog\Controller\AdminController::SUBSECTION_TABLE] = $subsection->id;
                $hierarchies[$prodId][\Catalog\Controller\AdminController::SECTION_TABLE] = $section->id;
            }
			
			$priceRequestTable = $sl->get('PriceRequestTable');
			$requests = $priceRequestTable->fetchAllSorted();
			
            $productsInfo[$prodId]->count = $prodCount;
            $truePrice = CatalogService::getTruePrice(
                $productsInfo[$prodId]->price_without_nds,
                $user,
                $hierarchies[$prodId],
                $discounts,
                $productsInfo[$prodId]->opt2,
				$requests,
				true
            );
            $productsInfo[$prodId]->price = $truePrice;
            $orderSumm += $productsInfo[$prodId]->price*$prodCount;
        }

        $order->summ = $orderSumm;
        $order->comment = $comment;
        $sl->get('OrderTable')->save($order);

        $orderProdsTable = $sl->get('ProdToOrderTable');
        $ptos = $orderProdsTable->fetchByCond('order_id', $orderId);
        foreach ($ptos as $onePto) {
            $orderProdsTable->del($onePto);
        }

        foreach($productsInfo as $id=>$prodData)
        {
            $pto = new ProdToOrder();
            $pto->exchangeArray(array(
                'order_id' => $orderId,
                'product_id' => $id,
                'price' => $prodData->price,
                'count' => floor($prodData->count),
            ));
            $orderProdsTable->save($pto);
        }

        list($email, $mailView) = MailService::prepareChangeOrderManagerMailData($this->serviceLocator, $user, $orderId, $order, $productsInfo);

        if ($email != MailService::getCurrentManagerMail()) {
            MailService::sendMail($email, $mailView, "Корректировка заказа номер " . $orderId . " на Aledo");
        }

        MailService::sendMail(MailService::getCurrentManagerMail(), $mailView, "Корректировка заказа номер " . $orderId . " на Aledo");

        list($email, $mailView) = MailService::prepareChangeOrderUserMailData($this->serviceLocator, $user, $orderId, $order, $productsInfo);
        MailService::sendMail($email, $mailView, "Корректировка заказа номер " . $orderId . " на Aledo");

        if ($user->getIsPartner()) {
            UserService::addHistoryAction(
                $sl,
                $user->getId(),
                UserService::USER_ACTION_EDIT_ORDER,
                "/admin/requests/order/" . $orderId . '/',
                time()
            );
        }

        return $this->redirect()->toRoute('cabinet');
    }

    public function indexAction()
    {
        $this->layout()->pageTitle = 'Личный кабинет';

        $id = $this->zfcUserAuthentication()->getIdentity()->getId();
        $user = $this->getUserTable()->find($id);

        $sl = $this->getServiceLocator();
        $ordersRaw = $sl->get('OrderTable')->fetchByCond('user_id', $id);
        $orders = ApplicationService::makeIdArrayFromObjectArray($ordersRaw);
        $ordersIds = array();
        foreach($orders as $order){
            $ordersIds[] = $order->id;
        }

        if (count($ordersIds) > 0) {
            $inProds = $sl->get('ProdToOrderTable')->fetchByCond('order_id', $ordersIds);
            $prodIds = array();
            foreach($inProds as $inProd){
                $prodIds[] = $inProd->product_id;
            }

            $products = $sl->get('Catalog/Model/ProductTable')->fetchByCond('id', $prodIds);

            $prods = ApplicationService::makeIdArrayFromObjectArray($products);

            foreach($inProds as $inProd){
                $prods[$inProd->product_id]->order_price = $inProd->price;
                $prods[$inProd->product_id]->order_count = $inProd->count;

                $orders[$inProd->order_id]->products[] = clone($prods[$inProd->product_id]);
            }
        }
        $commercialsJson = '';
        $cm = CommercialMapper::getInstance($sl);
        $roleLinker = $this->getServiceLocator()->get('RoleLinkerTable')->find($user->user_id, 'user_id');
        $role = $roleLinker->role_id;

        if ($role == 'admin' || $role == 'manager') {
            $commercials = $cm->getList($user->user_id);
            $commercialsJson = \Zend\Json\Json::encode($commercials);
		}
		
		$allUsers =  $sl->get('UserTable')->fetchAll();
        $partners = array();
        $nonPartners = array();
        foreach ($allUsers as $usr) {
            if ($usr->is_partner == 1) {
                $partners[] = $usr;
            } else {
                $nonPartners[] = $usr;
            }
        }

        $allGroups =  $sl->get('PartnerGroupTable')->fetchAll();
        $groupsNamesById = array(0 => '-');
        foreach( $allGroups as $group) {
            $groupsNamesById[$group->id] = $group->name;
        }

        $managers = array(0 => '-');
        $managersIds = array();
        foreach ($allUsers as $entity) {
            $managersIds[] = $entity->manager_id;
        }
        if (count($managersIds)) {
            $allManagers = $this->getServiceLocator()->get('UserTable')->fetchByCond('user_id', $managersIds);
            foreach ($allManagers as $currentManager) {
                $managers[$currentManager->user_id] = $currentManager->username;
            }
        }

        foreach ($partners as &$entity1) {
            $entity1->user_id = (int)$entity1->user_id;
            if (isset($groupsNamesById[$entity1->partner_group])) {
                $entity1->group_name = $groupsNamesById[$entity1->partner_group];
            }
            if (isset($managers[$entity1->manager_id])) {
                $entity1->manager_name = $managers[$entity1->manager_id];
            }
        }

        $entitiesJson = \Zend\Json\Json::encode($partners);
		
        return array(
			'usersJson' => $entitiesJson,
            'user' => $user,
            'role' => $role,
            'orders' => $orders,
            'commercialsJson' => $commercialsJson,
            'pageTitle' => 'Личный кабинет',
            'breadCrumbs'  => array(
                array('link'=> $this->url()->fromRoute('home'), 'text'=>ucfirst('Главная')),
            ),
        );
    }

    private function generatePassword($length = 8){
        $chars = 'abdefhiknrstyzABDEFGHKNQRSTYZ23456789';
        $numChars = strlen($chars);
        $string = '';
        for ($i = 0; $i < $length; $i++) {
            $string .= substr($chars, rand(1, $numChars) - 1, 1);
        }
        return $string;
    }

    public function rememberpasswordAction()
    {
        $token = $this->params()->fromQuery('token', false);
        if (!$token) {
            return $this->redirect()->toRoute('home');
        }
        $user = $this->getUserTable()->fetchByCond('token', $token);
        if (is_array($user) && count($user) > 0) {
            $user = $user[0];
        }
        if (!$user) {
            return $this->redirect()->toRoute('home');
        }
        $password = $this->generatePassword(8);
        $bcrypt = new Bcrypt();
        $bcrypt->setCost(4);
        $user->password = $bcrypt->create($password);

        /** @var \ZfcUser\Mapper\User $userMapper */
        $this->getUserTable()->save($user);
        list($email, $mailView) = MailService::prepareRememberPasswordMailData($this->getServiceLocator(), $user, $password);
        MailService::sendMail($email, $mailView, "Новый пароль на " . $_SERVER['SERVER_NAME']);
        if ($user->is_partner) {
            UserService::addHistoryAction(
                $this->getServiceLocator(),
                $user->user_id,
                UserService::USER_ACTION_NEW_PASSWORD,
                "",
                time()
            );
        }
        return $this->redirect()->toRoute('request');
    }

    public function forgotAction()
    {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $post = $request->getPost()->toArray();
        } else {
            return $this->redirect()->toRoute('home');
        }
        $email = $post['identity'];
        $user = $this->getUserTable()->fetchByCond('email', $email);
        if (is_array($user) && count($user) > 0) {
            $user = $user[0];
        }

        if (!$user) {
            $response = $this->getResponse();
            $response->setContent(
                \Zend\Json\Json::encode(
                    array(
                        'success' => 0,
                        'messages' => array('global' => array("There is no user here"))
                    )
                )
            );
            return $response;
        } else {

            $token = $this->generatePassword(31);
            $user->token = $token;

            /** @var \ZfcUser\Mapper\User $userMapper */
            $this->getUserTable()->save($user);
            list($email, $mailView) = MailService::prepareForgotPasswordMailData($this->getServiceLocator(), $user, $token);
            MailService::sendMail($email, $mailView, "Запрос на восстановление пароля для " . $_SERVER['SERVER_NAME']);
            if ($user->is_partner) {
                UserService::addHistoryAction(
                    $this->getServiceLocator(),
                    $user->user_id,
                    UserService::USER_ACTION_NEW_PASSWORD,
                    "",
                    time()
                );
            }
        }
        $response = $this->getResponse();
        $response->setContent(
            \Zend\Json\Json::encode(
                array(
                    'success' => 1,
                    'messages' => array()
                )
            )
        );
        return $response;
    }

    private function authenticate($redirectUrl)
    {
        /** @var \Zend\Http\Request $request */
        $request = $this->getRequest();
        $postContent = $request->getPost();

        // если пришли из логина, у нас уже установлены поля credential и identity
        // если пришли из регистрации, требуется трактовать password как credential и email как identity
        // это всё какой-то дикий костыль, и я приношу свои извинения тому бедолаге, который будет это разгребать
        if (!($postContent->get('credential'))) {
            $postContent->set('credential', $postContent->get('user_cur_password'));
        }
        if (!($postContent->get('identity'))) {
            $postContent->set('identity', $postContent->get('user_email'));
        }

        $request->setPost($postContent);
        if ($this->zfcUserAuthentication()->getAuthService()->hasIdentity()) {
            return $this->redirect()->toUrl($redirectUrl);
        }

        /** @var \ZfcUser\Authentication\Adapter\AdapterChain $adapter */

        $adapter = $this->zfcUserAuthentication()->getAuthAdapter();

        $redirect = $this->params()->fromPost('redirect', $this->params()->fromQuery('redirect', false));


        $result = $adapter->prepareForAuthentication($this->getRequest());

        // Return early if an adapter returned a response
        if ($result instanceof Response) {
            return $result;
        }
        /** @var \Zend\Authentication\Result $auth */
        $auth = $this->zfcUserAuthentication()->getAuthService()->authenticate($adapter);

        if (!$auth->isValid()) {
            $this->flashMessenger()->setNamespace('zfcuser-login-form')->addMessage('Authentication failed. Please try again.');
            $code = $auth->getCode();

            $messageField = "global";
            switch ($code) {
                case \Zend\Authentication\Result::FAILURE_IDENTITY_NOT_FOUND :
                    $messageField = "identity";
                    break;
                case \Zend\Authentication\Result::FAILURE_IDENTITY_AMBIGUOUS :
                    $messageField = "identity";
                    break;
                case \Zend\Authentication\Result::FAILURE_UNCATEGORIZED:
                    $messageField = "identity";
                    break;
                case \Zend\Authentication\Result::FAILURE_CREDENTIAL_INVALID :
                    $messageField = "credential";
                    break;
                default :
                    $messageField = "global";
            }
            $errors = $auth->getMessages();
            $errorText = array();

            if (count($errors) > 0 && $code <= 0) {
                $errorText[$messageField] = $errors;
            }

            $adapter->resetAdapters();
            $response = $this->getResponse();
            $response->setContent(
                \Zend\Json\Json::encode(
                    array(
                        'success' => 0,
                        'messages' => $errorText
                    )
                )
            );
            return $response;
        }
        $response = $this->getResponse();
        $response->setContent(
            \Zend\Json\Json::encode(
                array(
                    'success' => 1,
                    'messages' => array(),
                    'redirect' => $redirect
                )
            )
        );
        if ($this->zfcUserAuthentication()->getIdentity()->getIsPartner()) {
            UserService::addHistoryAction(
                $this->getServiceLocator(),
                $this->zfcUserAuthentication()->getIdentity()->getId(),
                UserService::USER_ACTION_LOGIN,
                $redirect,
                time()
            );
        }
        return $response;
    }

    public function loginAction()
    {

        /** @var \Zend\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $post = $request->getPost()->toArray();
        } else {
            return $this->redirect()->toRoute('home');
        }

        if ($this->zfcUserAuthentication()->getAuthService()->hasIdentity()) {
            $response = $this->getResponse();
            return $this->redirect()->toRoute("home");

        }


        $form = $this->getServiceLocator()->get('zfcuser_login_form');

            if (isset($post['redirect'])) {
                $redirect = $post['redirect'];
            } else {
            $redirect = '/home';
        }

        $form->setData($post);

        if (!$form->isValid()) {
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

        // clear adapters
        $this->zfcUserAuthentication()->getAuthAdapter()->resetAdapters();
        $this->zfcUserAuthentication()->getAuthService()->clearIdentity();

        //return $this->forward()->dispatch('zfcuser', array('action' => 'authenticate'));
        $r = $this->authenticate($redirect);

        return $r;
    }

    public function logoutAction() {
        $this->zfcUserAuthentication()->getAuthAdapter()->resetAdapters();
        $this->zfcUserAuthentication()->getAuthAdapter()->logoutAdapters();
        $this->zfcUserAuthentication()->getAuthService()->clearIdentity();

        $redirect = $this->params()->fromQuery('redirect', false);


        if ($redirect) {
            return $this->redirect()->toUrl($redirect)->getHeaders()->addHeaders(
                array(
                    'Cache-Control' => 'no-cache',
                    'Expires' => '-1',
                )
            );
        }

        return $this->redirect()->toRoute('home')->getHeaders()->addHeaders(
            array(
                'Cache-Control' => 'no-cache',
                'Expires' => '-1',
            )
        );
    }

    public function updateRegisterInfoAction() {
        /** @var \Zend\Http\Request $request */
        $request = $this->getRequest();
        $user = $this->zfcUserAuthentication()->getIdentity();
        if ($request->isPost()){
            $data = $request->getPost()->toArray();
            $form  = $this->getServiceLocator()->get('zfcuser_updateregisterinfo_form');


            if (!$this->zfcUserAuthentication()->getAuthService()->hasIdentity()) {
                $response = $this->getResponse();
                $response->setContent(
                    \Zend\Json\Json::encode(
                        array(
                            'success' => 0,
                            'messages' => array('global' => "You are not login yet")
                        )
                    )
                );
                return $response;

            } else {

            }

            $form->setData($data);
            if (!$form->isValid()) {
                $response = $this->getResponse();

                $messages = $form->getMessages();
                $response->setContent(
                    \Zend\Json\Json::encode(
                        array(
                            'success' => 0,
                            'messages' => $messages
                        )
                    )
                );
                return $response;
            }



            $data = $form->getData();

            /* @var $user \ZfcUser\Entity\User */
            if (isset($data['password']) && !empty($data['password'])) {
                $bcrypt = new Bcrypt();
                $bcrypt->setCost(4);
                $passHash = $bcrypt->verify($data['password'], $user->getPassword());

                if (!$passHash) {
                    $response = $this->getResponse();

                    $response->setContent(
                        \Zend\Json\Json::encode(
                            array(
                                'success' => 0,

                                'messages' => array('password' => array("PasswordIncorrect" => "")),
                                'passHash' => $passHash
                            )
                        )
                    );
                    return $response;
                }
            } else {
                $response = $this->getResponse();

                $response->setContent(
                    \Zend\Json\Json::encode(
                        array(
                            'success' => 0,
                            'messages' => array('global' => "Please, input your password")
                        )
                    )
                );
                return $response;
            }

            $user->setUsername($data['username']);
            $user->setIsSpamed(($data['is_spamed'] == 'true' ) ? true : false);
            $user->setPhone($data['phone']);
            $user->setCity($data['city']);
            $user->setState(1);
            /** @var \ZfcUser\Mapper\User $userMapper */
            $userMapper = $this->getServiceLocator()->get('zfcuser_user_mapper');
            $userMapper->update($user);
        } else {
            $response = $this->getResponse();
            $response->setContent(
                \Zend\Json\Json::encode(
                    array(
                        'success' => 1,
                        'messages' => array('global' => 'no ajax request')
                    )
                )
            );
        }

        $response = $this->getResponse();
        $response->setContent(
            \Zend\Json\Json::encode(
                array(
                    'success' => 1,
                    'messages' => array()
                )
            )
        );
        if ($user->getIsPartner()) {
            UserService::addHistoryAction(
                $this->getServiceLocator(),
                $user->getId(),
                UserService::USER_ACTION_EDIT_PROFILE_INFO,
                "",
                time()
            );
        }
        return $response;
    }

    public function changepasswordAction()
    {
        /** @var \Zend\Http\Request $request */
        $request = $this->getRequest();
        $user = $this->zfcUserAuthentication()->getIdentity();
        if ($request->isPost()){
            $data = $request->getPost()->toArray();
            $form  = $this->getServiceLocator()->get('zfcuser_changepassword_form');


            if (!$this->zfcUserAuthentication()->getAuthService()->hasIdentity()) {
                $response = $this->getResponse();
                $response->setContent(
                    \Zend\Json\Json::encode(
                        array(
                            'success' => 0,
                            'messages' => array('global' => "You are not login yet")
                        )
                    )
                );
                return $response;

            }

            $form->setData($data);
            if (!$form->isValid()) {
                $response = $this->getResponse();

                $response->setContent(
                    \Zend\Json\Json::encode(
                        array(
                            'success' => 0,
                            'messages' => $form->getMessages()
                        )
                    )
                );
                return $response;
            }

            $bcrypt = new Bcrypt();
            $bcrypt->setCost(4);
            $passHash = $bcrypt->verify($data['credential'], $user->getPassword());
            if (!$passHash) {
                $response = $this->getResponse();

                $response->setContent(
                    \Zend\Json\Json::encode(
                        array(
                            'success' => 0,

                            'messages' => array('credential' => array("PasswordIncorrect" => "")),
                            'passHash' => $passHash
                        )
                    )
                );
                return $response;
            }

            $data = $form->getData();
            /* @var $user \ZfcUser\Entity\User */
            if (isset($data['newCredential']) && !empty($data['newCredential'])) {
                $bcrypt = new Bcrypt();
                $bcrypt->setCost(4);
                $user->setPassword($bcrypt->create($data['newCredential']));
            }

            /** @var \ZfcUser\Mapper\User $userMapper */
            $userMapper = $this->getServiceLocator()->get('zfcuser_user_mapper');
            $userMapper->update($user);
        } else {
            $response = $this->getResponse();
            $response->setContent(
                \Zend\Json\Json::encode(
                    array(
                        'success' => 1,
                        'messages' => array('global' => 'no ajax request')
                    )
                )
            );
        }
        $response = $this->getResponse();
        $response->setContent(
            \Zend\Json\Json::encode(
                array(
                    'success' => 1,
                    'messages' => array()
                )
            )
        );

        if ($user->getIsPartner()) {
            UserService::addHistoryAction(
                $this->getServiceLocator(),
                $user->getId(),
                UserService::USER_ACTION_EDIT_PROFILE_INFO,
                "",
                time()
            );
        }
        return $response;
    }

    public function registerAction()
    {
        /** @var \Zend\Http\Request $request */
        $request = $this->getRequest();

        if ($request->isPost()) {
            $data = $request->getPost()->toArray();
            /** @var \User\Form\Register $form */
            $form  = $this->getServiceLocator()->get('CustomRegisterForm');
            $user = new \ZfcUser\Entity\User();
            if ($data['user_is_spamed']) {
                $data['user_is_spamed'] = $data['user_is_spamed'] == "true" ? 1 : 0;
            }
            $form->setData($data);
            if (!$form->isValid()) {
                /** @var \Zend\Http\Response $response */
                $response = $this->getResponse();

                $response->setContent(
                    \Zend\Json\Json::encode(
                        array(
                            'success' => 0,
                            'errors' => $form->getMessages(),
                            'messages' => $form->getMessages()
                        )
                    )
                );
                return $response;
            }

            $data = $form->getData();
            /* @var $user \ZfcUser\Entity\User */

            $bcrypt = new Bcrypt();
            $bcrypt->setCost(4);
            $user->setPassword($bcrypt->create($data['user_cur_password']));
            $user->setUsername($data['user_name']);
            $user->setIsSpamed($data['user_is_spamed']);
            $user->setEmail($data['user_email']);
            $user->setPhone($data['user_tel']);
            $user->setCity($data['user_city']);
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
            if (isset($post['redirect'])) {
                $redirect = $post['redirect'];
            } else {
                $redirect = 'home';
            }
            //добро пожаловать на сайт, логин, пароль
            list($email, $mailView) = MailService::prepareRegisterUserMailData($this->serviceLocator, $user, $data['user_cur_password']);
            MailService::sendMail($email, $mailView, "Добро пожаловать на Aledo!");

            if ($user->getIsSpamed()) {
                list($email, $mailView) = MailService::prepareNewSpamedRegisterManagerData($this->serviceLocator, $user);
                MailService::sendMail($email, $mailView, "Новый подписчик на Aledo номер " . $user->getId());
            }
            //маразм конечно, при регистрации юзер не будет партнёром, но...
            if ($user->getIsPartner()) {
                UserService::addHistoryAction(
                    $this->getServiceLocator(),
                    $user->getId(),
                    UserService::USER_ACTION_REGISTER,
                    $redirect,
                    time()
                );
            }
            //зарегался новый юзер. Имя, почта, телефон
            list($email, $mailView) = MailService::prepareRegisterManagerMailData($this->serviceLocator, $user);
            MailService::sendMail($email, $mailView, "На Aledo новый пользователь номер " . $user->getId());

            return $this->authenticate($redirect);

        } else {
            $response = $this->getResponse();
            $response->setContent(
                \Zend\Json\Json::encode(
                    array(
                        'success' => 0,
                        'messages' => array('global' => 'no ajax query')
                    )
                )
            );
            return $response;
        }
    }

    /**
     * @return UserTable array|object
     */
    public function getUserTable()
    {
        if (!$this->userTable) {
            $sm = $this->getServiceLocator();
            $this->userTable = $sm->get('UserTable');
        }
        return $this->userTable;
    }
}