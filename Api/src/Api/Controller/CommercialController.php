<?php
namespace Api\Controller;

use Commercials\Mapper\CommercialMapper;
use Zend\Json\Json;



class CommercialController extends ApiController
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
     * GET /api/commercials/:id
     */
    public function get($id) {
        $sl = $this->getServiceLocator();

        $cm = CommercialMapper::getInstance($sl);
        $user = $this->zfcUserAuthentication()->getIdentity();
        $result = $cm->getByUID($user->getId(), $id, true, true, true);

        $this->response->setContent(Json::encode($result))->setStatusCode(200);
        return $this->response;
    }

    /**
     * DELETE /api/commercials/:id
     */
    public function delete($id) {
        $sl = $this->getServiceLocator();

        $cm = CommercialMapper::getInstance($sl);
        $user = $this->zfcUserAuthentication()->getIdentity();
        $result = $cm->getByUID($user->getId(), $id, false);
        $result = $cm->delete($result->id, true);

        $this->response->setContent(Json::encode($result))->setStatusCode(200);
        return $this->response;
    }

    /**
     * PUT /api/commercials/:id
     */
    public function update($id, $data) {
        $sl = $this->getServiceLocator();
        $cm = CommercialMapper::getInstance($sl);
        $user = $this->zfcUserAuthentication()->getIdentity();
        $data = $data['entity'];
        if (isset($data['rooms'])) {
            unset($data['rooms']);
        }

        $result = $cm->updateByUID($user->getId(), $id, $data);

        $this->response->setContent(Json::encode($result))->setStatusCode(200);
        return $this->response;
    }

    /**
     * POST /api/commercials/
     */
    public function create($data) {
        $sl = $this->getServiceLocator();
        $cm = CommercialMapper::getInstance($sl);
        $user = $this->zfcUserAuthentication()->getIdentity();
        $result = $cm->add("Новое КП", $user->getId());

        $this->response->setContent(Json::encode($result))->setStatusCode(200);
        return $this->response;
    }
}