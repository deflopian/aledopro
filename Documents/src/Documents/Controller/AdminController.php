<?php
namespace Documents\Controller;

use Api\Model\File;
use Application\Controller\SampleAdminController;
use Documents\Model\DocumentTable;
use Info\Service\SeoService;
use Documents\Model\Document;

class AdminController extends SampleAdminController
{
    protected $entityName = 'Documents\Model\Document';

    public function indexAction()
    {
        $return = parent::indexAction();
        $documentsTable = $this->getServiceLocator()->get('DocumentsTable');
        $catalogs = $documentsTable->fetchByCond('type', DocumentTable::TYPE_CATALOG);
        $comments = $documentsTable->fetchByCond('type', DocumentTable::TYPE_COMMENT);
        $certificates = $documentsTable->fetchByCond('type', DocumentTable::TYPE_CERTIFICATE);
        $instructions = $documentsTable->fetchByCond('type', DocumentTable::TYPE_INSTRUCTION);

        $catalogsJson = \Zend\Json\Json::encode($catalogs);
        $commentsJson = \Zend\Json\Json::encode($comments);
        $certificatesJson = \Zend\Json\Json::encode($certificates);
        $instructionsJson = \Zend\Json\Json::encode($instructions);
        $return['catalogsJson'] = $catalogsJson;
        $return['commentsJson'] = $commentsJson;
        $return['certificatesJson'] = $certificatesJson;
        $return['instructionsJson'] = $instructionsJson;
        return $return;
    }

    public function viewAction() {
        $return = parent::viewAction();
        $entity = $return['entity'];
/** @var DocumentTable $fileTable */
        $fileTable = $this->getServiceLocator()->get('FilesTable');


        if ($return['entity']->img) {
            /** @var File $file */
            $file = $fileTable->find($return['entity']->img);
            if ($file) {
                $return['entity']->imgName = $file->name;
                $return['entity']->imgRealName = $file->real_name;
            }
        }
        if ($return['entity']->file) {
            /** @var File $file2 */
            $file2 = $fileTable->find($return['entity']->file);
            if ($file2) {
                $return['entity']->fileName = $file2->name;
                $return['entity']->fileRealName = $file2->real_name;
            }
        }

        $entityJson = \Zend\Json\Json::encode($entity);
        $return['entityJson'] = $entityJson;
        return $return;
    }
}