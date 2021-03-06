<?php
namespace Api\Controller;

use Api\Model\File;
use Api\Model\FileTable;
use Zend\Json\Json;
use Zend\Mvc\Controller\AbstractRestfulController;
use Application\Service\ApplicationService;
use Application\Service\GoogleContactsService;
use Application\Service\MailService;

class FileController extends ApiController
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
     * POST /api/item/
     */
    public function create($data) {
        $sl = $this->getServiceLocator();
        $type = $data['type'];
        if (!$type) {
            return $this->response->setStatusCode(400);
        }

        $result = "test";

        $parentType = $data['parentType'];
        $parentId = $data['parentId'];
        if (!is_numeric($parentId)) {
            $parentId = \Zend\Json\Json::decode($parentId);
        }
        $folder = $data['folder'];
        $field = $data['field'];
		
		if ($parentType == 'contacts' && ApplicationService::isDomainZone('by')) {
			return $this->response->setStatusCode(400);
		}

        $parentObjectTableName = $this->getTableName($parentType);
        $parentObjectTable = $sl->get($parentObjectTableName);
        if ($field != "id") {
            $parentObject = $parentObjectTable->find($parentId);

            if (!$parentObject) {
                return $this->response->setStatusCode(400);
            }
        }

        $tableName = $this->getTableName($type);
        $table = $sl->get($tableName);
        if ($field != "id") {
            if (!empty($parentObject->$field)) {
                $filename = false;
                if (is_numeric($parentObject->$field)) {
                    $file = $table->find($parentObject->$field);
                    if ($file) {
                        $filename = $file->name;
                    }
                } elseif (is_string($parentObject->$field)) {
                    $filename = $parentObject->$field;
                }
                if ($filename) {
                    $this->unlinkFile('/images/'. $folder .'/' . $filename);
                }
            }
        }

        $data = $data['file'];

        $filename = $data['name'];
		$filetype = $data['type'];

        $adapter = new \Zend\File\Transfer\Adapter\Http();
        $adapter->setDestination($this->getFolder('/images/' . $folder));


        $pos=strrpos($filename, ".");
        $len=strlen($filename);
        $extension = "img";
        if($pos >= 0)
        {
            $extension = substr($filename,$pos+1,$len-$pos) ;
        }
        $adapter->addFilter('File\Rename',
            array(
                'target' => $adapter->getDestination($filename).'/img.' . $extension,
                'randomize' => true,
            ));
        $resultId = 0;
        if($adapter->receive($filename)){
            //если приходит массив айдишек, сохраняем файл для каждой
            if (is_array($parentId)) {
                foreach ($parentId as $pid) {
                    $oldEntity = $table->fetchByCond('uid', $pid);
                    foreach ($oldEntity as $oneE) {
                        $table->del($oneE->id);
                    }
                    $entity = new File();
                    $entity->name = $adapter->getFileName(null, false);
                    $entity->type = FileTable::TYPE_IMAGE;
                    $entity->real_name = $filename;
                    $entity->path = $adapter->getFileName(null, false);
                    $entity->size = $adapter->getFileSize();
                    $entity->timestamp = time(true);
                    if ($field == "id") {
                        $entity->uid = $pid;
                    }

                    $resultId = $table->save($entity);
                }

            } else {
                $entity = new File();
                $entity->name = $adapter->getFileName(null, false);
                $entity->type = FileTable::TYPE_IMAGE;
                $entity->real_name = $filename;
                $entity->path = $adapter->getFileName(null, false);
                $entity->size = $adapter->getFileSize();
                $entity->timestamp = time(true);
                if ($field == "id") {
                    $entity->uid = $parentId;
                    $oldEntity = $table->fetchByCond('uid', $parentId);
                    foreach ($oldEntity as $oneE) {
                        $table->del($oneE->id);
                    }
                }

                $resultId = $table->save($entity);
            }
            if ($field != "id") {
                $parentObject->$field = $resultId;
                $parentObjectTable->save($parentObject);
            }
        }
		
		if ($parentType == "contacts") {
			GoogleContactsService::parseCSV($sl, $_SERVER['DOCUMENT_ROOT'] . '/images/' . $folder . '/' . $adapter->getFileName(null, false), $filetype);
		}
		
		if ($parentType == "document" && !ApplicationService::isDomainZone('by')) {
			list($email, $mailView) = MailService::prepareNotificationMailData($sl, $parentObject, MailService::NOTIFICATION_DOCUMENTS);
			MailService::sendMail($email, $mailView, "Новый документ загружен на сайт!");
		}

        $result = array('name' => $adapter->getFileName(null, false), 'realName' => $filename, 'id' => $resultId);
		if ($parentType == "contacts") $result['reload'] = 1;
        $this->response->setContent(Json::encode($result))->setStatusCode(200);
        return $this->response;
    }

    /**
     * DELETE /api/item/
     */
    public function delete($id, $data) {
        $sl = $this->getServiceLocator();
        $type = $this->params()->fromQuery('type', false);
        $parentId = $this->params()->fromQuery('parentId', false);
        $tableName = $this->getTableName($type);
        $table = $sl->get($tableName);

        $file = $table->find($parentId);

        if (!$file) {
            return $this->response->setStatusCode(400);
        }
        $filename = $file->name;
        if ($type) {
            $parentType = $this->params()->fromQuery('parentType', false);
            $parentId = $this->params()->fromQuery('parentId', false);
            $folder = $this->params()->fromQuery('folder', false);
            $field = $this->params()->fromQuery('field', false);

            $parentObjectTableName = $this->getTableName($parentType);
            $parentObjectTable = $sl->get($parentObjectTableName);
            $parentObject = $parentObjectTable->find($parentId);
            if ($field == "id") {
                if ($tableName == "FilesTable") {
                    $table->del($file);
                }
            } else {
                $parentObject->$field = false;
                $parentObjectTable->save($parentObject);
            }


            $this->unlinkFile('/images/'. $folder .'/' . $filename);
        } else {
            $this->unlinkFile('/uploads/files/' . $filename);
        }

        return parent::delete($id, array('type' => "file"));
    }
}