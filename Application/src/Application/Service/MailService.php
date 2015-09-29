<?php
namespace Application\Service;

use BjyAuthorize\Guard\Controller;
use Catalog\Controller\CatalogController;
use Catalog\Mapper\CatalogMapper;
use Catalog\Service\CatalogService;
use Discount\Model\Discount;
use Discount\Service\DiscountService;
use Documents\Model\DocumentTable;
use Info\Model\PartnerRequest;
use Reports\Model\Report;
use Services\Controller\ServicesController;
use User\Model\User;
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
	const NOTIFICATION_SERIES = 1;
	const NOTIFICATION_PROJECTS = 2;
	const NOTIFICATION_ARTICLES = 3;
	const NOTIFICATION_DEVELOPERS = 4;
	const NOTIFICATION_DOCUMENTS = 5;

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
     * @param \ZfcUser\Entity\User $user
     *
     * @return array
     */
    public static function prepareUserRequestPartnershipMailData($sl, $request, $user)
    {
        $email = $user->getEmail();


        $view = new ViewModel(array(
            'username' => $request->partner_lastname . " " . $request->partner_name . " " . $request->partner_fathername
        ));
        $view->setTerminal(true);
        $view->setTemplate('application/index/email/email-user-request-partnership');
        $formView = $sl->get('viewrenderer')->render($view);
        return array($email, $formView);
    }


    /**
     * @param $sl
     * @param PartnerRequest $partnerRequest
     * @param \ZfcUser\Entity\User $user
     *
     * @return array
     */
    public static function prepareManagerRequestPartnershipMailData($sl, $partnerRequest, $requestId, $user)
    {
        $params1 = array();
        $params2 = array();

        $name = $partnerRequest->partner_lastname . " " . $partnerRequest->partner_name . " " . $partnerRequest->partner_fathername;
        $params1['Ф.И.О.'] = $name ? $name : "не указано";
        $params1['E-Mail'] = $user->getEmail() ? $user->getEmail() : "не указано";
        $params1['Должность'] = $partnerRequest->partner_job_title ? $partnerRequest->partner_job_title : "не указано";
        $params1['Номер телефона'] = $user->getPhone() ? $user->getPhone() : "не указано";

        $params2['Полное название компании'] = $partnerRequest->partner_company_name ? $partnerRequest->partner_company_name : "не указано";
        $params2['Сфера деятельности'] = $partnerRequest->partner_scope ? $partnerRequest->partner_scope : "не указано";
        $params2['Примеры брендов'] = $partnerRequest->partner_brands ? $partnerRequest->partner_brands : "не указано";
        $params2['Область/Город'] = $user->getCity() ? $user->getCity() : "не указано";
        $params2['Телефоны офиса'] = $partnerRequest->partner_office_tel ? $partnerRequest->partner_office_tel : "не указано";
        $params2['Website'] = $partnerRequest->partner_website ? $partnerRequest->partner_website : "не указано";

        $view = new ViewModel(array(
            'paramsClient' => $params1,
            'paramsCompany' => $params2,
            'requestId' => $requestId,
        ));

        $view->setTerminal(true);
        $view->setTemplate('application/index/email/email-manager-request-partnership');
        $formView = $sl->get('viewrenderer')->render($view);
        return array(self::$currentManagerMail, $formView, $user->getEmail());
    }


    /**
     * @param $sl
     * @param $requestId int
	 * @param $requestDetails \Vacancies\Model\VacancyRequest
	 * @param $vacancy \Vacancies\Model\Vacancy
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
	 * @param $requestDetails \Vacancies\Model\VacancyRequest
	 * @param $vacancy \Vacancies\Model\Vacancy
     * @return array
     */
    public static function prepareVacancyAcceptedMailData($sl, $requestDetails, $vacancy)
    {
        $view = new ViewModel(array(
            'requestDetails'     => $requestDetails,
            'vacancy'   => $vacancy
        ));
        $view->setTerminal(true);
        $view->setTemplate('application/index/email/email-vacancy-employee');
        $formView = $sl->get('viewrenderer')->render($view);
        return array($requestDetails->mail, $formView);
    }
	
    /**
     * @param $sl
	 * @param $entity Object
	 * @param $type
     * @return array
     */
    public static function prepareNotificationMailData($sl, $entity, $type)
    {
        $title = $entity->title;
		
		switch ($type) {
			case self::NOTIFICATION_SERIES:
				$id = $entity->id;
				$category = 'новая серия';
				$link = '/catalog/series/' . $entity->id . '/';
				break;
			case self::NOTIFICATION_PROJECTS:
				$category = 'новый проект';
				$link = '/projects/view/' . $entity->id . '/';
				break;
			case self::NOTIFICATION_ARTICLES:
				$category = 'новая статья в блоге';
				$link = '/articles/view/' . $entity->id . '/';
				break;
			case self::NOTIFICATION_DEVELOPERS:
				$category = 'новый производитель';
				$link = '/brands/view/' . $entity->id . '/';
				break;
			case self::NOTIFICATION_DOCUMENTS:
				$fileTable = $sl->get('FilesTable');
				$file = $fileTable->find($entity->file);
				
				$link = '/images/documents/' . $file->name;
				
				if ($entity->type == DocumentTable::TYPE_CATALOG) {
					$category = 'новый каталог';
				}
				else if ($entity->type == DocumentTable::TYPE_CERTIFICATE) {
					$category = 'новый сертификат';
				}
				else if ($entity->type == DocumentTable::TYPE_INSTRUCTION) {
					$category = 'новая инструкция';
				}
				else if ($entity->type == DocumentTable::TYPE_COMMENT) {
					$category = 'новый отзыв';
				}
				break;
		}
		
		$view = new ViewModel(array(
			'id' => $id,
            'category' => $category,
			'title' => $title,
			'link' => $link
        ));
        $view->setTerminal(true);
		$view->setTemplate('application/index/email/email-notification');
        $formView = $sl->get('viewrenderer')->render($view);
		
		$to = GoogleContactsService::getMails($sl);
		return array($to ? $to : self::$currentManagerMail, $formView);
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
     * @param $user \User\Model\User
     * @param $password string
	 * @param $forced boolean
     * @return array
     */
    public static function prepareRememberPasswordMailData($sl, $user, $password, $forced = false)
    {
        $email = $user->email;
        $login = $email;
        $view = new ViewModel(array(
            'login' => $login,
            'username' => $user->username,
            'password' => $password,
			'forced' => $forced
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

        return array(self::$currentManagerMail, $formView);
    }
}