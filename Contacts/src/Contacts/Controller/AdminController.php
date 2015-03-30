<?php
namespace Contacts\Controller;

use Application\Controller\SampleAdminController;
use Contacts\Model\Contact;
use Info\Service\SeoService;

class AdminController extends SampleAdminController
{
    protected $entityName = 'Contacts\Model\Contact';

    public function indexAction()
    {
        $this->setData();
        $contact = $this->getServiceLocator()->get($this->table)->find(1);
        return array(
            'contact' => $contact,
        );
    }
}