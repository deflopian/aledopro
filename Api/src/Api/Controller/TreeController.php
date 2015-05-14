<?php
namespace Api\Controller;

use Api\Model\File;
use Api\Model\FileTable;
use Catalog\Mapper\CatalogMapper;
use Catalog\Model\FilterField;
use Catalog\Service\CatalogService;
use Commercials\Model\CommercialProd;
use Discount\Mapper\DiscountMapper;
use Discount\Model\Discount;
use Discount\Model\DiscountTable;
use Zend\Json\Json;
use Zend\Mvc\Controller\AbstractRestfulController;

class TreeController extends ApiController
{

    /**
     * POST /api/tree/
     */
    public function create($data) {
        $type = $data['type'];
        $options = $data['options'];
        $itemId = $data['itemId'];
        $sl = $this->getServiceLocator();
        unset($data['type']);
        unset($data['options']);
        unset($data['itemId']);

        if (!$itemId || !$type) {
            return $this->response->setStatusCode(400);
        }
        $lastId = 0;
        if ($type == \Catalog\Controller\AdminController::FILTER_FIELD_TABLE && isset($options['sectionId']) && $options['sectionId'] > 0) {
            /** @var FilterField $oldEntity */
            $oldEntity = CatalogService::getEntityByType($sl, $itemId, $type);
            /** @var \Catalog\Model\FilterFieldTable $table */
            $table = $sl->get('Catalog\Model\FilterFieldTable');
            $normalMaxId = $table->getMaxId();

            if ($oldEntity) {

                if ($oldEntity->section_id == 0 || $oldEntity->section_type != $options['sectionType']) {

                    $oldEntity->section_id = $options['sectionId'];
                    if ($options['sectionType']) {
                        $oldEntity->section_type = $options['sectionType'];
                    }
                    $oldEntity->id = null;
                    $entity = CatalogService::createAndFillEntity($type, $oldEntity->toArray());
                    $lastId = $normalMaxId;
                } else {
                    $entity = $oldEntity;
                    $lastId = $entity->id;
                }
                $this->response->setContent($lastId);
            } else {
                return $this->response->setStatusCode(404);
            }

        } elseif ($type == \Catalog\Controller\AdminController::DISCOUNT_TABLE) {
            /** @var DiscountTable $entityTable */
            $entityTable = $sl->get('DiscountTable');
            if ($options['isGroup'] > 0) {
                $entities = $entityTable->fetchByConds(array(   'user_id' => $options['isGroup'],
                    'section_type' => $options['discountType'],
                    'section_id' => $options['sectionId'],
                    'is_group' => 1));
            } else {
                $entities = $entityTable->fetchByConds(array(   'user_id' => $itemId,
                    'section_type' => $options['discountType'],
                    'section_id' => $options['sectionId'],
                    'is_group' => 0));
            }

            if ($entities) {
                $entity = reset($entities);
            } else {
                $entity = new Discount();
                if ($options['isGroup'] > 0) {
                    $entity->user_id = $options['isGroup'];
                    $entity->is_group = 1;
                } else {
                    $entity->user_id = $itemId;
                    $entity->is_group = 0;
                }
                $entity->section_id = $options['sectionId'];
                $entity->section_type = $options['discountType'];


            }

            foreach ($data as $field => $val) {
                $entity->$field = $val;
            }

            $res = $entityTable->save($entity);
            return $this->response->setContent($res)->setStatusCode(200);
        } elseif ($type == \Catalog\Controller\AdminController::COMMERCIAL_ROOMS_TABLE) {

            $entityTable = $sl->get('CommercialProdsTable');
            if (array_key_exists("add", $data)) {
                $entity = new CommercialProd();
                $entity->product_id = $options['sectionId'];
                $entity->room_id = $itemId;
                $entity->old_price = $options['old_price'];
                $entityTable->save($entity);

                return $this->response->setContent(1)->setStatusCode(200);
            } elseif (array_key_exists("remove", $data)) {
                $entity = $entityTable->fetchByConds(
                    array(
                        "product_id" => $options['sectionId'],
                        "room_id"    => $itemId
                    )
                );
                $entity = reset($entity);
                if ($entity) {
                    $entityTable->del($entity->id);
                    return $this->response->setContent(0)->setStatusCode(200);
                } else {
                    return $this->response->setStatusCode(404);
                }

            }

            return $this->response->setStatusCode(400);

        } else {
            $entity = CatalogService::getEntityByType($sl, $itemId, $type);
        }

        if (!$entity) {
            return $this->response->setStatusCode(404);
        }
        foreach ($data as $field => $val) {
            if ($field == 'is_offer' && $type == \Catalog\Controller\AdminController::PRODUCT_TABLE) {
                $ser = CatalogService::getEntityByType($sl, $entity->series_id, \Catalog\Controller\AdminController::SERIES_TABLE);
                if ($val == 1) {
                    if ($ser->is_offer == 0) {
                        $ser->is_offer = 2;
                        CatalogService::saveEntityByType($sl, $ser, \Catalog\Controller\AdminController::SERIES_TABLE);
                    } elseif ($ser->is_offer > 1) {
                        $ser->is_offer++;
                        CatalogService::saveEntityByType($sl, $ser, \Catalog\Controller\AdminController::SERIES_TABLE);
                    }
                } else {
                    if ($ser->is_offer > 1) {
                        if ($ser->is_offer == 2) {
                            $ser->is_offer = 0;
                        } else {
                            $ser->is_offer--;
                        }
                        CatalogService::saveEntityByType($sl, $ser, \Catalog\Controller\AdminController::SERIES_TABLE);
                    }
                }
            }
            $entity->$field = $val;
        }

        CatalogService::saveEntityByType($sl, $entity, $type);

        if ($type == \Catalog\Controller\AdminController::FILTER_FIELD_TABLE) {
            return $this->response->setContent($lastId)->setStatusCode(200);
        }
        return $this->response->setStatusCode(200);
    }

