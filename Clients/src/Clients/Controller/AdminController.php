<?php
namespace Clients\Controller;

use Application\Controller\SampleAdminController;
use Info\Service\SeoService;
use Clients\Model\Client;

class AdminController extends SampleAdminController
{
    protected $entityName = 'Clients\Model\Client';

    public function indexAction()
    {
        $return = parent::indexAction();
        return $return;
    }
}