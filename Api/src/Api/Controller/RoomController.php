<?php
namespace Api\Controller;

use Commercials\Mapper\CommercialMapper;
use Commercials\Mapper\CommercialRoomMapper;
use Commercials\Model\CommercialRoom;
use Zend\Json\Json;



class RoomController extends ApiController
{

    protected function getTableName($name) {
        if (substr($name, -1, 1) != "s") {
            $name .= "s";
        }
        return ucfirst($name) . $this->table;
    }
    protected function getEntityName($name) {
        return ucfirst($name);
    }

    /**
     * @param $cm
     * @param $crm
     * @param $roomId
     * @return mixed
     */
    private function checkRoomAuth($cm, $crm, $roomId) {
        $user = $this->zfcUserAuthentication()->getIdentity();
        $statusCode = 200;
        $res = false;
        if (!$user) {
            $statusCode = 403;
            return array($statusCode, $res);
        }
        $result = $crm->get($roomId, false);
        if (!$result) {
            $statusCode = 404;
            return array($statusCode, $res);
        }
        $comm = $cm->get($result->commercial_id, false, false);
        if (!$comm) {
            $statusCode = 404;
            return array($statusCode, $res);
        }
        if ($comm->user_id != $user->getId()) {
            $statusCode = 403;
            return array($statusCode, $res);
        }
        $res = true;
        return array($statusCode, $res);
    }

    /**
     * GET /api/commercials/
     */
    public function getList() {
        $sl = $this->getServiceLocator();
        $type = $this->params()->fromQuery('type', false);
        if (!$type) {
            return $this->response->setStatusCode(400);
        }

        $tableName = $this->getTableName($type);
        $table = $sl->get($tableName);

        $results = $table->fetchAll();
        $this->response->setContent(Json::encode($results))->setStatusCode(200);
        return $this->response;
    }

    /**
     * GET /api/rooms/:id
     */
    public function get($id) {
        $sl = $this->getServiceLocator();

        $cm = CommercialMapper::getInstance($sl);
        $crm = CommercialRoomMapper::getInstance($sl);

        list($res, $code) = $this->checkRoomAuth($cm, $crm, $id);
        if (!$res) {
            return $this->response->setStatusCode($code);
        }
        //удостоверились, что у юзера есть права смотреть это помещение, получаем помещение с продуктами
        $realResult = $crm->get($id, true);

        $this->response->setContent(Json::encode($realResult))->setStatusCode(200);
        return $this->response;
    }

    /**
     * DELETE /api/rooms/:id
     */
    public function delete($id) {
        $sl = $this->getServiceLocator();
        $cm = CommercialMapper::getInstance($sl);
        $crm = CommercialRoomMapper::getInstance($sl);
        list($res, $code) = $this->checkRoomAuth($cm, $crm, $id);
        if (!$res) {
            return $this->response->setStatusCode($code);
        }
        $result = $crm->delete($id);

        $this->response->setContent(Json::encode($result))->setStatusCode(200);
        return $this->response;
    }

    /**
     * PUT /api/commercials/:id
     */
    public function update($id, $data) {
        $sl = $this->getServiceLocator();
        $cm = CommercialMapper::getInstance($sl);
        $crm = CommercialRoomMapper::getInstance($sl);
        list($res, $code) = $this->checkRoomAuth($cm, $crm, $id);
        if (!$res) {
            return $this->response->setStatusCode($code);
        }
        $data = $data['entity'];

        $result = $crm->update($id, $data);

        $this->response->setContent(Json::encode($result))->setStatusCode(200);
        return $this->response;
    }

    /**
     * POST /api/commercials/:id
     */
    public function create($data) {
        if (!isset($data['commId'])) {
            return $this->response->setStatusCode(400);
        }
        $commId = $data['commId'];
        $sl = $this->getServiceLocator();
        $user = $this->zfcUserAuthentication()->getIdentity();
        $cm = CommercialMapper::getInstance($sl);
        $crm = CommercialRoomMapper::getInstance($sl);
        $comm = $cm->getByUID($user->getId(), $commId, false, false);
        if (!$comm) {
            return $this->response->setStatusCode(404);
        }
        if ($comm->user_id != $user->getId()) {
            return $this->response->setStatusCode(403);
        }
        $room = new CommercialRoom();
        $room->commercial_id = $comm->id;
        $room->title = "Новое помещение";
        $room->summ = 0;
        $result = $crm->add($room);
        $room->id = $result;

        $this->response->setContent(Json::encode($room))->setStatusCode(200);
        return $this->response;
    }
}