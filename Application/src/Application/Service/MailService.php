<?php
namespace Application\Service;

use BjyAuthorize\Guard\Controller;
use Catalog\Controller\CatalogController;
use Catalog\Mapper\CatalogMapper;
use Catalog\Service\CatalogService;
use Discount\Model\Discount;
use Discount\Service\DiscountService;
use Info\Model\PartnerRequest;
use Reports\Model\Report;
use Services\Controller\ServicesController;
use Zend\Mail\Headers;
use Zend\Mail\Message;
use Zend\Mail\Transport\Sendmail as SendmailTransport;
use Zend\View\Model\ViewModel;
use Zend\View\Renderer\PhpRenderer;
use Zend\View\View;

class MailService
{
    public static $currentManagerMail = "info@aledo-pro.ru";
    private static $kaledoscopManagerMail = "info@kaledoscop.ru";
    public static $developerMail = "deflopian@gmail.com";
    const CURRENT_DOMEN = "aledo-pro.ru";

    public static function sendMail($email, $data, $subject = "Новый заказ", $from = false)
    {
        $message = new Message();
        $message->setEncoding("UTF-8");
        $bodyPart = new \Zend\Mime\Message();

        $bodyMessage = new \Zend\Mime\Part( $data);
        $bodyMessage->type = 'text/html';
        $bodyMessage->charset = 'utf-8';

        $bodyPart->setParts(array($bodyMessage));

        $fromMail = $from ? $from : self::$currentManagerMail;

        $message->setBody($bodyPart);
        $message->addFrom($fromMail, 'Aledo')
            ->addTo($email)
            ->setSubject($subject);

        $transport = new SendmailTransport();
        $transport->send($message);
    }



    /**
     * @param $sl
     * @param $user \ZfcUser\Entity\User | \Cart\Model\OrderUser
     * @param $orderId int
     * @param $order \Cart\Model\Order
     * @param $productsInfo \Catalog\Model\Product[]
     * @param $ptos \Cart\Model\ProdToOrder[]
     * @return array
     */
    public static function prepareOrderUserMailData($sl, $user, $orderId, $order, $productsInfo, $ptos)
    {
        $email = $user->getEmail();
        $view = new ViewModel(array(
            'username' => $user->getUsername(),
            'orderId' => $orderId,
            'orderInfo' => $order,
            'products' => $productsInfo,
            'prodsInfo' => $ptos,
        ));
        $view->setTerminal(true);
        $view->setTemplate('application/index/email/email-after-order-user');
        $formView = $sl->get('viewrenderer')->render($view);
        $managerId = false;

        if (is_callable(array($user, 'getManagerId'))) {
            $managerId = $user->getManagerId();

        }

        $managerMail = self::$currentManagerMail;
        if ($managerId) {
            $manager = $sl->get('UserTable')->find($managerId);
            $managerMail = $manager->email;
        }
        return array($email, $formView, $managerMail);
    }

    /**
     * @param $sl
     * @param \User\Model\User $user
     * @param $managerId
     *
     * @return array
     */
    public static function prepareNewManagerManagerMailData($sl, $user, $managerId)
    {
        $manager = $sl->get('UserTable')->find($managerId);
        $email = $manager->email;
        $params = array();

        $params['Имя'] = !empty($user->alias) ? $user->alias : $user->username;
        $params['E-Mail'] = $user->email;
        $params['Телефон'] = $user->phone;
        $params['Город'] = $user->city;
        $params['Подписка'] = $user->is_spamed ? 'Да' : 'Нет';

        $view = new ViewModel(array(
            'params' => $params,
            'username' => $manager->username
        ));
        $view->setTerminal(true);
        $view->setTemplate('application/index/email/email-manager-newmanager-add');
        $formView = $sl->get('viewrenderer')->render($view);
        return array($email, $formView);
    }

    /**
     * @param $sl
     * @param \User\Model\User $user
     * @param $managerId
     *
     * @return array
     */
    public static function prepareNewManagerUserMailData($sl, $user, $managerId)
    {
        $manager = $sl->get('UserTable')->find($managerId);
        $email = $user->email;


        $view = new ViewModel(array(
            'manager' => $manager,
            'username' => $user->username
        ));
        $view->setTerminal(true);
        $view->setTemplate('application/index/email/email-user-newmanager-add');
        $formView = $sl->get('viewrenderer')->render($view);
        return array($email, $formView);
    }

