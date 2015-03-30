<?php
namespace Terms\Controller;

use Zend\Mvc\Controller\AbstractActionController;

class TermsController extends AbstractActionController
{
    public function indexAction()
    {
        $this->getResponse()->setStatusCode(404);
        return;
    }
}