<?php
namespace Team\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Team\Model\WorkerTable;

class TeamController extends AbstractActionController
{
    private $workerTable;

    //Нет места для сео
    public function indexAction()
    {
        $this->getResponse()->setStatusCode(404);
        return;
    }

    /**
     * @return WorkerTable array|object
     */
    public function getWorkerTable()
    {
        if (!$this->workerTable) {
            $sm = $this->getServiceLocator();
            $this->workerTable = $sm->get('TeamTable');
        }
        return $this->workerTable;
    }
}