    /**
     * @param $sl
     * @param \User\Model\User $user
     *
     * @return array
     */
    public static function prepareUserPartnershipMailData($sl, $user)
    {
        $email = $user->email;


        $view = new ViewModel(array(
            'username' => $user->username
        ));
        $view->setTerminal(true);
        $view->setTemplate('application/index/email/email-user-add-to-partner');
        $formView = $sl->get('viewrenderer')->render($view);
        return array($email, $formView);
    }

    /**
     * @param $sl
     * @param $report Report
     *
     * @return array
     */
    public static function prepareReportData($sl, $report)
    {
        $view = new ViewModel(array(
            'report' => $report
        ));
        $view->setTerminal(true);
        $view->setTemplate('application/index/email/simple-report');
        $formView = $sl->get('viewrenderer')->render($view);
        return array(self::$currentManagerMail, $formView);
    }


    /**
     * @param $sl
     * @param PartnerRequest $request
     *
     * @return array
     */
    public static function prepareUserRequestPartnershipMailData($sl, $request)
    {
        $email = $request->email;


        $view = new ViewModel(array(
            'username' => $request->name
        ));
        $view->setTerminal(true);
        $view->setTemplate('application/index/email/email-user-request-partnership');
        $formView = $sl->get('viewrenderer')->render($view);
        return array($email, $formView);
    }


    /**
     * @param $sl
     * @param PartnerRequest $partnerRequest
     *
     * @return array
     */
    public static function prepareManagerRequestPartnershipMailData($sl, $partnerRequest, $requestId)
    {
        $params1 = array();
        $params2 = array();

        $params1['Ф.И.О.'] = $partnerRequest->name ? $partnerRequest->name : "не указано";
        $params1['E-Mail'] = $partnerRequest->email ? $partnerRequest->email : "не указано";
        $params1['Род деятельности'] = $partnerRequest->activity ? $partnerRequest->activity : "не указано";
        $params1['Должность'] = $partnerRequest->job ? $partnerRequest->job : "не указано";
        $params1['Номер телефона'] = $partnerRequest->phone ? $partnerRequest->phone : "не указано";

        $params2['Название компании'] = $partnerRequest->company_name ? $partnerRequest->company_name : "не указано";
        $params2['Сфера деятельности'] = $partnerRequest->company_activity ? $partnerRequest->company_activity : "не указано";
        $params2['Примеры брендов'] = $partnerRequest->brands_sample ? $partnerRequest->brands_sample : "не указано";
        $params2['Индекс'] = $partnerRequest->post_index ? $partnerRequest->post_index : "не указано";
        $params2['Область/Город'] = $partnerRequest->city ? $partnerRequest->city : "не указано";
        $params2['Улица, номер дома офиса'] = $partnerRequest->adress ? $partnerRequest->adress : "не указано";
        $params2['Телефон'] = $partnerRequest->company_phone ? $partnerRequest->company_phone : "не указано";
        $params2['Факс'] = $partnerRequest->company_fax ? $partnerRequest->company_fax : "не указано";
        $params2['E-mail'] = $partnerRequest->company_email ? $partnerRequest->company_email : "не указано";
        $params2['Website'] = $partnerRequest->company_website ? $partnerRequest->company_website : "не указано";

        $view = new ViewModel(array(
            'paramsClient' => $params1,
            'paramsCompany' => $params2,
            'requestId' => $requestId,
        ));
        $view->setTerminal(true);
        $view->setTemplate('application/index/email/email-manager-request-partnership');
        $formView = $sl->get('viewrenderer')->render($view);
        return array(self::$currentManagerMail, $formView, $partnerRequest->email ? $partnerRequest->email : $partnerRequest->company_email);
    }


