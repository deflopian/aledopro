<?php
namespace Discount\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Discount\Model\DiscountTable;

class DiscountController extends AbstractActionController
{
    private $clientTable;

    //Нет места для сео
    public function indexAction()
    {
        $this->getResponse()->setStatusCode(404);
        return;
    }
    /**
     * @return DiscountTable array|object
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