<?php
namespace Cart\Controller;

use Application\Service\ApplicationService;
use Application\Service\MailService;
use Cart\Form\OrderForm;
use Cart\Model\Order;
use Cart\Model\OrderUser;
use Cart\Model\ProdToOrder;
use Catalog\Service\CatalogService;
use User\Service\UserService;
use Zend\Mvc\Controller\AbstractActionController;

class CartController extends AbstractActionController
{
    const UPLOAD_PATH = '/uploads/orders/';
    public function indexAction()
    {
        $this->layout()->pageTitle = 'Корзина';

        $prodsInCart = array();
        $sl = $this->getServiceLocator();
        $identity = null;
        $hierarchies = array();
        $productsObj = isset($_COOKIE['products_in_cart']) ? \Zend\Json\Json::decode($_COOKIE['products_in_cart']) : null;
        $products = (array)$productsObj;
        if($products){
            $productTable = $sl->get('Catalog\Model\ProductTable');
            $seriesTable = $sl->get('Catalog\Model\SeriesTable');
            $subsectionTable = $sl->get('Catalog\Model\SubSectionTable');
            $sectionTable = $sl->get('Catalog\Model\SectionTable');
            $filtermParamTable = $sl->get('Catalog\Model\FilterParamTable');
            $mainParamsTable = $sl->get('Catalog\Model\ProductMainParamsTable');
            $allParamsTable = $sl->get('Catalog\Model\ProductParamsTable');
            $allParams = $allParamsTable->fetchAll();
            $colors = ApplicationService::makeIdArrayFromObjectArray($filtermParamTable->fetchByCond('field', 'color_of_light'));
            $casecolors = ApplicationService::makeIdArrayFromObjectArray($filtermParamTable->fetchByCond('field', 'case_color'));
            foreach($products as $id=>$count){
                $product = $productTable->find($id);
                $series = $seriesTable->find($product->series_id);
                $subsection = $subsectionTable->find($series->subsection_id);
                $section = $sectionTable->find($subsection->section_id);

                if ($this->zfcUserAuthentication()->hasIdentity()) {
                    $identity = $this->zfcUserAuthentication()->getIdentity();
                    $hierarchies[$product->id][\Catalog\Controller\AdminController::PRODUCT_TABLE] = $product->id;
                    $hierarchies[$product->id][\Catalog\Controller\AdminController::SERIES_TABLE] = $series->id;
                    $hierarchies[$product->id][\Catalog\Controller\AdminController::SUBSECTION_TABLE] = $subsection->id;
                    $hierarchies[$product->id][\Catalog\Controller\AdminController::SECTION_TABLE] = $section->id;

                }


                $product->preview_img = (isset($series->previewName) ? $series->previewName : $series->img);
                $product->color_of_light = isset($colors[$product->color_of_light]) ? $colors[$product->color_of_light]->value : $product->color_of_light;
                $product->case_color = isset($casecolors[$product->case_color]) ? $casecolors[$product->case_color]->value : $product->case_color;
                $mainParamsList = $mainParamsTable->fetchByCond('product_type', $section->display_style);
                $mainParams = array();
                foreach ($mainParamsList as $mainParam) {
                    $mainParams[$mainParam->field] = $allParams[$mainParam->field]->title;
                }
                $product->mainParams = $mainParams;

                $data = array(
                    'count' => $count,
                    'product' => $product,
                );
                $prodsInCart[] = $data;
            }




        } else {
            $user = $this->zfcUserAuthentication()->hasIdentity();
            return $this->redirect()->toRoute( $user ? 'cabinet' : 'catalog');
        }
        $seoData = $sl->get('SeoDataTable')->find( \Info\Service\SeoService::CART, 1 );
        $this->layout()->setVariables(
            array(
                'seoData' => $seoData,
                'isCart' => true,
            )
        );

        $return = array(
            'seoData' => $seoData,
            'prodsInCart' => $prodsInCart,
        );

        if ($identity) {
            //последний подгруженный файл с реквизитами (если заказывал как юр. лицо)
            $lastFile = "";
            $orderTable = $sl->get('OrderTable');
            $lastUridOrders = $orderTable->fetchByConds(array('user_id' => $identity->getId(), 'orderState' => 2), array('file' => ''), "id DESC");

            $lastUridOrder = reset($lastUridOrders);

            $lastFile = $lastUridOrder->file;
            $return['lastFile'] = $lastFile;
        }

        if ($identity && $identity->getisPartner()) {
            $discounts = $sl->get('DiscountTable')->fetchByUserId($identity->getId(), $identity->getPartnerGroup(), false, 0, $sl);
            $return['user'] = $identity;
            $return['discounts'] = $discounts;
            $return['hierarchies'] = $hierarchies;
        }

        return $return;
    }