    /**
     * @param $sl
     * @param $user \ZfcUser\Entity\User | \Cart\Model\OrderUser
     * @param $orderId int
     * @param $order \Cart\Model\Order
     * @param $productsInfo \Catalog\Model\Product[]
     * @param $ptos \Cart\Model\ProdToOrder[]
     * @return array
     */
    public static function prepareVacancyMailData($sl, $requestId, $requestDetails, $vacancy)
    {
        $view = new ViewModel(array(
            'requestId' => $requestId,
            'requestDetails'     => $requestDetails,
            'vacancy'   => $vacancy
        ));
        $view->setTerminal(true);
        $view->setTemplate('application/index/email/email-vacancy-manager');
        $formView = $sl->get('viewrenderer')->render($view);
        return array(self::$currentManagerMail, $formView);
    }

    /**
     * @param $sl
     * @param $user \User\Model\User
     * @param $token string
     * @return array
     */
    public static function prepareForgotPasswordMailData($sl, $user, $token)
    {
        $email = $user->email;
        $login = $email;
        $view = new ViewModel(array(
            'login' => $login,
            'username' => $user->username,
            'token' => $token
        ));
        $view->setTerminal(true);
        $view->setTemplate('application/index/email/email-user-forgot-password');
        $formView = $sl->get('viewrenderer')->render($view);
        return array($email, $formView);
    }


    /**
     * @param $sl
     * @param $user \ZfcUser\Entity\User | \Cart\Model\OrderUser
     * @param $password string
     * @return array
     */
    public static function prepareRememberPasswordMailData($sl, $user, $password)
    {
        $email = $user->email;
        $login = $email;
        $view = new ViewModel(array(
            'login' => $login,
            'username' => $user->username,
            'password' => $password
        ));
        $view->setTerminal(true);
        $view->setTemplate('application/index/email/email-user-remember-password');
        $formView = $sl->get('viewrenderer')->render($view);
        return array($email, $formView);
    }

    /**
     * @param $sl
     * @param $user \ZfcUser\Entity\User | \Cart\Model\OrderUser
     * @param $orderId int
     * @param $order \Cart\Model\Order
     * @param $productsInfo \Catalog\Model\Product[]
     * @param $ptos \Cart\Model\ProdToOrder[]
     * @param $isRegistered boolean
     * @param $filePath string
     * @return array
     */
    public static function prepareOrderManagerMailData($sl, $user, $orderId, $order, $productsInfo, $ptos, $isRegistered = false, $filePath = null)
    {
        $params = array();
        $username = $user->getUsername();
        if (is_callable(array($user, 'getAlias'))) {
            $useralias = $user->getAlias();
        } else {
            $useralias = null;
        }

        $params['Статус'] = $isRegistered ? "Зарегистрированный" : "Без регистрации";
        $params['Ф.И.О.'] =  !empty($useralias) ? $useralias : $username;
        $params['E-Mail'] = $user->getEmail();

        if (is_callable(array($user, 'getManagerId'))) {
            $managerId = $user->getManagerId();
        } else {
            $managerId = false;
        }


        if ($managerId) {
            $manager = $sl->get('UserTable')->find($managerId);
            $params['Ответственный менеджер'] = $manager->username;
        }

        if ($user->getPhone() != "") {
            $params['Телефон'] = $user->getPhone();
        }
        if ($user->getCity() != "") {
            $params['Город'] = $user->getCity();
        }
        if ($user->getIsSpamed() != "") {
            $params['Подписка'] = $user->getIsSpamed() ? 'Да' : 'Нет';
        }

        $view = new ViewModel(array(
            'orderId' => $orderId,
            'orderInfo' => $order,
            'products' => $productsInfo,
            'prodsInfo' => $ptos,
            'params' => $params,
            'filePath' => (is_null($filePath) ? null : ('http://' . self::CURRENT_DOMEN . $filePath)),
        ));
        $view->setTerminal(true);
        $view->setTemplate('application/index/email/email-after-order-manager');
        $formView = $sl->get('viewrenderer')->render($view);
        return array($managerId ? $manager->email : self::$currentManagerMail, $formView, $user->getEmail());
    }

