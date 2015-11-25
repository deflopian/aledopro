<?php
namespace Api\Controller;

use Catalog\Controller\AdminController;
use Catalog\Mapper\CatalogMapper;
use Catalog\Service\CatalogService;
use Catalog\Service\Hierarchy;
use Commercials\Mapper\CommercialMapper;
use Commercials\Mapper\CommercialProdMapper;
use Commercials\Mapper\CommercialRoomMapper;
use Commercials\Model\CommercialProd;
use Commercials\Model\CommercialRoom;
use Zend\Json\Json;



class CommercialProdController extends ApiController
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
        return array($statusCode, $res, array($comm, $result));
    }

    /**
     * @param $cm
     * @param $crm
     * @param $cpm
     * @param $roomId
     * @return mixed
     */
    private function checkProdAuth($cm, $crm, $cpm, $prodId) {
        $user = $this->zfcUserAuthentication()->getIdentity();
        $statusCode = 200;
        $res = false;
        if (!$user) {
            $statusCode = 403;
            return array($statusCode, $res);
        }
        $result = $cpm->get($prodId);
        if (!$result) {
            $statusCode = 404;
            return array($statusCode, $res);
        }
        $room = $crm->get($result->room_id, false);
        if (!$room) {
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
        return array($statusCode, $res, array($comm, $result));
    }

    /**
     * DELETE /api/comprod/:id
     */
    public function delete($id) {
        $sl = $this->getServiceLocator();
        $cm = CommercialMapper::getInstance($sl);
        $crm = CommercialRoomMapper::getInstance($sl);
        $cpm = CommercialProdMapper::getInstance($sl);
        list($res, $code) = $this->checkProdAuth($cm, $crm, $cpm, $id);
        if (!$res) {
            return $this->response->setStatusCode($code);
        }
        $result = $cpm->delete($id);

        $this->response->setContent(Json::encode($result))->setStatusCode(200);
        return $this->response;
    }

    /**
     * POST /api/comprod/
     */
    public function create($data) {
        if (!isset($data['prodId'])) {
            return $this->response->setStatusCode(400);
        }
        if (!isset($data['roomId'])) {
            return $this->response->setStatusCode(400);
        }
        $prodId = $data['prodId'];
        $roomId = $data['roomId'];
        $sl = $this->getServiceLocator();
        $user = $this->zfcUserAuthentication()->getIdentity();
        $cm = CommercialMapper::getInstance($sl);
        $crm = CommercialRoomMapper::getInstance($sl);
        $cpm = CommercialProdMapper::getInstance($sl);
        $catalogMapper = CatalogMapper::getInstance($sl);

        list($res, $code, $arr) = $this->checkRoomAuth($cm, $crm, $roomId);
        if (!$res) {
            return $this->response->setStatusCode($code);
        }



        $metaProd = $catalogMapper->getProduct($prodId);
        if (!$metaProd) {
            return $this->response->setStatusCode(404);
        }

        $discounts = $sl->get('DiscountTable')->fetchByUserId($user->getId(), $user->getPartnerGroup(), false, 0, $sl);
		
		$priceRequestTable = $sl->get('PriceRequestTable');
		$requests = $priceRequestTable->fetchAllSorted();

        list($tree, $type) = $catalogMapper->getParentTree($prodId);
        $prod = new CommercialProd();
        $prod->room_id = $roomId;
        $prod->product_id = $prodId;
        $prod->count = 1;
        $prod->old_price = CatalogService::getTruePrice($metaProd->price_without_nds, null, $tree, null, 0, $requests);
//        $prod->old_price = CatalogService::getTruePrice($metaProd->price_without_nds, $user, $tree, $discounts, $metaProd->opt2);

        $result = $cpm->add($prod);

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
        $cpm = CommercialProdMapper::getInstance($sl);
        list($res, $code, $fail) = $this->checkProdAuth($cm, $crm, $cpm, $id);
        if (!$res) {
            return $this->response->setStatusCode($code);
        }
        $data = $data['entity'];

        $result = $cpm->update($id, $data);
        if ($fail && $fail[0]) {
            $cm->updateSumm($fail[0]);
        }

        $this->response->setContent(Json::encode($result))->setStatusCode(200);
        return $this->response;
    }
}