    public function saveFormAjaxAction()
    {
        $request = $this->getRequest();
        if ($request->isXmlHttpRequest()) {

            $post = array_merge_recursive(
                $request->getPost()->toArray(),
                $request->getFiles()->toArray()
            );

            $sl = $this->getServiceLocator();
            $user = $this->zfcUserAuthentication()->hasIdentity();

            $success = 0;
            $return = $messages = array();

            $form = new OrderForm('order', $user);
            $form->setData($post);

            if ($form->isValid()) {
                $data = $form->getData();

                $productData = \Zend\Json\Json::decode($post['order-products']);
                $realProductsIds = get_object_vars(\Zend\Json\Json::decode($_COOKIE['products_in_cart']));

                $productDataCopy = array();
                foreach ($productData  as $id => $value) {
                    if (array_key_exists($id, $realProductsIds)) {
                        $productDataCopy[$id] = $value;
                    }
                }
                $productData = $productDataCopy;

                $prodTable = $sl->get('Catalog/Model/ProductTable');
                $orderTable = $sl->get('OrderTable');
                $totalPrice = 0;
                $identity = null;
                $discounts = array();
                if ($user) {
                    $identity = $this->zfcUserAuthentication()->getIdentity();
                    $discounts = $sl->get('DiscountTable')->fetchByUserId($identity->getId(), $identity->getPartnerGroup(), false, 0, $sl);
                }

                $hierarchies = array();
                $seriesTable = $sl->get('Catalog\Model\SeriesTable');
                $subsectionTable = $sl->get('Catalog\Model\SubSectionTable');
                $sectionTable = $sl->get('Catalog\Model\SectionTable');

                $productsInfo = array();


                $allParamsTable = $sl->get('Catalog\Model\ProductParamsTable');
                $filtermParamTable = $sl->get('Catalog\Model\FilterParamTable');
                $allParams = $allParamsTable->fetchAll();

                $colors = ApplicationService::makeIdArrayFromObjectArray($filtermParamTable->fetchByCond('field', 'color_of_light'));
                $caseColors = ApplicationService::makeIdArrayFromObjectArray($filtermParamTable->fetchByCond('field', 'case_color'));
                $ipratings = ApplicationService::makeIdArrayFromObjectArray($filtermParamTable->fetchByCond('field', 'ip_rating'));
                $elpowers = ApplicationService::makeIdArrayFromObjectArray($filtermParamTable->fetchByCond('field', 'electro_power'));
                $constructions = ApplicationService::makeIdArrayFromObjectArray($filtermParamTable->fetchByCond('field', 'construction'));

                foreach($productData as $id=>$prodData)
                {
                    $product = $prodTable->find($id);
                    $series = $seriesTable->find($product->series_id);
                    $subsection = $subsectionTable->find($series->subsection_id);
                    $section = $sectionTable->find($subsection->section_id);
                    $productsInfo[$id] = $product;
                    $mainParamsTable = $sl->get('Catalog\Model\ProductMainParamsTable');
                    $productsInfo[$id]->color_of_light = isset($colors[$product->color_of_light]) ? $colors[$product->color_of_light]->value : $product->color_of_light;
                    $productsInfo[$id]->case_color = isset($caseColors[$product->case_color]) ? $caseColors[$product->case_color]->value : $product->case_color;
                    $productsInfo[$id]->ip_rating = isset($ipratings[$product->ip_rating]) ? $ipratings[$product->ip_rating]->value : $product->ip_rating;
                    $productsInfo[$id]->electro_power = isset($elpowers[$product->electro_power]) ? $elpowers[$product->electro_power]->value : $product->electro_power;
                    $productsInfo[$id]->construction = isset($constructions[$product->construction]) ? $constructions[$product->construction]->value : $product->construction;
                    $mainParamsList = $mainParamsTable->fetchByCond('product_type', $section->display_style);
                    $mainParams = array();

                    foreach ($mainParamsList as $mainParam) {
                        $mainParams[$mainParam->field] = $allParams[$mainParam->field]->title;
                    }
                    $productsInfo[$id]->mainParams = $mainParams;

                    $trueCount = floor($prodData[0]);

                    if ($identity && $identity->getIsPartner()) {

                        $hierarchies[$product->id][\Catalog\Controller\AdminController::PRODUCT_TABLE] = $product->id;
                        $hierarchies[$product->id][\Catalog\Controller\AdminController::SERIES_TABLE] = $series->id;
                        $hierarchies[$product->id][\Catalog\Controller\AdminController::SUBSECTION_TABLE] = $subsection->id;
                        $hierarchies[$product->id][\Catalog\Controller\AdminController::SECTION_TABLE] = $section->id;

                        $truePrice = CatalogService::getTruePrice(
                            $product->price_without_nds,
                            $identity,
                            $hierarchies[$product->id],
                            $discounts,
                            $product->opt2
                        );

                    } else {
                        $truePrice = CatalogService::getTruePrice($product->price_without_nds);
                    }

                    $productData[$id][1] = $truePrice;
                    $totalPrice += $truePrice * $trueCount;
                }

                $order = new Order();
                $orderData = array(
                    'comment' => $data['comment'],
                    'summ' => $totalPrice,
                    'orderState' => $post['buyer-state'],
                );
                $filePath = null;
                //заказчик - юридическое лицо, сохраняем его реквизиты
                if ($post['buyer-state'] == 2) {
                    if (isset($data['file']['tmp_name'])) {
                        $filePath = substr($data['file']['tmp_name'], strlen($_SERVER['DOCUMENT_ROOT'] . self::UPLOAD_PATH));

                        $orderData['file'] =  $filePath;
                        $filePath = self::UPLOAD_PATH . $filePath;
                    } elseif (isset($post['lastFile']) && $post['lastFile'] == 1) {

                        if ($identity) {
                            //последний подгруженный файл с реквизитами (если заказывал как юр. лицо)
                            $lastFile = "";
                            $orderTable = $sl->get('OrderTable');
                            $lastUridOrders = $orderTable->fetchByConds(array('user_id' => $identity->getId(), 'orderState' => 2), array('file' => ''), "id DESC");
                            $lastUridOrder = reset($lastUridOrders);

                            $lastFile = $lastUridOrder->file;

                            $orderData['file'] =  $lastFile;
                            $filePath = self::UPLOAD_PATH . $lastFile;
                        }
                    }

                }
                if($user){
                    $orderData['user_id'] = $this->zfcUserAuthentication()->getIdentity()->getId();
                }
                $order->exchangeArray($orderData);
                $orderId = $orderTable->save($order);

                if(!$user){
//                    $userData = json_decode($post['userVals'], true);
                    $userData['name'] = $post['userVals-name'];
                    $userData['mail'] = $post['userVals-mail'];
                    $userData['phone'] = $post['userVals-phone'];
                    $userData['city'] = $post['userVals-city'];
                    $userData['is_spamed'] = $post['userVals-is_spamed'];

                    $orderUser = new OrderUser();
                    $orderUser->exchangeArray(array(
                        'order_id' => $orderId,
                        'username'     => $userData['name'],
                        'email'     => $userData['mail'],
                        'phone'     => $userData['phone'],
                        'city'     => $userData['city'],
                        'isSpamed'     => isset($userData['is_spamed']),
                    ));
                    $sl->get('OrderUserTable')->save($orderUser);
                }

                if(isset($orderId)){
                    $orderProdsTable = $sl->get('ProdToOrderTable');
                    $ptosIds = array();

                    foreach($productData as $id=>$prodData)
                    {
                        $pto = new ProdToOrder();
                        $pto->exchangeArray(array(
                            'order_id' => $orderId,
                            'product_id' => $id,
                            'price' => $prodData[1],
                            'count' => floor($prodData[0]),
                        ));
                        $orderProdsTable->save($pto);
                        $ptosIds[$id] = $pto;
                    }
                    $success = 1;

                    $return['orderId'] = $orderId;
                    if (isset($orderId) && !empty($orderId)) {
                        if ($user) {
                            $isRegistered = true;
                            $userInfo = $this->zfcUserAuthentication()->getIdentity();
                        } else {
                            $isRegistered = false;
                            $userInfoData = $sl->get('OrderUserTable')->fetchByCond('order_id', $orderId);
                            if ($userInfoData && count($userInfoData)>0) {
                                $userInfo = $userInfoData[0];
                            }
                        }


                        if ($post['buyer-state'] == 2) {
                            if (isset($data['file']['name'])) {
                                $order->originalName = $data['file']['name'];
                            } elseif (isset($order->file) && $order->file) {
                                $order->originalName = $order->file;
                            }
                        }

                        if (isset($userInfo)) {
                            //отправляем юзеру письмо с деталями заказа

                            list($email, $mailView, $from) = MailService::prepareOrderUserMailData($this->serviceLocator, $userInfo, $orderId, $order, $productsInfo, $ptosIds);
                            MailService::sendMail($email, $mailView, "Детали заказа", $from);

                            //сообщаем менеджеру детали нового заказа
                            list($email, $mailView, $from) = MailService::prepareOrderManagerMailData($this->serviceLocator, $userInfo, $orderId, $order, $productsInfo, $ptosIds, $isRegistered, $filePath);
                            if ($email != MailService::$currentManagerMail) {
                                MailService::sendMail($email, $mailView, "Новый заказ номер " . $orderId . " на Aledo", $from);
                            }
                            if ($user && $this->zfcUserAuthentication()->getIdentity()->getIsPartner()) {
                                UserService::addHistoryAction(
                                    $this->getServiceLocator(),
                                    $this->zfcUserAuthentication()->getIdentity()->getId(),
                                    UserService::USER_ACTION_MAKE_ORDER,
                                    "/admin/requests/order/view/$orderId/",
                                    time()
                                );
                            }
                            MailService::sendMail(MailService::$currentManagerMail, $mailView, "Новый заказ номер " . $orderId . " на Aledo");
//                            MailService::sendMail("deflopian@gmail.com", $mailView, "Новый заказ номер " . $orderId . " на Aledo");
                        }
                    }
                }

            } else {
                $messages = $form->getMessages();
                var_dump($messages);
            }

            $return['success'] = $success;
            $return['messages'] = $messages;

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode( $return ));
            return $response;
        }