    /**
     * @param $sl
     * @param $user \ZfcUser\Entity\User | \Cart\Model\OrderUser
     * @param $orderId int
     * @param $order \Cart\Model\Order
     * @param $productsInfo \Catalog\Model\Product[]
     * @param $ptos \Cart\Model\ProdToOrder[]
     * @param $isRegistered boolean
     * @param $filePath string
     * @return array
     */
    public static function prepareChangeOrderManagerMailData($sl, $user, $orderId, $order, $productsInfo)
    {
        $params = array();
        $userName = $user->getUsername();
        $userAlias = $user->getAlias();
        $params['Ф.И.О.'] = !empty($userAlias) ? $userAlias : $userName;
        $params['E-Mail'] = $user->getEmail();

        if (is_callable(array($user, 'getManagerId'))) {
            $managerId = $user->getManagerId();
        } else {
            $managerId = false;
        }


        if ($managerId) {
            $manager = $sl->get('UserTable')->find($managerId);
            $params['Ответственный менеджер'] = $manager->username;
        }

        if ($user->getPhone() != "") {
            $params['Телефон'] = $user->getPhone();
        }
        if ($user->getCity() != "") {
            $params['Город'] = $user->getCity();
        }
        if ($user->getIsSpamed() != "") {
            $params['Подписка'] = $user->getIsSpamed() ? 'Да' : 'Нет';
        }

        $view = new ViewModel(array(
            'orderId' => $orderId,
            'orderInfo' => $order,
            'products' => $productsInfo,
            'params' => $params,
        ));
        $view->setTerminal(true);
        $view->setTemplate('application/index/email/email-change-order-manager');
        $formView = $sl->get('viewrenderer')->render($view);
        return array($managerId ? $manager->email : self::$currentManagerMail, $formView, );
    }

/**
     * @param $sl
     * @param $user \ZfcUser\Entity\User | \Cart\Model\OrderUser
     * @param $orderId int
     * @param $order \Cart\Model\Order
     * @param $productsInfo \Catalog\Model\Product[]
     * @param $ptos \Cart\Model\ProdToOrder[]
     * @param $isRegistered boolean
     * @param $filePath string
     * @return array
     */
    public static function prepareChangeOrderUserMailData($sl, $user, $orderId, $order, $productsInfo)
    {
        $params = array();
        $params['Ф.И.О.'] = $user->getUsername();
        $params['E-Mail'] = $user->getEmail();

        if (is_callable(array($user, 'getManagerId'))) {
            $managerId = $user->getManagerId();
        } else {
            $managerId = false;
        }


        if ($managerId) {
            $manager = $sl->get('UserTable')->find($managerId);
            $params['Ответственный менеджер'] = $manager->username;
        }

        if ($user->getPhone() != "") {
            $params['Телефон'] = $user->getPhone();
        }
        if ($user->getCity() != "") {
            $params['Город'] = $user->getCity();
        }
        if ($user->getIsSpamed() != "") {
            $params['Подписка'] = $user->getIsSpamed() ? 'Да' : 'Нет';
        }

        $view = new ViewModel(array(
            'orderId' => $orderId,
            'orderInfo' => $order,
            'products' => $productsInfo,
            'params' => $params,
            'username' => $user->getUsername(),
        ));
        $view->setTerminal(true);
        $view->setTemplate('application/index/email/email-change-order-user');
        $formView = $sl->get('viewrenderer')->render($view);
        return array($user->getEmail(), $formView);
    }


