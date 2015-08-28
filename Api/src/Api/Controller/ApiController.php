<?php
namespace Api\Controller;

use Zend\Json\Json;
use Zend\Mvc\Controller\AbstractRestfulController;



class ApiController extends AbstractRestfulController
{
    protected $table = "Table";

    protected function getTableName($name) {
		if ($name == "user") {
			return ucfirst("User") . $this->table;
		}
        if (substr($name, -1, 1) != "s") {
            $name .= "s";
        }
        return ucfirst($name) . $this->table;
    }
    protected function getEntityName($name) {
        return ucfirst($name);
    }

    /**
     * GET /api/item/
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
     * GET /api/item/:id
     */
    public function get($id) {
        $sl = $this->getServiceLocator();
        $type = $this->params()->fromQuery('type', false);
        if (!$type) {
            return $this->response->setStatusCode(400);
        }

        $tableName = $this->getTableName($type);
        $table = $sl->get($tableName);

        $result = $table->find($id);
		if ($type == "user") {
			unset($result->password);
			unset($result->token);
		}
        $this->response->setContent(Json::encode($result))->setStatusCode(200);
        return $this->response;
    }

    /**
     * DELETE /api/item/:id
     */
    public function delete($id, $data) {
        $sl = $this->getServiceLocator();
        $type = $data['type'];
        if (!$type) {
            return $this->response->setStatusCode(400);
        }

        $tableName = $this->getTableName($type);
        $table = $sl->get($tableName);

        $result = $table->del($id);
        $this->response->setContent(Json::encode($result))->setStatusCode(200);
        return $this->response;
    }

    /**
     * PUT /api/item/:id
     */
    public function update($id, $data) {
        $sl = $this->getServiceLocator();
        $type = $this->params()->fromQuery('type', false);
        if (!$type) {
            return $this->response->setStatusCode(400);
        }

        $tableName = $this->getTableName($type);


        $table = $sl->get($tableName);
        $entity = $table->find($id);
        foreach ($data as $key => $val) {
            if (isset($entity->$key)) {
                $entity->$key = $val;
            }
        }
        $result = $table->save($entity);
        $this->response->setContent(Json::encode($result))->setStatusCode(200);
        return $this->response;
    }

    /**
     * POST /api/item/
     */
    public function create($data) {
        $sl = $this->getServiceLocator();
        $type = $this->params()->fromQuery('type', false);
        if (!$type) {
            return $this->response->setStatusCode(400);
        }

        $tableName = $this->getTableName($type);
        $entityName = $this->getEntityName($type);


        $table = $sl->get($tableName);
        $entity = new $entityName();
        foreach ($data as $key => $val) {
            if (isset($entity->$key)) {
                $entity->$key = $val;
            }
        }
        $result = $table->save($entity);
        $this->response->setContent(Json::encode($result))->setStatusCode(200);
        return $this->response;
    }
}