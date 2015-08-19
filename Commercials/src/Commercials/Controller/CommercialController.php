<?php
/**
 * Created by PhpStorm.
 * User: Вадим
 * Date: 03.02.15
 * Time: 17:31
 */

namespace Commercials\Controller;


use Catalog\Service\CatalogService;
use Commercials\Mapper\CommercialMapper;
use Commercials\Service\CommercialService;
use Zend\Json\Json;
use Zend\Mvc\Controller\AbstractActionController;

class CommercialController extends AbstractActionController {
    public function exportFileAction()
    {
        $id = (int) $this->params()->fromRoute('id', 0);
        if (!$id) {
            return $this->redirect()->toRoute('cabinet');
        }

        $user = $this->zfcUserAuthentication()->getIdentity();
        if (!$user) {
            $this->getResponse()->setStatusCode(403);
        }
        $commercialMapper = CommercialMapper::getInstance($this->getServiceLocator());

        $commercial = $commercialMapper->getByUID($user->getId(), $id, true, true, true);
        CommercialService::makeCommercialXls($commercial, $user->getUsername());

        return $this->getResponse()->setStatusCode(200);
    }

    public function changeRoomOrderAction()
    {
        $request = $this->getRequest();

        $success = 0;
        $data = Json::decode($request->getContent(), Json::TYPE_ARRAY);

        if ($request->isPost()) {
            $commUid = isset($data['comm_uid']) ? $data['comm_uid'] : false;
            $order = isset($data['order']) ? $data['order'] : false;
            $user = $this->zfcUserAuthentication()->getIdentity();
            if ($order && $commUid && $user) {

                $comm = CommercialMapper::getInstance($this->getServiceLocator())->getByUID($user->getId(), $commUid);

                if ($comm) {
                    $table = $this->getServiceLocator()->get('CommercialRoomsTable');
                    $rooms = $comm->rooms;
                    $roomsArr = array();

                    foreach ($rooms as $room) {
                        $roomsArr[$room->id] = $room;
                    }
                    $sorted = 0;
                    foreach($order as $id=>$serialNum){
                        if (array_key_exists($id, $roomsArr)) {
                            $roomsArr[$id]->order = $serialNum;
                            $table->save($roomsArr[$id]);
                            $sorted++;
                        }
                    }

                    if ($sorted > 0) {
                        $success = 1;
                    }
                }
            }
            var_dump($order,$commUid,$user);
            $response = $this->getResponse();
            $response->setContent(\Zend\Json\Json::encode(array('success' => $success)));
            return $response;
        }
        return $this->redirect()->toRoute('cabinet');
    }

    public function actualizeAction()
    {
        $id = (int) $this->params()->fromRoute('id', 0);
        if (!$id) {
            return $this->redirect()->toRoute('cabinet');
        }

        $user = $this->zfcUserAuthentication()->getIdentity();

        if (!$user) {
            $this->getResponse()->setStatusCode(403);
        }

        $discounts = $this->getServiceLocator()->get('DiscountTable')->fetchByUserId($user->getId(), $user->getPartnerGroup(), false, 0,  $this->getServiceLocator());
        $commercialMapper = CommercialMapper::getInstance($this->getServiceLocator());

        $commercial = $commercialMapper->getByUID($user->getId(), $id, true, true, true);
        $commercialMapper->actualize($commercial, $user, $discounts);

        return $this->redirect()->toRoute('cabinet');
    }
} 