    /**
     * @param $sl
     * @param $discounts Discount[]
     * @param $user
     * @return array
     */
    public static function prepareDiscountMailData($sl, $discounts, $user) {
        $email = $user->email;

        $cm = CatalogMapper::getInstance($sl);
        list($discounts, $sectionIds, $subsectionIds, $seriesIds, $productsIds) =
            DiscountService::sortDiscountByHierarchy($sl, $discounts);

        foreach ($sectionIds as $skey => $sid) {
            if (empty($sid)) unset($sectionIds[$skey]);
        }
        foreach ($subsectionIds as $skey => $sid) {
            if (empty($sid)) unset($subsectionIds[$skey]);
        }
        foreach ($seriesIds as $skey => $sid) {
            if (empty($sid)) unset($seriesIds[$skey]);
        }
        foreach ($productsIds as $skey => $sid) {
            if (empty($sid)) unset($productsIds[$skey]);
        }

        $sections = array();
        $subsections = array();
        $series = array();
        $products = array();
        if (count($sectionIds)) {
            $fsections = $sl->get('Catalog\Model\SectionTable')->fetchByCond('id', $sectionIds);
            foreach ($fsections as $fs) {
                $sections[$fs->id] = $fs;
            }
        }
        if (count($subsectionIds)) {
            $fsubsections = $sl->get('Catalog\Model\SubsectionTable')->fetchByCond('id', $subsectionIds);
            foreach ($fsubsections as $fs) {
                $subsections[$fs->id] = $fs;
            }
        }
        if (count($seriesIds)) {
            $fseries = $sl->get('Catalog\Model\SeriesTable')->fetchByCond('id', $seriesIds);
            $fileTable = $sl->get('FilesTable');
            foreach ($fseries as $fs) {
                if ($fs->preview) {

                    $file = $fileTable->find($fs->preview);
                    if ($file) {
                        $fs->previewName = $file->name;
                    }
                }
                $series[$fs->id] = $fs;
            }
        }
        if (count($productsIds)) {
            $fproducts = $sl->get('Catalog\Model\ProductTable')->fetchByCond('id', $productsIds);
            foreach ($fproducts as $fs) {
                $products[$fs->id] = $fs;
            }
        }

        $view = new ViewModel(array(
            'username' => $user->username,
            'sections' => $sections,
            'subsections' => $subsections,
            'series' => $series,
            'products' => $products,
            'discounts' => $discounts,
        ));
        $view->setTerminal(true);
        $view->setTemplate('application/index/email/email-user-discounts');
        $formViewUser = $sl->get('viewrenderer')->render($view);
        $view->setVariable('username', $user->alias ? $user->alias : $user->username);
        $view->setTemplate('application/index/email/email-manager-discounts');
        $formViewManager = $sl->get('viewrenderer')->render($view);
        $managerId = $user->manager_id;

        $managerMail = self::$currentManagerMail;
        if ($managerId) {
            $manager = $sl->get('UserTable')->find($managerId);
            $managerMail = $manager->email;
        }
        return array($email, $formViewUser, $formViewManager, $managerMail);
    }


    /**
     * @param $sl
     * @param $discounts Discount[]
     * @param $user
     * @return array
     */
    public static function prepareDiscountManagerMailData($sl, $discounts, $user) {
        $email = $user->email;

        $cm = CatalogMapper::getInstance($sl);

        list($discounts, $sectionIds, $subsectionIds, $seriesIds, $productsIds) =
            DiscountService::sortDiscountByHierarchy($sl, $discounts);

        foreach ($sectionIds as $skey => $sid) {
            if (empty($sid)) unset($sectionIds[$skey]);
        }
        foreach ($subsectionIds as $skey => $sid) {
            if (empty($sid)) unset($subsectionIds[$skey]);
        }
        foreach ($seriesIds as $skey => $sid) {
            if (empty($sid)) unset($seriesIds[$skey]);
        }
        foreach ($productsIds as $skey => $sid) {
            if (empty($sid)) unset($productsIds[$skey]);
        }

        $sections = array();
        $subsections = array();
        $series = array();
        $products = array();
        if (count($sectionIds)) {
            $fsections = $sl->get('Catalog\Model\SectionTable')->fetchByCond('id', $sectionIds);
            foreach ($fsections as $fs) {
                $sections[$fs->id] = $fs;
            }
        }
        if (count($subsectionIds)) {
            $fsubsections = $sl->get('Catalog\Model\SubsectionTable')->fetchByCond('id', $subsectionIds);
            foreach ($fsubsections as $fs) {
                $subsections[$fs->id] = $fs;
            }
        }
        if (count($seriesIds)) {
            $fseries = $sl->get('Catalog\Model\SeriesTable')->fetchByCond('id', $seriesIds);
            $fileTable = $sl->get('FilesTable');
            foreach ($fseries as $fs) {
                if ($fs->preview) {

                    $file = $fileTable->find($fs->preview);
                    if ($file) {
                        $fs->previewName = $file->name;
                    }
                }
                $series[$fs->id] = $fs;
            }
        }
        if (count($productsIds)) {
            $fproducts = $sl->get('Catalog\Model\ProductTable')->fetchByCond('id', $productsIds);
            foreach ($fproducts as $fs) {
                $products[$fs->id] = $fs;
            }
        }

        $view = new ViewModel(array(
            'username' => $user->alias ? $user->alias : $user->username,
            'sections' => $sections,
            'subsections' => $subsections,
            'series' => $series,
            'products' => $products,
            'discounts' => $discounts,
        ));
        $view->setTerminal(true);
        $view->setTemplate('application/index/email/email-manager-discounts');
        $formView = $sl->get('viewrenderer')->render($view);
        $managerId = $user->manager_id;

        $managerMail = self::$currentManagerMail;
        if ($managerId) {
            $manager = $sl->get('UserTable')->find($managerId);
            $managerMail = $manager->email;
        }
        return array($managerMail, $formView);
    }

