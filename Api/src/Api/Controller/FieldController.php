<?php
namespace Api\Controller;

use Api\Model\File;
use Api\Model\FileTable;
use Api\Service\EntityService;
use Catalog\Service\CatalogService;
use Zend\Json\Json;
use Zend\Mvc\Controller\AbstractRestfulController;

class FieldController extends ApiController
{

    /**
     * PUT /api/entity/
     */
    public function update($id, $data) {
        $sl = $this->getServiceLocator();
        $type = $data['type'];

        $field = $data['name'];
        $value = $data['value'];

        $entity = EntityService::getEntityByType($sl, $id, $type);
        if (!$entity) {
            $this->response->setContent(Json::encode(array('success' => 0, 'error' => 'entity not found')))->setStatusCode(404);
        }

        $entity->$field = $value;

        $lastId = EntityService::saveEntityByType($sl, $entity, $type);
        $result = array('success' => 1, 'id' => $lastId);
        $this->response->setContent(Json::encode($result))->setStatusCode(200);


        return $this->response;
    }

    /**
     * DELETE /api/item/
     */
    public function delete($id, $data) {
        $sl = $this->getServiceLocator();
        $type = $this->params()->fromQuery('type', false);


        return parent::delete($id, array('type' => "d"));
    }
}