        return $this->redirect()->toRoute('cart');
    }

    public function addToCartAction() {
        if ($this->zfcUserAuthentication()->hasIdentity()) {
            $prodId = $this->params()->fromPost('prodId', false);
            $user = $this->zfcUserAuthentication()->getIdentity();
            if ($user->getIsPartner()) {
                UserService::addHistoryAction(
                    $this->getServiceLocator(),
                    $user->getId(),
                    UserService::USER_ACTION_ADD_TO_CART,
                    "/catalog/product/$prodId",
                    time()
                );
            }
        }

        return $this->getResponse()->setContent(\Zend\Json\Json::encode( array('success' => 1) ));
    }

    public function removeFromCartAction() {
        if ($this->zfcUserAuthentication()->hasIdentity()) {
            $prodId = $this->params()->fromPost('prodId', false);
            $user = $this->zfcUserAuthentication()->getIdentity();
            if ($user->getIsPartner()) {
                UserService::addHistoryAction(
                    $this->getServiceLocator(),
                    $user->getId(),
                    UserService::USER_ACTION_REMOVE_FROM_CART,
                    "/catalog/product/$prodId",
                    time()
                );
            }
        }
        return $this->getResponse()->setContent(\Zend\Json\Json::encode( array('success' => 1) ));
    }

    public function getAuthAction()
    {
        $request = $this->getRequest();
        if ($request->isXmlHttpRequest()) {
            $sl = $this->getServiceLocator();
            $auth = $sl->get('zfcuserauthservice');

            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode( array(
                'auth' => $auth->hasIdentity()
            )));
            return $response;
        }

        return $this->redirect()->toRoute('cart');
    }
}