    public static function prepareUserMailData($sl, $data, $orderId, $type)
    {
        $username = $data['name'];
        $email = $data['mail'];
        $fileName = "";
        if (isset($data['file']['name'])) {
            $fileName = $data['file']['name'];
        }

        $view = new ViewModel(array(
            'username' => $username,
            'orderId' => $orderId,
            'comment' => $data['comment'],
            'attachedFileName' => $fileName));
        $view->setTerminal(true);

        if ($type == ServicesController::CALCULATION_FORM) {
            $params = array();
            if (isset($data['l']) && !empty($data['l'])) {
                $params['Длина помещения'] = $data['l'] . " м";
            }
            if (isset($data['w']) && !empty($data['w'])) {
                $params['Ширина помещения'] = $data['w'] . " м";
            }
            if (isset($data['h']) && !empty($data['h'])) {
                $params['Высота помещения'] = $data['h'] . " м";
            }

            $goals = ServicesController::getGoals();

            if (isset($data['goal']) && !empty($data['goal']) && $data['goal'] <= sizeof($goals)) {
                $params['Назначение помещения'] = $goals[$data['goal']];
            }

            $view->setVariable('params', $params);
            $view->setTemplate('application/index/email/email-user-2');
        } else {
            $view->setTemplate('application/index/email/email-user-1');
        }

        $formView = $sl->get('viewrenderer')->render($view);

        return array($email, $formView);
    }

    /**
     * зарегался новый пользователь - приветствуем юзера,
     * высылаем ему логин/пароль
     *
     * @param $sl
     * @param $user \ZfcUser\Entity\User
     * @param $password string пароль передаётся в открытую! Не спали случайно :)
     * @return array
     */
    public static function prepareRegisterUserMailData($sl, $user, $password)
    {
        $email = $user->getEmail();
        $login = $email;
        $view = new ViewModel(array(
            'login' => $login,
            'username' => $user->getUsername(),
            'password' => $password
        ));
        $view->setTerminal(true);
        $view->setTemplate('application/index/email/email-user-newuser-registered');
        $formView = $sl->get('viewrenderer')->render($view);
        return array($email, $formView);
    }

    /**
     * зарегался новый пользователь - пишем менеджеру
     * @param $sl
     * @param $user \ZfcUser\Entity\User
     * @return array
     */
    public static function prepareRegisterManagerMailData($sl, $user)
    {
        $params = array();

        $params['Имя'] = $user->getUsername();
        $params['E-Mail'] = $user->getEmail();
        $params['Телефон'] = $user->getPhone();
        $params['Город'] = $user->getCity();
        $params['Подписка'] = $user->getIsSpamed() ? 'Да' : 'Нет';

        $view = new ViewModel(array(
            'params' => $params
        ));
        $view->setTerminal(true);
        $view->setTemplate('application/index/email/email-manager-newuser-registered');
        $formView = $sl->get('viewrenderer')->render($view);
        return array(self::$currentManagerMail, $formView);
    }

    /**
     * зарегался новый пользователь - пишем менеджеру
     * @param $sl
     * @param $user \ZfcUser\Entity\User
     * @return array
     */
    public static function prepareNewSpamedRegisterManagerData($sl, $user)
    {
        $params = array();

        $params['Имя'] = $user->getUsername();
        $params['E-Mail'] = $user->getEmail();
        $params['Город'] = $user->getCity();
        $params['Телефон'] = $user->getPhone();

        $view = new ViewModel(array(
            'params' => $params
        ));
        $view->setTerminal(true);
        $view->setTemplate('application/index/email/email-new-spamed-register-manager');
        $formView = $sl->get('viewrenderer')->render($view);
        return array(self::$currentManagerMail, $formView);
    }