    /**
     * PUT /api/tree/
     */
    public function update($id, $data) {
        $type = $data['type'];
        $options = $data['options'];
        $itemId = $data['itemId'];
        $sl = $this->getServiceLocator();
        unset($data['type']);
        unset($data['options']);
        unset($data['itemId']);

        if (!$itemId || !$type) {
            return $this->response->setStatusCode(400);
        }

        if ($type == \Catalog\Controller\AdminController::FILTER_FIELD_TABLE) {

            if (isset($options['orders'])) {
                $duplicateNew = false;
                if (isset($options['sectionId']) && $options['sectionId'] > 0) {
                    $duplicateNew = true;
                }
                $oldIdsToNew = array();
                $lastId = 0;
                $insertedCount = 0;
                $table = $sl->get('Catalog\Model\FilterFieldTable');
                $normalMaxId = $table->getMaxId();
                foreach ($options['orders'] as $key => $fieldId) {
                    $oldEntity = CatalogService::getEntityByType($sl, $fieldId, $type);

                    if ($duplicateNew && ($oldEntity->section_id == 0 || $oldEntity->section_type != $options['sectionType']) ) {

                        $oldEntity->section_id = $options['sectionId'];
                        $oldEntity->section_type = $options['sectionType'];
                        $oldEntity->id = null;
                        $entity = CatalogService::createAndFillEntity($type, $oldEntity->toArray());
                        $lastId = 0;
                    } else {
                        $entity = $oldEntity;
                        $lastId = $oldEntity->id;
                    }
                    if ($entity) {
                        $entity->order = $key;
                    }



                    if (!$entity->id) {


                        $lastId = $normalMaxId + $insertedCount++;

                        $entity->id = $lastId;

                    }

                    CatalogService::saveEntityByType($sl, $entity, $type);
                    $oldIdsToNew[$fieldId] = (int)$lastId;
                }
                return $this->response->setContent(\Zend\Json\Json::encode($oldIdsToNew))->setStatusCode(200);
            }

            if (isset($options['special']) && $options['special'] == "removeAll") {
                $sectionId = $options['sectionId'];
                $sectionType = $options['sectionType'];

                if ($sectionId > 0) {
                    $table = $sl->get('Catalog\Model\FilterFieldTable');
                    $table->deleteAllBySection($sectionId, $sectionType);
                    $this->response->setStatusCode(200);
                }
            }
        }
        return $this->response->setStatusCode(200);
    }

    /**
     * DELETE /api/tree/
     */
    public function delete($id, $data) {
        $sl = $this->getServiceLocator();
        /** @var DiscountTable $entityTable */
        $entityTable = $sl->get('DiscountTable');
        $entityTable->delById($id);
        return $this->response->setStatusCode(200);
    }
}