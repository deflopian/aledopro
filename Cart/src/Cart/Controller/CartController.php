<?php
namespace Cart\Controller;

use Application\Service\ApplicationService;
use Application\Service\MailService;
use Cart\Form\OrderForm;
use Cart\Model\Order;
use Cart\Model\OrderUser;
use Cart\Model\ProdToOrder;
use Catalog\Model\FilterFieldTable;
use Catalog\Service\CatalogService;
use User\Service\UserService;
use Zend\Mvc\Controller\AbstractActionController;

class CartController extends AbstractActionController
{
    const UPLOAD_PATH = '/uploads/orders/';
    public function indexAction()
    {
        $this->layout()->pageTitle = 'Корзина';
        $this->layout()->breadCrumbs = array(
            array('link'=> $this->url()->fromRoute('home'), 'text'=>ucfirst('Главная')),
        );

        $prodsInCart = array();
        $sl = $this->getServiceLocator();
        $identity = null;
        $hierarchies = array();
        $productsObj = isset($_COOKIE['products_in_cart']) ? \Zend\Json\Json::decode($_COOKIE['products_in_cart']) : null;
        $products = (array)$productsObj;


        $fileTable = $sl->get('FilesTable');

        if ($products) {
            $productTable = $sl->get('Catalog\Model\ProductTable');
            $seriesTable = $sl->get('Catalog\Model\SeriesTable');
            $subsectionTable = $sl->get('Catalog\Model\SubSectionTable');
            $sectionTable = $sl->get('Catalog\Model\SectionTable');
            $filtermParamTable = $sl->get('Catalog\Model\FilterParamTable');
            $mainParamsTable = $sl->get('Catalog\Model\ProductMainParamsTable');
            $allParamsTable = $sl->get('Catalog\Model\ProductParamsTable');
            $allParams = $allParamsTable->fetchAll("", false, true);
            $params = $filtermParamTable->fetchAll();
            $sortedParams = ApplicationService::makeIdArrayFromObjectArray($params);
//            $colors = ApplicationService::makeIdArrayFromObjectArray($filtermParamTable->fetchByCond('field', 'color_of_light'));
//            $casecolors = ApplicationService::makeIdArrayFromObjectArray($filtermParamTable->fetchByCond('field', 'case_color'));
            foreach ($products as $id => $count) {
                $product = $productTable->find($id);
                $series = $seriesTable->find($product->series_id);
                $subsection = $subsectionTable->find($series->subsection_id);
                $section = $sectionTable->find($subsection->section_id);
				
				$file = $fileTable->fetchByCond('uid', $product->id);
                if ($file) {
					$file = reset($file);
                    $product->previewName = $file->name;
					$product->preview = $file->id;
                }

                if ($series && $series->preview) {
                    $file = $fileTable->find($series->preview);
                    if ($file) {
                        $series->previewName = $file->name;
                    }
                }

				$hierarchies[$product->id][\Catalog\Controller\AdminController::PRODUCT_TABLE] = $product->id;
				$hierarchies[$product->id][\Catalog\Controller\AdminController::SERIES_TABLE] = $series->id;
				$hierarchies[$product->id][\Catalog\Controller\AdminController::SUBSECTION_TABLE] = $subsection->id;
				$hierarchies[$product->id][\Catalog\Controller\AdminController::SECTION_TABLE] = $section->id;
				
                if ($this->zfcUserAuthentication()->hasIdentity()) {
                    $identity = $this->zfcUserAuthentication()->getIdentity();
                }

                $product->preview_img = (isset($series->previewName) ? $series->previewName : $series->img);
//                $product->color_of_light = isset($colors[$product->color_of_light]) ? $colors[$product->color_of_light]->value : $product->color_of_light;
//                $product->case_color = isset($casecolors[$product->case_color]) ? $casecolors[$product->case_color]->value : $product->case_color;
                /** @var FilterFieldTable $filterFieldTable */
                $filterFieldTable = $this->getServiceLocator()->get('FilterFieldTable');
                $filters = $filterFieldTable->fetchAll($subsection->id, \Catalog\Controller\AdminController::SUBSECTION_TABLE, $section->id, "order ASC");
                $mainParams = array();

                foreach ($filters as $fkey => $filter) {
                    if ($filter->cart_param == 1) {
                        $f = $allParams[$filter->field_id];
                        $fName = $f->field;
                        if ($product->$fName && in_array($f->field, CatalogService::$intFields)) {

                            $product->$fName = isset($sortedParams[$product->$fName]) ? $sortedParams[$product->$fName]->value : $product->$fName;

                        }
                        if ($product->$fName) {
                            $mainParams[$f->field] = $f->title;
                            $product->$fName = $f->pre_value . $product->$fName . $f->post_value;
                        }
                    }
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
        }
		
		$priceRequestTable = $sl->get('PriceRequestTable');
		$requests = $priceRequestTable->fetchAllSorted();
		
		$return['hierarchies'] = $hierarchies;
		$return['requests'] = $requests;
        $return['isAuth'] = $this->zfcUserAuthentication()->hasIdentity();
		
		$return['isDomainZoneBy'] = ApplicationService::isDomainZone('by');

        $return['pageTitle'] = 'Корзина';
        $return['breadCrumbs'] = array(
            array('link'=> $this->url()->fromRoute('home'), 'text'=>ucfirst('Главная')),
        );
        return $return;
    }

    public function saveFormAjaxAction()
    {
        $request = $this->getRequest();

        if ($request->isPost()) {

            $post = array_merge_recursive(
                $request->getPost()->toArray(),
                $request->getFiles()->toArray()
            );

            $sl = $this->getServiceLocator();
            $user = $this->zfcUserAuthentication()->hasIdentity();
            //return array();
            $success = 0;
            $return = $messages = array();

            $form = new OrderForm('order', $user);

            $form->setData($post);
            $productDataCopy = array();

            if ($form->isValid()) {
                $data = $form->getData();

                $productData = \Zend\Json\Json::decode($post['order-products']);
                $realProductsIds = get_object_vars(\Zend\Json\Json::decode($_COOKIE['products_in_cart']));

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
                $allParams = $allParamsTable->fetchAll("", false, true);
                $params = $filtermParamTable->fetchAll();
                $sortedParams = ApplicationService::makeIdArrayFromObjectArray($params);

//                $colors = ApplicationService::makeIdArrayFromObjectArray($filtermParamTable->fetchByCond('field', 'color_of_light'));
//                $caseColors = ApplicationService::makeIdArrayFromObjectArray($filtermParamTable->fetchByCond('field', 'case_color'));
//                $ipratings = ApplicationService::makeIdArrayFromObjectArray($filtermParamTable->fetchByCond('field', 'ip_rating'));
//                $elpowers = ApplicationService::makeIdArrayFromObjectArray($filtermParamTable->fetchByCond('field', 'electro_power'));
//                $constructions = ApplicationService::makeIdArrayFromObjectArray($filtermParamTable->fetchByCond('field', 'construction'));

                foreach($productData as $id=>$prodData)
                {

                    $product = $prodTable->find($id);
                    $series = $seriesTable->find($product->series_id);
                    $subsection = $subsectionTable->find($series->subsection_id);
                    $section = $sectionTable->find($subsection->section_id);

                    $mainParamsTable = $sl->get('Catalog\Model\ProductMainParamsTable');
//                    $productsInfo[$id]->color_of_light = isset($colors[$product->color_of_light]) ? $colors[$product->color_of_light]->value : $product->color_of_light;
//                    $productsInfo[$id]->case_color = isset($caseColors[$product->case_color]) ? $caseColors[$product->case_color]->value : $product->case_color;
//                    $productsInfo[$id]->ip_rating = isset($ipratings[$product->ip_rating]) ? $ipratings[$product->ip_rating]->value : $product->ip_rating;
//                    $productsInfo[$id]->electro_power = isset($elpowers[$product->electro_power]) ? $elpowers[$product->electro_power]->value : $product->electro_power;
//                    $productsInfo[$id]->construction = isset($constructions[$product->construction]) ? $constructions[$product->construction]->value : $product->construction;

                    /** @var FilterFieldTable $filterFieldTable */
                    $filterFieldTable = $this->getServiceLocator()->get('FilterFieldTable');
                    $filters = $filterFieldTable->fetchAll($subsection->id, \Catalog\Controller\AdminController::SUBSECTION_TABLE, $section->id, "order ASC");
                    $mainParams = array();

                    foreach ($filters as $fkey => $filter) {
                        if ($filter->cart_param == 1) {
                            $f = $allParams[$filter->field_id];
                            $fName = $f->field;
                            if ($product->$fName && in_array($f->field, CatalogService::$intFields)) {

                                $product->$fName = isset($sortedParams[$product->$fName]) ? $sortedParams[$product->$fName]->value : $product->$fName;

                            }
                            if ($product->$fName) {
                                $mainParams[$f->field] = $f->title;
                                $product->$fName = $f->pre_value . $product->$fName . $f->post_value;
                            }
//                            $product->$fName = $f->pre_value . $product->$fName . $f->post_value;
//                            $mainParams[$f->field] = $f->title;
                        }
                    }
                    $product->mainParams = $mainParams;
                    $productsInfo[$id] = $product;
//                    $mainParams = array();
//                    foreach ($filters as $fkey => $filter) {
//                        if ($filter->cart_param == 1) {
//
//                            $mainParams[$allParams[$filter->field_id]->field] = $allParams[$filter->field_id]->title;
//                        }
//                    }


                    //$productsInfo[$id]->mainParams = $mainParams;

                    $trueCount = floor($prodData->count);
					
					$priceRequestTable = $sl->get('PriceRequestTable');
					$requests = $priceRequestTable->fetchAllSorted();
					
					$hierarchies[$product->id][\Catalog\Controller\AdminController::PRODUCT_TABLE] = $product->id;
                    $hierarchies[$product->id][\Catalog\Controller\AdminController::SERIES_TABLE] = $series->id;
                    $hierarchies[$product->id][\Catalog\Controller\AdminController::SUBSECTION_TABLE] = $subsection->id;
                    $hierarchies[$product->id][\Catalog\Controller\AdminController::SECTION_TABLE] = $section->id;

                    if ($identity && $identity->getIsPartner()) {
                        $truePrice = CatalogService::getTruePrice(
                            $product->price_without_nds,
                            $identity,
                            $hierarchies[$product->id],
                            $discounts,
                            $product->opt2,
							$requests
                        );

                    } else {
                        $truePrice = CatalogService::getTruePrice($product->price_without_nds, null, $hierarchies[$product->id], null, 0, $requests);
                    }

                    $productData[$id]->price = $truePrice;
                    $totalPrice += $truePrice * $trueCount;
                }

                $order = new Order();
                $orderData = array(
                    'comment' => $data['order_comment'],
                    'summ' => $totalPrice,
                    'orderState' => $post['buyType'],
                );
                $filePath = null;

                //заказчик - юридическое лицо, сохраняем его реквизиты
                if ($post['buyType'] == 2) {
                    if (isset($data['order_file']['tmp_name'])) {
                        $filePath = substr($data['order_file']['tmp_name'], strlen($_SERVER['DOCUMENT_ROOT'] . self::UPLOAD_PATH));

                        $orderData['file'] =  $filePath;
                        $filePath = self::UPLOAD_PATH . $filePath;
                    } elseif (isset($post['order_lastFile']) && $post['order_lastFile'] == 1) {

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
                    $userData['name'] = $post['user_name'];
                    $userData['mail'] = $post['user_email'];
                    $userData['phone'] = $post['user_tel'];
                    $userData['city'] = $post['user_city'];
                    $userData['is_spamed'] = $post['user_is_spamed'] ? 1 : 0;

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
                            'price' => $prodData->price,
                            'count' => floor($prodData->count),
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


                        if ($post['buyType'] == 2) {
                            if (isset($data['order_file']['name'])) {
                                $order->originalName = $data['order_file']['name'];
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
                            if ($email != MailService::getCurrentManagerMail()) {
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
                            MailService::sendMail(MailService::getCurrentManagerMail(), $mailView, "Новый заказ номер " . $orderId . " на Aledo");

                        }
                    }
                }

            } else {
                $messages = $form->getMessages();
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