<?php
namespace Clients\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Clients\Model\ClientTable;

class ClientController extends AbstractActionController
{
    private $clientTable;

    //Нет места для сео
    public function indexAction()
    {
        $this->getResponse()->setStatusCode(404);
        return;
    }

    /**
     * @return WorkerTable array|object
     */
    public function getClientTable()
    {
        if (!$this->clientTable) {
            $sm = $this->getServiceLocator();
            $this->clientTable = $sm->get('ClientTable');
        }
        return $this->clientTable;
    }
}