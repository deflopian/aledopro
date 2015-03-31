<?php
namespace Api\Controller;

use Api\Model\File;
use Api\Model\FileTable;
use Api\Service\EntityService;
use Catalog\Service\CatalogService;
use Zend\Json\Json;
use Zend\Mvc\Controller\AbstractRestfulController;

class EntityController extends ApiController
{

    protected function unlinkFile($url)
    {
        unlink($_SERVER['DOCUMENT_ROOT'] . $url);
    }

    protected function getFolder($url)
    {

        return $_SERVER['DOCUMENT_ROOT'] . $url;
    }

    /**
     * POST /api/entity/
     */
    public function create($data) {
        $sl = $this->getServiceLocator();
        $type = $data['type'];

        $entity = $data['entity'];

        $entity = CatalogService::createAndFillEntity($type, $entity);

        if ($entity) {
            $lastId = EntityService::saveEntityByType($sl, $entity, $type);
            $result = array('success' => 1, 'id' => $lastId);
            $this->response->setContent(Json::encode($result))->setStatusCode(200);
        } else {
            $result = array('success' => 0);
            $this->response->setContent(Json::encode($result))->setStatusCode(400);
        }


        return $this->response;
    }

    /**
     * DELETE /api/item/
     */
    public function delete($id, $data) {
        $sl = $this->getServiceLocator();
        $type = $this->params()->fromQuery('type', false);
        if ($type == 26) {
            parent::delete($id, array('type' => "document"));
        } else {
            $sl = $this->getServiceLocator();

            $tableName = CatalogService::$tables[$type];
            $table = $sl->get($tableName);

            $result = $table->del($id);
            $this->response->setContent(Json::encode($result))->setStatusCode(200);
            return $this->response;
        }
        return $this->response;
    }
}