    /**
     * @param $sl
     * @param $data Данные, которые юзер вбил в форму
     * @param $orderId Айди заказа
     * @param $type тип формы
     * @param $filePath string
     * @return array
     */
    public static function prepareManagerMailData($sl, $data, $orderId, $type, $filePath=null)
    {
        $formName = "";
        switch($type){
            case ServicesController::CALCULATION_FORM:
                $formName = "Расчет"; //Запрос на расчет
                break;

            case ServicesController::PROJECT_ORDER_FORM:
                $formName = "Проект"; //Заявка на проект
                break;

            case ServicesController::CONSULT_ORDER_FORM:
                $formName = "Консультацию"; //Заявка на консультацию
                break;

            case ServicesController::MODERNISATION_FORM:
                $formName = "Энергоаудит"; //Заявка на энергоаудит
                break;

            case ServicesController::MONTAJ_FORM:
                $formName = "Монтаж"; //Заявка на монтаж
                break;
        }
        $fileName = "";
        if (isset($data['file']['name'])) {
            $fileName = $data['file']['name'];
        }

        $view = new ViewModel(array(
            'params' => $data,
            'orderId' => $orderId,
            'formName' => $formName,
            'attachedFileName' => $fileName,
            'attachedFilePath' => (is_null($filePath) ? null : ('http://' . self::CURRENT_DOMEN . $filePath)),
        ));
        $view->setTerminal(true);

        $params = array();
        if (isset($data['name']) && !empty($data['name'])) {
            $params['Ф.И.О.'] = $data['name'];
        }
        if (isset($data['mail']) && !empty($data['mail'])) {
            $params['E-Mail'] = $data['mail'];
        }
        if (isset($data['phone']) && !empty($data['phone'])) {
            $params['Телефон'] = $data['phone'];
        }
        if (isset($data['city']) && !empty($data['city'])) {
            $params['Город'] = $data['city'];
        }
        if (isset($data['comment']) && !empty($data['comment'])) {
            $params['Комментарий'] = $data['comment'];
        }

        if ($type == ServicesController::CALCULATION_FORM) {

            if (isset($data['l']) && !empty($data['l'])) {
                $params['Длина помещения'] = $data['l'] . " м";
            }
            if (isset($data['w']) && !empty($data['w'])) {
                $params['Ширина помещения'] = $data['w'] . " м";
            }
            if (isset($data['h']) && !empty($data['h'])) {
                $params['Высота помещения'] = $data['h'] . " м";
            }

            $goals = ServicesController::getGoals();

            if (isset($data['goal']) && !empty($data['goal']) && $data['goal'] <= sizeof($goals)) {
                $params['Назначение помещения'] = $goals[$data['goal']];
            }
        }

        $view->setVariable('params', $params);
        $view->setTemplate('application/index/email/email-manager-1');

        $formView = $sl->get('viewrenderer')->render($view);

        return array(self::$currentManagerMail, $formView);
    }

    /**
     * @param $sl
     * @param $data Данные, которые юзер вбил в форму
     * @return array
     */
    public static function prepareManagerKaledoscopNotifyRequest($sl, $data)
    {

        if (!isset($data['name']) || !is_string($data['name']) || !isset($data['email']) || !is_string($data['email'])) {
            return false;
        }

        $view = new ViewModel(array(
            'params' => $data,
        ));
        $view->setTerminal(true);

        $view->setTemplate('application/index/email/kaledoscp-notify-request-manager');

        $formView = $sl->get('viewrenderer')->render($view);

        return array(self::$kaledoscopManagerMail, $formView);
    }

    /**
     * @param $sl
     * @param $data
     * @return array
     */
    public static function prepareFeedbackMessage($sl, $data)
    {

        if (!isset($data['fio']) || !is_string($data['fio']) || !isset($data['email']) || !is_string($data['email'])) {
            return false;
        }

        $view = new ViewModel(array(
            'params' => $data,
        ));
        $view->setTerminal(true);

        $view->setTemplate('application/index/email/feedback-message');

        $formView = $sl->get('viewrenderer')->render($view);

        return array(self::$developerMail, $formView);
    }
}