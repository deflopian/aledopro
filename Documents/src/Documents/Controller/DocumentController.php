<?php
namespace Documents\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Documents\Model\DocumentTable;

class DocumentsController extends AbstractActionController
{
    public $documentsTable = null;

    //Нет места для сео
    public function indexAction()
    {
        $this->getResponse()->setStatusCode(404);
        return;
    }

    /**
     * @return DocumentTable array|object
     */
    public function getDocumentsTable()
    {
        $sm = $this->getServiceLocator();
        $this->documentsTable = $sm->get('DocumentsTable');

        return $this->documentsTable;
    }
}